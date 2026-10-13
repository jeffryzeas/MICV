<?php
namespace Tests\Feature;

use App\Support\SituacionLaboral;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SituacionLaboralTest extends TestCase
{
    public function test_nicaraguense_en_costa_rica_debe_declarar_situacion(): void
    {
        $this->expectException(ValidationException::class);
        SituacionLaboral::validar(['pais_residencia' => 'CR', 'nacionalidad' => 'Nicaragüense']);
    }

    public function test_nacional_en_su_pais_limpia_permiso_anterior(): void
    {
        $d = SituacionLaboral::validar(['pais_residencia' => 'CR', 'nacionalidad' => 'Costarricense', 'autorizacion_laboral' => 'Permiso anterior']);
        $this->assertNull($d['autorizacion_laboral']);
    }

    public function test_normaliza_nacionalidad_sin_tildes_y_admite_declaracion_sin_permiso(): void
    {
        $d = SituacionLaboral::validar(['pais_residencia' => 'CR', 'nacionalidad' => ' NICARAGUENSE ', 'autorizacion_laboral' => 'Sin autorización']);
        $this->assertSame('Nicaragüense', $d['nacionalidad']);
        $this->assertSame('Sin autorización', $d['autorizacion_laboral']);
    }

    public function test_rechaza_residencia_sin_pais_especifico(): void
    {
        $this->expectException(ValidationException::class);
        SituacionLaboral::validar(['pais_residencia' => 'OT', 'nacionalidad' => 'Nicaragüense']);
    }
}
