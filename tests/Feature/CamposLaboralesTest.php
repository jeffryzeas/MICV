<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Services\CvDatos;
use App\Services\CvEjemplo;
use App\Services\CvWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;
use ZipArchive;

class CamposLaboralesTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulario_agrupa_campos_y_muestra_ejemplos_sin_autocompletarlos(): void
    {
        view()->share('errors', new ViewErrorBag);
        $persona = new Cliente(['nacionalidad' => 'Nicaragüense', 'pais_residencia' => 'NI']);
        $html = view('partials.situacion-laboral', ['cliente' => $persona])->render();
        $this->assertStringContainsString('data-extranjero-laboral', $html);
        $this->assertStringContainsString('hidden style="display:none"', $html);
        $this->assertStringContainsString('Ejemplo: Cuento con permiso de trabajo vigente y disponibilidad inmediata.', $html);
        $this->assertStringContainsString('placeholder="Ej.: Responsable, puntual, trabajo en equipo."', $html);
        $this->assertStringNotContainsString('@hidden', $html);
        preg_match('/name="disponibilidad"[^>]+/', $html, $campo);
        $this->assertStringContainsString('value=""', $campo[0]);
        $this->assertStringContainsString('disabled', $campo[0]);
        $persona->pais_residencia = 'CR';
        $html = view('partials.situacion-laboral', ['cliente' => $persona])->render();
        $this->assertStringNotContainsString('hidden style="display:none"', $html);
    }

    public function test_cambio_al_pais_nacional_preserva_datos_guardados_y_omite_entradas_ocultas(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class, \App\Http\Middleware\AceptaTerminos::class]);
        $u = User::create(['usuario' => 'LABORAL', 'name' => 'Ana Prueba', 'email' => 'laboral@example.test', 'rol' => 'cliente', 'password' => 'Prueba12345678', 'activo' => true, 'edicion_cliente' => ['datos']]);
        $c = Cliente::create([
            'user_id' => $u->id, 'primer_nombre' => 'Ana', 'primer_apellido' => 'Prueba', 'pais' => 'CR',
            'tipo_documento' => 'dimex', 'numero_documento' => '12345678901', 'nacionalidad' => 'Nicaragüense',
            'pais_residencia' => 'CR', 'autorizacion_laboral' => 'Permiso vigente',
            'disponibilidad' => 'Dato conservado', 'cualidades' => 'Cualidad conservada', 'activo' => true,
        ]);
        $this->actingAs($u)->put(route('mi-cv.actualizar'), [
            'primer_nombre' => 'Ana', 'primer_apellido' => 'Prueba', 'nacionalidad' => 'Nicaragüense', 'pais_residencia' => 'NI',
            'disponibilidad' => 'No debe reemplazar', 'cualidades' => 'No debe reemplazar',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $c->refresh();
        $this->assertSame('NI', $c->pais_residencia);
        $this->assertSame('CR', $c->pais);
        $this->assertSame('Dato conservado', $c->disponibilidad);
        $this->assertSame('Cualidad conservada', $c->cualidades);
        $this->assertNull($c->autorizacion_laboral);
    }

    public function test_cv_y_word_ocultan_datos_anteriores_si_coinciden_nacionalidad_y_residencia(): void
    {
        $c = CvEjemplo::cliente();
        $c->forceFill(['pais_residencia' => 'NI', 'nacionalidad' => 'Nicaragüense', 'disponibilidad' => 'DisponibilidadUnicaLaboral', 'cualidades' => 'CualidadUnicaLaboral', 'autorizacion_laboral' => 'PermisoUnicoLaboral']);
        foreach (['NI', 'CR'] as $pais) {
            $c->pais_residencia = $pais;
            $html = view('cv.carta', CvDatos::armar($c, 'carta', conFoto: false))->render();
            $xml = $this->xmlWord(CvWord::generar($c, conFoto: false));
            foreach (['DisponibilidadUnicaLaboral', 'CualidadUnicaLaboral', 'PermisoUnicoLaboral'] as $valor) {
                if ($pais === 'NI') {
                    $this->assertStringNotContainsString($valor, $html);
                    $this->assertStringNotContainsString($valor, $xml);
                } else {
                    $this->assertStringContainsString($valor, $html);
                    $this->assertStringContainsString($valor, $xml);
                }
            }
        }
    }

    private function xmlWord(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'micv-laboral-');
        file_put_contents($ruta, $contenido);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($ruta));
            return $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
            unlink($ruta);
        }
    }
}
