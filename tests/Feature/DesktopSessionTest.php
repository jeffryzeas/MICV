<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Cyber,DesktopDevice,DesktopLicense};
use Illuminate\Support\Str;
class DesktopSessionTest extends TestCase {
 use RefreshDatabase;
 private User $user;private DesktopDevice $device;private DesktopLicense $license;
 protected function setUp():void {
  parent::setUp();config(['desktop.require_https'=>false]);
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
  $cy=Cyber::create(['codigo'=>'NATIVE','nombre'=>'Negocio','activo'=>true]);
  $this->user=User::create(['usuario'=>'NATIVE','name'=>'Usuario','email'=>'native@example.test','password'=>'Password123456','rol'=>'cyber','cyber_id'=>$cy->id,'activo'=>true,'permisos'=>['clientes.ver','cartas']]);
  $this->license=DesktopLicense::create(['cyber_id'=>$cy->id,'nombre'=>'Prueba','key_hash'=>hash('sha256','license'),'activa'=>true,'expires_at'=>now()->addMonth(),'device_limit'=>1,'offline_days'=>7,'modulos'=>['clientes']]);
  $this->device=DesktopDevice::create(['uuid'=>(string)Str::uuid(),'user_id'=>$this->user->id,'license_id'=>$this->license->id,'access_role'=>'cyber','nombre'=>'Equipo','token_hash'=>hash('sha256',str_repeat('b',64))]);
 }
 public function test_bridge_requires_authenticated_device_and_regenerates_session():void {
  $this->post('/desktop/session')->assertUnauthorized();$this->assertGuest();
  $this->withToken(str_repeat('b',64))->post('/desktop/session')->assertRedirect(route('dashboard'))->assertSessionHas('desktop_device_id',$this->device->id);$this->assertAuthenticatedAs($this->user);
 }
 public function test_desktop_session_obeys_license_modules_and_revocation():void {
  $this->withToken(str_repeat('b',64))->post('/desktop/session')->assertRedirect();
  $this->get('/cartas')->assertForbidden();$this->license->update(['modulos'=>['clientes','cartas']]);$this->get('/cartas')->assertOk();
  $this->device->update(['revoked'=>true]);$this->get('/inicio')->assertForbidden();
 }
 public function test_customer_and_admin_do_not_need_commercial_license():void {
  foreach(['cliente','admin'] as $role){
   $this->user->update(['rol'=>$role,'cyber_id'=>null]);$this->device->update(['access_role'=>$role,'license_id'=>null]);
   $this->withToken(str_repeat('b',64))->post('/desktop/session')->assertRedirect(route($role==='cliente'?'mi-cv.panel':'dashboard'));
   if($role==='cliente')$this->get(route('admin.desktop.index'))->assertForbidden();
   else $this->get(route('admin.desktop.index'))->assertOk();
  }
 }
 public function test_switching_user_cannot_reuse_desktop_session():void {
  $this->withToken(str_repeat('b',64))->post('/desktop/session')->assertRedirect();
  $other=User::create(['usuario'=>'OTHER','name'=>'Otro','email'=>'other@example.test','password'=>'Password123456','rol'=>'admin','activo'=>true]);
  $this->actingAs($other)->get('/inicio')->assertForbidden();
 }
 public function test_desktop_header_alone_cannot_access_web_without_activation():void {
  $this->withHeader('X-MiCV-Desktop','1')->get('/')->assertUnauthorized();
 }
 public function test_regular_web_login_is_not_forced_into_desktop_licensing():void {
  $this->actingAs($this->user)->get('/cartas')->assertOk();
 }
 public function test_administrator_can_enable_every_application_module():void {
  $this->user->update(['rol'=>'admin']);$this->actingAs($this->user)->put(route('admin.desktop.update',$this->license),['activa'=>true,'expires_at'=>now()->addMonth()->toDateTimeString(),'device_limit'=>2,'offline_days'=>7,'modulos'=>array_keys(\App\Support\Negocios::MODULOS)])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(array_keys(\App\Support\Negocios::MODULOS),$this->license->fresh()->modulos);
 }
 public function test_binary_and_stream_download_responses_are_supported():void {
  $this->license->update(['modulos'=>['clientes','cartas']]);
  \Illuminate\Support\Facades\Route::middleware('web')->get('/desktop-test-file',fn()=>response()->download(base_path('composer.json'),'prueba.json'))->name('cartas.desktop-test-file');
  \Illuminate\Support\Facades\Route::middleware('web')->get('/desktop-test-stream',fn()=>response()->streamDownload(fn()=>print('prueba'),'prueba.txt'))->name('cartas.desktop-test-stream');
  $this->withToken(str_repeat('b',64))->post('/desktop/session');
  $this->get('/desktop-test-file')->assertOk()->assertDownload('prueba.json');
  $this->get('/desktop-test-stream')->assertOk()->assertDownload('prueba.txt');
 }
 public function test_each_business_module_requires_both_license_and_user_permission():void {
  $this->user->update(['permisos'=>array_keys(\App\Support\Permisos::LISTA)]);
  $tested=[];
  foreach(\Illuminate\Support\Facades\Route::getRoutes() as $route){
   $name=$route->getName();$required=\App\Support\Permisos::deRuta($name);
   if(count($required)!==1)continue;
   $module=\App\Support\Negocios::moduloPermiso($required[0]);if(!$module)continue;
   $this->license->update(['modulos'=>[]]);$device=$this->device->fresh(['user.cyber','license.cyber']);
   $this->assertFalse(\App\Services\Desktop\DesktopWebPolicy::allows($device,$name),$name);
   $this->license->update(['modulos'=>[$module]]);$device=$this->device->fresh(['user.cyber','license.cyber']);
   $this->assertSame($device->user->tienePermiso($required[0]),\App\Services\Desktop\DesktopWebPolicy::allows($device,$name),$name);
   $tested[$module]=true;
  }
  $this->assertEqualsCanonicalizing(array_keys(\App\Support\Negocios::MODULOS),array_keys($tested));
 }
 public function test_native_request_token_must_match_the_bound_session():void {
  $this->withToken(str_repeat('b',64))->post('/desktop/session');
  $this->withHeader('X-MiCV-Desktop','1')->withToken(str_repeat('x',64))->get('/inicio')->assertUnauthorized();
 }
 public function test_https_remains_required_for_native_session_when_enabled():void {
  config(['desktop.require_https'=>true]);
  $this->withToken(str_repeat('b',64))->post('http://localhost/desktop/session')->assertStatus(400);
 }
 public function test_nested_menu_hides_unlicensed_children():void {
  $this->withToken(str_repeat('b',64))->post('/desktop/session');
  $content=$this->get('/inicio')->assertOk()->getContent();
  preg_match('/<nav id="jzMenu".*?<\/nav>/s',$content,$menu);
  $this->assertStringNotContainsString('href="'.route('cartas.index').'"',$menu[0]);
  $this->license->update(['modulos'=>['clientes','cartas']]);
  $content=$this->get('/inicio')->assertOk()->getContent();
  preg_match('/<nav id="jzMenu".*?<\/nav>/s',$content,$menu);
  $this->assertStringContainsString('href="'.route('cartas.index').'"',$menu[0]);
 }
}
