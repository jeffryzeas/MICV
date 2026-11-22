<?php
namespace App\Services;
use App\Models\{Cliente, PostulacionEmpresa};
use Illuminate\Support\Str;

/** Propuesta editable basada en datos reales, sin inventar experiencia ni capacidades. */
class PostulacionRedaccion
{
    public function generar(Cliente $c, PostulacionEmpresa $empresa, ?string $cargo = null): array
    {
        $cargo = trim($cargo ?? $empresa->cargo ?? '');
        $nombre = $c->nombreCompleto();
        $saludo = $empresa->contacto ? 'A la atención de '.$empresa->contacto.':' : 'Estimado equipo de selección de '.$empresa->nombre.':';
        $intro = $cargo !== '' ? 'Me gustaría postularme al puesto de '.$cargo.' en '.$empresa->nombre.'.' : 'Me gustaría ser considerado/a para las oportunidades laborales disponibles en '.$empresa->nombre.', de acuerdo con mi perfil.';
        $perfil = trim(preg_replace('/\s+/u', ' ', strip_tags($c->perfil_profesional ?? '')));
        $profesion = trim(strip_tags($c->profesion ?? ''));
        $presentacion = $perfil !== '' ? Str::limit($perfil, 550) : ($profesion !== '' ? 'Mi área de trabajo es '.$profesion.'.' : '');
        $contacto = array_filter([$nombre, $c->telefono ? 'Teléfono: '.$c->celulares() : null]);
        return [
            'asunto'=>Str::limit(($cargo !== '' ? 'Postulación a '.$cargo : 'Postulación laboral').' — '.$nombre, 190, ''),
            'mensaje'=>implode("\n\n", array_filter([$saludo, $intro, $presentacion, 'Adjunto mi CV en PDF para su valoración. Quedo a disposición para ampliar la información o participar en una entrevista.', 'Gracias por su tiempo y consideración.', "Atentamente,\n".implode("\n", $contacto)])),
        ];
    }
}
