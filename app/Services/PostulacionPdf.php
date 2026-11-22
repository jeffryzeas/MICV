<?php
namespace App\Services;
use App\Models\{Cliente, User};

class PostulacionPdf
{
    public function generar(Cliente $cliente, User $actor, array $opciones): string
    {
        $html = view('cv.'.$opciones['diseno'], CvDatos::armar($cliente, $opciones['diseno'], $opciones['paleta'], $opciones['foto'], $actor, $opciones['idioma']))->render();
        return CvPdf::desdeHtml($html, CvDatos::esPro($opciones['diseno']));
    }
}
