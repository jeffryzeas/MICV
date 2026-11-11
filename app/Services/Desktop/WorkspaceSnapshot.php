<?php
namespace App\Services\Desktop;
use App\Models\{Cliente,Configuracion,DesktopDevice,CorreoServicio,JzChat,JzMensaje};
use App\Services\ChatAcceso;
use App\Support\{Negocios,Permisos};
use Illuminate\Support\Facades\DB;

/** Authorized staging export, never a dump of authentication tables or server secrets. */
class WorkspaceSnapshot {
 public const TABLES=['cybers','users','configuracion','paises','municipios_nicaragua','clientes','cliente_cyber','cliente_estudios','cliente_experiencias','cliente_idiomas','cliente_habilidades','cliente_referencias','tarjetas','correo_servicios','autoservicio_solicitudes','pos_config','pos_categorias','pos_productos','pos_proveedores','pos_compras','pos_compra_items','pos_movimientos','pos_cajas','pos_caja_movimientos','pos_ventas','pos_venta_items','pos_venta_pagos','contable_movimientos','jz_chats','jz_mensajes','documentos_bloqueados'];
 public function __construct(private DesktopDevice $device) {}
 public function allows(string ...$permissions):bool {
  $u=$this->device->user;if($u->esAdmin())return true;if(!$u->esCyber())return false;
  foreach($permissions as $p){$m=Negocios::moduloPermiso($p);if($u->tienePermiso($p)&&(!$m||(in_array($m,$this->device->license?->modulos??[],true)&&$this->device->negocio()?->moduloHabilitado($m))))return true;}return false;
 }
 private function tenant($q){return $this->device->user->esAdmin()&&!$this->device->negocio()?$q:$q->where('cyber_id',$this->device->negocio()?->id??0);}
 public function queries():array {
  $u=$this->device->user;$q=[];$clients=Cliente::visiblesPara($u)->select('clientes.id');
  if($u->esAdmin()&&$this->device->negocio())$clients->whereHas('cybers',fn($c)=>$c->where('cybers.id',$this->device->negocio()->id));
  if(!$u->esCliente()&&!$this->allows('clientes.ver'))$clients->whereRaw('1=0');
  $q['clientes']=DB::table('clientes')->whereIn('id',clone $clients);
  $q['cliente_cyber']=DB::table('cliente_cyber')->whereIn('cliente_id',clone $clients);
  if($this->device->negocio())$q['cliente_cyber']->where('cyber_id',$this->device->negocio()->id);
  foreach(['estudios','experiencias','idiomas','habilidades','referencias'] as $r)$q['cliente_'.$r]=DB::table('cliente_'.$r)->whereIn('cliente_id',clone $clients);
  $q['cybers']=DB::table('cybers');
  if($u->esCliente())$q['cybers']->whereIn('id',(clone $q['cliente_cyber'])->select('cyber_id'));
  elseif($this->device->negocio())$q['cybers']->where('id',$this->device->negocio()->id);
  $q['users']=DB::table('users');
  if (!$u->esAdmin()) {
   $q['users']->where(function ($query) use ($u, $q) {
    $query->where('id', $u->id);
    if ($this->allows('personal')) {
     $query->orWhere(fn ($staff) => $staff->where('cyber_id', $u->cyber_id)->where('rol', 'cyber'));
    }
    if ($this->allows('clientes.permisos')) {
     $query->orWhere(fn ($portal) => $portal->where('rol', 'cliente')
      ->whereIn('id', (clone $q['clientes'])->whereNotNull('user_id')->select('user_id')));
    }
   });
  }
  foreach(['configuracion','paises','municipios_nicaragua'] as $t)$q[$t]=DB::table($t);
  if($u->esCliente()||$this->allows('tarjetas'))$q['tarjetas']=DB::table('tarjetas')->whereIn('cliente_id',clone $clients);
  if($this->allows('correos'))$q['correo_servicios']=$this->tenant(DB::table('correo_servicios'))->whereIn('cliente_id',clone $clients);
  if($this->allows('autoservicio'))$q['autoservicio_solicitudes']=$this->tenant(DB::table('autoservicio_solicitudes'));
  foreach(['pos_config'=>['pos.vender','pos.config','pos.ventas','pos.reportes','contabilidad.ver'],'pos_categorias'=>['pos.vender','pos.productos'],'pos_productos'=>['pos.vender','pos.productos','pos.compras.editar'],'pos_proveedores'=>['pos.proveedores','pos.compras','pos.productos.editar'],'pos_compras'=>['pos.compras'],'pos_movimientos'=>['pos.productos'],'pos_cajas'=>['pos.caja','pos.ventas','pos.reportes'],'pos_ventas'=>['pos.ventas','pos.reportes'],'contable_movimientos'=>['contabilidad.ver']] as $t=>$permissions)if($this->allows(...$permissions))$q[$t]=$this->tenant(DB::table($t));
  foreach(['pos_compra_items'=>['pos_compras','compra_id'],'pos_caja_movimientos'=>['pos_cajas','caja_id'],'pos_venta_items'=>['pos_ventas','venta_id'],'pos_venta_pagos'=>['pos_ventas','venta_id']] as $t=>[$parent,$fk])if(isset($q[$parent]))$q[$t]=DB::table($t)->whereIn($fk,(clone $q[$parent])->select('id'));
  if(($u->esCliente()||$this->allows('chat'))&&ChatAcceso::habilitado($u)){
   $chats=JzChat::with('uno.cyber','dos.cyber')->where(fn($w)=>$w->where('uno_id',$u->id)->orWhere('dos_id',$u->id))->limit(20001)->get();abort_if($chats->count()>20000,413);
   $ids=$chats->filter(fn($c)=>ChatAcceso::puede($u,$c->otro($u)))->pluck('id')->all();
   $q['jz_chats']=DB::table('jz_chats')->whereIn('id',$ids);
   $q['jz_mensajes']=DB::table('jz_mensajes')->whereIn('chat_id',$ids)->whereNotIn('id',DB::table('jz_mensajes_ocultos')->where('user_id',$u->id)->select('mensaje_id'));
  }
  if($u->esAdmin())$q['documentos_bloqueados']=DB::table('documentos_bloqueados');return $q;
 }
 public function project(string $table,array $row):array {
  if ($table === 'users' && $row['rol'] === 'cliente' && $this->device->user->esCyber()) {
   return array_intersect_key($row, array_flip(['id','usuario','name','email','rol','activo','edicion_cliente']));
  }
  if($table==='users')$row=array_intersect_key($row,array_flip(['id','cyber_id','encargado_id','usuario','name','email','rol','activo','permisos','edicion_cliente','email_verified_at','ultimo_acceso','terminos_version','terminos_aceptados_en','chat_foto','created_at','updated_at']));
  if($table==='configuracion'){
   $allowed=array_diff((new Configuracion)->getFillable(),['smtp_host','smtp_puerto','smtp_usuario','smtp_password','smtp_encriptacion','recaptcha_site_key','recaptcha_secret_key']);
   $row=array_intersect_key($row,array_flip(array_merge(['id','created_at','updated_at'],$allowed)));
   $row['smtp_activo']=false;$row['recaptcha_activo']=false;$row['registro_abierto']=false;
  }
  if($table==='cybers'&&$this->device->user->esCliente())$row=array_intersect_key($row,array_flip(['id','codigo','nombre','logo','color_primario','color_secundario','activo','direccion','telefono','correo','created_at','updated_at']));
  if($table==='pos_proveedores'&&!$this->allows('pos.proveedores','pos.compras')){
   $row=array_intersect_key($row,array_flip(['id','cyber_id','nombre','activo','created_at','updated_at']));
  }
  if($table==='pos_productos'&&!$this->allows('pos.productos','pos.compras.editar'))unset($row['costo'],$row['proveedor_id']);
  if(in_array($table,['pos_ventas','pos_venta_items'],true)&&!$this->allows('pos.reportes'))unset($row['costo_total'],$row['costo']);
  if($table==='correo_servicios')$row['clave_correo']=(new CorreoServicio)->setRawAttributes($row)->clave_correo;
  if($table==='jz_mensajes'){
   if($row['eliminado_en'])foreach(['texto','archivo','miniatura','nombre'] as $f)$row[$f]=null;
   else $row['texto']=(new JzMensaje)->setRawAttributes($row)->texto;
  }
  return $row;
 }
 public function build():array {
  return DB::transaction(function(){
   $tables=array_fill_keys(self::TABLES,[]);$count=0;
   foreach($this->queries() as $table=>$query){$rows=$query->limit(20001)->get();$count+=$rows->count();abort_if($rows->count()>20000||$count>100000,413,'La descarga supera el límite; no se creó una copia parcial.');$tables[$table]=$rows->map(fn($row)=>$this->project($table,(array)$row))->all();}
   return ['format'=>'micv-workspace-v1','state'=>'staged','issuer'=>rtrim(config('desktop.server_url'),'/'),'device'=>$this->device->uuid,'user_id'=>$this->device->user_id,'tenant_id'=>$this->device->negocio()?->id??0,'role'=>$this->device->user->rol,'issued_at'=>now()->timestamp,'tables'=>$tables,'files'=>WorkspaceFiles::describe($tables),'encrypted_fields'=>['correo_servicios'=>['clave_correo'],'jz_mensajes'=>['texto']],'permissions'=>array_values(array_filter(array_keys(Permisos::LISTA),fn($p)=>$this->allows($p)))];
  });
 }
}
