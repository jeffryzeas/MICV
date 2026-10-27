<?php
namespace App\Http\Controllers;
use App\Models\{User,Cliente,ContableMovimiento,DesktopLicense,DesktopDevice,Cyber};
use App\Models\Pos\{PosConfig,PosProducto,PosCaja};
use App\Services\Desktop\{LicenseSigner,SyncOperations};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Validator};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
class DesktopApiController extends Controller {
 public function connection(Request $r){
  abort_unless(!config('desktop.require_https')||$r->secure(),400,'Use HTTPS para conectar el escritorio.');
  $signer=app(LicenseSigner::class);
  if(!$signer->diagnostics()['ready'])return response()->json(['message'=>'La firma del servidor necesita atención. El administrador debe revisar Comprobar conexión en Licencias de escritorio.'],503)->header('Cache-Control','no-store');
  return response()->json(['version'=>1,'server_url'=>rtrim(config('desktop.server_url'),'/'),'public_key'=>$signer->publicKey()])->header('Cache-Control','no-store');
 }
 private function account(Request $r): User {
  abort_unless(!config('desktop.require_https')||$r->secure(),400,'Use HTTPS para conectar el escritorio.');
  $d=$r->validate(['usuario'=>'required|string|max:150','password'=>'required|string|max:255']);
  $u=User::with('cyber')->where('usuario',$d['usuario'])->orWhere('email',mb_strtolower($d['usuario']))->first();
  abort_unless($u && Hash::check($d['password'],$u->password) && $u->activo && in_array($u->rol,['admin','cyber','cliente'],true),401,'Usuario o contraseña no válidos.');
  abort_if($u->esCyber() && !$u->cyber?->activo,403,'La empresa no está activa.');
  return $u;
 }
 public function access(Request $r){
  $u=$this->account($r);
  return response()->json(['user_id'=>$u->id,'name'=>$u->name,'role'=>$u->rol,'business_id'=>$u->cyber_id??0,'license_required'=>$u->esCyber(),'businesses'=>$u->esAdmin()?Cyber::where('activo',true)->orderBy('nombre')->get(['id','nombre']):[]])->header('Cache-Control','private, no-store');
 }
 private function metadata(DesktopDevice $device): array {
  $signer=app(LicenseSigner::class);$scopes=$signer->scopes($device);$cy=$device->negocio();$cfg=$cy?PosConfig::para($cy):null;
  $cash=$cy&&in_array('pos.caja',$scopes,true)?PosCaja::abiertaDe($cy->id):null;
  return ['lease'=>$signer->lease($device),'server_time'=>now()->timestamp,'catalogs'=>['countries'=>\App\Support\Pais::paraJs(),'nationalities'=>\App\Support\Nacionalidades::paraJs()],
   'business'=>['id'=>$cy?->id??0,'nombre'=>$cy?->nombre??($device->user->esAdmin()?'Administración general':'Mi cuenta'),'pais'=>$cy?->pais??'NI','moneda'=>$cfg?->moneda??'NIO','simbolo'=>$cfg?->simbolo??'C$','contabilidad_pos'=>(bool)$cy?->contabilidad_pos,'decimales'=>$cfg?->decimales??2,'impuesto_tasa'=>$cfg?->impuesto_tasa??0,'precios_con_impuesto'=>$cfg?->precios_con_impuesto??true,'metodos'=>$cfg?->metodosActivos()??[]],
   'cash'=>$cash?['id'=>$cash->id,'monto_inicial'=>$cash->monto_inicial,'esperado'=>$cash->resumen()['esperado']]:null];
 }
 public function activate(Request $r){
  $u=$this->account($r);
  $d=$r->validate(['license_key'=>'nullable|string|max:100','business_id'=>'nullable|integer|min:0','device_uuid'=>'required|uuid','device_name'=>'required|string|max:100']);
  app(LicenseSigner::class)->secret();
  $token=bin2hex(random_bytes(32));
  $device=DB::transaction(function()use($d,$u,$token){
   // Serializa activaciones del mismo usuario; el límite empresarial se bloquea por licencia.
   User::whereKey($u->id)->lockForUpdate()->first();$l=null;$business=null;
   if($u->esCyber()){
    abort_unless(!empty($d['license_key']),422,'Para activar esta empresa, ingrese su licencia.');
    $l=DesktopLicense::with('cyber')->where('key_hash',hash('sha256',trim($d['license_key'])))->lockForUpdate()->first();
    abort_unless($l && $l->activa && $l->expires_at->isFuture() && $l->cyber?->activo && (int)$u->cyber_id===(int)$l->cyber_id,401,'La licencia de la empresa no es válida.');
   }elseif($u->esAdmin() && !empty($d['business_id'])){
    $business=Cyber::where('activo',true)->findOrFail($d['business_id']);
   }elseif($u->esCliente())abort_if(!empty($d['business_id']),403,'Un cliente solo puede acceder a su cuenta.');
   $device=DesktopDevice::where('uuid',$d['device_uuid'])->lockForUpdate()->first();
   abort_if($device && ($device->revoked||(int)$device->user_id!==$u->id),403,'Este equipo pertenece a otra cuenta o fue revocado.');
   $context=$l?->cyber_id??$business?->id??0;
   abort_if($device && (($device->access_role && $device->access_role!==$u->rol)||(int)($device->negocio()?->id??0)!==(int)$context),409,'Use un perfil local separado para otra cuenta o negocio; se conservan sus datos.');
   if($l){abort_if($device && $device->license_id!==$l->id,403,'El equipo pertenece a otra licencia.');abort_if(!$device && $l->devices()->where('revoked',false)->count()>=$l->device_limit,403,'Se alcanzó el límite de equipos de esta licencia.');}
   $device??=new DesktopDevice(['uuid'=>$d['device_uuid'],'user_id'=>$u->id]);
   $device->fill(['license_id'=>$l?->id,'business_id'=>$business?->id,'access_role'=>$u->rol,'nombre'=>$d['device_name'],'token_hash'=>hash('sha256',$token),'last_seen_at'=>now()])->save();return $device->load('license.cyber','business','user.cyber');
  });
  return response()->json(['token'=>$token]+$this->metadata($device))->header('Cache-Control','no-store');
 }
 public function renew(Request $r){return response()->json($this->metadata($r->attributes->get('desktop_device')));}
 public function catalog(Request $r){
  $d=$r->validate(['entity'=>'required|in:clients,products,ledger','after'=>'nullable|integer|min:0']);$device=$r->attributes->get('desktop_device');$cy=$device->negocio()?->id;$scopes=app(LicenseSigner::class)->scopes($device);$after=(int)($d['after']??0);
  if($d['entity']==='clients'){
   abort_unless(in_array('clientes.ver',$scopes,true),403);$query=Cliente::query();if($device->user->esCliente())$query->where('user_id',$device->user_id);elseif(!$device->user->esAdmin()||$cy)$query->whereHas('cybers',fn($q)=>$q->where('cybers.id',$cy));$rows=$query->where('id','>',$after)->orderBy('id')->limit(250)->get()->map(fn($c)=>SyncOperations::clientRow($c));
  }elseif($d['entity']==='products'){
   abort_unless(in_array('pos.vender',$scopes,true),403);$rows=PosProducto::where('cyber_id',$cy)->where('activo',true)->where('id','>',$after)->orderBy('id')->limit(250)->get()->map(fn($p)=>['id'=>$p->id,'data'=>$p->only(['nombre','codigo','precio','stock','controla_stock','impuesto_tasa','exento','unidad'])]);
  }else{
   abort_unless(in_array('contabilidad.ver',$scopes,true),403);$rows=ContableMovimiento::where('cyber_id',$cy)->where('id','>',$after)->orderBy('id')->limit(250)->get()->map(fn($m)=>['id'=>$m->id,'data'=>array_merge($m->only(['tipo','concepto','categoria','monto','moneda','estado','origen','referencia','venta_id']),['fecha'=>$m->fecha->toDateString()])]);
  }
  return response()->json(['rows'=>$rows,'next'=>count($rows)===250?$rows->last()['id']:null]);
 }
 public function push(Request $r){
  $d=$r->validate(['operations'=>'required|array|min:1|max:20','operations.*.uuid'=>'required|uuid|distinct','operations.*.type'=>'required|in:client.create,client.update,ledger.create,cash.open,cash.close,sale.create','operations.*.occurred_at'=>'required|date','operations.*.data'=>'required|array']);
  abort_if(strlen(json_encode($d))>1024*1024,413,'El lote es demasiado grande.');$device=$r->attributes->get('desktop_device');$results=[];
  foreach($d['operations'] as $op){
   try{$results[]=DB::transaction(function()use($device,$op){
    $locked=DesktopDevice::with('license.cyber','user.cyber')->lockForUpdate()->findOrFail($device->id);app(LicenseSigner::class)->authorize($locked);
    // Ordenar claves evita tratar un reintento equivalente como otra operación.
    $canonical=function($v)use(&$canonical){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);return array_map($canonical,$v);};
    $hash=hash('sha256',json_encode($canonical($op),JSON_THROW_ON_ERROR));
    $receipt=DB::table('desktop_receipts')->where('device_id',$device->id)->where('operation_uuid',$op['uuid'])->first();
    if($receipt){abort_unless(hash_equals($receipt->payload_hash,$hash),409,'La misma referencia se recibió con datos diferentes.');return ['uuid'=>$op['uuid'],'status'=>'accepted','entity_id'=>$receipt->entity_id];}
    $id=app(SyncOperations::class)->run($locked,$op);
    DB::table('desktop_receipts')->insert(['device_id'=>$device->id,'operation_uuid'=>$op['uuid'],'payload_hash'=>$hash,'tipo'=>$op['type'],'entity_id'=>$id,'occurred_at'=>\Carbon\Carbon::parse($op['occurred_at'])->utc(),'created_at'=>now(),'updated_at'=>now()]);
    return ['uuid'=>$op['uuid'],'status'=>'accepted','entity_id'=>$id];
   });}catch(ValidationException $e){$results[]=['uuid'=>$op['uuid'],'status'=>'conflict','message'=>collect($e->errors())->flatten()->implode(' ')];}
   catch(\Throwable $e){if($e instanceof HttpExceptionInterface && $e->getStatusCode()<500)$msg=$e->getMessage()?:'Revise los datos y permisos de esta operación.';else{report($e);$msg='No se pudo aplicar la operación. Se conserva para reintentar.';}$results[]=['uuid'=>$op['uuid'],'status'=>'conflict','message'=>$msg];}
  }
  return response()->json(['results'=>$results]);
 }
}
