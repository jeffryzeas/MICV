<?php
namespace App\Http\Middleware;
use App\Models\DesktopDevice;
use App\Services\Desktop\LicenseSigner;
use Illuminate\Http\Request;
use Closure;
class DesktopAuth {
 public function handle(Request $r,Closure $next){
  abort_unless(!config('desktop.require_https')||$r->secure(),400,'Use HTTPS para conectar el escritorio.');
  $token=$r->bearerToken();abort_unless(is_string($token)&&strlen($token)===64,401,'Active el equipo nuevamente.');
  $device=DesktopDevice::with('license.cyber','user.cyber')->where('token_hash',hash('sha256',$token))->first();abort_unless($device,401,'Active el equipo nuevamente.');
  app(LicenseSigner::class)->authorize($device);
  $r->attributes->set('desktop_device',$device);$r->setUserResolver(fn()=>$device->user);
  $device->forceFill(['last_seen_at'=>now()])->save();
  return $next($r)->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
 }
}
