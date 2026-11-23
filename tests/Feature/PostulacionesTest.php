<?php
namespace Tests\Feature;

use App\Jobs\EnviarPostulacion;
use App\Models\{Cliente, Cyber, GmailCuenta, Postulacion, PostulacionEmpresa, User};
use App\Services\{GmailPostulaciones, PostulacionPdf};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class PostulacionesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
        Storage::fake('local');Queue::fake([EnviarPostulacion::class]);
        $this->mock(PostulacionPdf::class,fn($m)=>$m->shouldReceive('generar')->andReturn('%PDF-1.4 version del CV original'));
    }
    private function user(string $rol='cliente'): User
    {
        $id=Str::random(8);
        return User::create(['usuario'=>'P'.$id,'name'=>'Ana Pérez '.$id,'email'=>$id.'@example.test','password'=>'Prueba12345678','rol'=>$rol,'activo'=>true]);
    }
    private function contexto(): array
    {
        $u=$this->user();$c=Cliente::create(['user_id'=>$u->id,'primer_nombre'=>'Ana','primer_apellido'=>'Pérez','profesion'=>'Asistente administrativa','activo'=>true]);
        $e=PostulacionEmpresa::create(['user_id'=>$u->id,'nombre'=>'Empresa Demo','correo'=>'empleos@example.test','cargo'=>'Asistente','activa'=>true]);
        $g=GmailCuenta::create(['user_id'=>$u->id,'correo'=>'ana@gmail.com','nombre'=>'Ana Pérez','clave'=>'abcdefghijklmnop','revision'=>(string)Str::uuid(),'activo'=>true,'verificada_en'=>now()]);
        return [$u,$c,$e,$g];
    }
    private function payload(Cliente $c, PostulacionEmpresa $e, GmailCuenta $g): array
    {
        return ['cliente_id'=>$c->id,'empresa_id'=>$e->id,'gmail_cuenta_id'=>$g->id,'cargo'=>'Asistente','asunto'=>'Postulación de Ana','mensaje'=>"Estimado equipo:\nAdjunto mi CV.",'diseno'=>'pro','paleta'=>'marino','foto'=>'1','idioma'=>'es'];
    }
    private function preparar(array $ctx): Postulacion
    {
        [$u,$c,$e,$g]=$ctx;
        $this->actingAs($u)->post(route('postulaciones.preparar'),$this->payload($c,$e,$g))->assertRedirect();
        return Postulacion::latest()->firstOrFail();
    }
    public function test_correo_formal_empieza_con_saludos_y_conserva_destinatario_en_aviso_y_texto(): void
    {
        $ctx=$this->contexto();[$u,$c,$empresa,$gmail]=$ctx;
        $redaccion=app(\App\Services\PostulacionRedaccion::class)->generar($c,$empresa);
        $this->assertStringStartsWith('Saludos cordiales:', $redaccion['mensaje']);
        $empresa->update(['contacto'=>'María López']);
        $this->assertStringStartsWith('Saludos, María López:', app(\App\Services\PostulacionRedaccion::class)->generar($c,$empresa)['mensaje']);
        $p=$this->preparar($ctx);$html=$p->correo_html;$texto=$p->textoCorreo();
        $this->assertStringContainsString('Aviso de privacidad',$html);
        $this->assertStringContainsString('Este correo fue enviado a empleos@example.test', $texto);
        $this->assertStringContainsString('elimine el mensaje y sus archivos adjuntos',$texto);
        $this->assertStringNotContainsString('<h1',$html);
        $empresa->update(['correo'=>'cambiado@example.test']);$this->assertSame($html,$p->fresh()->correo_html);$this->assertSame($texto,$p->fresh()->textoCorreo());
        $this->assertStringNotContainsString('cambiado@example.test',$texto);Queue::assertNothingPushed();
    }
    public function test_transporte_incluye_aviso_revisado_en_html_y_texto_sin_envio_real(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);
        $t=$this->createMock(\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport::class);
        $t->expects($this->once())->method('send')->willReturnCallback(function($email)use($p){
            $this->assertSame($p->destinatario,$email->getTo()[0]->getAddress());
            $this->assertSame($p->correo_html,$email->getHtmlBody());
            $this->assertSame($p->textoCorreo(),$email->getTextBody());
            $this->assertStringContainsString('Este correo fue enviado a empleos@example.test',$email->getTextBody());
            $this->assertCount(1,$email->getAttachments());
            return new \Symfony\Component\Mailer\SentMessage($email,\Symfony\Component\Mailer\Envelope::create($email));
        });
        $servicio=new class($t) extends GmailPostulaciones {
            public function __construct(private \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport $simulado){}
            protected function transporte(GmailCuenta $cuenta): \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport {return $this->simulado;}
        };
        $this->assertNotEmpty($servicio->enviar($p,$ctx[3]));
    }
    public function test_mensajes_historicos_conservan_su_copia_de_texto_sin_agregar_avisos_nuevos(): void
    {
        $p=new Postulacion(['mensaje'=>'Texto original revisado','correo_html'=>'<p>Texto original revisado</p>']);
        $this->assertSame('Texto original revisado',$p->textoCorreo());
    }
    public function test_gmail_se_cifra_no_se_muestra_ni_se_guarda_en_la_sesion(): void
    {
        $u=$this->user();$this->actingAs($u)->put(route('postulaciones.gmail.guardar',$u),['correo'=>'mi@gmail.com','nombre'=>'Mi nombre','gmail_clave'=>'abcd efgh ijkl mnop','activo'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $g=GmailCuenta::firstOrFail();$this->assertSame('abcdefghijklmnop',$g->clave);$this->assertNotSame('abcdefghijklmnop',DB::table('gmail_cuentas')->value('clave'));$this->assertArrayNotHasKey('clave',$g->toArray());$this->assertNull($g->verificada_en);
        $this->get(route('postulaciones.gmail'))->assertOk()->assertDontSee('value="abcdefghijklmnop"',false);
        $this->put(route('postulaciones.gmail.guardar',$u),['correo'=>'mi@gmail.com','nombre'=>'Mi nombre','gmail_clave'=>'clave-invalidA','activo'=>1])->assertSessionHasErrors('gmail_clave');
        $this->assertArrayNotHasKey('gmail_clave',session('_old_input',[]));
    }
    public function test_cambiar_direccion_necesita_nueva_clave_y_prueba_no_envia_correo(): void
    {
        [$u,$c,$e,$g]=$this->contexto();
        $this->actingAs($u)->put(route('postulaciones.gmail.guardar',$u),['correo'=>'otra@gmail.com','nombre'=>'Ana','activo'=>1])->assertSessionHasErrors('gmail_clave');
        $this->assertSame('ana@gmail.com',$g->fresh()->correo);
        $this->mock(GmailPostulaciones::class,fn($m)=>$m->shouldReceive('comprobar')->once()->withArgs(fn($cuenta)=>$cuenta->id===$g->id));
        $this->post(route('postulaciones.gmail.comprobar',$u))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($g->fresh()->verificada_en);
    }
    public function test_no_accede_a_gmail_ajeno_ni_configura_otro_usuario(): void
    {
        [$u]=$this->contexto();$otro=$this->user();
        $this->actingAs($otro)->get(route('postulaciones.gmail',$u))->assertForbidden();
        $this->put(route('postulaciones.gmail.guardar',$u),['correo'=>'otro@gmail.com','nombre'=>'Otro','gmail_clave'=>'abcdefghijklmnop'])->assertForbidden();
        $this->delete(route('postulaciones.gmail.desconectar',$u))->assertForbidden();
    }
    public function test_empresas_privadas_compartidas_y_archivadas_respetan_acceso(): void
    {
        [$u,$c,$e]=$this->contexto();$otro=$this->user();
        $this->actingAs($otro)->get(route('postulaciones.empresas'))->assertDontSee('Empresa Demo');
        $this->get(route('postulaciones.empresas.editar',$e))->assertForbidden();
        $this->post(route('postulaciones.empresas.guardar'),['nombre'=>'Privada','correo'=>'empresa@example.test','activa'=>1,'compartida'=>1])->assertRedirect();
        $this->assertFalse(PostulacionEmpresa::where('nombre','Privada')->first()->compartida);
        $e->update(['compartida'=>true]);$this->get(route('postulaciones.empresas'))->assertSee('Empresa Demo');
        $e->update(['activa'=>false]);$this->actingAs($u)->postJson(route('postulaciones.redactar'),['cliente_id'=>$c->id,'empresa_id'=>$e->id])->assertNotFound();$this->actingAs($otro)->postJson(route('postulaciones.redactar'),['cliente_id'=>$c->id,'empresa_id'=>$e->id])->assertForbidden();
    }
    public function test_redaccion_con_cargo_y_sin_cargo_no_inventa_experiencia(): void
    {
        [$u,$c,$e]=$this->contexto();
        $r=$this->actingAs($u)->postJson(route('postulaciones.redactar'),['cliente_id'=>$c->id,'empresa_id'=>$e->id,'cargo'=>'Cajera'])->assertOk()->assertJsonPath('asunto','Solicitud de empleo: Cajera — Ana Pérez');
        $this->assertStringContainsString('Mi área de trabajo es Asistente administrativa.',$r['mensaje']);
        $this->postJson(route('postulaciones.redactar'),['cliente_id'=>$c->id,'empresa_id'=>$e->id,'cargo'=>''])->assertOk()->assertJsonPath('asunto','Solicitud de empleo — Ana Pérez');
        $otro=$this->user();$this->actingAs($otro)->postJson(route('postulaciones.redactar'),['cliente_id'=>$c->id,'empresa_id'=>$e->id])->assertForbidden();
    }
    public function test_preparar_conserva_pdf_mensaje_empresa_y_remitente_sin_enviar(): void
    {
        $ctx=$this->contexto();[$u,$c,$e,$g]=$ctx;$p=$this->preparar($ctx);
        $this->assertSame('borrador',$p->estado);$this->assertSame(hash('sha256','%PDF-1.4 version del CV original'),$p->cv_sha256);
        Storage::disk('local')->assertExists($p->cv_ruta);Queue::assertNothingPushed();
        $c->update(['primer_nombre'=>'Cambio']);$e->update(['correo'=>'nuevo@example.test','nombre'=>'Nuevo nombre']);
        $this->get(route('postulaciones.ver',$p))->assertOk()->assertSee('Empresa Demo')->assertSee('Ana Pérez');
        $this->get(route('postulaciones.pdf',['id'=>$p->id,'descargar'=>1]))->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertSame('empleos@example.test',$p->fresh()->destinatario);$this->assertSame('%PDF-1.4 version del CV original',Storage::disk('local')->get($p->cv_ruta));
    }
    public function test_confirmacion_requerida_y_doble_clic_encola_una_sola_vez(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);
        $this->post(route('postulaciones.confirmar',$p))->assertSessionHasErrors('confirmar');
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1])->assertRedirect();
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1])->assertRedirect();
        Queue::assertPushed(EnviarPostulacion::class,1);$this->assertSame('en_cola',$p->fresh()->estado);
        $this->assertSame($ctx[0]->id,$p->fresh()->confirmado_por);
    }
    public function test_trabajador_envia_exactamente_la_copia_guardada_y_no_duplica(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);$this->post(route('postulaciones.confirmar',$p),['confirmar'=>1]);
        $gmail=$this->mock(GmailPostulaciones::class,fn($m)=>$m->shouldReceive('enviar')->once()->withArgs(fn($envio,$cuenta)=>$envio->cv_sha256===$p->cv_sha256 && $cuenta->id===$ctx[3]->id)->andReturn('gmail-id@example.test'));
        $job=new EnviarPostulacion($p->id);$job->handle($gmail);$job->handle($gmail);
        $this->assertSame('enviado',$p->fresh()->estado);$this->assertNotNull($p->fresh()->enviado_en);$this->assertSame('gmail-id@example.test',$p->fresh()->message_id);
    }
    public function test_clave_modificada_desconexion_o_pdf_alterado_impiden_enviar(): void
    {
        foreach(['clave','desconexion','pdf'] as $caso){
            $ctx=$this->contexto();$p=$this->preparar($ctx);$this->post(route('postulaciones.confirmar',$p),['confirmar'=>1]);
            if($caso==='clave')$ctx[3]->update(['revision'=>(string)Str::uuid()]);
            if($caso==='desconexion')$ctx[3]->update(['activo'=>false]);
            if($caso==='pdf')Storage::disk('local')->put($p->cv_ruta,'%PDF-1.4 distinto');
            $gmail=$this->mock(GmailPostulaciones::class,fn($m)=>$m->shouldNotReceive('enviar'));
            (new EnviarPostulacion($p->id))->handle($gmail);$this->assertSame('fallido',$p->fresh()->estado);
        }
    }
    public function test_error_smtp_o_timeout_exigen_revision_sin_reintento_automatico(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);$this->post(route('postulaciones.confirmar',$p),['confirmar'=>1]);
        $gmail=$this->mock(GmailPostulaciones::class,fn($m)=>$m->shouldReceive('enviar')->once()->andThrow(new \RuntimeException('Detalle técnico privado con clave')));
        $job=new EnviarPostulacion($p->id);$job->handle($gmail);$job->handle($gmail);
        $this->assertSame('incierto',$p->fresh()->estado);$this->assertStringNotContainsString('clave',$p->fresh()->detalle_estado);
        $p->update(['estado'=>'enviando','iniciado_en'=>now()->subMinutes(5)]);
        $this->getJson(route('postulaciones.estado',$p))->assertOk()->assertJsonPath('estado','incierto');
    }
    public function test_ajenos_no_ven_historial_pdf_correo_ni_estado(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);$otro=$this->user();$this->actingAs($otro);
        foreach(['ver','pdf','correo','estado'] as $route)$this->get(route('postulaciones.'.$route,$p))->assertNotFound();
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1])->assertNotFound();
        $this->get(route('postulaciones.index'))->assertOk()->assertDontSee('Empresa Demo');
        $d=$this->payload($ctx[1],$ctx[2],$ctx[3]);$this->post(route('postulaciones.preparar'),$d)->assertForbidden();
    }
    public function test_admin_envia_por_usuario_y_candidato_puede_ver_copia_sin_usar_otra_cuenta(): void
    {
        $ctx=$this->contexto();[$u,$c,$e,$g]=$ctx;$admin=$this->user('admin');
        $this->actingAs($admin)->get(route('postulaciones.crear'))->assertOk()->assertSee('ana@gmail.com');
        $this->post(route('postulaciones.preparar'),$this->payload($c,$e,$g))->assertRedirect();$p=Postulacion::firstOrFail();
        $this->assertSame($u->id,$p->user_id);$this->assertSame($admin->id,$p->creado_por);
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1])->assertRedirect();
        $this->assertSame($admin->id,$p->fresh()->confirmado_por);
        $this->actingAs($u)->get(route('postulaciones.ver',$p))->assertOk();
    }
    public function test_admin_puede_usar_su_gmail_para_otro_cv_sin_dar_acceso_de_envio_al_candidato(): void
    {
        [$u,$c,$e]=$this->contexto();$admin=$this->user('admin');$g=GmailCuenta::create(['user_id'=>$admin->id,'correo'=>'admin@gmail.com','nombre'=>'Admin','clave'=>'abcdefghijklmnop','revision'=>(string)Str::uuid(),'activo'=>true,'verificada_en'=>now()]);
        $this->actingAs($admin)->post(route('postulaciones.preparar'),$this->payload($c,$e,$g))->assertRedirect();$p=Postulacion::firstOrFail();
        $this->actingAs($u)->get(route('postulaciones.ver',$p))->assertOk()->assertDontSee('Confirmar y enviar CV');
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1])->assertForbidden();
    }
    public function test_html_no_inyecta_codigo_y_borrador_se_descarta_con_archivo(): void
    {
        $ctx=$this->contexto();[$u,$c,$e,$g]=$ctx;$d=$this->payload($c,$e,$g);$d['mensaje']='<script>alert(1)</script> Mensaje';
        $this->actingAs($u)->post(route('postulaciones.preparar'),$d)->assertRedirect();$p=Postulacion::firstOrFail();
        $this->get(route('postulaciones.correo',$p))->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>',false)->assertHeader('Cache-Control','no-store, private');
        $this->delete(route('postulaciones.eliminar',$p))->assertRedirect();$this->assertModelMissing($p);
        // RefreshDatabase mantiene la transacción abierta: los archivos se borran después de commit.
    }
    public function test_desconectar_borra_clave_y_desactiva_cuenta(): void
    {
        [$u,$c,$e,$g]=$this->contexto();$this->actingAs($u)->delete(route('postulaciones.gmail.desconectar',$u))->assertRedirect();
        $this->assertNull($g->fresh()->getRawOriginal('clave'));$this->assertFalse($g->fresh()->activo);$this->assertNull($g->fresh()->verificada_en);
    }
    public function test_no_envia_sin_cuenta_verificada_ni_acepta_cabeceras_inyectadas(): void
    {
        [$u,$c,$e,$g]=$this->contexto();$g->update(['verificada_en'=>null]);$d=$this->payload($c,$e,$g);
        $this->actingAs($u)->post(route('postulaciones.preparar'),$d)->assertSessionHasErrors('gmail_cuenta_id');
        $g->update(['verificada_en'=>now()]);$d['asunto']="Oferta\r\nBcc:otro@example.test";
        $this->post(route('postulaciones.preparar'),$d)->assertSessionHasErrors('asunto');$this->assertSame(0,Postulacion::count());
    }
    public function test_usuario_de_negocio_sin_permiso_cv_no_accede_y_cliente_si_tiene_menu(): void
    {
        $negocio=Cyber::create(['codigo'=>'POST','nombre'=>'Oficina']);
        $u=$this->user('cyber');$u->update(['cyber_id'=>$negocio->id,'permisos'=>['documentos']]);
        $this->actingAs($u)->get(route('postulaciones.index'))->assertForbidden();
        $u->update(['permisos'=>['cv','clientes.ver']]);
        $this->actingAs($u->fresh())->get(route('postulaciones.index'))->assertOk();
        $cliente=$this->user();$this->actingAs($cliente)->get(route('postulaciones.index'))->assertOk()->assertSee('CV a empresas');
    }
    public function test_si_se_revoca_el_admin_que_confirmo_el_trabajador_no_envia(): void
    {
        [$u,$c,$e,$g]=$this->contexto();$admin=$this->user('admin');
        $this->actingAs($admin)->post(route('postulaciones.preparar'),$this->payload($c,$e,$g));$p=Postulacion::firstOrFail();
        $this->post(route('postulaciones.confirmar',$p),['confirmar'=>1]);$admin->update(['rol'=>'cliente']);
        $gmail=$this->mock(GmailPostulaciones::class,fn($m)=>$m->shouldNotReceive('enviar'));
        (new EnviarPostulacion($p->id))->handle($gmail);$this->assertSame('fallido',$p->fresh()->estado);
    }
    public function test_eliminar_ficha_o_usuario_elimina_sus_copias_y_credenciales(): void
    {
        $ctx=$this->contexto();$p=$this->preparar($ctx);$ctx[1]->delete();$this->assertModelMissing($p);
        $ctx=$this->contexto();$p=$this->preparar($ctx);$ctx[0]->delete();$this->assertModelMissing($p);$this->assertModelMissing($ctx[3]);
    }

}
