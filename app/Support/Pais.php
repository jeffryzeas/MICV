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
        'HN' => [
            'nombre' => 'Honduras', 'bandera' => '🇭🇳', 'gentilicio' => 'Hondureño', 'telefono' => '+504',
            'documentos' => ['dni_hn' => 'DNI (Documento Nacional de Identificación)', 'pasaporte' => 'Pasaporte', 'residencia_hn' => 'Carné de residente'],
            'seguro' => 'IHSS', 'seguro_largo' => 'N.º de afiliación IHSS', 'municipio' => 'Municipio', 'departamento' => 'Departamento',
            'companias' => ['Tigo', 'Claro', 'Otro'], 'ley' => 'el Código del Trabajo de la República de Honduras',
            'escudo' => 'escudo-honduras.png', 'ciudad_def' => 'Tegucigalpa',
        ],
        'SV' => [
            'nombre' => 'El Salvador', 'bandera' => '🇸🇻', 'gentilicio' => 'Salvadoreño', 'telefono' => '+503',
            'documentos' => ['dui_sv' => 'DUI (Documento Único de Identidad)', 'pasaporte' => 'Pasaporte', 'residencia_sv' => 'Carné de residente'],
            'seguro' => 'ISSS', 'seguro_largo' => 'N.º de afiliación ISSS', 'municipio' => 'Municipio', 'departamento' => 'Departamento',
            'companias' => ['Claro', 'Tigo', 'Movistar', 'Digicel', 'Otro'], 'ley' => 'el Código de Trabajo de la República de El Salvador',
            'escudo' => 'escudo-el-salvador.png', 'ciudad_def' => 'San Salvador',
        ],
        'GT' => [
            'nombre' => 'Guatemala', 'bandera' => '🇬🇹', 'gentilicio' => 'Guatemalteco', 'telefono' => '+502',
            'documentos' => ['dpi_gt' => 'DPI (Documento Personal de Identificación)', 'pasaporte' => 'Pasaporte', 'residencia_gt' => 'Documento de residente'],
            'seguro' => 'IGSS', 'seguro_largo' => 'N.º de afiliación IGSS', 'municipio' => 'Municipio', 'departamento' => 'Departamento',
            'companias' => ['Tigo', 'Claro', 'Otro'], 'ley' => 'el Código de Trabajo de la República de Guatemala',
            'escudo' => 'escudo-guatemala.png', 'ciudad_def' => 'Ciudad de Guatemala',
        ],
        'PA' => [
            'nombre' => 'Panamá', 'bandera' => '🇵🇦', 'gentilicio' => 'Panameño', 'telefono' => '+507',
            'documentos' => [
                'cedula_pa' => 'Cédula de identidad',
                'pasaporte' => 'Pasaporte',
                'residencia_pa' => 'Carné de residente',
            ],
            'seguro' => 'CSS', 'seguro_largo' => 'N.º de seguro social (CSS)', 'municipio' => 'Distrito', 'departamento' => 'Provincia',
            'companias' => ['+Móvil', 'Tigo', 'Digicel', 'Otro'], 'ley' => 'el Código de Trabajo de la República de Panamá',
            'escudo' => 'escudo-panama.png', 'ciudad_def' => 'Ciudad de Panamá',
        ],
        'MX' => [
            'nombre' => 'México', 'bandera' => '🇲🇽', 'gentilicio' => 'Mexicano', 'telefono' => '+52',
            'documentos' => ['curp_mx' => 'CURP', 'ine_mx' => 'INE (credencial para votar)', 'pasaporte' => 'Pasaporte', 'residencia_mx' => 'Tarjeta de residente'],
            'seguro' => 'IMSS', 'seguro_largo' => 'N.º de Seguridad Social (IMSS)', 'municipio' => 'Municipio', 'departamento' => 'Estado',
            'companias' => ['Telcel', 'AT&T', 'Movistar', 'Bait', 'Otro'], 'ley' => 'la Ley Federal del Trabajo',
            'escudo' => 'escudo-mexico.png', 'ciudad_def' => 'Ciudad de México',
        ],
        'DO' => [
            'nombre' => 'República Dominicana', 'bandera' => '🇩🇴', 'gentilicio' => 'Dominicano', 'telefono' => '+1',
            'documentos' => [
                'cedula_do' => 'Cédula de identidad y electoral',
                'pasaporte' => 'Pasaporte',
                'residencia_do' => 'Carné de residencia',
            ],
            'seguro' => 'NSS', 'seguro_largo' => 'N.º de Seguridad Social (NSS)', 'municipio' => 'Municipio', 'departamento' => 'Provincia',
            'companias' => ['Claro', 'Altice', 'Viva', 'Otro'], 'ley' => 'el Código de Trabajo de la República Dominicana',
            'escudo' => 'escudo-republica-dominicana.png', 'ciudad_def' => 'Santo Domingo',
        ],
        'CU' => [
            'nombre' => 'Cuba', 'bandera' => '🇨🇺', 'gentilicio' => 'Cubano', 'telefono' => '+53',
            'documentos' => [
                'ci_cu' => 'Carné de identidad',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Municipio', 'departamento' => 'Provincia',
            'companias' => ['Cubacel', 'Otro'], 'ley' => 'el Código de Trabajo de la República de Cuba',
            'escudo' => null, 'ciudad_def' => 'La Habana',
        ],
        'CO' => [
            'nombre' => 'Colombia', 'bandera' => '🇨🇴', 'gentilicio' => 'Colombiano', 'telefono' => '+57',
            'documentos' => ['cedula_co' => 'Cédula de ciudadanía', 'ce_co' => 'Cédula de extranjería', 'pasaporte' => 'Pasaporte', 'ppt_co' => 'PPT (Permiso por Protección Temporal)'],
            'seguro' => 'EPS', 'seguro_largo' => 'EPS a la que está afiliado(a)', 'municipio' => 'Municipio', 'departamento' => 'Departamento',
            'companias' => ['Claro', 'Movistar', 'Tigo', 'WOM', 'Otro'], 'ley' => 'el Código Sustantivo del Trabajo de Colombia',
            'escudo' => 'escudo-colombia.png', 'ciudad_def' => 'Bogotá',
        ],
        'VE' => [
            'nombre' => 'Venezuela', 'bandera' => '🇻🇪', 'gentilicio' => 'Venezolano', 'telefono' => '+58',
            'documentos' => [
                'ci_ve' => 'Cédula de identidad',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Municipio', 'departamento' => 'Estado',
            'companias' => ['Movistar', 'Digitel', 'Movilnet', 'Otro'], 'ley' => 'la Ley Orgánica del Trabajo, los Trabajadores y las Trabajadoras',
            'escudo' => 'escudo-venezuela.png', 'ciudad_def' => 'Caracas',
        ],
        'EC' => [
            'nombre' => 'Ecuador', 'bandera' => '🇪🇨', 'gentilicio' => 'Ecuatoriano', 'telefono' => '+593',
            'documentos' => [
                'cedula_ec' => 'Cédula de ciudadanía',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Cantón', 'departamento' => 'Provincia',
            'companias' => ['Claro', 'Movistar', 'CNT', 'Otro'], 'ley' => 'el Código del Trabajo del Ecuador',
            'escudo' => 'escudo-ecuador.png', 'ciudad_def' => 'Quito',
        ],
        'PE' => [
            'nombre' => 'Perú', 'bandera' => '🇵🇪', 'gentilicio' => 'Peruano', 'telefono' => '+51',
            'documentos' => [
                'dni_pe' => 'DNI',
                'ce_pe' => 'Carné de extranjería',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Distrito', 'departamento' => 'Departamento',
            'companias' => ['Claro', 'Movistar', 'Entel', 'Bitel', 'Otro'], 'ley' => 'la legislación laboral del Perú',
            'escudo' => 'escudo-peru.png', 'ciudad_def' => 'Lima',
        ],
        'BO' => [
            'nombre' => 'Bolivia', 'bandera' => '🇧🇴', 'gentilicio' => 'Boliviano', 'telefono' => '+591',
            'documentos' => [
                'ci_bo' => 'Cédula de identidad',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => 'CNS', 'seguro_largo' => 'N.º de asegurado (Caja de salud)', 'municipio' => 'Municipio', 'departamento' => 'Departamento',
            'companias' => ['Entel', 'Tigo', 'Viva', 'Otro'], 'ley' => 'la Ley General del Trabajo de Bolivia',
            'escudo' => 'escudo-bolivia.png', 'ciudad_def' => 'La Paz',
        ],
        'CL' => [
            'nombre' => 'Chile', 'bandera' => '🇨🇱', 'gentilicio' => 'Chileno', 'telefono' => '+56',
            'documentos' => [
                'rut_cl' => 'RUT / RUN (cédula)',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => 'AFP', 'seguro_largo' => 'AFP y sistema de salud (Fonasa / Isapre)', 'municipio' => 'Comuna', 'departamento' => 'Región',
            'companias' => ['Entel', 'Movistar', 'Claro', 'WOM', 'Otro'], 'ley' => 'el Código del Trabajo de Chile',
            'escudo' => 'escudo-chile.png', 'ciudad_def' => 'Santiago',
        ],
        'AR' => [
            'nombre' => 'Argentina', 'bandera' => '🇦🇷', 'gentilicio' => 'Argentino', 'telefono' => '+54',
            'documentos' => [
                'dni_ar' => 'DNI',
                'cuil_ar' => 'CUIL',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => 'CUIL', 'seguro_largo' => 'CUIL', 'municipio' => 'Localidad', 'departamento' => 'Provincia',
            'companias' => ['Personal', 'Claro', 'Movistar', 'Otro'], 'ley' => 'la Ley de Contrato de Trabajo',
            'escudo' => 'escudo-argentina.png', 'ciudad_def' => 'Buenos Aires',
        ],
        'UY' => [
            'nombre' => 'Uruguay', 'bandera' => '🇺🇾', 'gentilicio' => 'Uruguayo', 'telefono' => '+598',
            'documentos' => [
                'ci_uy' => 'Cédula de identidad',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Localidad', 'departamento' => 'Departamento',
            'companias' => ['Antel', 'Movistar', 'Claro', 'Otro'], 'ley' => 'la legislación laboral del Uruguay',
            'escudo' => 'escudo-uruguay.png', 'ciudad_def' => 'Montevideo',
        ],
        'PY' => [
            'nombre' => 'Paraguay', 'bandera' => '🇵🇾', 'gentilicio' => 'Paraguayo', 'telefono' => '+595',
            'documentos' => [
                'ci_py' => 'Cédula de identidad',
                'pasaporte' => 'Pasaporte',
            ],
            'seguro' => 'IPS', 'seguro_largo' => 'N.º de asegurado IPS', 'municipio' => 'Distrito', 'departamento' => 'Departamento',
            'companias' => ['Tigo', 'Personal', 'Claro', 'Otro'], 'ley' => 'el Código del Trabajo del Paraguay',
            'escudo' => 'escudo-paraguay.png', 'ciudad_def' => 'Asunción',
        ],
        'ES' => [
            'nombre' => 'España', 'bandera' => '🇪🇸', 'gentilicio' => 'Español', 'telefono' => '+34',
            'documentos' => ['dni_es' => 'DNI', 'nie_es' => 'NIE (extranjeros)', 'pasaporte' => 'Pasaporte'],
            'seguro' => 'NUSS', 'seguro_largo' => 'N.º de afiliación a la Seguridad Social', 'municipio' => 'Municipio', 'departamento' => 'Provincia',
            'companias' => ['Movistar', 'Vodafone', 'Orange', 'Yoigo', 'Otro'], 'ley' => 'el Estatuto de los Trabajadores',
            'escudo' => 'escudo-espana.png', 'ciudad_def' => 'Madrid',
        ],
        'US' => [
            'nombre' => 'Estados Unidos', 'bandera' => '🇺🇸', 'gentilicio' => 'Estadounidense', 'telefono' => '+1',
            'documentos' => ['licencia_us' => 'Licencia de conducir / State ID', 'green_us' => 'Green Card (residente permanente)', 'pasaporte' => 'Pasaporte'],
            // El número de Seguro Social (SSN) NO debe ir en un CV: no se pide.
            'seguro' => null, 'seguro_largo' => null, 'municipio' => 'Ciudad', 'departamento' => 'Estado',
            'companias' => ['AT&T', 'Verizon', 'T-Mobile', 'Otro'], 'ley' => null,
            'escudo' => 'escudo-estados-unidos.png', 'ciudad_def' => '',
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
        '+504' => '🇭🇳 +504', '+507' => '🇵🇦 +507', '+52' => '🇲🇽 +52', '+53' => '🇨🇺 +53', '+57' => '🇨🇴 +57',
        '+58' => '🇻🇪 +58', '+593' => '🇪🇨 +593', '+51' => '🇵🇪 +51', '+591' => '🇧🇴 +591', '+56' => '🇨🇱 +56',
        '+54' => '🇦🇷 +54', '+598' => '🇺🇾 +598', '+595' => '🇵🇾 +595', '+34' => '🇪🇸 +34', '+1' => '🇺🇸🇩🇴 +1',
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

    /** Compatibilidad: ahora todo lo valida App\Support\Documentos. */
    public static function validarCostaRica(string $tipo, string $numero): array
    {
        return Documentos::validar($tipo, $numero);
    }
}
