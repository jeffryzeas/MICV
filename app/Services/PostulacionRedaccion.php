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
        $contactoEmpresa = trim((string) $empresa->contacto);
        $saludo = $contactoEmpresa !== '' ? 'Saludos, '.$contactoEmpresa.':' : 'Saludos cordiales:';
        $intro = $cargo !== '' ? 'Les escribo para presentar mi candidatura al puesto de '.$cargo.' en '.$empresa->nombre.'.' : 'Les escribo para presentar mi currículum y expresar mi interés en las oportunidades laborales de '.$empresa->nombre.' que se ajusten a mi perfil.';
        $perfil = trim(preg_replace('/\s+/u', ' ', strip_tags($c->perfil_profesional ?? '')));
        $profesion = trim(strip_tags($c->profesion ?? ''));
        $presentacion = $perfil !== '' ? Str::limit($perfil, 550) : ($profesion !== '' ? 'Mi área de trabajo es '.$profesion.'.' : '');
        $contacto = array_filter([$nombre, $c->telefono ? 'Teléfono: '.$c->celulares() : null]);
        return [
            'asunto'=>Str::limit(($cargo !== '' ? 'Solicitud de empleo: '.$cargo : 'Solicitud de empleo').' — '.$nombre, 190, ''),
            'mensaje'=>implode("\n\n", array_filter([$saludo, $intro, $presentacion, 'Adjunto mi currículum en formato PDF para su valoración. Quedo a su disposición para ampliar cualquier información y coordinar una entrevista.', 'Agradezco su tiempo y la atención a mi solicitud.', "Atentamente,\n".implode("\n", $contacto)])),
        ];
    }
}
