<?php
namespace Tests\Feature;

use App\Models\{Cliente, Configuracion, RegistroPendiente, User};
use App\Support\{AntiBot, SituacionLaboral};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, Hash};
use Tests\TestCase;

class IdentidadClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class, \App\Http\Middleware\AceptaTerminos::class]);
        Configuracion::actual()->update(['registro_abierto'=>true]);
        User::create(['usuario'=>'ADMINIDENTIDAD','name'=>'Administrador','email'=>'admin-identidad@example.test','password'=>'Prueba12345678','rol'=>'admin','activo'=>true]);
    }

    private function registro(array $extra=[]): array
    {
        return array_replace(['pais'=>'CR', 'tipo_documento'=>'dimex', 'nacionalidad'=>' NICARAGUENSE ', 'pais_residencia'=>'CR',
            'primer_nombre'=>'Persona', 'primer_apellido'=>'Prueba', 'email'=>'identidad@example.test',
            'password'=>'Prueba12345678', 'password_confirmation'=>'Prueba12345678', 'acepto'=>'1',
            AntiBot::CAMPO=>'', '_t'=>Crypt::encryptString((string)(time()-5))],$extra);
    }

    public function test_cuenta_sencilla_y_datos_completados_dentro_de_la_aplicacion(): void
    {
        $this->post(route('registro.guardar'),$this->registro())->assertSessionHasNoErrors()->assertRedirect(route('registro.codigo'));
        $p=RegistroPendiente::firstOrFail();
        $this->assertArrayNotHasKey('nacionalidad',$p->datos);
        $this->assertArrayNotHasKey('pais_residencia',$p->datos);
        $p->update(['codigo'=>Hash::make('123456')]);
        $this->withSession(['registro_token'=>$p->token])->post(route('registro.codigo.guardar'),['codigo'=>'123456'])->assertRedirect(route('mi-cv.panel'));
        $c=Cliente::firstOrFail();
        $this->assertNull($c->nacionalidad);
        $this->assertNull($c->pais_residencia);
        $this->put(route('mi-cv.actualizar'),['pais'=>'CR','tipo_documento'=>'dimex','numero_documento'=>'12345678901',
            'fecha_nacimiento'=>'1990-01-01','municipio_nacimiento'=>'Matagalpa','departamento_nacimiento'=>'Matagalpa',
            'primer_nombre'=>'Persona','primer_apellido'=>'Prueba','nacionalidad'=>'Nicaragüense','pais_residencia'=>'CR','autorizacion_laboral'=>'En trámite'])
            ->assertSessionHasNoErrors()->assertRedirect(route('mi-cv.panel'));
        $c->refresh();
        $this->assertSame('Nicaragüense',$c->nacionalidad);
        $this->assertSame('CR',$c->pais);
        $this->assertSame('dimex',$c->tipo_documento);
        $this->assertSame('CR',$c->pais_residencia);
        $this->get(route('mi-cv.panel'))->assertOk()->assertSee('Nacionalidad: Nicaragüense')->assertSee('Reside en: Costa Rica')->assertDontSee('asignaciones automáticas');
    }

    public function test_formulario_publico_no_pide_datos_que_se_completan_dentro(): void
    {
        $this->get(route('registro.mostrar'))->assertOk()->assertDontSee('name="nacionalidad"',false)->assertDontSee('name="pais_residencia"',false)->assertDontSee('name="tipo_documento"',false);
        $this->post(route('registro.guardar'),$this->registro(['nacionalidad'=>'','pais_residencia'=>'']))->assertSessionHasNoErrors()->assertRedirect(route('registro.codigo'));
        $this->assertDatabaseCount('registros_pendientes',1);
    }

    public function test_registro_rechaza_pais_de_documento_invalido(): void
    {
        $this->post(route('registro.guardar'),$this->registro(['pais'=>'XX']))->assertSessionHasErrors('pais');
        $this->assertDatabaseCount('registros_pendientes',0);
    }

    public function test_tres_paises_distintos_son_validos_y_no_cambian_por_el_documento(): void
    {
        $d=SituacionLaboral::validar(['pais'=>'CR','tipo_documento'=>'dimex','nacionalidad'=>'Nicaragüense','pais_residencia'=>'PA','autorizacion_laboral'=>'En trámite']);
        $this->assertSame('Nicaragüense',$d['nacionalidad']);
        $this->assertSame('PA',$d['pais_residencia']);
        $this->assertSame('CR',$d['pais']);
        $this->assertSame('En trámite',$d['autorizacion_laboral']);
    }

    public function test_panel_no_muestra_avisos_sobre_datos_internos(): void
    {
        $u=User::create(['usuario'=>'SINAVISO','name'=>'Persona Prueba','email'=>'sinaviso@example.test','password'=>'Prueba12345678','rol'=>'cliente','activo'=>true]);
        Cliente::create(['user_id'=>$u->id,'pais'=>'CR','tipo_documento'=>'dimex','numero_documento'=>'12345678901','primer_nombre'=>'Persona','primer_apellido'=>'Prueba','nacionalidad'=>'Costarricense','pais_residencia'=>'CR']);
        $this->actingAs($u)->get(route('mi-cv.panel'))->assertOk()->assertDontSee('Revise su nacionalidad')->assertDontSee('nacionalidad sugerida automáticamente');
        $this->get(route('mi-cv.editar'))->assertOk()->assertDontSee('Revise su nacionalidad')->assertSee('id="fNacSel"',false);
    }

    public function test_editar_solo_documento_no_reescribe_nacionalidad_ni_residencia(): void
    {
        $u=User::create(['usuario'=>'IDENTIDAD','name'=>'Persona Prueba','email'=>'persona@example.test','password'=>'Prueba12345678','rol'=>'cliente','activo'=>true,'edicion_cliente'=>['documento']]);
        $c=Cliente::create(['user_id'=>$u->id,'pais'=>'NI','tipo_documento'=>'pasaporte','numero_documento'=>'A1234567','primer_nombre'=>'Persona','primer_apellido'=>'Prueba','nacionalidad'=>'Nicaragüense','pais_residencia'=>'PA']);
        $this->actingAs($u)->put(route('mi-cv.actualizar'),['pais'=>'CR','tipo_documento'=>'dimex','numero_documento'=>'12345678901','fecha_nacimiento'=>'1990-01-01','municipio_nacimiento'=>'Matagalpa','departamento_nacimiento'=>'Matagalpa'])->assertSessionHasNoErrors()->assertRedirect();
        $c->refresh();
        $this->assertSame('dimex',$c->tipo_documento);
        $this->assertSame('Nicaragüense',$c->nacionalidad);
        $this->assertSame('PA',$c->pais_residencia);
    }

    public function test_pendiente_anterior_no_inventa_nacionalidad_al_verificar(): void
    {
        $p=RegistroPendiente::create(['token'=>str_repeat('a',48),'email'=>'viejo@example.test','datos'=>['pais'=>'CR','primer_nombre'=>'Persona','primer_apellido'=>'Prueba','password'=>Hash::make('Prueba12345678'),'terminos'=>'1.0'],'codigo'=>Hash::make('123456'),'expira_en'=>now()->addMinutes(10),'enviado_en'=>now()]);
        $this->withSession(['registro_token'=>$p->token])->post(route('registro.codigo.guardar'),['codigo'=>'123456'])->assertRedirect(route('mi-cv.panel'));
        $c=Cliente::firstOrFail();
        $this->assertNull($c->nacionalidad);
        $this->assertNull($c->pais_residencia);
        $this->get(route('mi-cv.panel'))->assertOk()->assertSee('Nacionalidad: Sin declarar');
    }
}
