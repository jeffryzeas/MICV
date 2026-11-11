<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Str,Facades\DB};
use App\Models\{User,Cyber,Cliente,Configuracion,CorreoServicio,DesktopDevice,DesktopLicense,JzChat,JzMensaje};
use App\Models\Pos\PosProducto;
use App\Services\Desktop\{LicenseSigner,WorkspaceSnapshot};
use App\Support\Negocios;
class DesktopWorkspaceTest extends TestCase {
 use RefreshDatabase;
 private string $key;private User $user;private Cyber $business;private DesktopDevice $device;private DesktopLicense $license;
 protected function setUp():void {
  parent::setUp();$this->key=tempnam(sys_get_temp_dir(),'workspace-key');file_put_contents($this->key,base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
  config(['desktop.signing_key'=>$this->key,'desktop.require_https'=>false,'desktop.server_url'=>'https://example.test']);
  $this->business=Cyber::create(['codigo'=>'SPACE','nombre'=>'Negocio','activo'=>true]);$this->user=$this->makeUser('owner','cyber',$this->business->id);
  $this->license=DesktopLicense::create(['cyber_id'=>$this->business->id,'nombre'=>'Prueba','key_hash'=>hash('sha256','license'),'activa'=>true,'expires_at'=>now()->addMonth(),'device_limit'=>1,'offline_days'=>7,'modulos'=>array_keys(Negocios::MODULOS)]);
  $this->device=DesktopDevice::create(['uuid'=>(string)Str::uuid(),'user_id'=>$this->user->id,'license_id'=>$this->license->id,'access_role'=>'cyber','nombre'=>'PC','token_hash'=>hash('sha256',str_repeat('d',64))]);$this->withToken(str_repeat('d',64));
 }
 protected function tearDown():void {@unlink($this->key);parent::tearDown();}
 private function makeUser(string $name,string $role='cliente',?int $business=null):User{return User::create(['usuario'=>$name,'name'=>$name,'email'=>$name.'@example.test','password'=>'LocalPassword123','rol'=>$role,'cyber_id'=>$business,'activo'=>true]);}
 private function client(Cyber $business,?User $user=null):Cliente{$c=Cliente::create(['primer_nombre'=>'Ana','primer_apellido'=>'Prueba','pais'=>'CR','tipo_documento'=>'otro','numero_documento'=>Str::random(12),'user_id'=>$user?->id]);$c->cybers()->attach($business->id);return $c;}
 private function snapshot():array {
  $r=$this->getJson('/api/desktop/v1/workspace')->assertOk()->json();$json=base64_decode($r['payload']);$this->assertTrue(sodium_crypto_sign_verify_detached(base64_decode($r['signature']),$json,base64_decode(app(LicenseSigner::class)->publicKey())));return json_decode($json,true,512,JSON_THROW_ON_ERROR);
 }
 public function test_export_excludes_authentication_and_server_credentials():void {
  Configuracion::actual()->update(['smtp_usuario'=>'secret-user','smtp_password'=>'secret-password','recaptcha_secret_key'=>'secret-recaptcha','smtp_activo'=>true]);$data=$this->snapshot();$json=json_encode($data);
  foreach(['secret-user','secret-password','secret-recaptcha',$this->user->password,'token_hash','remember_token'] as $secret)$this->assertStringNotContainsString($secret,$json);
  $this->assertEqualsCanonicalizing(WorkspaceSnapshot::TABLES,array_keys($data['tables']));$this->assertFalse($data['tables']['configuracion'][0]['smtp_activo']);$this->assertSame('staged',$data['state']);
 }
 public function test_business_and_child_rows_are_scoped():void {
  $other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro','activo'=>true]);$own=$this->client($this->business);$foreign=$this->client($other);$own->estudios()->create(['nivel'=>'curso','titulo'=>'Propio']);$foreign->estudios()->create(['nivel'=>'curso','titulo'=>'Ajeno']);
  foreach([$this->business,$other] as $b)PosProducto::create(['cyber_id'=>$b->id,'tipo'=>'producto','nombre'=>$b->nombre,'precio'=>100,'costo'=>50]);$t=$this->snapshot()['tables'];
  $this->assertSame([$own->id],array_column($t['clientes'],'id'));$this->assertSame(['Propio'],array_column($t['cliente_estudios'],'titulo'));$this->assertSame([$this->business->id],array_column($t['pos_productos'],'cyber_id'));$this->assertSame([],$t['documentos_bloqueados']);
 }
 public function test_revocation_and_missing_token_deny_export():void {$this->license->update(['activa'=>false]);$this->getJson('/api/desktop/v1/workspace')->assertForbidden();$this->withToken('')->getJson('/api/desktop/v1/workspace')->assertUnauthorized();}
 public function test_cashier_does_not_receive_unlicensed_data_or_costs():void {
  PosProducto::create(['cyber_id'=>$this->business->id,'tipo'=>'producto','nombre'=>'Producto','precio'=>100,'costo'=>47]);$this->user->update(['permisos'=>['pos.vender']]);$this->license->update(['modulos'=>['pos']]);$t=$this->snapshot()['tables'];
  $this->assertCount(1,$t['pos_productos']);$this->assertArrayNotHasKey('costo',$t['pos_productos'][0]);foreach(['clientes','correo_servicios','tarjetas','contable_movimientos','pos_compras','pos_proveedores'] as $table)$this->assertSame([],$t[$table]);
 }
 public function test_customer_permission_metadata_is_limited_to_linked_accounts():void {
  $linked = $this->makeUser('linked');
  $linked->update(['edicion_cliente'=>['foto'],'chat_foto'=>'fotos/private.png']);
  $this->client($this->business, $linked);
  $this->makeUser('unlinked');
  $this->user->update(['permisos'=>['clientes.ver','clientes.permisos']]);
  $snapshot = $this->snapshot()['tables'];
  $this->assertSame([$this->user->id,$linked->id], array_column($snapshot['users'],'id'));
  $this->assertSame('["foto"]',$snapshot['users'][1]['edicion_cliente']);
  $this->assertArrayNotHasKey('chat_foto',$snapshot['users'][1]);
  $this->user->update(['permisos'=>['clientes.ver']]);
  $this->assertSame([$this->user->id], array_column($this->snapshot()['tables']['users'],'id'));
 }
 public function test_customer_own_cv_needs_no_business_license():void {
  $customer=$this->makeUser('customer');$own=$this->client($this->business,$customer);$this->client($this->business);$this->device->update(['user_id'=>$customer->id,'license_id'=>null,'access_role'=>'cliente']);$t=$this->snapshot()['tables'];
  $this->assertSame([$own->id],array_column($t['clientes'],'id'));$this->assertSame([$customer->id],array_column($t['users'],'id'));$this->assertSame([],$t['pos_productos']);
 }
 public function test_authorized_mail_can_be_reencrypted_locally():void {
  $client=$this->client($this->business);CorreoServicio::create(['cyber_id'=>$this->business->id,'cliente_id'=>$client->id,'proveedor'=>'Gmail','correo'=>'test@example.test','celular'=>'88888888','clave_correo'=>'Customer-secret','pais'=>'CR']);$this->assertNotSame('Customer-secret',DB::table('correo_servicios')->value('clave_correo'));
  $data=$this->snapshot();$this->assertSame('Customer-secret',$data['tables']['correo_servicios'][0]['clave_correo']);$this->assertSame(['clave_correo'],$data['encrypted_fields']['correo_servicios']);
 }
 public function test_chat_excludes_foreign_hidden_and_deleted_content():void {
  $contact=$this->makeUser('contact','cyber',$this->business->id);$chat=JzChat::create(['uno_id'=>$this->user->id,'dos_id'=>$contact->id]);
  foreach(['visible','hidden','deleted'] as $text){$m=JzMensaje::create(['chat_id'=>$chat->id,'user_id'=>$contact->id,'clave'=>(string)Str::uuid(),'texto'=>$text]);if($text==='hidden')DB::table('jz_mensajes_ocultos')->insert(['mensaje_id'=>$m->id,'user_id'=>$this->user->id]);if($text==='deleted')$m->update(['eliminado_en'=>now(),'archivo'=>'chat/private.pdf']);}
  $a=$this->makeUser('other-a');$b=$this->makeUser('other-b');$foreign=JzChat::create(['uno_id'=>$a->id,'dos_id'=>$b->id]);JzMensaje::create(['chat_id'=>$foreign->id,'user_id'=>$a->id,'clave'=>(string)Str::uuid(),'texto'=>'foreign']);
  $t=$this->snapshot()['tables'];$this->assertCount(1,$t['jz_chats']);$this->assertCount(2,$t['jz_mensajes']);$this->assertSame(['visible',null],array_column($t['jz_mensajes'],'texto'));$this->assertNull($t['jz_mensajes'][1]['archivo']);
 }
 public function test_admin_selected_business_filters_pivots_without_license():void {
  $this->user->update(['rol'=>'admin','cyber_id'=>null]);$this->device->update(['license_id'=>null,'access_role'=>'admin','business_id'=>$this->business->id]);$other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro','activo'=>true]);$client=$this->client($this->business);$client->cybers()->attach($other->id);
  $data=$this->snapshot();$this->assertSame('admin',$data['role']);$this->assertSame([$this->business->id],array_column($data['tables']['cliente_cyber'],'cyber_id'));
 }

 public function test_file_download_requires_current_ownership_and_hash():void {
  \Illuminate\Support\Facades\Storage::fake('local');$disk=\Illuminate\Support\Facades\Storage::disk('local');$disk->put('fotos/owned.jpg','picture');
  $c=$this->client($this->business);$c->update(['foto'=>'fotos/owned.jpg']);$data=$this->snapshot();$file=$data['files'][0];
  $this->assertSame(hash('sha256','picture'),$file['sha256']);
  $url='/api/desktop/v1/workspace/file?'.http_build_query(['table'=>'clientes','key'=>$c->id,'field'=>'foto','sha256'=>$file['sha256']]);
  $this->get($url)->assertOk()->assertDownload('owned.jpg');
  $disk->put('fotos/owned.jpg','changed');$this->getJson($url)->assertStatus(409);
  $other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro','activo'=>true]);$c->cybers()->sync([$other->id]);$this->getJson($url)->assertNotFound();
 }
 public function test_traversal_and_symlinks_cannot_export_private_server_files():void {
  \Illuminate\Support\Facades\Storage::fake('local');$disk=\Illuminate\Support\Facades\Storage::disk('local');$disk->makeDirectory('fotos');
  $c=$this->client($this->business);$c->update(['foto'=>'../desktop-signing.key']);$this->getJson('/api/desktop/v1/workspace')->assertStatus(422);
  symlink($this->key,$disk->path('fotos/escape.jpg'));$c->update(['foto'=>'fotos/escape.jpg']);$this->getJson('/api/desktop/v1/workspace')->assertForbidden();
 }
 public function test_missing_images_are_declared_without_exporting_arbitrary_paths():void {
  \Illuminate\Support\Facades\Storage::fake('local');$c=$this->client($this->business);$c->update(['foto'=>'fotos/missing.jpg']);$data=$this->snapshot();
  $this->assertTrue($data['files'][0]['missing']);$this->assertNull($data['files'][0]['sha256']);$this->assertSame(0,$data['files'][0]['size']);
 }
}
