<?php
namespace App\Services\Desktop;
use App\Models\{Cliente,ContableMovimiento,DesktopDevice};
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
 private function permit(DesktopDevice $d,string $p): void {abort_unless(in_array($p,app(LicenseSigner::class)->scopes($d),true),403,'Su cuenta no tiene permiso para esta operación.');}
 private function ref(DesktopDevice $d,mixed $value,string $type): int {
  if(is_string($value)&&str_starts_with($value,'local:')){
   $r=DB::table('desktop_receipts')->where('device_id',$d->id)->where('operation_uuid',substr($value,6))->where('tipo',$type)->first();
   abort_unless($r,409,'Primero debe sincronizar la operación relacionada.');return $r->entity_id;
  }
  abort_unless(is_numeric($value)&&(int)$value>0,422,'La referencia no es válida.');return (int)$value;
 }
 public function run(DesktopDevice $device,array $op): int {
  $p=$op['data'];$cy=$device->license->cyber;$u=$device->user;$cfg=PosConfig::para($cy);
  $occurred=\Carbon\Carbon::parse($op['occurred_at'])->utc();
  abort_unless($occurred->lte(now()->addMinutes(5)) && $occurred->gte($device->license->created_at->copy()->subMinutes(5)),422,'Revise la fecha declarada de la operación local.');
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
   $c=Cliente::whereHas('cybers',fn($q)=>$q->where('cybers.id',$cy->id))->whereKey($this->ref($device,$p['id']??null,'client.create'))->lockForUpdate()->firstOrFail();
   abort_unless(is_string($p['revision']??null)&&hash_equals(self::clientRow($c)['revision'],$p['revision']),409,'El cliente cambió en la web. Revise ambas versiones antes de volver a guardar.');
   $req->merge(self::clientRow($c)['data']===[]?[]:array_merge(self::clientRow($c)['data'],$fields));
   $validated=app(ClienteGuardar::class)->validarEscritorio($req);
   $duplicate=Cliente::where('tipo_documento',$validated['tipo_documento'])->where('numero_documento',$validated['numero_documento'])->whereKeyNot($c->id)->exists();
   abort_if($duplicate,409,'Ese documento ya está registrado.');
   $c->update(collect($validated)->only(self::CLIENT_FIELDS)->all());ClienteGuardar::sincronizarNombreCuenta($c);return $c->id;
  }
  if($op['type']==='ledger.create'){
   $this->permit($device,'contabilidad.registrar');
   $d=Validator::make($p,['tipo'=>['required',Rule::in(['ingreso','gasto'])],'categoria'=>'required|string|max:100','concepto'=>'required|string|max:255','monto'=>'required|numeric|min:0.01|max:999999999.99','fecha'=>'required|date_format:Y-m-d','referencia'=>'nullable|string|max:120','moneda'=>['required',Rule::in([$cfg->moneda])]])->validate();
   abort_if(ContableMovimiento::where('cyber_id',$cy->id)->where('clave_operacion',$op['uuid'])->exists(),409,'La referencia ya fue usada por otra operación.');
   return LibroIngresosGastos::manual($cy,$u,$d+['clave_operacion'=>$op['uuid']])->id;
  }
  if($op['type']==='cash.open'){
   $this->permit($device,'pos.caja');$d=Validator::make($p,['monto_inicial'=>'required|numeric|min:0|max:999999999.99'])->validate();
   DB::table('cybers')->where('id',$cy->id)->lockForUpdate()->first();
   abort_if(PosCaja::abiertaDe($cy->id),409,'Ya hay una caja abierta en el negocio. Revise la apertura pendiente.');
   return PosCaja::create(['cyber_id'=>$cy->id,'user_id'=>$u->id,'abierta_en'=>$occurred,'monto_inicial'=>$d['monto_inicial']])->id;
  }
  if($op['type']==='cash.close'){
   $this->permit($device,'pos.caja.cerrar');$data=Validator::make($p,['efectivo_contado'=>'required|numeric|min:0|max:999999999.99'])->validate();
   $caja=PosCaja::where('cyber_id',$cy->id)->whereKey($this->ref($device,$p['caja_id']??null,'cash.open'))->lockForUpdate()->firstOrFail();
   abort_unless($caja->estado==='abierta',409,'La caja ya está cerrada.');
   $esperado=$caja->resumen()['esperado'];$caja->update(['estado'=>'cerrada','cerrada_en'=>$occurred,'cerrada_por'=>$u->id,'efectivo_esperado'=>$esperado,'efectivo_contado'=>$data['efectivo_contado'],'diferencia'=>round($data['efectivo_contado']-$esperado,2),'notas_cierre'=>'Cierre sincronizado desde escritorio '.$device->nombre]);return $caja->id;
  }
  if($op['type']==='sale.create'){
   $this->permit($device,'pos.vender');
   $data=Validator::make($p,['caja_id'=>'required','cliente_id'=>'nullable','cliente_nombre'=>'nullable|string|max:150','items'=>'required|array|min:1|max:100','items.*.producto_id'=>'required|integer|min:1','items.*.cantidad'=>'required|numeric|min:0.01|max:999999','items.*.precio'=>'required|numeric|min:0|max:999999999','pagos'=>'required|array|min:1|max:10','pagos.*.metodo'=>'required|string|max:30','pagos.*.monto'=>'required|numeric|min:0.01|max:999999999','pagos.*.referencia'=>'nullable|string|max:100','total'=>'required|numeric|min:0|max:999999999','moneda'=>['required',Rule::in([$cfg->moneda])]])->validate();
   if(!empty($data['cliente_id'])){$data['cliente_id']=$this->ref($device,$data['cliente_id'],'client.create');abort_unless(Cliente::whereKey($data['cliente_id'])->whereHas('cybers',fn($q)=>$q->where('cybers.id',$cy->id))->exists(),422,'El cliente no pertenece a este negocio.');}
   $caja=PosCaja::where('cyber_id',$cy->id)->whereKey($this->ref($device,$data['caja_id'],'cash.open'))->firstOrFail();
   $data['clave_operacion']=$op['uuid'];$data['notas']='Escritorio '.$device->nombre.' · referencia local '.$op['uuid'].' · fecha local declarada '.$op['occurred_at'];
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
