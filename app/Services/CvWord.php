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
    public static function generar(Cliente $c, string $lang = 'es', bool $conFoto = true, ?User $u = null, string $diseno = 'pro'): string
    {
        $esAts=$diseno==='ats';
        if($esAts)$conFoto=false;
        $d = CvDatos::armar($c, $esAts?'ats':'pro', null, $conFoto, $u, $lang);
        $t = $d['t'];
        $vt = $d['vt'];

        $foto = null;
        if ($conFoto && Archivos::existe($c->foto)) {
            $bin = Storage::disk(Archivos::DISCO)->get($c->foto);
            // La conversión se hace con Sharp en Node; no requiere la extensión GD.
            if (is_string($bin) && $bin !== '') {
                $foto = ['datos' => base64_encode($bin)];
            }
        }

        $json = [
            'ats' => $esAts,
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
                'cargo' => $e->cargo ?: $t('Experiencia'), 'fecha' => trim(($e->periodoTexto($lang) ?? '') . ($e->duracion ? ' · ' . $e->duracion : ''), ' ·'), 'empresa' => implode(' · ', array_filter([$e->empresa, $e->jornada, $e->ubicacion])), 'descripcion' => $e->descripcion,
            ])->values()->all(),
            'estudios' => $d['estudios']->map(fn ($e) => [
                'titulo' => $e->titulo ?: $t($e->nivelLegible()), 'anio' => $e->periodo ?: ($e->anio ? (string) $e->anio : null),
                'sub' => trim($t($e->nivelLegible()) . ($e->institucion ? ' · ' . $e->institucion : '')), 'detalle' => $e->detalle,
            ])->values()->all(),
            'habilidades' => $d['habilidades']->pluck('habilidad')->filter()->values()->all(),
            'idiomas' => $d['idiomas']->map(fn ($x) => ['nombre' => $x->nombre($lang), 'nivel' => $x->nivelTexto($lang), 'puntos' => max(0, min(5, (int) $x->puntos()))])->values()->all(),
            'personales' => array_values(array_map(fn ($f) => [$f[0], (string) $f[1]], array_merge($d['datosPro'], array_filter([['Autorización laboral', \App\Support\SituacionLaboral::esExtranjero($c->pais_residencia ?? '', $c->nacionalidad) ? $c->autorizacion_laboral : null], ['Disponibilidad', $c->disponibilidad], ['Cualidades', $c->cualidades]], fn ($f) => filled($f[1]))))),
            'referencias' => $d['referencias']->map(fn ($r) => [
                'nombre' => $r->nombreConTratamiento(),
                'detalle' => implode(' · ', array_filter([$r->detalle, $r->telefono ? $t('Tel.') . ' ' . $r->telefono : null, $r->correo])),
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
