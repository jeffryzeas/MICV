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
  config(['desktop.signing_key'=>$this->keyFile,'desktop.require_https'=>false,'app.url'=>'https://micv.example.test']);
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

}
