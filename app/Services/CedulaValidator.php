<?php

namespace App\Services;

use DateTime;

/**
 * Validador de la cédula de identidad nicaragüense.
 * Formato: MMM-DDMMAA-CCCCL (14 caracteres, sin guiones en BD).
 * Algoritmo público de checksum (Módulo 23, alfabeto sin I/O/Z),
 * análogo a un dígito verificador bancario — implementación propia.
 */
class CedulaValidator
{
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXY'; // 23 letras

    /** Rangos de código de municipio por departamento (verificados, no alfabéticos). */
    private const DEPARTAMENTOS = [
        ['nombre' => 'Managua',           'desde' => 1,   'hasta' => 9],
        ['nombre' => 'Carazo',            'desde' => 41,  'hasta' => 48],
        ['nombre' => 'Chinandega',        'desde' => 81,  'hasta' => 93],
        ['nombre' => 'Chontales',         'desde' => 121, 'hasta' => 130],
        ['nombre' => 'Estelí',            'desde' => 161, 'hasta' => 166],
        ['nombre' => 'Granada',           'desde' => 201, 'hasta' => 204],
        ['nombre' => 'Jinotega',          'desde' => 241, 'hasta' => 249],
        ['nombre' => 'León',              'desde' => 281, 'hasta' => 291],
        ['nombre' => 'Madriz',            'desde' => 321, 'hasta' => 330],
        ['nombre' => 'Boaco',             'desde' => 361, 'hasta' => 366],
        ['nombre' => 'Masaya',            'desde' => 401, 'hasta' => 409],
        ['nombre' => 'Matagalpa',         'desde' => 441, 'hasta' => 454],
        ['nombre' => 'Nueva Segovia',     'desde' => 481, 'hasta' => 488],
        ['nombre' => 'Rivas',             'desde' => 521, 'hasta' => 530],
        ['nombre' => 'Río San Juan',      'desde' => 561, 'hasta' => 566],
        ['nombre' => 'Costa Caribe Norte','desde' => 601, 'hasta' => 608],
        ['nombre' => 'Costa Caribe Sur',  'desde' => 641, 'hasta' => 648],
    ];

    /** Municipios por código exacto (ampliable). */
    private const MUNICIPIOS = [
        '361' => 'Boaco', '362' => 'Camoapa', '363' => 'Santa Lucía',
        '364' => 'San José de los Remates', '365' => 'San Lorenzo', '366' => 'Teustepe',
        '041' => 'Jinotepe', '042' => 'Diriamba', '043' => 'San Marcos',
        '044' => 'Santa Teresa', '045' => 'Dolores', '046' => 'La Paz de Carazo',
        '047' => 'El Rosario', '048' => 'La Conquista',
        '081' => 'Chinandega', '082' => 'Corinto', '083' => 'El Realejo',
        '084' => 'Chichigalpa', '085' => 'Posoltega', '086' => 'El Viejo',
        '087' => 'Puerto Morazán', '088' => 'Somotillo', '089' => 'Villanueva',
        '090' => 'Santo Tomás del Norte', '091' => 'Cinco Pinos',
        '092' => 'San Francisco del Norte', '093' => 'San Pedro del Norte',
        '121' => 'Juigalpa', '122' => 'Acoyapa', '123' => 'Santo Tomás',
        '124' => 'Villa Sandino', '125' => 'San Pedro de Lóvago', '126' => 'La Libertad',
        '127' => 'Santo Domingo', '128' => 'Comalapa', '129' => 'San Francisco de Cuapa',
        '130' => 'El Coral',
        '161' => 'Estelí', '162' => 'Pueblo Nuevo', '163' => 'Condega',
        '164' => 'San Juan de Limay', '165' => 'La Trinidad', '166' => 'San Nicolás',
        '201' => 'Granada', '202' => 'Nandaime', '203' => 'Diriomo', '204' => 'Diriá',
        '241' => 'Jinotega', '242' => 'San Rafael del Norte', '243' => 'San Sebastián de Yalí',
        '244' => 'La Concordia', '245' => 'San José de Bocay', '246' => 'El Cuá',
        '247' => 'Santa María de Pantasma', '248' => 'Wiwilí de Jinotega',
        '001' => 'Managua', '003' => 'Tipitapa', '008' => 'Ciudad Sandino',
        '281' => 'León', '284' => 'La Paz Centro', '287' => 'Nagarote',
        '321' => 'Somoto',
        '401' => 'Masaya', '402' => 'Nindirí', '408' => 'Masatepe',
        '441' => 'Matagalpa', '448' => 'Sébaco', '451' => 'Río Blanco',
        '452' => 'El Tuma - La Dalia', '453' => 'Rancho Grande', '454' => 'Waslala',
        '481' => 'Ocotal',
        '521' => 'Rivas',
        '561' => 'San Carlos',
        '601' => 'Puerto Cabezas (Bilwi)',
        '641' => 'Bluefields',
    ];

