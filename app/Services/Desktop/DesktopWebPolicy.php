<?php
namespace App\Services\Desktop;
use App\Models\DesktopDevice;
use App\Support\{Permisos,Negocios};
class DesktopWebPolicy {
 public static function allows(DesktopDevice $device,?string $route):bool {
  if(!$device->user->esCyber())return true;
  $required=Permisos::deRuta($route);
  if(!$required)return true;
  foreach($required as $permission){
   $module=Negocios::moduloPermiso($permission);
   if($device->user->tienePermiso($permission)&&(!$module || (in_array($module,$device->license?->modulos??[],true)&&$device->negocio()?->moduloHabilitado($module))))return true;
  }
  return false;
 }
}
