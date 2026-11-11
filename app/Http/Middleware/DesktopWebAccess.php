<?php
namespace App\Http\Middleware;
use Closure;
use App\Models\DesktopDevice;
use App\Services\Desktop\{LicenseSigner,DesktopWebPolicy};
use Illuminate\Http\Request;
class DesktopWebAccess {
 public function handle(Request $request,Closure $next){
  if($request->routeIs('desktop.session'))return $next($request);
  $id=$request->session()->get('desktop_device_id');
  if($request->header('X-MiCV-Desktop')==='1'){
   abort_unless(!config('desktop.require_https')||$request->secure(),400);
   $token=$request->bearerToken();abort_unless(is_string($token)&&strlen($token)===64,401);
   $bound=DesktopDevice::where('token_hash',hash('sha256',$token))->first();abort_unless($bound,401);
   abort_unless($id && (int)$id===(int)$bound->id,401,'Vuelva a conectar la sesión desde el programa.');
  }
  if(!$id)return $next($request);
  $device=DesktopDevice::with('license.cyber','business','user.cyber')->find($id);abort_unless($device,401);
  app(LicenseSigner::class)->authorize($device);
  abort_unless($request->user() && (int)$request->user()->id===(int)$device->user_id,403,'La sesión no pertenece al acceso de escritorio.');
  abort_unless(DesktopWebPolicy::allows($device,$request->route()?->getName()),403,'La licencia o su cuenta no permite este módulo.');
  $request->attributes->set('desktop_device',$device);
  $response=$next($request);
  $response->headers->set('Cache-Control','private, no-store');
  return $response;
 }
}
