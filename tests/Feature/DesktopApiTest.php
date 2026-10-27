<?php
namespace Tests\Feature;
use App\Models\{Cyber,User,DesktopLicense,DesktopDevice,ContableMovimiento,Cliente};
use App\Models\Pos\{PosProducto,PosConfig,PosVenta};
use App\Services\Desktop\{LicenseSigner,SyncOperations};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class DesktopApiTest extends TestCase {
 use RefreshDatabase;
 private string $keyFile;private Cyber $business;private User $user;private DesktopLicense $license;private string $device;private string $token;
 protected function setUp(): void {
  parent::setUp();$this->keyFile=tempnam(sys_get_temp_dir(),'micv-sign-');file_put_contents($this->keyFile,base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
  config(['desktop.signing_key'=>$this->keyFile,'desktop.require_https'=>false,'app.url'=>'https://micv.example.test','desktop.server_url'=>'https://micv.example.test']);
  $this->business=Cyber::create(['codigo'=>'DESKTOP','nombre'=>'Negocio escritorio','pais'=>'CR','activo'=>true,'modulos'=>['clientes','pos','contabilidad','documentos'],'contabilidad_pos'=>true]);
  $this->user=User::create(['usuario'=>'DESKTOP','email'=>'desktop@example.test','name'=>'Encargado','password'=>'Prueba12345678','rol'=>'cyber','cyber_id'=>$this->business->id,'activo'=>true,'permisos'=>LicenseSigner::PERMISSIONS]);
  $this->license=DesktopLicense::create(['cyber_id'=>$this->business->id,'nombre'=>'Licencia prueba','key_hash'=>hash('sha256','CLAVE-PRUEBA'),'activa'=>true,'expires_at'=>now()->addYear(),'device_limit'=>1,'offline_days'=>7,'modulos'=>['clientes','pos','contabilidad','documentos']]);$this->device=(string)Str::uuid();
  $cfg=PosConfig::para($this->business);$cfg->update(['impuesto_tasa'=>0,'precios_con_impuesto'=>true]);
 }
 protected function tearDown(): void {@unlink($this->keyFile);parent::tearDown();}
 private function activate(array $extra=[]){return $this->postJson('/api/desktop/v1/activate',$extra+['usuario'=>'DESKTOP','password'=>'Prueba12345678','license_key'=>'CLAVE-PRUEBA','device_uuid'=>$this->device,'device_name'=>'Equipo prueba']);}
 private function connect(): void {$r=$this->activate()->assertOk();$this->token=$r->json('token');$this->withToken($this->token);}
 private function op(string $type,array $data): array{return ['uuid'=>(string)Str::uuid(),'type'=>$type,'data'=>$data,'occurred_at'=>now()->toIso8601String()];}
 private function push(array $ops){return $this->postJson('/api/desktop/v1/push',['operations'=>$ops])->assertOk();}
 public function test_firma_activacion_limite_de_equipos_y_revocacion(): void {
  $r=$this->activate()->assertOk();$lease=$r->json('lease');$claims=json_decode(base64_decode($lease['payload']),true);
  $this->assertTrue(sodium_crypto_sign_verify_detached(base64_decode($lease['signature']),base64_decode($lease['payload']),base64_decode(app(LicenseSigner::class)->publicKey())));
  $this->assertSame($this->device,$claims['device']);$this->assertLessThanOrEqual(7*86400,$claims['valid_until']-$claims['issued_at']);
  $this->assertStringNotContainsString($r->json('token'),DesktopDevice::first()->getRawOriginal('token_hash'));
  $this->activate(['device_uuid'=>(string)Str::uuid()])->assertForbidden();
  $this->withToken($r->json('token'))->postJson('/api/desktop/v1/renew')->assertOk();
  $this->license->update(['activa'=>false]);$this->postJson('/api/desktop/v1/renew')->assertForbidden();
 }
 public function test_reintentos_no_duplican_movimientos_y_rechazan_payload_cambiado(): void {
  $this->connect();$op=$this->op('ledger.create',['tipo'=>'ingreso','categoria'=>'Servicios','concepto'=>'Ingreso local','monto'=>'25.50','fecha'=>now()->toDateString(),'moneda'=>'CRC']);
  $this->push([$op])->assertJsonPath('results.0.status','accepted');$this->push([$op])->assertJsonPath('results.0.status','accepted');$this->assertDatabaseCount('contable_movimientos',1);$this->assertDatabaseCount('desktop_receipts',1);
  $op['data']['monto']='999';$this->push([$op])->assertJsonPath('results.0.status','conflict');$this->assertSame('25.50',ContableMovimiento::first()->monto);
 }
 public function test_negocio_y_permisos_no_pueden_eludirse_desde_escritorio(): void {
  $other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro negocio','activo'=>true]);$c=Cliente::create(['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'102340567','primer_nombre'=>'Secreto','primer_apellido'=>'Ajeno']);$c->cybers()->attach($other->id);
  $this->connect();$this->getJson('/api/desktop/v1/catalog?entity=clients')->assertOk()->assertJsonCount(0,'rows');
  $this->user->update(['permisos'=>['clientes.ver']]);$op=$this->op('ledger.create',['tipo'=>'ingreso','categoria'=>'Servicios','concepto'=>'No permitido','monto'=>'5','fecha'=>now()->toDateString(),'moneda'=>'CRC']);
  $this->push([$op])->assertJsonPath('results.0.status','conflict');$this->assertDatabaseCount('contable_movimientos',0);
  $this->getJson('/api/desktop/v1/catalog?entity=products')->assertForbidden();
 }
 public function test_venta_transaccional_y_conflicto_no_descuenta_stock_ni_duplica_venta(): void {
  $this->connect();$p=PosProducto::create(['cyber_id'=>$this->business->id,'nombre'=>'Producto','precio'=>10,'costo'=>3,'stock'=>5,'controla_stock'=>true,'activo'=>true]);
  $open=$this->op('cash.open',['monto_inicial'=>'20']);$open['occurred_at']=now()->subMinutes(2)->toIso8601String();$this->push([$open])->assertJsonPath('results.0.status','accepted');
  $sale=$this->op('sale.create',['caja_id'=>'local:'.$open['uuid'],'items'=>[['producto_id'=>$p->id,'cantidad'=>'1','precio'=>'10']],'pagos'=>[['metodo'=>'efectivo','monto'=>'10']],'total'=>'10','moneda'=>'CRC']);$sale['occurred_at']=now()->subMinute()->toIso8601String();
  $this->push([$sale])->assertJsonPath('results.0.status','accepted');$this->push([$sale])->assertJsonPath('results.0.status','accepted');$this->assertDatabaseCount('pos_ventas',1);$this->assertSame(\Carbon\Carbon::parse($sale['occurred_at'])->timestamp,PosVenta::first()->created_at->timestamp);$this->assertSame('4.00',$p->fresh()->stock);
  $bad=$sale;$bad['uuid']=(string)Str::uuid();$bad['data']['total']='99';$this->push([$bad])->assertJsonPath('results.0.status','conflict');$this->assertSame('4.00',$p->fresh()->stock);$this->assertDatabaseCount('pos_ventas',1);$this->assertDatabaseCount('contable_movimientos',1);
 }
 public function test_conflicto_cliente_no_sobrescribe_edicion_web(): void {
  $c=Cliente::create(['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'102340567','primer_nombre'=>'Ana','primer_apellido'=>'Prueba','nacionalidad'=>'Costarricense','pais_residencia'=>'CR']);$c->cybers()->attach($this->business->id);$revision=SyncOperations::clientRow($c)['revision'];$c->update(['primer_nombre'=>'Actualizada en web']);
  $this->connect();$op=$this->op('client.update',['id'=>$c->id,'revision'=>$revision,'client'=>['primer_nombre'=>'Dato antiguo']]);$this->push([$op])->assertJsonPath('results.0.status','conflict');$this->assertSame('Actualizada en web',$c->fresh()->primer_nombre);
 }
 public function test_panel_de_licencias_es_solo_administrador(): void {
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
  $this->actingAs($this->user)->get(route('admin.desktop.index'))->assertForbidden();
  $this->user->update(['rol'=>'admin']);$this->get(route('admin.desktop.index'))->assertOk()->assertSee('Licencias de escritorio');
 }
 public function test_administrador_puede_cambiar_modulos_y_regenerar_clave_sin_exponer_hash(): void {
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
  $this->user->update(['rol'=>'admin']);$this->actingAs($this->user)->put(route('admin.desktop.update',$this->license),['activa'=>1,'expires_at'=>now()->addMonth()->toDateTimeString(),'device_limit'=>2,'offline_days'=>3,'modulos'=>['contabilidad']])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(['contabilidad'],$this->license->fresh()->modulos);$this->assertSame(3,$this->license->fresh()->offline_days);
  $this->post(route('admin.desktop.key',$this->license))->assertRedirect()->assertSessionHas('desktop_key');
  $this->assertNotSame(hash('sha256','CLAVE-PRUEBA'),$this->license->fresh()->getRawOriginal('key_hash'));
 }

 private function connectionAdmin(): void {
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
  $this->user->update(['rol'=>'admin']);$this->actingAs($this->user);
 }
 public function test_configuracion_descarga_json_sin_clave_privada(): void {
  $this->connectionAdmin();$before=file_get_contents($this->keyFile);
  $this->get(route('admin.desktop.profile'))->assertOk()->assertHeader('Content-Disposition','attachment; filename="MiCV-conexion.json"')->assertExactJson(['version'=>1,'server_url'=>'https://micv.example.test','public_key'=>app(LicenseSigner::class)->publicKey()])->assertDontSee($before,false);
  $this->assertSame($before,file_get_contents($this->keyFile));
 }
 public function test_diagnostico_web_no_expone_ni_cambia_firma(): void {
  $this->connectionAdmin();$before=file_get_contents($this->keyFile);
  $this->get(route('admin.desktop.profile',['diagnostico'=>1]))->assertOk()->assertSee('PHP de esta página')->assertSee('ready')->assertDontSee($before,false);
  $this->assertArrayNotHasKey('key_raw',app(LicenseSigner::class)->diagnostics());$this->assertSame($before,file_get_contents($this->keyFile));
 }
 public function test_firma_ausente_muestra_causa_sin_crear_otra(): void {
  $this->connectionAdmin();unlink($this->keyFile);
  $this->get(route('admin.desktop.profile'))->assertStatus(422)->assertSee('key_not_visible')->assertDontSee('Estamos en mantenimiento');$this->assertFileDoesNotExist($this->keyFile);
 }
 public function test_firma_invalida_no_se_reemplaza_ni_se_expone(): void {
  $this->connectionAdmin();$bad=base64_encode(str_repeat('x',64));file_put_contents($this->keyFile,$bad);
  $this->get(route('admin.desktop.profile'))->assertStatus(422)->assertSee('key_invalid')->assertDontSee($bad,false);$this->assertSame($bad,file_get_contents($this->keyFile));
 }
 public function test_php_web_sin_sodium_muestra_diagnostico(): void {
  $this->connectionAdmin();$this->app->instance(LicenseSigner::class,new class extends LicenseSigner {protected function sodiumAvailable(): bool {return false;}});
  $this->get(route('admin.desktop.profile'))->assertStatus(422)->assertSee('sodium_missing')->assertDontSee('Estamos en mantenimiento');
 }
 public function test_conexion_y_diagnostico_solo_para_administrador(): void {
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);$this->actingAs($this->user);
  $this->get(route('admin.desktop.profile'))->assertForbidden();$this->get(route('admin.desktop.profile',['diagnostico'=>1]))->assertForbidden();
 }

 public function test_conexion_automatica_publica_solo_entrega_datos_publicos(): void {
  $secret=file_get_contents($this->keyFile);
  $this->getJson('/api/desktop/v1/connection')->assertOk()->assertExactJson(['version'=>1,'server_url'=>'https://micv.example.test','public_key'=>app(LicenseSigner::class)->publicKey()])->assertDontSee($secret,false);
  $this->assertDatabaseCount('desktop_devices',0);$this->assertSame($secret,file_get_contents($this->keyFile));
 }
 public function test_conexion_automatica_no_expone_ruta_privada_si_firma_falla(): void {
  unlink($this->keyFile);$this->getJson('/api/desktop/v1/connection')->assertStatus(503)->assertJsonStructure(['message'])->assertDontSee($this->keyFile,false);
 }
 public function test_conexion_automatica_exige_https_y_limita_intentos(): void {
  config(['desktop.require_https'=>true]);$this->getJson('/api/desktop/v1/connection')->assertStatus(400);config(['desktop.require_https'=>false]);
  for($i=0;$i<29;$i++)$this->getJson('/api/desktop/v1/connection')->assertOk();
  $this->getJson('/api/desktop/v1/connection')->assertStatus(429);
 }
 public function test_firma_usa_dominio_escritorio_aunque_app_url_sea_ejemplo(): void {
  config(['app.url'=>'https://tu-dominio.com']);$r=$this->activate()->assertOk();$claims=json_decode(base64_decode($r->json('lease.payload')),true);$this->assertSame('https://micv.example.test',$claims['issuer']);
 }

 public function test_empresa_se_identifica_antes_de_pedir_licencia_y_no_puede_omitirla(): void {
  $this->postJson('/api/desktop/v1/access',['usuario'=>'DESKTOP','password'=>'Prueba12345678'])->assertOk()->assertJsonPath('role','cyber')->assertJsonPath('license_required',true);
  $this->activate(['license_key'=>null,'role'=>'admin'])->assertStatus(422);$this->assertDatabaseCount('desktop_devices',0);
 }
 public function test_administrador_entra_sin_licencia_y_puede_usar_contexto_empresarial(): void {
  $this->user->update(['rol'=>'admin']);$this->license->update(['activa'=>false]);
  $this->postJson('/api/desktop/v1/access',['usuario'=>'DESKTOP','password'=>'Prueba12345678'])->assertOk()->assertJsonPath('license_required',false)->assertJsonPath('businesses.0.id',$this->business->id);
  $r=$this->activate(['license_key'=>null,'business_id'=>$this->business->id])->assertOk();$c=json_decode(base64_decode($r->json('lease.payload')),true);
  $this->assertFalse($c['license_required']);$this->assertNull($c['license_id']);$this->assertSame('admin',$c['role']);$this->assertContains('pos.vender',$c['scopes']);
  $this->assertNull(DesktopDevice::first()->license_id);$this->withToken($r->json('token'))->postJson('/api/desktop/v1/renew')->assertOk();
  $this->push([$this->op('ledger.create',['tipo'=>'ingreso','categoria'=>'Servicios','concepto'=>'Admin sin licencia','monto'=>'5.00','fecha'=>now()->toDateString(),'moneda'=>'CRC'])])->assertJsonPath('results.0.status','accepted');
 }
 public function test_cliente_entra_sin_licencia_y_solo_recibe_su_ficha(): void {
  $this->user->update(['rol'=>'cliente','cyber_id'=>null]);
  $own=Cliente::create(['user_id'=>$this->user->id,'pais'=>'NI','tipo_documento'=>'cedula','primer_nombre'=>'Propio','primer_apellido'=>'Prueba']);
  Cliente::create(['pais'=>'NI','tipo_documento'=>'cedula','primer_nombre'=>'Ajeno','primer_apellido'=>'Privado']);
  $this->postJson('/api/desktop/v1/access',['usuario'=>'DESKTOP','password'=>'Prueba12345678'])->assertOk()->assertJsonPath('license_required',false)->assertJsonCount(0,'businesses');
  $r=$this->activate(['license_key'=>null])->assertOk();$this->withToken($r->json('token'));
  $this->getJson('/api/desktop/v1/catalog?entity=clients')->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.id',$own->id)->assertDontSee('Ajeno');
  $this->getJson('/api/desktop/v1/catalog?entity=products')->assertForbidden();$this->getJson('/api/desktop/v1/catalog?entity=ledger')->assertForbidden();
  $this->push([$this->op('client.update',['id'=>$own->id,'client'=>['primer_nombre'=>'Manipulado']])])->assertJsonPath('results.0.status','conflict');$this->assertSame('Propio',$own->fresh()->primer_nombre);
 }
 public function test_cliente_no_puede_suplantar_admin_o_elegir_negocio(): void {
  $this->user->update(['rol'=>'cliente','cyber_id'=>null]);$this->activate(['license_key'=>null,'business_id'=>$this->business->id,'role'=>'admin'])->assertForbidden();$this->assertDatabaseCount('desktop_devices',0);
 }
 public function test_acceso_sin_licencia_respeta_revocacion_y_cambio_de_rol(): void {
  $this->user->update(['rol'=>'admin']);$r=$this->activate(['license_key'=>null])->assertOk();$this->withToken($r->json('token'));
  DesktopDevice::first()->update(['revoked'=>true]);$this->postJson('/api/desktop/v1/renew')->assertForbidden();
  DesktopDevice::first()->update(['revoked'=>false]);$this->user->update(['rol'=>'cliente']);$this->postJson('/api/desktop/v1/renew')->assertForbidden();
 }
 public function test_login_no_revela_rol_sin_password_valido_ni_permite_cuenta_inactiva(): void {
  $this->postJson('/api/desktop/v1/access',['usuario'=>'DESKTOP','password'=>'incorrecta'])->assertUnauthorized()->assertJsonMissingPath('role');
  $this->user->update(['activo'=>false]);$this->postJson('/api/desktop/v1/access',['usuario'=>'DESKTOP','password'=>'Prueba12345678'])->assertUnauthorized();
 }
 public function test_admin_no_puede_mezclar_negocios_en_perfil_ya_activado(): void {
  $this->user->update(['rol'=>'admin']);$this->activate(['license_key'=>null,'business_id'=>$this->business->id])->assertOk();
  $this->activate(['license_key'=>null,'business_id'=>0])->assertStatus(409);$this->assertSame($this->business->id,DesktopDevice::first()->business_id);
 }

}