    /**
     * Valida una cédula y devuelve un array con toda la info derivada.
     * NOTA: valida estructura/formato, no confirma vigencia en el padrón electoral.
     */
    public static function validar(string $cedulaCruda): array
    {
        $cedula = self::normalizar($cedulaCruda);

        $resultado = [
            'valida' => false, 'cedula' => $cedula, 'errores' => [],
            'municipio_codigo' => null, 'departamento' => null, 'municipio' => null,
            'fecha_nacimiento' => null, 'edad' => null, 'correlativo' => null,
            'letra_esperada' => null, 'letra_ingresada' => null,
        ];

        if (!preg_match('/^(\d{3})(\d{6})(\d{4})([A-Za-z])$/', $cedula, $m)) {
            $resultado['errores'][] = 'El formato debe tener 13 dígitos seguidos de 1 letra verificadora (14 caracteres).';
            return $resultado;
        }

        [, $codMunicipio, $fechaStr, $correlativo, $letraIngresada] = $m;
        $letraIngresada = strtoupper($letraIngresada);

        $resultado['municipio_codigo'] = $codMunicipio;
        $resultado['correlativo'] = $correlativo;
        $resultado['letra_ingresada'] = $letraIngresada;

        $ubicacion = self::resolverUbicacion($codMunicipio);
        $resultado['departamento'] = $ubicacion['departamento'];
        $resultado['municipio'] = $ubicacion['municipio'];
        if ($ubicacion['departamento'] === null) {
            $resultado['errores'][] = "El código de municipio {$codMunicipio} no corresponde a ningún departamento reconocido.";
        }

        $fecha = self::resolverFecha($fechaStr);
        if ($fecha === null) {
            $resultado['errores'][] = "La fecha de nacimiento codificada ({$fechaStr}) no es válida.";
        } else {
            $resultado['fecha_nacimiento'] = $fecha['fecha_iso'];
            $resultado['edad'] = $fecha['edad'];
        }

        $letraEsperada = self::calcularLetraVerificadora($codMunicipio . $fechaStr . $correlativo);
        $resultado['letra_esperada'] = $letraEsperada;
        if ($letraEsperada !== $letraIngresada) {
            $resultado['errores'][] = "La letra verificadora no coincide. Se esperaba '{$letraEsperada}' y se recibió '{$letraIngresada}'.";
        }

        $resultado['valida'] = empty($resultado['errores']);
        return $resultado;
    }

    private static function normalizar(string $cedula): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($cedula)));
    }

    private static function calcularLetraVerificadora(string $trecediDigitos): string
    {
        $residuo = 0;
        foreach (str_split($trecediDigitos) as $digito) {
            $residuo = ($residuo * 10 + (int) $digito) % 23;
        }
        return self::ALFABETO[$residuo];
    }

    private static function resolverUbicacion(string $codMunicipio): array
    {
        $codigoInt = (int) $codMunicipio;
        $departamento = null;
        foreach (self::DEPARTAMENTOS as $dep) {
            if ($codigoInt >= $dep['desde'] && $codigoInt <= $dep['hasta']) {
                $departamento = $dep['nombre'];
                break;
            }
        }
        $municipio = self::MUNICIPIOS[$codMunicipio]
            ?? ($departamento ? 'Municipio no catalogado localmente' : null);

        return ['departamento' => $departamento, 'municipio' => $municipio];
    }

    private static function resolverFecha(string $ddmmaa): ?array
    {
        $dia = (int) substr($ddmmaa, 0, 2);
        $mes = (int) substr($ddmmaa, 2, 2);
        $aa = (int) substr($ddmmaa, 4, 2);

        $anioActualCorto = (int) date('y');
        $siglo = ($aa > $anioActualCorto) ? 1900 : 2000;
        $anioCompleto = $siglo + $aa;

        if (!checkdate($mes, $dia, $anioCompleto)) {
            return null;
        }

        $fechaNac = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $anioCompleto, $mes, $dia));
        $hoy = new DateTime('today');
        if ($fechaNac > $hoy) {
            return null;
        }

        return [
            'fecha_iso' => $fechaNac->format('Y-m-d'),
            'edad'      => $hoy->diff($fechaNac)->y,
        ];
    }
}
