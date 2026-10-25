<?php
namespace App\Http\Controllers;
use App\Models\{Cyber,DesktopLicense,DesktopDevice,Actividad};
use App\Services\Desktop\LicenseSigner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class DesktopLicenseController extends Controller {
 public function index(){return view('admin.desktop.index',['licencias'=>DesktopLicense::with('cyber','devices.user')->latest()->paginate(20),'negocios'=>Cyber::where('activo',true)->orderBy('nombre')->get()]);}
 public function store(Request $r){
  $d=$r->validate(['cyber_id'=>'required|exists:cybers,id','nombre'=>'required|string|max:100','expires_at'=>'required|date|after:now','device_limit'=>'required|integer|min:1|max:100','offline_days'=>'required|integer|min:1|max:30','modulos'=>'required|array|min:1','modulos.*'=>[Rule::in(['clientes','pos','contabilidad','documentos'])]]);
  $key='JZDESK-'.strtoupper(bin2hex(random_bytes(20)));$l=DesktopLicense::create($d+['key_hash'=>hash('sha256',$key),'created_by'=>$r->user()->id]);
  Actividad::registrar('desktop_licencia_crear','#'.$l->id);
  return back()->with('desktop_key',$key)->with('mensaje','Licencia creada. Guarde la clave: solo se muestra en esta ocasión.');
 }
 public function update(Request $r,DesktopLicense $license){
  $d=$r->validate(['activa'=>'required|boolean','expires_at'=>'required|date','device_limit'=>'required|integer|min:1|max:100','offline_days'=>'required|integer|min:1|max:30','modulos'=>'required|array|min:1','modulos.*'=>[Rule::in(['clientes','pos','contabilidad','documentos'])]]);
  $license->update($d);Actividad::registrar('desktop_licencia_actualizar','#'.$license->id);return back()->with('mensaje','Licencia actualizada.');
 }
 public function device(Request $r,DesktopDevice $device){$d=$r->validate(['revoked'=>'required|boolean']);$device->update($d);Actividad::registrar('desktop_equipo_actualizar','#'.$device->id);return back()->with('mensaje','Acceso del equipo actualizado.');}
 public function key(Request $r,DesktopLicense $license){$key='JZDESK-'.strtoupper(bin2hex(random_bytes(20)));$license->update(['key_hash'=>hash('sha256',$key)]);Actividad::registrar('desktop_clave_renovar','#'.$license->id);return back()->with('desktop_key',$key)->with('mensaje','Nueva clave generada. La clave anterior ya no permite activar equipos.');}
 public function profile(Request $r){
  $signer=app(LicenseSigner::class);$diagnostico=$signer->diagnostics();
  if($r->boolean('diagnostico') || !$diagnostico['ready']){
   return response()->view('admin.desktop.conexion',compact('diagnostico'),$diagnostico['ready']?200:422)->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
  }
  return response()->json(['version'=>1,'server_url'=>rtrim(config('app.url'),'/'),'public_key'=>$signer->publicKey()])->header('Content-Disposition','attachment; filename="MiCV-conexion.json"')->header('Cache-Control','private, no-store');
 }
}
