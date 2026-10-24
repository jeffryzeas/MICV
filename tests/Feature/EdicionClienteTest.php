<?php
namespace Tests\Feature;
use App\Models\{User,Cliente,Cyber};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class EdicionClienteTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);}
 private function contexto(?array $edicion=['cv']): array {
  $cy=Cyber::create(['codigo'=>'P'.Str::random(8),'nombre'=>'Negocio de prueba','activo'=>true]);$s=Str::random(8);
  $u=User::create(['usuario'=>'CL'.$s,'email'=>$s.'@example.test','name'=>'Ana Inicial','rol'=>'cliente','password'=>'Prueba12345678','activo'=>true,'edicion_cliente'=>$edicion]);
  $c=Cliente::create(['user_id'=>$u->id,'primer_nombre'=>'Ana','primer_apellido'=>'Inicial','pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'102340567','nacionalidad'=>'Costarricense','pais_residencia'=>'CR','foto'=>'foto-actual.jpg','activo'=>true]);$c->cybers()->attach($cy->id);
  return [$cy,$u,$c];
 }
 private function encargado(Cyber $cy,array $permisos=['clientes.permisos']): User {$s=Str::random(8);return User::create(['usuario'=>$s,'email'=>$s.'@example.test','name'=>'Encargado','rol'=>'cyber','password'=>'Prueba12345678','cyber_id'=>$cy->id,'activo'=>true,'permisos'=>$permisos]);}
 public function test_cliente_edita_su_cv_sin_cambiar_datos_ni_foto(): void {
  [$cy,$u,$c]=$this->contexto(['cv']);$this->actingAs($u)->put(route('mi-cv.actualizar'),['estudios'=>[['nivel'=>'curso','titulo'=>'Excel avanzado','institucion'=>'Centro de prueba']],'habilidades'=>['Excel']])->assertRedirect(route('mi-cv.panel'));
  $this->assertSame('Ana',$c->fresh()->primer_nombre);$this->assertSame('foto-actual.jpg',$c->fresh()->foto);$this->assertDatabaseHas('cliente_estudios',['cliente_id'=>$c->id,'titulo'=>'Excel avanzado']);
 }
 public function test_payload_de_campos_restringidos_y_roles_es_rechazado(): void {
  [$cy,$u,$c]=$this->contexto(['cv']);foreach([['primer_nombre'=>'Suplantado'],['rol'=>'admin'],['edicion_cliente'=>['datos']],['activo'=>false],['quitar_foto'=>1]] as $payload)$this->actingAs($u)->put(route('mi-cv.actualizar'),$payload)->assertForbidden();
  $this->assertSame('cliente',$u->fresh()->rol);$this->assertSame('Ana',$c->fresh()->primer_nombre);$this->assertSame('foto-actual.jpg',$c->fresh()->foto);
 }
 public function test_cuenta_solo_lectura_puede_ver_datos_pero_no_guardar(): void {
  [$cy,$u,$c]=$this->contexto([]);$this->actingAs($u)->get(route('mi-cv.editar'))->assertOk()->assertSee('acceso de consulta')->assertDontSee('Guardar mis datos');$this->put(route('mi-cv.actualizar'),['primer_nombre'=>'Otro'])->assertForbidden();
 }
 public function test_encargado_autorizado_configura_portal_sin_cambiar_password(): void {
  [$cy,$u,$c]=$this->contexto(null);$pass=$u->password;$enc=$this->encargado($cy);$this->actingAs($enc)->get(route('clientes.acceso',$c))->assertOk()->assertSee('Permisos de edición del cliente');$this->put(route('clientes.acceso.permisos',$c),['edicion_cliente'=>['datos','cv']])->assertRedirect();$this->assertSame(['datos','cv'],$u->fresh()->edicion_cliente);$this->assertSame($pass,$u->fresh()->password);
 }
 public function test_negocio_ajeno_y_usuario_sin_permiso_no_administran_edicion(): void {
  [$cy,$u,$c]=$this->contexto();$ajeno=Cyber::create(['codigo'=>'AJENO','nombre'=>'Ajeno','activo'=>true]);$this->actingAs($this->encargado($ajeno))->put(route('clientes.acceso.permisos',$c),['edicion_cliente'=>[]])->assertForbidden();$this->actingAs($this->encargado($cy,['clientes.ver']))->put(route('clientes.acceso.permisos',$c),['edicion_cliente'=>[]])->assertForbidden();$this->actingAs($u)->put(route('clientes.acceso.permisos',$c),['edicion_cliente'=>['datos']])->assertForbidden();
 }
 public function test_no_se_puede_eludir_bloqueo_de_foto_desde_el_chat(): void {
  [$cy,$u,$c]=$this->contexto(['cv']);$this->actingAs($u)->postJson(route('chat.perfil.guardar'),['quitar_foto'=>true])->assertForbidden();$this->assertSame('foto-actual.jpg',$c->fresh()->foto);
 }
 public function test_documento_se_edita_sin_borrar_estudios_protegidos(): void {
  [$cy,$u,$c]=$this->contexto(['documento']);$estudio=$c->estudios()->create(['nivel'=>'curso','titulo'=>'Registro protegido']);$this->actingAs($u)->put(route('mi-cv.actualizar'),['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'203450678','fecha_nacimiento'=>'1998-05-15','municipio_nacimiento'=>'San José','departamento_nacimiento'=>'San José'])->assertSessionHasNoErrors()->assertRedirect();$this->assertSame('203450678',$c->fresh()->numero_documento);$this->assertDatabaseHas('cliente_estudios',['id'=>$estudio->id,'titulo'=>'Registro protegido']);
 }
 public function test_edicion_de_datos_preserva_documento_y_relaciones(): void {
  [$cy,$u,$c]=$this->contexto(['datos']);$estudio=$c->estudios()->create(['nivel'=>'curso','titulo'=>'Curso existente']);$this->actingAs($u)->put(route('mi-cv.actualizar'),['primer_nombre'=>'Ana María','primer_apellido'=>'Inicial','nacionalidad'=>'Costarricense','pais_residencia'=>'CR','telefono'=>'88880000'])->assertRedirect();$this->assertSame('Ana María',$c->fresh()->primer_nombre);$this->assertSame('102340567',$c->fresh()->numero_documento);$this->assertDatabaseHas('cliente_estudios',['id'=>$estudio->id]);
 }

 public function test_cuenta_anterior_sin_restricciones_conserva_edicion_completa_de_su_ficha(): void {
  [$cy,$u,$c]=$this->contexto(null);$this->actingAs($u)->put(route('mi-cv.actualizar'),['pais'=>'CR','tipo_documento'=>'cedula_cr','numero_documento'=>'102340567','fecha_nacimiento'=>'1998-05-15','municipio_nacimiento'=>'San José','departamento_nacimiento'=>'San José','primer_nombre'=>'Ana','primer_apellido'=>'Actualizada','nacionalidad'=>'Costarricense','pais_residencia'=>'CR','habilidades'=>['Organización']])->assertSessionHasNoErrors()->assertRedirect(route('mi-cv.panel'));$this->assertSame('Actualizada',$c->fresh()->primer_apellido);$this->assertDatabaseHas('cliente_habilidades',['cliente_id'=>$c->id,'habilidad'=>'Organización']);$this->put(route('mi-cv.actualizar'),['notas'=>'No son datos del cliente'])->assertForbidden();
 }
}
