<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\Archivos;

/** Arma todos los datos que necesitan las plantillas del CV. */
class CvDatos
{
    public const DISENOS = [
        'clasico'  => 'Clásico (formal, con escudo del país)',
        'pro'      => 'Pro Lateral',
        'moderno'  => 'Pro Moderno',
        'elegante' => 'Pro Elegante',
    ];

    /** Descripción corta para los selectores. */
    public const DESCRIPCION = [
        'clasico'  => 'Marco doble y escudo de su país',
        'pro'      => 'Barra lateral de color',
        'moderno'  => 'Encabezado de color y 2 columnas',
        'elegante' => 'Blanco, letra elegante, minimalista',
    ];

    public static function esPro(string $d): bool
    {
        return $d !== 'clasico';
    }

    public static function armar(Cliente $c, string $diseno, ?string $paleta = null, bool $conFoto = true, ?User $u = null): array
    {
        $c->loadMissing(['estudios', 'experiencias', 'habilidades', 'referencias', 'cybers']);
        $cfg = Configuracion::actual();
        $cv = $cfg->cv();

        // Colores: los del Cyber que genera el CV (o el primer Cyber del cliente).
        $cyber = ($u && !$u->esAdmin() ? $u->cyber : null) ?? $c->cybers->first();
        $colores = [
            'primario'   => $cyber?->color_primario ?: $cfg->color_primario,
            'secundario' => $cyber?->color_secundario ?: $cfg->color_secundario,
        ];
        $clavePaleta = $paleta ?: $cv['pro_paleta'];
        $paletaFinal = $clavePaleta === 'cyber'
            ? ['nombre' => 'Colores del Cyber', 'lateral' => $colores['primario'], 'acento' => $colores['secundario'], 'suave' => $colores['secundario'] . '22']
            : Configuracion::paleta($clavePaleta);

        $pais = $c->datosPais();
        $personales = array_values(array_filter([
            ['Nombre y apellidos', self::nombreConFormato($c->nombreCompleto(), $cv['nombre_formato'])],
            [$c->tipoDocumentoLegible(), $c->numero_documento ? $c->documentoFormateado() : null],
            [$pais['seguro_largo'], $c->inss_nss],
            ['Fecha de nacimiento', $c->fecha_nacimiento?->format('d/m/Y')],
            ['Edad', $c->edad() !== null ? $c->edad() . ' años' : null],
            ['Sexo', $c->sexoLegible()],
            ['Nacionalidad', $c->nacionalidad],
            ['Lugar de nacimiento', $c->lugarNacimiento() ? $c->lugarNacimiento() . ($c->pais !== 'OT' ? ', ' . $pais['nombre'] : '') : null],
            ['Dirección', $c->direccion],
            ['Celular', $c->celulares()],
            ['Número de hijos', $c->numero_hijos !== null ? (string) $c->numero_hijos : null],
            ['Estado civil', $c->estado_civil],
            ['Profesión u oficio', $c->profesion],
            ['Correo electrónico', $c->correo],
        ], fn ($f) => filled($f[1])));

        $familia = array_values(array_filter([
            ['Nombre del padre', $c->nombre_padre],
            ['Nombre de la madre', $c->nombre_madre],
        ], fn ($f) => filled($f[1])));

        // Estudios ordenados por nivel (universidad primero) y luego por año desc.
        $ordenNivel = array_flip(array_keys(\App\Models\ClienteEstudio::NIVELES));
        $estudios = $c->estudios->sortBy([
            fn ($a, $b) => ($ordenNivel[$a->nivel] ?? 99) <=> ($ordenNivel[$b->nivel] ?? 99),
            fn ($a, $b) => ($b->anio ?? 0) <=> ($a->anio ?? 0),
        ])->values();

        // Escudo del país de la persona: Nicaragua o Costa Rica. Para otros
        // países se usa el escudo personalizado (si el admin subió uno).
        $escudo = null;
        if ($cv['mostrar_escudo'] || $cv['marca_agua_activa']) {
            $escudo = $pais['escudo']
                ? Archivos::dataUriLocal(resource_path('cv/' . $pais['escudo']))
                : Archivos::dataUri($cfg->escudo);
        }

        return [
            'c'            => $c,
            'cv'           => $cv,
            'cfg'          => $cfg,
            'diseno'       => $diseno,
            'paleta'       => $paletaFinal,
            'colores'      => $colores,
            'cyber'        => $cyber,
            'personales'   => $personales,
            'familia'      => $familia,
            'estudios'     => $estudios,
            'experiencias' => $c->experiencias,
            'habilidades'  => $c->habilidades,
            'referencias'  => $c->referencias,
            'foto'         => $conFoto ? Archivos::dataUri($c->foto) : null,
            'escudo'       => $escudo,
            'fuentesCss'   => self::fuentesCss($diseno),
            'pais'         => $pais,
        ];
    }

    public static function nombreConFormato(string $nombre, string $formato): string
    {
        return match ($formato) {
            'capital'  => mb_convert_case(mb_strtolower($nombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
            'tal_cual' => $nombre,
            default    => mb_strtoupper($nombre, 'UTF-8'),
        };
    }

    /** @font-face con las fuentes incrustadas en base64: el PDF no depende de internet. */
    public static function fuentesCss(string $diseno): string
    {
        $dir = resource_path('fonts');
        $face = function (string $familia, string $archivo, int $peso = 400, string $formato = 'woff2') use ($dir): string {
            $uri = Archivos::dataUriLocal("$dir/$archivo");
            if (!$uri) {
                return '';
            }
            return "@font-face{font-family:'$familia';src:url($uri) format('$formato');font-weight:$peso;font-style:normal;font-display:block;}\n";
        };

        if ($diseno === 'clasico') {
            return $face('CvNarrow', 'arial-narrow.ttf', 400, 'truetype')
                 . $face('CvScript', 'edwardian-script.ttf', 400, 'truetype');
        }

        $css = '';
        foreach ([400, 500, 600, 700, 800] as $p) {
            $css .= $face('CvSans', "montserrat-latin-$p-normal.woff2", $p);
        }
        return $css . $face('CvSerif', 'playfair-display-latin-700-normal.woff2', 700);
    }
}
