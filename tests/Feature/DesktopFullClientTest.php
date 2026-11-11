<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Cyber,Cliente,DesktopDevice,DesktopLicense};
use App\Services\Desktop\FullClientSync;
use Illuminate\Support\Str;
class DesktopFullClientTest extends TestCase {
 use RefreshDatabase;
 private string $key;private User $user;private Cyber $business;private DesktopDevice $device;
 protected function setUp():void {
  parent::setUp();$this->key=tempnam(sys_get_temp_dir(),'full-key');file_put_contents($this->key,base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
  config(['desktop.signing_key'=>$this->key,'desktop.require_https'=>false,'desktop.server_url'=>'https://example.test']);
  $this->business=Cyber::create(['codigo'=>'FULL','nombre'=>'Prueba','activo'=>true,'modulos'=>['clientes','cv']]);
  $this->user=User::create(['usuario'=>'LOCAL','name'=>'Operador','email'=>'local@example.test','password'=>'Password123456','rol'=>'cyber','cyber_id'=>$this->business->id,'activo'=>true,'permisos'=>['clientes.ver','clientes.editar']]);
  $license=DesktopLicense::create(['cyber_id'=>$this->business->id,'nombre'=>'Prueba','key_hash'=>hash('sha256','license-secret'),'activa'=>true,'expires_at'=>now()->addYear(),'device_limit'=>1,'offline_days'=>7,'modulos'=>['clientes','cv']]);
  $this->device=DesktopDevice::create(['uuid'=>(string)Str::uuid(),'user_id'=>$this->user->id,'license_id'=>$license->id,'access_role'=>'cyber','nombre'=>'Equipo','token_hash'=>hash('sha256',str_repeat('a',64))]);$this->withToken(str_repeat('a',64));
 }
 protected function tearDown():void {@unlink($this->key);parent::tearDown();}
 private function op(string $type,array $data):array {return ['uuid'=>(string)Str::uuid(),'type'=>$type,'occurred_at'=>now()->toIso8601String(),'data'=>$data];}
 private function fields():array {return ['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'102340567','primer_nombre'=>'Ana','primer_apellido'=>'Prueba','fecha_nacimiento'=>'1998-05-15','municipio_nacimiento'=>'San José','departamento_nacimiento'=>'San José','nacionalidad'=>'Costarricense','pais_residencia'=>'CR','activo'=>true,'estudios'=>[['nivel'=>'curso','titulo'=>'Contabilidad','institucion'=>'Centro local']],'experiencias'=>[['empresa'=>'Negocio','cargo'=>'Contadora']],'idiomas'=>[['idioma'=>'Español','nivel'=>'nativo']],'habilidades'=>['Excel'],'referencias'=>[['nombre'=>'Referencia','telefono'=>'88888888']]];}
 private function push(array $op){return $this->postJson('/api/desktop/v1/push',['operations'=>[$op]])->assertOk();}
 private function client():Cliente {$c=Cliente::create(collect($this->fields())->except(FullClientSync::RELATIONS)->all());$c->cybers()->attach($this->business->id);return $c;}
 public function test_full_dossier_sync_is_idempotent():void {
  $op=$this->op('client.full.create',['client'=>$this->fields()]);$r=$this->push($op)->assertJsonPath('results.0.status','accepted');$this->push($op)->assertJsonPath('results.0.status','accepted');
  $c=Cliente::findOrFail($r->json('results.0.entity_id'));foreach(FullClientSync::RELATIONS as $relation)$this->assertSame(1,$c->$relation()->count());$this->assertDatabaseCount('clientes',1);$this->assertDatabaseCount('desktop_receipts',1);
 }
 public function test_invalid_document_cannot_create_partial_cv():void {
  $op=$this->op('client.full.create',['client'=>array_replace($this->fields(),['numero_documento'=>'INVALID'])]);$this->push($op)->assertJsonPath('results.0.status','conflict');$this->assertDatabaseCount('clientes',0);$this->assertDatabaseCount('cliente_estudios',0);
 }
 public function test_cv_change_is_a_conflict():void {
  $c=$this->client();$revision=FullClientSync::revision($c);$c->estudios()->create(['nivel'=>'curso','titulo'=>'Cambio web']);
  $this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>$revision,'client'=>$this->fields()]))->assertJsonPath('results.0.status','conflict');$this->assertSame('Cambio web',$c->estudios()->first()->titulo);
 }
 public function test_full_update_keeps_all_cv_sections():void {
  $c=$this->client();$this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>$this->fields()]))->assertJsonPath('results.0.status','accepted');foreach(FullClientSync::RELATIONS as $relation)$this->assertSame(1,$c->$relation()->count());
 }
 public function test_read_only_customer_cannot_edit():void {
  $c=$this->client();$this->user->update(['rol'=>'cliente','cyber_id'=>null,'edicion_cliente'=>[]]);$c->update(['user_id'=>$this->user->id]);$this->device->update(['access_role'=>'cliente','license_id'=>null]);
  $this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>['estudios'=>[['nivel'=>'curso','titulo'=>'No autorizado']]]]))->assertJsonPath('results.0.status','conflict');$this->assertDatabaseCount('cliente_estudios',0);
 }
 public function test_customer_only_edits_authorized_groups():void {
  $c=$this->client();$this->user->update(['rol'=>'cliente','cyber_id'=>null,'edicion_cliente'=>['cv']]);$c->update(['user_id'=>$this->user->id]);$this->device->update(['access_role'=>'cliente','license_id'=>null]);
  $this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>['estudios'=>[['nivel'=>'curso','titulo'=>'Curso local']]]]))->assertJsonPath('results.0.status','accepted');$this->assertSame('Curso local',$c->estudios()->first()->titulo);
  $this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>['primer_nombre'=>'Prohibido']]))->assertJsonPath('results.0.status','conflict');$this->assertSame('Ana',$c->fresh()->primer_nombre);
 }
 public function test_other_tenant_and_permission_changes_are_rejected():void {
  $c=$this->client();$other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro','activo'=>true]);$c->cybers()->sync([$other->id]);
  $this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>$this->fields()]))->assertJsonPath('results.0.status','conflict');
  $this->user->update(['permisos'=>['clientes.ver']]);$this->push($this->op('client.full.create',['client'=>$this->fields()]))->assertJsonPath('results.0.status','conflict');
 }
 public function test_duplicate_document_does_not_overwrite_existing_dossier():void {
  $c=$this->client();$c->update(['primer_nombre'=>'Original']);$this->push($this->op('client.full.create',['client'=>$this->fields()]))->assertJsonPath('results.0.status','conflict');$this->assertSame('Original',$c->fresh()->primer_nombre);
 }
 public function test_card_sync_rejects_concurrent_changes_and_keeps_dossier_intact():void {
  $c = $this->client();
  $this->business->update(['modulos'=>['clientes','tarjetas']]);
  $this->user->update(['permisos'=>['tarjetas']]);
  $this->device->license->update(['modulos'=>['tarjetas']]);
  $data = [
   'id'=>$c->id,
   'revision'=>FullClientSync::revision($c),
   'card_revision'=>FullClientSync::cardRevision($c),
   'card'=>['diseno'=>\App\Models\Tarjeta::de($c)->diseno,'nombre'=>'Ana María'],
  ];
  $op = $this->op('client.full.update',$data);
  $this->push($op)->assertJsonPath('results.0.status','accepted');
  $this->push($op)->assertJsonPath('results.0.status','accepted');
  $data['card']['nombre'] = 'Edición en conflicto';
  $this->push($this->op('client.full.update',$data))->assertJsonPath('results.0.status','conflict');
  $this->assertSame('Ana María',\App\Models\Tarjeta::de($c)->d('nombre'));
  $this->assertSame('Ana',$c->fresh()->primer_nombre);
 }
 public function test_photo_only_sync_respects_customer_permissions():void {
  \Illuminate\Support\Facades\Storage::fake('local');
  $c = $this->client();
  $this->user->update(['rol'=>'cliente','cyber_id'=>null,'edicion_cliente'=>[]]);
  $this->device->update(['access_role'=>'cliente','license_id'=>null]);
  $c->update(['user_id'=>$this->user->id]);
  $data = ['id'=>$c->id,'revision'=>FullClientSync::revision($c),'photo_only'=>true,
   'photo'=>['content'=>'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=']];
  $this->push($this->op('client.full.update',$data))->assertJsonPath('results.0.status','conflict');
  $this->user->update(['edicion_cliente'=>['foto']]);
  $this->push($this->op('client.full.update',$data))->assertJsonPath('results.0.status','accepted');
  \Illuminate\Support\Facades\Storage::disk('local')->assertExists($c->fresh()->foto);
 }
 public function test_attendance_conflict_does_not_create_client():void {
  $this->business->update(['modulos'=>['clientes','autoservicio']]);
  $this->user->update(['permisos'=>['clientes.ver','clientes.editar','autoservicio']]);
  $this->device->license->update(['modulos'=>['clientes','autoservicio']]);
  $turno = \App\Models\AutoservicioSolicitud::create([
   'cyber_id'=>$this->business->id,'fecha'=>today(),'turno'=>1,
   'nombre'=>'Ana Prueba','datos'=>[],'estado'=>'descartada',
  ]);
  $this->push($this->op('client.full.create',['autoservicio_id'=>$turno->id,'client'=>$this->fields()]))
   ->assertJsonPath('results.0.status','conflict');
  $this->assertDatabaseCount('clientes',0);
  $this->assertSame('descartada',$turno->fresh()->estado);
 }
 public function test_client_permission_sync_rejects_concurrent_change_or_revoked_grant():void {
  $this->user->update(['permisos'=>['clientes.ver','clientes.permisos']]);
  $c = $this->client();
  $account = User::create(['usuario'=>'portal-test','name'=>'Ana','email'=>'portal-test@example.test',
   'rol'=>'cliente','password'=>'Local12345678','activo'=>true,'edicion_cliente'=>['foto']]);
  $c->update(['user_id'=>$account->id]);
  $data = ['id'=>$c->id,'user_id'=>$account->id,'revision'=>FullClientSync::fingerprint(['foto']),'edicion_cliente'=>['cv']];
  $this->push($this->op('client.permissions.update',$data))->assertJsonPath('results.0.status','accepted');
  $this->push($this->op('client.permissions.update',$data))->assertJsonPath('results.0.status','conflict');
  $data['revision'] = FullClientSync::fingerprint(['cv']);
  $this->user->update(['permisos'=>['clientes.ver']]);
  $this->push($this->op('client.permissions.update',$data))->assertJsonPath('results.0.status','conflict');
  $this->assertSame(['cv'],$account->fresh()->edicion_cliente);
 }
 public function test_team_sync_rechecks_manager_scope_and_delegation():void {
  $this->user->update(['permisos'=>['personal','clientes.ver']]);
  $cashier = User::create(['usuario'=>'CAJA-TEST','name'=>'Cajero','email'=>'caja-test@example.test',
   'rol'=>'cyber','cyber_id'=>$this->business->id,'encargado_id'=>$this->user->id,
   'password'=>'Local12345678','activo'=>true,'permisos'=>[]]);
  $data = [
   'id'=>$cashier->id,
   'revision'=>FullClientSync::fingerprint(['name'=>'Cajero','activo'=>true,'encargado_id'=>$this->user->id,'permisos'=>[]]),
   'cajero'=>['name'=>'Cajero editado','activo'=>true,'permisos'=>['clientes.editar']],
  ];
  $this->push($this->op('team.update',$data))->assertJsonPath('results.0.status','conflict');
  $data['cajero']['permisos'] = ['clientes.ver'];
  $other = User::create(['usuario'=>'OTRO-ENC','name'=>'Encargado','email'=>'other-enc@example.test',
   'rol'=>'cyber','cyber_id'=>$this->business->id,'password'=>'Local12345678','activo'=>true]);
  $cashier->update(['encargado_id'=>$other->id]);
  $this->push($this->op('team.update',$data))->assertJsonPath('results.0.status','conflict');
  $this->assertSame('Cajero',$cashier->fresh()->name);
  $this->assertSame([],$cashier->fresh()->permisos);
 }
 public function test_signed_dossier_download_is_scoped_and_complete():void {
  $c=$this->client();$c->estudios()->create(['nivel'=>'curso','titulo'=>'Curso privado']);
  $other=Cyber::create(['codigo'=>'OTHER','nombre'=>'Otro','activo'=>true]);$foreign=Cliente::create(['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'203450678','primer_nombre'=>'Ajeno','primer_apellido'=>'Privado']);$foreign->cybers()->attach($other->id);
  $envelope=$this->getJson('/api/desktop/v1/dossiers')->assertOk()->json();$raw=base64_decode($envelope['payload']);
  $this->assertTrue(sodium_crypto_sign_verify_detached(base64_decode($envelope['signature']),$raw,base64_decode(app(\App\Services\Desktop\LicenseSigner::class)->publicKey())));
  $data=json_decode($raw,true);$this->assertCount(1,$data['rows']);$this->assertSame('Curso privado',$data['rows'][0]['data']['estudios'][0]['titulo']);$this->assertSame(FullClientSync::revision($c),$data['rows'][0]['revision']);$this->assertStringNotContainsString('Ajeno',$raw);$this->assertSame($this->device->uuid,$data['device']);
  $this->user->update(['permisos'=>[]]);$this->getJson('/api/desktop/v1/dossiers')->assertForbidden();
 }
 public function test_admin_without_business_can_edit_full_dossier():void {
  $c=$this->client();$this->user->update(['rol'=>'admin','cyber_id'=>null]);$this->device->update(['access_role'=>'admin','license_id'=>null]);$this->push($this->op('client.full.update',['id'=>$c->id,'revision'=>FullClientSync::revision($c),'client'=>$this->fields()]))->assertJsonPath('results.0.status','accepted');
 }
}
