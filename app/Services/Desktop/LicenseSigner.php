<?php
namespace App\Services\Desktop;
use App\Models\DesktopDevice;
class LicenseSigner {
 public const PERMISSIONS=['clientes.ver','clientes.editar','pos.vender','pos.caja','pos.caja.cerrar','pos.ventas','contabilidad.ver','contabilidad.registrar','contabilidad.exportar','documentos'];
 public function secret(): string {
  abort_unless(function_exists('sodium_crypto_sign_detached'),503,'El servidor requiere la extensión sodium.');
  $key=@file_get_contents(config('desktop.signing_key'));$raw=$key?base64_decode(trim($key),true):false;
  abort_unless($raw!==false && strlen($raw)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES,503,'Configure la firma con php artisan desktop:keys.');return $raw;
 }
 public function publicKey(): string {return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secret()));}
 public function scopes(DesktopDevice $device): array {
  $u=$device->user;$l=$device->license;$cy=$l->cyber;
  return array_values(array_filter(self::PERMISSIONS,function($p)use($u,$l,$cy){$m=strtok($p,'.');return in_array($m,$l->modulos,true)&&$cy->moduloHabilitado($m)&&$u->tienePermiso($p);}));
 }
 public function lease(DesktopDevice $device): array {
  $l=$device->license;
  $claims=['version'=>1,'issuer'=>rtrim(config('app.url'),'/'),'device'=>$device->uuid,'license_id'=>$l->id,'tenant_id'=>$l->cyber_id,'user_id'=>$device->user_id,'issued_at'=>now()->timestamp,'valid_until'=>min($l->expires_at->timestamp,now()->addDays($l->offline_days)->timestamp),'scopes'=>$this->scopes($device),'nombre'=>$device->user->name,'negocio'=>$l->cyber->nombre];
  $payload=json_encode($claims,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  return ['payload'=>base64_encode($payload),'signature'=>base64_encode(sodium_crypto_sign_detached($payload,$this->secret()))];
 }
 public function authorize(DesktopDevice $device): void {
  $u=$device->user;$l=$device->license;
  abort_unless(!$device->revoked && $l->activa && $l->expires_at->isFuture() && $u->activo && !$u->esCliente() && $l->cyber?->activo && ($u->esAdmin() || (int)$u->cyber_id===(int)$l->cyber_id),403,'El acceso de escritorio fue desactivado o su licencia venció.');
 }
}
