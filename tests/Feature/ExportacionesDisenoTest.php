<?php
namespace Tests\Feature;

use App\Models\{User, Cyber, Cliente};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportacionesDisenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\CorreoVerificado::class,
            \App\Http\Middleware\AceptaTerminos::class,
        ]);
    }

    private function usuario(string $rol, ?Cyber $cyber = null): User
    {
        $id = Str::random(10);
        return User::create(['usuario' => $id, 'name' => 'Prueba', 'email' => $id.'@example.test', 'password' => 'Prueba12345678', 'rol' => $rol, 'activo' => true, 'cyber_id' => $cyber?->id]);
    }

    public function test_cliente_no_puede_descargar_directorio(): void
    {
        $this->actingAs($this->usuario('cliente'))->get(route('clientes.exportar', ['formato' => 'pdf']))->assertForbidden();
    }

    public function test_exportacion_rechaza_formato_no_admitido(): void
    {
        $this->actingAs($this->usuario('admin'))->getJson(route('clientes.exportar', ['formato' => 'html']))->assertUnprocessable()->assertJsonValidationErrors('formato');
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_pdf_respeta_cyber_y_filtros_sin_exponer_otros_clientes(): void
    {
        $a = Cyber::create(['codigo' => 'EXPA', 'nombre' => 'Negocio A', 'activo' => true]);
        $b = Cyber::create(['codigo' => 'EXPB', 'nombre' => 'Negocio B', 'activo' => true]);
        foreach ([[$a, 'AnaVisible'], [$a, 'OtraPersona'], [$b, 'AnaAjena']] as [$cyber, $nombre]) {
            $cliente = Cliente::create(['primer_nombre' => $nombre, 'primer_apellido' => 'Prueba']);
            $cliente->cybers()->attach($cyber->id);
        }
        // Inspect the real rendered report; only replace the external Chromium conversion.
        \Mockery::mock('alias:App\Services\CvPdf')->shouldReceive('desdeHtml')->once()->with(\Mockery::on(function ($html) {
            $this->assertStringContainsString('AnaVisible', $html);
            $this->assertStringNotContainsString('AnaAjena', $html);
            $this->assertStringNotContainsString('OtraPersona', $html);
            return true;
        }))->andReturn('%PDF-test');
        $this->actingAs($this->usuario('cyber', $a))->get(route('clientes.exportar', ['formato' => 'pdf', 'q' => 'Ana']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_insignia_institucional_no_modifica_verificacion_de_correo(): void
    {
        $admin = $this->usuario('admin');
        $perfil = \App\Http\Controllers\ChatPerfilController::datos($admin);
        $this->assertTrue($perfil['verificado']);
        $this->assertSame('Cuenta institucional', $perfil['verificacion_tipo']);
        $this->assertNull($admin->fresh()->email_verified_at);
    }
}
