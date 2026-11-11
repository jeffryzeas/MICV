<?php
namespace App\Services\Desktop;
use App\Models\DesktopDevice;
class LicenseSigner {
 public const PERMISSIONS=['clientes.ver','clientes.editar','pos.vender','pos.caja','pos.caja.cerrar','pos.ventas','contabilidad.ver','contabilidad.registrar','contabilidad.exportar','documentos'];
 protected function sodiumAvailable(): bool {
  return function_exists('sodium_crypto_sign_detached') && function_exists('sodium_crypto_sign_seed_keypair') && function_exists('sodium_crypto_sign_publickey_from_secretkey');
 }
 private function inspectKey(): array {
  $path=config('desktop.signing_key');$path=is_string($path)?trim($path):'';
  if($path!=='')clearstatcache(true,$path);
  $available=$this->sodiumAvailable();$visible=$path!=='' && @is_file($path);$readable=$visible && @is_readable($path);
  $state=['ready'=>false,'code'=>'','message'=>'','key_path'=>$path,'php_version'=>PHP_VERSION,'php_sapi'=>PHP_SAPI,'sodium'=>$available,'key_visible'=>$visible,'key_readable'=>$readable,'key_permissions'=>$visible?substr(sprintf('%o',@fileperms($path)),-4):null,'web_user_id'=>function_exists('posix_geteuid')?posix_geteuid():null,'key_owner_id'=>$visible?@fileowner($path):null,'key_raw'=>null];
  if(!$available){$state['code']='sodium_missing';$state['message']='Sodium no está disponible en el PHP que atiende esta página. Actívelo para este sitio en el hosting; el PHP de la terminal puede tener otra configuración.';return $state;}
  if($path===''){$state['code']='key_path_missing';$state['message']='La web no tiene cargada la ruta de firma. Compruebe que config/desktop.php está en esta instalación y limpie la caché de configuración de Laravel.';return $state;}
  if(!$visible){$state['code']='key_not_visible';$state['message']='El PHP de la web no encuentra o no puede acceder al archivo de firma en la ruta mostrada. Compruebe que desktop:keys se ejecutó en esta misma instalación y que el usuario web puede acceder a la carpeta privada.';return $state;}
  $encoded=$readable?@file_get_contents($path):false;
  if($encoded===false){$state['code']='key_not_readable';$state['message']='El archivo de firma existe, pero el PHP de la web no puede leerlo. Ajuste su propietario o permisos para el usuario PHP del sitio. No lo mueva a public ni use permisos 777.';return $state;}
  $raw=base64_decode(trim($encoded),true);
  $valid=$raw!==false && strlen($raw)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES;
  if($valid){$pair=sodium_crypto_sign_seed_keypair(substr($raw,0,SODIUM_CRYPTO_SIGN_SEEDBYTES));$valid=hash_equals(sodium_crypto_sign_secretkey($pair),$raw);}
  if(!$valid){$state['code']='key_invalid';$state['message']='El archivo de firma tiene un formato incorrecto o está incompleto. Debe ser la clave privada generada por desktop:keys, no la clave pública que imprimió la terminal. Conserve la firma original si ya hay equipos activados.';return $state;}
  $state['ready']=true;$state['code']='ready';$state['message']='La firma está disponible para el PHP de la web. Puede descargar la configuración de conexión.';$state['key_raw']=$raw;
  return $state;
 }
 public function diagnostics(): array {
  $state=$this->inspectKey();unset($state['key_raw']);return $state;
 }
 public function secret(): string {
  $state=$this->inspectKey();abort_unless($state['ready'],503,$state['message']);return $state['key_raw'];
 }
 public function publicKey(): string {return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secret()));}
 public function scopes(DesktopDevice $device): array {
  $u=$device->user;$cy=$device->negocio();
  if($u->esCliente())return ['clientes.ver'];
  if($u->esAdmin())return $cy?array_merge(self::PERMISSIONS,['admin']):['clientes.ver','admin'];
  $l=$device->license;
  if(!$l||!$cy)return [];
  return array_values(array_filter(self::PERMISSIONS,function($p)use($u,$l,$cy){$m=strtok($p,'.');return in_array($m,$l->modulos,true)&&$cy->moduloHabilitado($m)&&$u->tienePermiso($p);}));
 }
 public function lease(DesktopDevice $device): array {
  $u=$device->user;$l=$u->esCyber()?$device->license:null;$cy=$device->negocio();
  $until=$l?min($l->expires_at->timestamp,now()->addDays($l->offline_days)->timestamp):now()->addDays(30)->timestamp;
  $permissions=array_values(array_filter(array_keys(\App\Support\Permisos::LISTA),fn($p)=>(new WorkspaceSnapshot($device))->allows($p)));
  $claims=['permissions'=>$permissions,'client_edit_groups'=>$u->esCliente()?\App\Support\EdicionCliente::permitidos($u):[],'version'=>1,'issuer'=>rtrim(config('desktop.server_url'),'/'),'device'=>$device->uuid,'license_id'=>$l?->id,'license_required'=>$u->esCyber(),'role'=>$u->rol,'tenant_id'=>$cy?->id??0,'user_id'=>$device->user_id,'issued_at'=>now()->timestamp,'valid_until'=>$until,'scopes'=>$this->scopes($device),'nombre'=>$u->name,'negocio'=>$cy?->nombre??($u->esAdmin()?'Administración general':'Mi cuenta')];
  $payload=json_encode($claims,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  return ['payload'=>base64_encode($payload),'signature'=>base64_encode(sodium_crypto_sign_detached($payload,$this->secret()))];
 }
 public function authorize(DesktopDevice $device): void {
  $u=$device->user;$l=$device->license;
  abort_unless($u && $u->activo && !$device->revoked && (!$device->access_role || $device->access_role===$u->rol),403,'El acceso fue desactivado o cambió el rol de su cuenta.');
  if($u->esAdmin()){abort_if($device->negocio() && !$device->negocio()->activo,403,'El negocio fue desactivado.');return;}
  if($u->esCliente()){abort_unless($device->access_role==='cliente' && !$device->license_id && !$device->business_id,403,'Acceso de cliente no válido.');return;}
  abort_unless($u->esCyber() && $l && $l->activa && $l->expires_at->isFuture() && $l->cyber?->activo && (int)$u->cyber_id===(int)$l->cyber_id,403,'La licencia de la empresa venció o fue desactivada.');
 }
}
