<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\Archivos;
use App\Support\CvTexto;

/** Arma todos los datos que necesitan las plantillas del CV. */
class CvDatos
{
    public const DISENOS = [
        'clasico' => 'Clásico formal',
        'elegante' => 'Elegante',
        'esencial' => 'Esencial',
        'lineal' => 'Lineal',
        'folio' => 'Folio',
        'ejecutivo' => 'Ejecutivo',
        'pro' => 'Lateral',
        'moderno' => 'Moderno',
        'carta' => 'Carta Amarillo y Azul',
        'creativo' => 'Creativo',
        'atlas' => 'Atlas',
        'nordico' => 'Nórdico',
        'horizonte' => 'Horizonte',
        'vertice' => 'Vértice',
        'firma' => 'Firma',
        'orbita' => 'Órbita',
        'metropolitano' => 'Metropolitano',
        'prisma' => 'Prisma',
        'academico' => 'Académico',
        'estudio' => 'Estudio',
    ];

    /** Descripción corta para los selectores. */
    public const DESCRIPCION = [
        'estudio' => 'Encabezado compacto y bloques de experiencia',
        'lineal' => 'Una columna sencilla y lectura rápida',
        'academico' => 'Perfil académico con secciones horizontales',
        'esencial' => 'Diseño limpio que prioriza la lectura',
        'prisma' => 'Bloques redondeados y jerarquía visual',
        'metropolitano' => 'Contraste urbano y contacto compacto',
        'orbita' => 'Lateral suave con fotografía circular',
        'firma' => 'Estilo serif para perfiles profesionales',
        'folio' => 'Una columna con títulos horizontales',
        'vertice' => 'Composición geométrica y títulos marcados',
        'horizonte' => 'Cabecera destacada con acentos cálidos',
        'nordico' => 'Tipografía sobria y líneas ligeras',
        'atlas' => 'Encabezado editorial y secciones amplias',
        'clasico'  => 'Marco doble y escudo de su país',
        'pro'      => 'Barra lateral de color',
        'moderno'  => 'Encabezado de color y 2 columnas',
        'elegante' => 'Blanco, letra elegante, minimalista',
        'ejecutivo'=> 'Títulos a la izquierda, sobrio y ordenado',
        'creativo' => 'Columna derecha, etiquetas y barra inclinada',
        'carta' => 'Diseño Jeffry: columna amarilla y encabezado azul',
    ];

    public static function esPro(string $d): bool
    {
        return $d !== 'clasico';
    }

    public static function armar(Cliente $c, string $diseno, ?string $paleta = null, bool $conFoto = true, ?User $u = null, string $lang = 'es'): array
    {
        $lang = $lang === 'en' ? 'en' : 'es';
        $t = fn (string $texto) => CvTexto::t($texto, $lang);
        $c->loadMissing(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'cybers']);
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
        // Valores ya traducidos (si el CV es en inglés) para que las plantillas solo los pinten.
        $v = [
            'profesion'    => $lang === 'en' && filled($c->profesion_en) ? $c->profesion_en : $c->profesion,
            'perfil'       => $lang === 'en' && filled($c->perfil_en) ? $c->perfil_en : $c->perfil_profesional,
            'nacionalidad' => CvTexto::nacionalidad($c->nacionalidad, $lang),
            'estado_civil' => $c->estado_civil ? $t($c->estado_civil) : null,
            'sexo'         => $c->sexoLegible() ? $t($c->sexoLegible()) : null,
            'edad'         => $c->edad() !== null ? $c->edad() . ' ' . $t('años') : null,
            'doc'          => $t($c->tipoDocumentoLegible()),
            'seguro'       => $lang === 'en' ? (CvTexto::SEGURO_EN[$c->pais ?: 'NI'] ?? 'Social security number') : $pais['seguro_largo'],
            'pais'         => $t($pais['nombre']),
            'nacimiento'   => trim(($c->fecha_nacimiento?->format('d/m/Y') ?? '') . ($c->edad() !== null ? ' · ' . $c->edad() . ' ' . $t('años') : ''), ' ·'),
        ];
        $v['lugar'] = $c->lugarNacimiento() ? $c->lugarNacimiento() . ($c->pais !== 'OT' ? ', ' . $v['pais'] : '') : null;

        $personales = array_values(array_filter([
            [$t('Nombre y apellidos'), self::nombreConFormato($c->nombreCompleto(), $cv['nombre_formato'])],
            [$v['doc'], $c->numero_documento ? $c->documentoFormateado() : null],
            [$v['seguro'] ?: '', $c->inss_nss],
            [$t('Fecha de nacimiento'), $c->fecha_nacimiento?->format('d/m/Y')],
            [$t('Edad'), $v['edad']],
            [$t('Sexo'), $v['sexo']],
            [$t('Nacionalidad'), $v['nacionalidad']],
            [$t('Lugar de nacimiento'), $v['lugar']],
            [$t('Dirección'), $c->direccion],
            [$t('Celular'), $c->celulares()],
            [$t('Número de hijos'), $c->numero_hijos !== null ? (string) $c->numero_hijos : null],
            [$t('Estado civil'), $v['estado_civil']],
            [$t('Profesión u oficio'), $v['profesion']],
            [$t('Correo electrónico'), $c->correo],
            ['LinkedIn', $c->linkedin],
            [$t('Sitio web'), $c->sitio_web],
            ['GitHub', $c->github],
        ], fn ($f) => filled($f[1])));

        // Datos personales cortos para las plantillas Pro (sin nombre ni contacto).
        $datosPro = array_values(array_filter([
            [$v['doc'], $c->numero_documento ? $c->documentoFormateado() : null],
            [$t('Nacimiento'), $v['nacimiento']],
            [$t('Lugar de nacimiento'), $c->lugarNacimiento()],
            [$t('Nacionalidad'), $v['nacionalidad']],
            [$t('Estado civil'), $v['estado_civil']],
            [$t('Hijos'), $c->numero_hijos !== null ? (string) $c->numero_hijos : null],
            [$v['seguro'] ?: '', $c->inss_nss],
        ], fn ($f) => filled($f[1]) && filled($f[0])));

        // Contacto con íconos para las plantillas Pro: [ícono, texto].
        $tel = fn ($n) => trim(($c->telefono_codigo_pais ? $c->telefono_codigo_pais . ' ' : '') . $n);
        $contactos = array_values(array_filter([
            $c->telefono ? [$c->telefono_whatsapp ? 'whatsapp' : 'tel', $tel($c->telefono)] : null,
            $c->telefono_secundario ? ['tel', $tel($c->telefono_secundario)] : null,
            $c->correo ? ['mail', $c->correo] : null,
            $c->direccion ? ['casa', $c->direccion] : null,
        ]));

        $familia = array_values(array_filter([
            [$t('Nombre del padre'), $c->nombre_padre],
            [$t('Nombre de la madre'), $c->nombre_madre],
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
            $escudo = !empty($pais['escudo_subido'])
                ? Archivos::dataUri($pais['escudo_subido'])
                : ($pais['escudo'] ? Archivos::dataUriLocal(resource_path('cv/' . $pais['escudo']))
                : ($c->pais === 'OT' ? Archivos::dataUri($cfg->escudo) : null)); // país sin escudo: el CV va limpio
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
            'contactos'    => $contactos,
            'datosPro'     => $datosPro,
            'idiomas'      => $c->idiomas,
            'lang'         => $lang,
            't'            => $t,
            'vt'           => $v,
            'enlaces'      => $c->enlaces(),
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
