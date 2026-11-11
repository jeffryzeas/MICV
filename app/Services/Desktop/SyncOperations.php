<?php
namespace App\Services\Desktop;
use App\Models\{AutoservicioSolicitud,Cliente,ContableMovimiento,DesktopDevice};
use App\Models\Pos\{PosCaja,PosConfig,PosProducto};
use App\Services\{ClienteGuardar,LibroIngresosGastos};
use App\Services\Pos\Ventas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\Rule;
class SyncOperations {
 public const CLIENT_FIELDS=['pais','tipo_documento','numero_documento','primer_nombre','segundo_nombre','primer_apellido','segundo_apellido','fecha_nacimiento','municipio_nacimiento','departamento_nacimiento','sexo','nacionalidad','pais_residencia','autorizacion_laboral','telefono_codigo_pais','telefono','correo','direccion','profesion'];
 public static function clientRow(Cliente $c): array {
  $data=$c->only(self::CLIENT_FIELDS);$data['fecha_nacimiento']=$c->fecha_nacimiento?->toDateString();
  return ['id'=>$c->id,'revision'=>hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),'data'=>$data];
 }
 private function permit(DesktopDevice $d,string $p): void {abort_unless((new WorkspaceSnapshot($d))->allows($p),403,'Su cuenta no tiene permiso para esta operación.');}
 private function ref(DesktopDevice $d,mixed $value,string|array $type): int {
  if(is_string($value)&&str_starts_with($value,'local:')){
   $r=DB::table('desktop_receipts')->where('device_id',$d->id)->where('operation_uuid',substr($value,6))->whereIn('tipo',(array)$type)->first();
   abort_unless($r,409,'Primero debe sincronizar la operación relacionada.');return $r->entity_id;
  }
  abort_unless(is_numeric($value)&&(int)$value>0,422,'La referencia no es válida.');return (int)$value;
 }
 private function resolveProducts(DesktopDevice $device,array $data): array {
  if (isset($data['items']) && is_array($data['items'])) {
   foreach ($data['items'] as &$item) {
    if (is_array($item) && is_string($item['producto_id']??null) && str_starts_with($item['producto_id'],'local:')) {
     $item['producto_id']=$this->ref($device,$item['producto_id'],'product.create');
    }
   }
  }
  return $data;
 }

 private function product(DesktopDevice $device,array $op): int {
  $this->permit($device,'pos.productos.editar');
  $tenant=$device->negocio()->id;
  $payload=$op['data'];
  $product=null;
  if ($op['type']!=='product.create') {
   $product=PosProducto::where('cyber_id',$tenant)->whereKey($this->ref($device,$payload['id']??null,'product.create'))->lockForUpdate()->firstOrFail();
   abort_unless(is_string($payload['revision']??null)
      && hash_equals(\App\Services\Pos\CatalogoProductos::revision($product),$payload['revision']),409,'El producto cambió en el servidor.');
  }
  if ($op['type']==='product.delete') {
   \App\Services\Pos\CatalogoProductos::borrar($product);
   return $product->id;
  }
  abort_unless(is_array($payload['product']??null),422,'Faltan los datos del producto.');
  $fields=$payload['product'];
  foreach (['categoria_id'=>'category.create','proveedor_id'=>'supplier.create'] as $field=>$kind) {
   if (is_string($fields[$field]??null) && str_starts_with($fields[$field],'local:')) {
    $fields[$field]=$this->ref($device,$fields[$field],$kind);
   }
  }
  $request=Request::create('/desktop/product','POST',$fields);
  $request->setUserResolver(fn()=>$device->user);
  $temporary=null;
  try {
   if (isset($payload['photo'])) {
    abort_unless(is_string($payload['photo']) && strlen($payload['photo'])<=820000,422,'La foto excede el tamaño admitido.');
    $raw=base64_decode($payload['photo'],true);
    abort_unless($raw!==false && $raw!=='' && strlen($raw)<=600*1024,422,'La foto no es válida.');
    $temporary=tempnam(sys_get_temp_dir(),'micv-product-');
    abort_unless($temporary && file_put_contents($temporary,$raw)===strlen($raw),503);
    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
    abort_unless(in_array($mime,['image/jpeg','image/png','image/webp'],true),422,'Use una foto JPG, PNG o WEBP.');
    $request->files->set('foto',new \Illuminate\Http\UploadedFile($temporary,'foto.'.match($mime){
     'image/png'=>'png','image/webp'=>'webp',default=>'jpg',
    },$mime,null,true));
   }
   if (!$product) return \App\Services\Pos\CatalogoProductos::crear($request,$tenant)->id;
   \App\Services\Pos\CatalogoProductos::editar($request,$product);
   return $product->id;
  } finally {
   if ($temporary) @unlink($temporary);
  }
 }
 private function catalogAuxiliary(DesktopDevice $device,array $op): int {
  [$kind,$action]=explode('.',$op['type'],2);
  $this->permit($device,$kind==='category'?'pos.productos.editar':'pos.proveedores');
  $tenant=$device->negocio()->id;
  $model=$kind==='category'?\App\Models\Pos\PosCategoria::class:\App\Models\Pos\PosProveedor::class;
  $data=$op['data'];
  $row=null;
  if ($action!=='create') {
   $row=$model::where('cyber_id',$tenant)->whereKey($this->ref($device,$data['id']??null,$kind.'.create'))->lockForUpdate()->firstOrFail();
   abort_unless(is_string($data['revision']??null)
       && hash_equals(\App\Services\Pos\CatalogoAuxiliar::revision($row),$data['revision']),409,'El registro cambió en el servidor.');
  }
  if ($action==='delete') {
   \App\Services\Pos\CatalogoAuxiliar::borrar($row);
   return $row->id;
  }
  abort_unless(is_array($data['fields']??null),422);
  return \App\Services\Pos\CatalogoAuxiliar::guardar(Request::create('/desktop/catalog','POST',$data['fields']),$tenant,$kind,$row)->id;
 }
 public function run(DesktopDevice $device,array $op): int {
  $p=$op['data'];$cy=$device->negocio();$u=$device->user;
  $occurred=\Carbon\Carbon::parse($op['occurred_at'])->utc();
  abort_unless($occurred->lte(now()->addMinutes(5)) && $occurred->gte(($device->license?->created_at??$device->created_at)->copy()->subMinutes(5)),422,'Revise la fecha declarada de la operación local.');
  if(in_array($op['type'],['client.full.create','client.full.update'],true))return app(FullClientSync::class)->run($device,$op);
  abort_unless($cy && !$u->esCliente(),403,'Esta cuenta no puede modificar operaciones empresariales.');
  if ($op['type'] === 'team.update') {
   $this->permit($device, 'personal');
   $data = Validator::make($p, [
    'id'=>'required|integer|min:1', 'revision'=>'required|regex:/^[a-f0-9]{64}$/',
    'cajero'=>'required|array:name,activo,permisos',
    'cajero.activo'=>'required|boolean', 'cajero.permisos'=>'present|array',
   ])->validate();
   $request = Request::create('/desktop/equipo', 'PUT', $data['cajero']);
   $request->setUserResolver(fn () => $u);
   app(\App\Http\Controllers\EquipoController::class)->editarDesdeEscritorio(
    $request, $cy, $data['id'], $data['revision'], new WorkspaceSnapshot($device),
   );
   return (int) $data['id'];
  }
  if ($op['type'] === 'client.permissions.update') {
   $this->permit($device, 'clientes.permisos');
   $data = Validator::make($p, [
    'id'=>'required', 'user_id'=>'required|integer|min:1',
    'revision'=>'required|regex:/^[a-f0-9]{64}$/', 'edicion_cliente'=>'present|array|max:4',
   ])->validate();
   $cliente = Cliente::query()->whereHas('cybers', fn ($q) => $q->whereKey($cy->id))
    ->whereKey($this->ref($device, $data['id'], 'client.full.create'))->lockForUpdate()->firstOrFail();
   $request = Request::create('/desktop/client-permissions', 'PUT', ['edicion_cliente'=>$data['edicion_cliente']]);
   $request->setUserResolver(fn () => $u);
   app(\App\Http\Controllers\AccesoClienteController::class)->aplicarPermisos($request, $cliente, $data);
   return $cliente->id;
  }
  if ($op['type'] === 'brand.update') {
   $this->permit($device, 'marca');
   app(\App\Services\MarcaNegocio::class)->sincronizar($cy, $p);
   return $cy->id;
  }
  if ($op['type'] === 'autoservicio.discard') {
   $this->permit($device, 'autoservicio');
   $data = Validator::make($p, ['id'=>'required|integer|min:1'])->validate();
   $solicitud = AutoservicioSolicitud::bloquearPendiente($data['id'], $u, $cy->id);
   $solicitud->resolver($u);
   return $solicitud->id;
  }
  $cfg=PosConfig::para($cy);
  if ($op['type'] === 'pos.config.update') {
   $this->permit($device, 'pos.config');
   $data = Validator::make($p, ['config'=>'required|array','revision'=>'required|regex:/^[a-f0-9]{64}$/'])->validate();
   $request = Request::create('/desktop/pos/configuracion', 'PUT', $data['config']);
   $request->setUserResolver(fn () => $u);
   app(\App\Http\Controllers\Pos\ConfigController::class)->aplicar($request, $cfg, $data['revision']);
   return $cfg->id;
  }
  if(in_array($op['type'],['client.create','client.update'],true)){
   $this->permit($device,'clientes.editar');
   abort_unless(is_array($p['client']??null),422,'Faltan los datos del cliente.');
   $fields=collect($p['client'])->only(self::CLIENT_FIELDS)->all();
   $req=Request::create('/desktop','POST',$fields+['cyber_id'=>$cy->id]);$req->setUserResolver(fn()=>$u);
   if($op['type']==='client.create'){
    $num=\App\Support\Documentos::limpiar($fields['tipo_documento']??'', $fields['numero_documento']??'');
    abort_if(Cliente::where('tipo_documento',$fields['tipo_documento']??'')->where('numero_documento',$num)->exists(),409,'El documento ya está registrado. Revise el cliente desde el sistema web antes de continuar.');
    return app(ClienteGuardar::class)->registrar($req)['cliente']->id;
   }
   $c=Cliente::whereHas('cybers',fn($q)=>$q->where('cybers.id',$cy->id))->whereKey($this->ref($device,$p['id']??null,['client.create','client.full.create']))->lockForUpdate()->firstOrFail();
   abort_unless(is_string($p['revision']??null)&&hash_equals(self::clientRow($c)['revision'],$p['revision']),409,'El cliente cambió en la web. Revise ambas versiones antes de volver a guardar.');
   $req->merge(self::clientRow($c)['data']===[]?[]:array_merge(self::clientRow($c)['data'],$fields));
   $validated=app(ClienteGuardar::class)->validarEscritorio($req);
   $duplicate=Cliente::where('tipo_documento',$validated['tipo_documento'])->where('numero_documento',$validated['numero_documento'])->whereKeyNot($c->id)->exists();
   abort_if($duplicate,409,'Ese documento ya está registrado.');
   $c->update(collect($validated)->only(self::CLIENT_FIELDS)->all());ClienteGuardar::sincronizarNombreCuenta($c);return $c->id;
  }
  if($op['type']==='ledger.create'){
   $this->permit($device,'contabilidad.registrar');
   $d=Validator::make($p,['tipo'=>['required',Rule::in(['ingreso','gasto'])],'categoria'=>'required|string|max:100','concepto'=>'required|string|max:255','monto'=>'required|numeric|min:0.01|max:999999999999.99','fecha'=>'required|date_format:Y-m-d','referencia'=>'nullable|string|max:120','moneda'=>['required',Rule::in([$cfg->moneda])]])->validate();
   abort_if(ContableMovimiento::where('cyber_id',$cy->id)->where('clave_operacion',$op['uuid'])->exists(),409,'La referencia ya fue usada por otra operación.');
   return LibroIngresosGastos::manual($cy,$u,$d+['clave_operacion'=>$op['uuid']])->id;
  }
  if($op['type']==='ledger.void'){
   $this->permit($device,'contabilidad.anular');
   $d=Validator::make($p,['motivo'=>'required|string|min:8|max:500'])->validate();
   $movement=ContableMovimiento::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['id']??null,'ledger.create'))->lockForUpdate()->firstOrFail();
   abort_unless($movement->origen==='manual' && $movement->estado==='activo',409,'El movimiento ya no se puede anular.');
   $movement->update(['estado'=>'anulado','motivo_anulacion'=>$d['motivo'],'anulada_por'=>$u->id,'anulada_en'=>$occurred]);
   return $movement->id;
  }
  if($op['type']==='cash.move'){
   $this->permit($device,'pos.caja.movimientos');
   $d=Validator::make($p,['tipo'=>'required|in:ingreso,retiro','monto'=>'required|numeric|min:0.01|max:999999999','motivo'=>'required|string|max:200'])->validate();
   $cash=PosCaja::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['caja_id']??null,'cash.open'))->lockForUpdate()->firstOrFail();
   abort_unless($cash->estado==='abierta',409,'La caja ya está cerrada.');
   abort_if($d['tipo']==='retiro' && round((float)$d['monto'],2)>$cash->resumen()['esperado']+0.001,409,'La caja no tiene efectivo suficiente.');
   return $cash->movimientos()->create($d+['user_id'=>$u->id])->id;
  }
  if($op['type']==='cash.open'){
   $this->permit($device,'pos.caja');$d=Validator::make($p,['monto_inicial'=>'required|numeric|min:0|max:999999999.99'])->validate();
   DB::table('cybers')->where('id',$cy->id)->lockForUpdate()->first();
   abort_if(PosCaja::abiertaDe($cy->id),409,'Ya hay una caja abierta en el negocio. Revise la apertura pendiente.');
   return PosCaja::create(['cyber_id'=>$cy->id,'user_id'=>$u->id,'abierta_en'=>$occurred,'monto_inicial'=>$d['monto_inicial']])->id;
  }
  if($op['type']==='cash.close'){
   $this->permit($device,'pos.caja.cerrar');$data=Validator::make($p,['efectivo_contado'=>'required|numeric|min:0|max:999999999.99','efectivo_esperado'=>'nullable|numeric','notas_cierre'=>'nullable|string|max:300','conteo'=>'nullable|array','conteo.*'=>'nullable|integer|min:0|max:100000'])->validate();
   $caja=PosCaja::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['caja_id']??null,'cash.open'))->lockForUpdate()->firstOrFail();
   abort_unless($caja->estado==='abierta',409,'La caja ya está cerrada.');
   $esperado=$caja->resumen()['esperado'];abort_if(isset($data['efectivo_esperado'])&&abs((float)$data['efectivo_esperado']-$esperado)>0.005,409,'El efectivo esperado cambió en el servidor.');$caja->update(['estado'=>'cerrada','cerrada_en'=>$occurred,'cerrada_por'=>$u->id,'efectivo_esperado'=>$esperado,'efectivo_contado'=>$data['efectivo_contado'],'diferencia'=>round($data['efectivo_contado']-$esperado,2),'conteo'=>array_filter($data['conteo']??[]),'notas_cierre'=>$data['notas_cierre']??'Cierre sincronizado desde escritorio '.$device->nombre]);return $caja->id;
  }
  if(in_array($op['type'],['product.create','product.update','product.delete'],true))return $this->product($device,$op);
  if(str_starts_with($op['type'],'category.')||str_starts_with($op['type'],'supplier.'))return $this->catalogAuxiliary($device,$op);
  if($op['type']==='purchase.create'){
   $this->permit($device,'pos.compras.editar');
   if(is_string($p['proveedor_id']??null)&&str_starts_with($p['proveedor_id'],'local:'))$p['proveedor_id']=$this->ref($device,$p['proveedor_id'],'supplier.create');
   return \App\Services\Pos\Compras::registrar($cy,$u,$this->resolveProducts($device,$p))->id;
  }
  if($op['type']==='purchase.void'){
   $this->permit($device,'pos.compras.anular');
   $purchase=\App\Models\Pos\PosCompra::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['id']??null,'purchase.create'))->firstOrFail();
   \App\Services\Pos\Compras::anular($purchase,$u);
   return $purchase->id;
  }
  if($op['type']==='stock.adjust'){
   $this->permit($device,'pos.ajustar');
   $d=Validator::make($p,['id'=>'required','modo'=>'required|in:sumar,restar,fijar',
    'cantidad'=>'required|numeric|min:0|max:1000000','motivo'=>'required|string|max:120','stock_previo'=>'required|numeric'])->validate();
   $product=PosProducto::where('cyber_id',$cy->id)->whereKey($this->ref($device,$d['id'],'product.create'))->lockForUpdate()->firstOrFail();
   abort_if($d['modo']==='fijar' && abs((float)$d['stock_previo']-(float)$product->stock)>0.005,409,'Las existencias cambiaron en el servidor. Revise el ajuste.');
   $delta=match($d['modo']){'sumar'=>(float)$d['cantidad'],'restar'=>-(float)$d['cantidad'],default=>(float)$d['cantidad']-(float)$product->stock};
   if(!$product->controla_stock)$product->update(['controla_stock'=>true]);
   if($delta!=0)\App\Services\Pos\Inventario::mover($product,$delta,'ajuste',$d['motivo'],$u->id);
   return $product->id;
  }
  if($op['type']==='sale.void'){
   $this->permit($device,'pos.anular');
   $d=Validator::make($p,['motivo'=>'required|string|min:3|max:200'])->validate();
   $sale=\App\Models\Pos\PosVenta::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['id']??null,'sale.create'))->firstOrFail();
   abort_unless($sale->estado!=='anulada',409,'La venta ya estaba anulada.');
   abort_if($sale->caja?->estado==='cerrada'&&!$u->esAdmin(),403,'Solo el administrador puede anular una venta de una caja cerrada.');
   Ventas::anular($sale,$u,$d['motivo']);
   return $sale->id;
  }
  if($op['type']==='sale.create'){
   $this->permit($device,'pos.vender');
   $p=$this->resolveProducts($device,$p);
   $data=Validator::make($p,['caja_id'=>'required','cliente_id'=>'nullable','cliente_nombre'=>'nullable|string|max:150','items'=>'required|array|min:1|max:200','items.*.producto_id'=>'nullable|integer|min:1','items.*.descripcion'=>'nullable|string|max:150','items.*.descuento'=>'nullable|numeric|min:0','descuento_pct'=>'nullable|numeric|min:0|max:100','notas'=>'nullable|string|max:300','items.*.cantidad'=>'required|numeric|min:0.01|max:999999','items.*.precio'=>'required|numeric|min:0|max:999999999','pagos'=>'required|array|min:1|max:10','pagos.*.metodo'=>'required|string|max:30','pagos.*.monto'=>'required|numeric|min:0|max:999999999','pagos.*.referencia'=>'nullable|string|max:100','total'=>'required|numeric|min:0|max:999999999','moneda'=>['required',Rule::in([$cfg->moneda])]])->validate();
   if(!empty($data['cliente_id'])){$data['cliente_id']=$this->ref($device,$data['cliente_id'],['client.create','client.full.create']);abort_unless(Cliente::whereKey($data['cliente_id'])->whereHas('cybers',fn($q)=>$q->where('cybers.id',$cy->id))->exists(),422,'El cliente no pertenece a este negocio.');}
   $caja=PosCaja::where('cyber_id',$cy->id)->whereKey($this->ref($device,$data['caja_id'],'cash.open'))->firstOrFail();
   $data['clave_operacion']=$op['uuid'];$data['notas']=$data['notas']??('Escritorio '.$device->nombre.' · referencia local '.$op['uuid'].' · fecha local declarada '.$op['occurred_at']);
   abort_if(\App\Models\Pos\PosVenta::where('cyber_id',$cy->id)->where('clave_operacion',$op['uuid'])->exists(),409,'La referencia ya fue usada por otra operación.');
   $venta=Ventas::registrar($data,$cfg,$caja,$u);
   abort_if(abs((float)$venta->total-(float)$data['total'])>0.005,409,'Los precios o impuestos cambiaron. La venta local se conserva para revisión y no se aplicó al servidor.');
   $venta->forceFill(['created_at'=>$occurred])->save();
   ContableMovimiento::where('venta_id',$venta->id)->where('cyber_id',$cy->id)->update(['fecha'=>$occurred->copy()->timezone(\App\Support\Negocios::ZONAS[$cy->pais]??config('app.timezone'))->toDateString()]);
   return $venta->id;
  }
  abort(422,'Tipo de operación no admitido.');
 }
}
