<?php

namespace App\Support;

/**
 * Todo lo que cambia según el país de la persona: documentos, seguro
 * social, nombres de las divisiones (municipio/cantón…), código telefónico,
 * compañías de celular, ley laboral y escudo del CV clásico.
 */
class Pais
{
    public const DATOS = [
        'NI' => [
            'nombre'      => 'Nicaragua',
            'bandera'     => '🇳🇮',
            'gentilicio'  => 'Nicaragüense',
            'telefono'    => '+505',
            'documentos'  => [
                'cedula'             => 'Cédula de identidad',
                'pasaporte'          => 'Pasaporte',
                'residencia'         => 'Cédula de residencia',
                'partida_nacimiento' => 'Código de persona (partida de nacimiento)',
            ],
            'seguro'      => 'INSS',
            'seguro_largo'=> 'Número INSS',
            'municipio'   => 'Municipio',
            'departamento'=> 'Departamento',
            'companias'   => ['Claro', 'Tigo', 'Otro'],
            'ley'         => 'el Código del Trabajo de la República de Nicaragua',
            'escudo'      => 'escudo-nicaragua.png',
            'ciudad_def'  => 'Managua',
        ],
        'CR' => [
            'nombre'      => 'Costa Rica',
            'bandera'     => '🇨🇷',
            'gentilicio'  => 'Costarricense',
            'telefono'    => '+506',
            'documentos'  => [
                'cedula_cr' => 'Cédula de identidad',
                'dimex'     => 'DIMEX (residencia)',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro'      => 'CCSS',
            'seguro_largo'=> 'N.º de asegurado CCSS',
            'municipio'   => 'Cantón',
            'departamento'=> 'Provincia',
            'companias'   => ['Kölbi', 'Claro', 'Liberty', 'Otro'],
            'ley'         => 'el Código de Trabajo de la República de Costa Rica',
            'escudo'      => 'escudo-costa-rica.png',
            'ciudad_def'  => 'San José',
        ],
        'OT' => [
            'nombre'      => 'Otro país',
            'bandera'     => '🌎',
            'gentilicio'  => '',
            'telefono'    => '',
            'documentos'  => [
                'pasaporte' => 'Pasaporte',
                'otro'      => 'Documento de identidad',
            ],
            'seguro'      => 'Seguro social',
            'seguro_largo'=> 'N.º de seguro social',
            'municipio'   => 'Ciudad',
            'departamento'=> 'Estado / provincia',
            'companias'   => ['Otro'],
            'ley'         => null,
            'escudo'      => null,
            'ciudad_def'  => '',
        ],
    ];

    public const CODIGOS_TELEFONO = [
        '+505' => '🇳🇮 +505', '+506' => '🇨🇷 +506', '+503' => '🇸🇻 +503', '+502' => '🇬🇹 +502',
        '+504' => '🇭🇳 +504', '+507' => '🇵🇦 +507', '+1' => '🇺🇸 +1', '+34' => '🇪🇸 +34', '+52' => '🇲🇽 +52',
    ];

    /** Provincia según el primer dígito de la cédula costarricense. */
    public const PROVINCIAS_CR = [
        1 => 'San José', 2 => 'Alajuela', 3 => 'Cartago', 4 => 'Heredia',
        5 => 'Guanacaste', 6 => 'Puntarenas', 7 => 'Limón',
    ];

    public static function de(?string $codigo): array
    {
        return self::DATOS[$codigo] ?? self::DATOS['NI'];
    }

    public static function codigos(): array
    {
        return array_keys(self::DATOS);
    }

    /** Todos los tipos de documento de todos los países. */
    public static function todosLosDocumentos(): array
    {
        $t = [];
        foreach (self::DATOS as $p) {
            $t += $p['documentos'];
        }
        return $t;
    }

    /** País al que pertenece un tipo de documento (para clientes viejos). */
    public static function paisDeDocumento(?string $tipo): string
    {
        foreach (self::DATOS as $cod => $p) {
            if (array_key_exists((string) $tipo, $p['documentos']) && $tipo !== 'pasaporte') {
                return $cod;
            }
        }
        return 'NI';
    }

    /** Para el JavaScript de los formularios. */
    public static function paraJs(): array
    {
        $r = [];
        foreach (self::DATOS as $cod => $p) {
            $r[$cod] = collect($p)->only(['nombre', 'gentilicio', 'telefono', 'documentos', 'seguro_largo', 'municipio', 'departamento', 'companias'])->all();
        }
        return $r;
    }

    /** Valida la cédula de Costa Rica (9 dígitos) y DIMEX (11 o 12 dígitos). */
    public static function validarCostaRica(string $tipo, string $numero): array
    {
        $r = ['valida' => true, 'errores' => [], 'provincia' => null, 'formateado' => $numero];
        if ($tipo === 'cedula_cr') {
            if (!preg_match('/^[1-9]\d{8}$/', $numero)) {
                $r['valida'] = false;
                $r['errores'][] = 'La cédula de Costa Rica tiene 9 dígitos (ej. 1-0234-0567).';
                return $r;
            }
            $r['provincia'] = self::PROVINCIAS_CR[(int) $numero[0]] ?? null;
            $r['formateado'] = $numero[0] . '-' . substr($numero, 1, 4) . '-' . substr($numero, 5, 4);
        } elseif ($tipo === 'dimex') {
            if (!preg_match('/^\d{11,12}$/', $numero)) {
                $r['valida'] = false;
                $r['errores'][] = 'El DIMEX tiene 11 o 12 dígitos, sin guiones.';
            }
        }
        return $r;
    }
}
