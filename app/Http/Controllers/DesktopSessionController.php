<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class DesktopSessionController extends Controller {
 public function open(Request $request){
  // DesktopAuth validates the bearer token, account and license. No passwords
  // or long-lived credentials are placed in the URL or in page JavaScript.
  $device=$request->attributes->get('desktop_device');
  Auth::login($device->user,false);$request->session()->regenerate();
  $request->session()->put('desktop_device_id',$device->id);
  if($device->user->esAdmin() && $device->negocio())$request->session()->put('pos_cyber',$device->negocio()->id);
  return redirect()->route($device->user->esCliente()?'mi-cv.panel':'dashboard')->header('Cache-Control','private, no-store');
 }
}
