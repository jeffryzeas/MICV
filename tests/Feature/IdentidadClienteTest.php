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

    public function test_registro_y_verificacion_conservan_nacionalidad_dimex_y_residencia_independientes(): void
    {
        $this->post(route('registro.guardar'),$this->registro())->assertSessionHasNoErrors()->assertRedirect(route('registro.codigo'));
        $p=RegistroPendiente::firstOrFail();
        $this->assertSame('Nicaragüense',$p->datos['nacionalidad']);
        $this->assertSame('dimex',$p->datos['tipo_documento']);
        $p->update(['codigo'=>Hash::make('123456')]);
        $this->withSession(['registro_token'=>$p->token])->post(route('registro.codigo.guardar'),['codigo'=>'123456'])->assertRedirect(route('mi-cv.panel'));
        $c=Cliente::firstOrFail();
        $this->assertSame('Nicaragüense',$c->nacionalidad);
        $this->assertSame('CR',$c->pais);
        $this->assertSame('dimex',$c->tipo_documento);
        $this->assertSame('CR',$c->pais_residencia);
        $this->assertNull($c->autorizacion_laboral);
        $this->get(route('mi-cv.panel'))->assertOk()->assertSee('Nacionalidad: Nicaragüense')->assertSee('Reside en: Costa Rica');
    }

    public function test_registro_no_infiere_nacionalidad_o_residencia_ausentes(): void
    {
        $this->post(route('registro.guardar'),$this->registro(['nacionalidad'=>'','pais_residencia'=>'']))->assertSessionHasErrors(['nacionalidad','pais_residencia']);
        $this->assertDatabaseCount('registros_pendientes',0);
    }

    public function test_registro_rechaza_documento_de_otro_emisor_y_nacionalidad_invalida(): void
    {
        $this->post(route('registro.guardar'),$this->registro(['pais'=>'NI','tipo_documento'=>'dimex']))->assertSessionHasErrors('tipo_documento');
        $this->post(route('registro.guardar'),$this->registro(['nacionalidad'=>'12345']))->assertSessionHasErrors('nacionalidad');
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

    public function test_aviso_antiguo_no_cambia_ni_rechaza_nacionalidad_declarada(): void
    {
        $c=Cliente::create(['pais'=>'CR','tipo_documento'=>'dimex','numero_documento'=>'12345678901','primer_nombre'=>'Persona','primer_apellido'=>'Prueba','nacionalidad'=>'Costarricense','pais_residencia'=>'CR']);
        $this->assertTrue($c->revisarIdentidad());
        $this->assertSame('Costarricense',$c->fresh()->nacionalidad);
        $c->nacionalidad='Nicaragüense';
        $this->assertFalse($c->revisarIdentidad());
        $c->nacionalidad=null;
        $this->assertTrue($c->revisarIdentidad());
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
