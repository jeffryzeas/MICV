<?php
namespace App\Services\Desktop;
use App\Models\{AutoservicioSolicitud,Cliente,DesktopDevice,Tarjeta};
use App\Services\ClienteGuardar;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{DB,Validator};
class FullClientSync {
 public const RELATIONS=['estudios','experiencias','idiomas','habilidades','referencias'];
 public static function row(Cliente $client):array {
  $client=$client->fresh()??throw new \RuntimeException('La ficha ya no existe.');
  $state=['client'=>$client->getAttributes()];
  foreach(self::RELATIONS as $relation)$state[$relation]=$client->$relation()->reorder()->orderBy('id')->get()->map(fn($row)=>$row->getAttributes())->all();
  return $state+['revision'=>self::fingerprint($state)];
 }
 public static function fingerprint(array $state):string {
  $canonical=function($value)use(&$canonical){if(!is_array($value))return $value;if(!array_is_list($value))ksort($value);return array_map($canonical,$value);};
  return hash('sha256',json_encode($canonical($state),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }
 public static function revision(Cliente $client):string {return self::row($client)['revision'];}
 public static function cardRevision(Cliente $client): string {
  $card = Tarjeta::firstWhere('cliente_id', $client->id);
  if (!$card) {
   return self::fingerprint([]);
  }
  $data = $card->only(['diseno','color','imagen','qr','datos']);
  $logo = \App\Support\Archivos::dataUri($card->logo);
  $data['logo'] = $logo === null ? null : hash('sha256', $logo);
  return self::fingerprint($data);
 }
 public function run(DesktopDevice $device,array $operation):int {
  $user=$device->user;$create=$operation['type']==='client.full.create';
  $photoOnly = !$create && ($operation['data']['photo_only'] ?? false) === true;
  $card = !$create && array_key_exists('card', $operation['data']);
  abort_if($card && $photoOnly, 422);
  $module = $card ? 'tarjetas' : ($photoOnly ? 'fotos' : 'clientes');
  $permission = $module === 'clientes' ? 'clientes.editar' : $module;
  if($user->esCyber())abort_unless(in_array($module,$device->license?->modulos??[],true)&&$device->negocio()?->moduloHabilitado($module)&&$user->tienePermiso($permission),403,'No puede editar clientes.');
  elseif($user->esCliente())abort_if($create,403,'El cliente solo puede editar su propia ficha.');
  else abort_unless($user->esAdmin(),403);
  $data = Validator::make($operation['data'], [
   'autoservicio_id' => $create ? 'nullable|integer|min:1' : 'prohibited',
   'client' => $photoOnly || $card ? 'prohibited' : 'required|array',
   'card' => $card ? 'present|array' : 'prohibited',
   'card_revision' => $card ? 'required|regex:/^[a-f0-9]{64}$/' : 'prohibited',
   'id' => $create ? 'nullable' : 'required',
   'revision' => $create ? 'nullable' : 'required|regex:/^[a-f0-9]{64}$/',
   'photo' => $photoOnly ? 'required|array' : 'nullable|array',
   'photo.content' => 'required_with:photo|string',
  ])->validate();
  $turno = $data['autoservicio_id'] ?? null;
  $negocio = $device->negocio();
  if ($turno) {
   abort_unless($negocio && (new WorkspaceSnapshot($device))->allows('autoservicio'), 403);
  }
  $fields=$data['client']??$data['card']??[];unset($fields['user_id'],$fields['creado_por'],$fields['foto'],$fields['password']);
  if($user->esCyber()){$fields['cyber_id']=$user->cyber_id;unset($fields['cybers'],$fields['cybers_enviados']);}
  if($user->esCliente())unset($fields['cyber_id'],$fields['cybers'],$fields['cybers_enviados']);
  if($create && $user->esAdmin() && $negocio){
   $fields['cyber_id']=$negocio->id;
   unset($fields['cybers'],$fields['cybers_enviados']);
  }
  if(!$create){
   $id=$data['id'];
   if(is_string($id)&&str_starts_with($id,'local:')){
    $id=DB::table('desktop_receipts')->where('device_id',$device->id)
     ->where('operation_uuid',substr($id,6))->where('tipo','client.full.create')->value('entity_id');
    abort_unless($id,409,'Primero sincronice el registro del cliente.');
   }
   abort_unless((is_int($id) || (is_string($id) && ctype_digit($id))) && filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false,422,'La referencia del cliente no es válida.');
   $data['id']=(int)$id;
  }
  $request=Request::create('/desktop/full-client','POST',$fields);$request->setUserResolver(fn()=>$user);$temp=null;
  $request->attributes->set('desktop_create_only',$create);
  try {
   if(isset($data['photo'])){
    abort_if(strlen($data['photo']['content'])>1024*1024,413,'La foto requiere una transferencia por partes.');
    $bytes=base64_decode($data['photo']['content'],true);abort_if($bytes===false||strlen($bytes)===0,422,'La foto no es válida.');
    $temp=tempnam(sys_get_temp_dir(),'micv-photo-');abort_unless($temp,503);file_put_contents($temp,$bytes);
    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temp);abort_unless(in_array($mime,['image/jpeg','image/png','image/webp'],true),422,'Use una foto JPG, PNG o WEBP.');
    $request->files->set($card ? 'logo' : 'foto',new UploadedFile($temp,'foto.'.match($mime){'image/png'=>'png','image/webp'=>'webp',default=>'jpg'},$mime,null,true));
   }
   return DB::transaction(function()use($user,$create,$data,$request,$photoOnly,$card,$turno,$negocio){
    $service=app(ClienteGuardar::class);
    if($create){
     $solicitud = $turno ? AutoservicioSolicitud::bloquearPendiente($turno, $user, $negocio->id) : null;
     $cliente = $service->registrar($request)['cliente'];
     $solicitud?->resolver($user, $cliente);
     return $cliente->id;
    }
    $client=Cliente::query()->visiblesPara($user)->whereKey($data['id'])->lockForUpdate()->firstOrFail();
    abort_unless(hash_equals(self::revision($client),$data['revision']),409,'La ficha o su CV cambió en el servidor. Revise ambas versiones antes de sincronizar.');
    if ($card) {
     abort_unless(hash_equals(self::cardRevision($client),$data['card_revision']),409,'La tarjeta cambió en el servidor. Revise ambas versiones.');
     app(\App\Http\Controllers\TarjetaController::class)->guardar($request, $client);
     return $client->id;
    }
    if ($photoOnly) {
     $photo = $request->file('foto');
     $request->merge(['imagen' => 'data:'.$photo->getMimeType().';base64,'.$data['photo']['content']]);
     $response = app(\App\Http\Controllers\FotoController::class)->usarEnCv($request, $client);
     abort_unless($response->getStatusCode() === 200, 422, 'La foto no es válida.');
     return $client->id;
    }
    return ($user->esCliente()?$service->actualizarPropio($request,$client):$service->actualizar($request,$client))->id;
   });
  } finally {if($temp)@unlink($temp);}
 }
}
