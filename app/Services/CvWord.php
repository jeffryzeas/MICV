<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** CV en Word (.docx), editable, con los mismos datos y el mismo idioma que el PDF. */
class CvWord
{
    public static function generar(Cliente $c, string $lang = 'es', bool $conFoto = true, ?User $u = null): string
    {
        $d = CvDatos::armar($c, 'pro', null, $conFoto, $u, $lang);
        $t = $d['t'];
        $vt = $d['vt'];

        $foto = null;
        if ($conFoto && Archivos::existe($c->foto)) {
            $bin = Storage::disk(Archivos::DISCO)->get($c->foto);
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                ob_start();
                imagejpeg($img, null, 90);
                $foto = ['tipo' => 'jpg', 'datos' => base64_encode(ob_get_clean()), 'ancho' => imagesx($img), 'alto' => imagesy($img)];
                imagedestroy($img);
            }
        }

        $json = [
            'titulo'   => ($lang === 'en' ? 'Resume — ' : 'CV — ') . $c->nombreCompleto(),
            'autor'    => $c->nombreCompleto(),
            'color'    => $d['paleta']['lateral'] ?? '#0B3C74',
            'acento'   => $d['paleta']['acento'] ?? '#C9971F',
            'nombre'   => CvDatos::nombreConFormato($c->nombreCompleto(), $d['cv']['nombre_formato']),
            'puesto'   => $vt['profesion'],
            'foto'     => $foto,
            'contacto' => array_values(array_merge(
                array_map(fn ($x) => $x[1], $d['contactos']),
                array_map(fn ($x) => $x[1], $d['enlaces'])
            )),
            'perfil'   => $vt['perfil'],
            'experiencias' => $d['experiencias']->map(fn ($e) => [
                'cargo' => $e->cargo ?: $t('Experiencia'), 'fecha' => $e->periodoTexto($lang), 'empresa' => $e->empresa, 'descripcion' => $e->descripcion,
            ])->values()->all(),
            'estudios' => $d['estudios']->map(fn ($e) => [
                'titulo' => $e->titulo ?: $t($e->nivelLegible()), 'anio' => $e->anio ? (string) $e->anio : null,
                'sub' => trim($t($e->nivelLegible()) . ($e->institucion ? ' · ' . $e->institucion : '')), 'detalle' => $e->detalle,
            ])->values()->all(),
            'habilidades' => $d['habilidades']->pluck('habilidad')->filter()->values()->all(),
            'idiomas' => $d['idiomas']->map(fn ($x) => ['nombre' => $x->nombre($lang), 'nivel' => $x->nivelTexto($lang), 'puntos' => max(0, min(5, (int) $x->puntos()))])->values()->all(),
            'personales' => array_values(array_map(fn ($f) => [$f[0], (string) $f[1]], $d['datosPro'])),
            'referencias' => $d['referencias']->map(fn ($r) => [
                'nombre' => $r->nombreConTratamiento(),
                'detalle' => implode(' · ', array_filter([$r->telefono ? $t('Tel.') . ' ' . $r->telefono : null, $r->correo])),
            ])->values()->all(),
            'familia' => $d['familia'],
            'secciones' => [
                'perfil' => $t('Perfil profesional'), 'experiencia' => $t('Experiencia laboral'), 'estudios' => $t('Formación académica'),
                'habilidades' => $t('Habilidades'), 'idiomas' => $t('Idiomas'), 'personales' => $t('Datos personales'),
                'referencias' => $t('Referencias'), 'familia' => $t('Datos familiares'),
            ],
        ];

        $dir = storage_path('app/tmp-word');
        @mkdir($dir, 0775, true);
        $salida = $dir . '/' . Str::random(24) . '.docx';
        try {
            Nodo::correr(resource_path('cv/cv-word.cjs'), [$salida], 60, json_encode($json, JSON_UNESCAPED_UNICODE));
            return (string) file_get_contents($salida);
        } finally {
            @unlink($salida);
        }
    }
}
