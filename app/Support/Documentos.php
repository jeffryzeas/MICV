<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Verificación de documentos de identidad por país, con las reglas
 * públicas de cada uno (formato y dígito verificador cuando existe):
 *
 *  - Costa Rica: cédula (9 dígitos, provincia por el 1.er dígito) y DIMEX.
 *  - Honduras: DNI de 13 dígitos (departamento de inscripción).
 *  - El Salvador: DUI de 9 dígitos con dígito verificador.
 *  - Guatemala: DPI / CUI de 13 dígitos con dígito verificador, departamento y municipio.
 *  - México: CURP de 18 caracteres con dígito verificador (fecha, sexo y estado).
 *  - Colombia: cédula de ciudadanía (6 a 10 dígitos) y cédula de extranjería.
 *  - España: DNI y NIE con letra de control.
 *  - Estados Unidos: licencia / State ID y Green Card (solo formato básico).
 *  - Panamá (provincia), República Dominicana (verificador), Cuba (fecha y sexo),
 *    Venezuela, Ecuador (provincia y verificador), Perú, Bolivia, Chile (RUT),
 *    Argentina (DNI y CUIL con verificador), Uruguay (verificador) y Paraguay.
 *
 * La cédula de Nicaragua la valida App\Services\CedulaValidator.
 * No se consulta ningún registro oficial en línea: se revisan las reglas
 * del número, que es lo que está publicado.
 *
 * 'valida' = false bloquea el guardado. 'aviso' solo advierte (para reglas
 * cuyo dígito verificador puede tener excepciones).
 */
class Documentos
{
    public const DEPTOS_HN = [
        1 => 'Atlántida', 2 => 'Colón', 3 => 'Comayagua', 4 => 'Copán', 5 => 'Cortés', 6 => 'Choluteca',
        7 => 'El Paraíso', 8 => 'Francisco Morazán', 9 => 'Gracias a Dios', 10 => 'Intibucá', 11 => 'Islas de la Bahía',
        12 => 'La Paz', 13 => 'Lempira', 14 => 'Ocotepeque', 15 => 'Olancho', 16 => 'Santa Bárbara', 17 => 'Valle', 18 => 'Yoro',
    ];

    /** Departamento => cantidad de municipios (para revisar el DPI). */
    public const DEPTOS_GT = [
        1 => ['Guatemala', 17], 2 => ['El Progreso', 8], 3 => ['Sacatepéquez', 16], 4 => ['Chimaltenango', 16],
        5 => ['Escuintla', 14], 6 => ['Santa Rosa', 14], 7 => ['Sololá', 19], 8 => ['Totonicapán', 8],
        9 => ['Quetzaltenango', 24], 10 => ['Suchitepéquez', 21], 11 => ['Retalhuleu', 9], 12 => ['San Marcos', 30],
        13 => ['Huehuetenango', 33], 14 => ['Quiché', 21], 15 => ['Baja Verapaz', 8], 16 => ['Alta Verapaz', 17],
        17 => ['Petén', 14], 18 => ['Izabal', 5], 19 => ['Zacapa', 11], 20 => ['Chiquimula', 11],
        21 => ['Jalapa', 7], 22 => ['Jutiapa', 17],
    ];

    public const ESTADOS_MX = [
        'AS' => 'Aguascalientes', 'BC' => 'Baja California', 'BS' => 'Baja California Sur', 'CC' => 'Campeche',
        'CL' => 'Coahuila', 'CM' => 'Colima', 'CS' => 'Chiapas', 'CH' => 'Chihuahua', 'DF' => 'Ciudad de México',
        'DG' => 'Durango', 'GT' => 'Guanajuato', 'GR' => 'Guerrero', 'HG' => 'Hidalgo', 'JC' => 'Jalisco',
        'MC' => 'Estado de México', 'MN' => 'Michoacán', 'MS' => 'Morelos', 'NT' => 'Nayarit', 'NL' => 'Nuevo León',
        'OC' => 'Oaxaca', 'PL' => 'Puebla', 'QT' => 'Querétaro', 'QR' => 'Quintana Roo', 'SP' => 'San Luis Potosí',
        'SL' => 'Sinaloa', 'SR' => 'Sonora', 'TC' => 'Tabasco', 'TS' => 'Tamaulipas', 'TL' => 'Tlaxcala',
        'VZ' => 'Veracruz', 'YN' => 'Yucatán', 'ZS' => 'Zacatecas', 'NE' => 'Nacido en el extranjero',
    ];

    /** Ayuda corta que se muestra debajo del campo. */
    public const AYUDA = [
        'cedula_cr'  => '9 dígitos, ej. 1-0234-0567 (la provincia sale del primer número).',
        'dimex'      => '11 o 12 dígitos, sin guiones.',
        'dni_hn'     => '13 dígitos, ej. 0801-1990-12345.',
        'dui_sv'     => '9 dígitos, ej. 01234567-8.',
        'dpi_gt'     => '13 dígitos (CUI), ej. 2490 46554 0101.',
        'curp_mx'    => '18 letras y números, ej. GOML960707MDFRRC08.',
        'ine_mx'     => 'Clave de elector: 18 caracteres.',
        'cedula_co'  => 'De 6 a 10 dígitos, sin puntos.',
        'ce_co'      => 'Número de la cédula de extranjería.',
        'dni_es'     => '8 números y una letra, ej. 12345678Z.',
        'nie_es'     => 'X, Y o Z + 7 números + letra, ej. X1234567L.',
        'licencia_us'=> 'Número de su licencia o State ID.',
        'green_us'   => 'USCIS # / A-Number (9 dígitos).',
        'pasaporte'  => 'Tal como aparece en el pasaporte.',
        'residencia' => 'Número de la cédula de residencia.',
        'partida_nacimiento' => 'Código de persona del certificado de nacimiento.',
        'otro'       => 'Número del documento.',
        'cedula_pa'  => 'Con guiones, ej. 8-123-4567 (también PE-, E-, N-).',
        'cedula_do'  => '11 dígitos, ej. 001-1234567-8.',
        'ci_cu'      => '11 dígitos; los 6 primeros son la fecha de nacimiento.',
        'ci_ve'      => 'V o E y el número, ej. V-12345678.',
        'cedula_ec'  => '10 dígitos, ej. 1710034065.',
        'dni_pe'     => '8 dígitos.',
        'ce_pe'      => 'Número del carné de extranjería.',
        'ci_bo'      => 'Número de cédula (con complemento si tiene).',
        'rut_cl'     => 'Con dígito verificador, ej. 12.345.678-5.',
        'dni_ar'     => '7 u 8 dígitos, sin puntos.',
        'cuil_ar'    => '11 dígitos, ej. 20-12345678-6.',
        'ci_uy'      => 'Con dígito verificador, ej. 1.234.567-2.',
        'ci_py'      => 'De 5 a 8 dígitos.',
    ];

    public const PROVINCIAS_PA = [
        1 => 'Bocas del Toro', 2 => 'Coclé', 3 => 'Colón', 4 => 'Chiriquí', 5 => 'Darién', 6 => 'Herrera', 7 => 'Los Santos',
        8 => 'Panamá', 9 => 'Veraguas', 10 => 'Guna Yala', 11 => 'Emberá-Wounaan', 12 => 'Ngäbe-Buglé', 13 => 'Panamá Oeste',
    ];

    public const PROVINCIAS_EC = [
        1 => 'Azuay', 2 => 'Bolívar', 3 => 'Cañar', 4 => 'Carchi', 5 => 'Cotopaxi', 6 => 'Chimborazo', 7 => 'El Oro',
        8 => 'Esmeraldas', 9 => 'Guayas', 10 => 'Imbabura', 11 => 'Loja', 12 => 'Los Ríos', 13 => 'Manabí',
        14 => 'Morona Santiago', 15 => 'Napo', 16 => 'Pastaza', 17 => 'Pichincha', 18 => 'Tungurahua',
        19 => 'Zamora Chinchipe', 20 => 'Galápagos', 21 => 'Sucumbíos', 22 => 'Orellana',
        23 => 'Santo Domingo de los Tsáchilas', 24 => 'Santa Elena', 30 => 'Exterior',
    ];

    /** La cédula panameña necesita los guiones (8-123-4567). */
    public static function limpiarPanama(string $n): string
    {
        $n = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', str_replace([' ', '.', '–'], '-', $n)));
        return trim(preg_replace('/-+/', '-', $n), '-');
    }

    /** Cómo se guarda el número según el tipo de documento. */
    public static function limpiar(?string $tipo, $numero): string
    {
        return $tipo === 'cedula_pa' ? self::limpiarPanama((string) $numero) : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $numero));
    }

    public static function validar(string $tipo, string $n): array
    {
        $n = $tipo === 'cedula_pa' ? self::limpiarPanama($n) : strtoupper(preg_replace('/[^A-Za-z0-9Ññ]/u', '', $n));
        $r = ['valida' => true, 'errores' => [], 'aviso' => null, 'formateado' => $n, 'resumen' => null,
              'departamento' => null, 'municipio' => null, 'fecha_nacimiento' => null, 'sexo' => null, 'provincia' => null];
        $mal = function (string $msg) use (&$r) { $r['valida'] = false; $r['errores'][] = $msg; return $r; };

        switch ($tipo) {
            case 'cedula_cr':
                if (!preg_match('/^[1-9]\d{8}$/', $n)) return $mal('La cédula de Costa Rica tiene 9 dígitos (ej. 1-0234-0567).');
                $r['provincia'] = $r['departamento'] = Pais::PROVINCIAS_CR[(int) $n[0]] ?? null;
                $r['formateado'] = $n[0] . '-' . substr($n, 1, 4) . '-' . substr($n, 5, 4);
                $r['resumen'] = $r['provincia'] ? 'Provincia de inscripción: ' . $r['provincia'] : null;
                break;

            case 'dimex':
                if (!preg_match('/^\d{11,12}$/', $n)) return $mal('El DIMEX tiene 11 o 12 dígitos, sin guiones.');
                break;

            case 'dni_hn':
                if (!preg_match('/^\d{13}$/', $n)) return $mal('El DNI de Honduras tiene 13 dígitos (ej. 0801-1990-12345).');
                $dep = (int) substr($n, 0, 2); $anio = (int) substr($n, 4, 4);
                if (!isset(self::DEPTOS_HN[$dep])) return $mal('Los dos primeros números deben ser un departamento de Honduras (01 a 18).');
                if ($anio < 1900 || $anio > (int) date('Y')) return $mal('Los números 5 a 8 deben ser un año válido.');
                $r['departamento'] = self::DEPTOS_HN[$dep];
                $r['formateado'] = substr($n, 0, 4) . '-' . substr($n, 4, 4) . '-' . substr($n, 8, 5);
                $r['resumen'] = 'Departamento de inscripción: ' . $r['departamento'];
                break;

            case 'dui_sv':
                if (!preg_match('/^\d{9}$/', $n)) return $mal('El DUI tiene 9 dígitos (ej. 01234567-8).');
                $suma = 0;
                for ($i = 0; $i < 8; $i++) { $suma += (int) $n[$i] * (9 - $i); }
                $dv = (10 - $suma % 10) % 10;
                if ($dv !== (int) $n[8]) $r['aviso'] = 'Revise el número: el dígito verificador del DUI no coincide.';
                $r['formateado'] = substr($n, 0, 8) . '-' . $n[8];
                break;

            case 'dpi_gt':
                if (!preg_match('/^\d{13}$/', $n)) return $mal('El DPI (CUI) tiene 13 dígitos.');
                $dep = (int) substr($n, 9, 2); $mun = (int) substr($n, 11, 2);
                if (!isset(self::DEPTOS_GT[$dep])) return $mal('Los dígitos 10 y 11 deben ser un departamento de Guatemala (01 a 22).');
                if ($mun < 1 || $mun > self::DEPTOS_GT[$dep][1]) return $mal('Los dos últimos dígitos no son un municipio de ' . self::DEPTOS_GT[$dep][0] . '.');
                $suma = 0;
                for ($i = 0; $i < 8; $i++) { $suma += (int) $n[$i] * ($i + 2); }
                if ($suma % 11 !== (int) $n[8]) $r['aviso'] = 'Revise el número: el dígito verificador del DPI no coincide.';
                $r['departamento'] = self::DEPTOS_GT[$dep][0];
                $r['formateado'] = substr($n, 0, 4) . ' ' . substr($n, 4, 5) . ' ' . substr($n, 9, 4);
                $r['resumen'] = 'Departamento: ' . $r['departamento'];
                break;

            case 'curp_mx':
                $estados = implode('|', array_keys(self::ESTADOS_MX));
                if (!preg_match('/^[A-Z][AEIOUX][A-Z]{2}(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([HMX])(' . $estados . ')[B-DF-HJ-NP-TV-Z]{3}([0-9A-Z])(\d)$/', $n, $m)) {
                    return $mal('La CURP tiene 18 caracteres: 4 letras, fecha (AAMMDD), H o M, estado y 5 caracteres más.');
                }
                $tabla = '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ';
                $suma = 0;
                for ($i = 0; $i < 17; $i++) { $suma += mb_strpos($tabla, $n[$i]) * (18 - $i); }
                $dv = (10 - $suma % 10) % 10;
                if ($dv !== (int) $m[7]) return $mal('La CURP no es correcta: el último dígito (verificador) no coincide.');
                $siglo = ctype_digit($m[6]) ? 1900 : 2000;
                $fecha = sprintf('%04d-%s-%s', $siglo + (int) $m[1], $m[2], $m[3]);
                if (!checkdate((int) $m[2], (int) $m[3], $siglo + (int) $m[1])) return $mal('La fecha dentro de la CURP no existe.');
                $r['fecha_nacimiento'] = $fecha;
                $r['sexo'] = ['H' => 'M', 'M' => 'F', 'X' => 'O'][$m[4]];
                $r['departamento'] = self::ESTADOS_MX[$m[5]];
                $r['resumen'] = 'Nació el ' . Carbon::parse($fecha)->format('d/m/Y') . ' · ' . $r['departamento'];
                break;

            case 'ine_mx':
                if (!preg_match('/^[A-Z0-9]{18}$/', $n)) return $mal('La clave de elector tiene 18 caracteres.');
                break;

            case 'cedula_co':
                if (!preg_match('/^\d{6,10}$/', $n)) return $mal('La cédula de Colombia tiene de 6 a 10 dígitos, sin puntos.');
                $r['formateado'] = number_format((int) $n, 0, ',', '.');
                break;

            case 'ce_co':
                if (!preg_match('/^[A-Z0-9]{5,12}$/', $n)) return $mal('Revise el número de la cédula de extranjería.');
                break;

            case 'dni_es':
            case 'nie_es':
                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                if ($tipo === 'dni_es') {
                    if (!preg_match('/^(\d{8})([A-Z])$/', $n, $m)) return $mal('El DNI tiene 8 números y una letra (ej. 12345678Z).');
                    $num = (int) $m[1];
                } else {
                    if (!preg_match('/^([XYZ])(\d{7})([A-Z])$/', $n, $m)) return $mal('El NIE es X, Y o Z + 7 números + letra (ej. X1234567L).');
                    $num = (int) (['X' => '0', 'Y' => '1', 'Z' => '2'][$m[1]] . $m[2]);
                    $m[2] = $m[3];
                }
                if ($letras[$num % 23] !== $m[2]) return $mal('La letra no corresponde al número (debería ser ' . $letras[$num % 23] . ').');
                break;

            case 'licencia_us':
                if (!preg_match('/^[A-Z0-9]{4,20}$/', $n)) return $mal('Revise el número de la licencia o State ID.');
                break;

            case 'green_us':
                $n2 = ltrim($n, 'A');
                if (!preg_match('/^\d{8,9}$/', $n2)) return $mal('El USCIS # (A-Number) tiene 8 o 9 dígitos.');
                $r['formateado'] = 'A' . $n2;
                break;

            case 'cedula_pa':
                if (!preg_match('/^(?:(\d{1,2})(AV|PI)?|PE|E|N)-(\d{1,4})-(\d{1,6})$/', $n, $m)) {
                    return $mal('La cédula panameña va con guiones: provincia-tomo-asiento (ej. 8-123-4567, PE-12-345, E-8-12345).');
                }
                if ($m[1] !== '') {
                    $prov = (int) $m[1];
                    if (!isset(self::PROVINCIAS_PA[$prov])) return $mal('El primer número debe ser una provincia de Panamá (1 a 13).');
                    $r['departamento'] = self::PROVINCIAS_PA[$prov];
                    $r['resumen'] = 'Provincia: ' . $r['departamento'];
                }
                break;

            case 'cedula_do':
                if (!preg_match('/^\d{11}$/', $n)) return $mal('La cédula dominicana tiene 11 dígitos (ej. 001-1234567-8).');
                $suma = 0;
                for ($i = 0; $i < 10; $i++) { $x = (int) $n[$i] * ($i % 2 ? 2 : 1); $suma += $x > 9 ? $x - 9 : $x; }
                if ((10 - $suma % 10) % 10 !== (int) $n[10]) $r['aviso'] = 'Revise el número: el dígito verificador de la cédula no coincide.';
                $r['formateado'] = substr($n, 0, 3) . '-' . substr($n, 3, 7) . '-' . $n[10];
                break;

            case 'ci_cu':
                if (!preg_match('/^(\d{2})(\d{2})(\d{2})(\d)(\d{4})$/', $n, $m)) return $mal('El carné de identidad cubano tiene 11 dígitos.');
                $siglo = $m[4] === '9' ? 1800 : ((int) $m[4] <= 5 ? 1900 : 2000);
                if (!checkdate((int) $m[2], (int) $m[3], $siglo + (int) $m[1])) return $mal('Los 6 primeros dígitos deben ser la fecha de nacimiento (AAMMDD).');
                $r['fecha_nacimiento'] = sprintf('%04d-%s-%s', $siglo + (int) $m[1], $m[2], $m[3]);
                $r['sexo'] = ((int) $n[9]) % 2 === 0 ? 'M' : 'F';
                $r['resumen'] = 'Nació el ' . Carbon::parse($r['fecha_nacimiento'])->format('d/m/Y');
                break;

            case 'ci_ve':
                if (!preg_match('/^([VE])?(\d{6,9})$/', $n, $m)) return $mal('La cédula venezolana es V o E y de 6 a 9 dígitos (ej. V-12345678).');
                $r['formateado'] = ($m[1] ?: 'V') . '-' . number_format((int) $m[2], 0, ',', '.');
                break;

            case 'cedula_ec':
                if (!preg_match('/^\d{10}$/', $n)) return $mal('La cédula ecuatoriana tiene 10 dígitos.');
                $prov = (int) substr($n, 0, 2);
                if (!isset(self::PROVINCIAS_EC[$prov])) return $mal('Los dos primeros dígitos deben ser una provincia del Ecuador (01 a 24).');
                if ((int) $n[2] > 5) return $mal('El tercer dígito de una cédula debe ser menor que 6.');
                $suma = 0;
                for ($i = 0; $i < 9; $i++) { $x = (int) $n[$i] * ($i % 2 ? 1 : 2); $suma += $x > 9 ? $x - 9 : $x; }
                if ((10 - $suma % 10) % 10 !== (int) $n[9]) return $mal('La cédula no es correcta: el último dígito (verificador) no coincide.');
                $r['departamento'] = $prov === 30 ? null : self::PROVINCIAS_EC[$prov];
                $r['resumen'] = 'Provincia: ' . self::PROVINCIAS_EC[$prov];
                break;

            case 'dni_pe':
                if (!preg_match('/^\d{8}$/', $n)) return $mal('El DNI peruano tiene 8 dígitos.');
                break;

            case 'ce_pe':
            case 'ci_bo':
                if (!preg_match('/^[A-Z0-9]{5,12}$/', $n)) return $mal('Revise el número del documento.');
                break;

            case 'rut_cl':
                if (!preg_match('/^(\d{7,8})([\dK])$/', $n, $m)) return $mal('El RUT tiene 7 u 8 números y un dígito verificador (ej. 12.345.678-5).');
                $suma = 0; $f = 2;
                for ($i = strlen($m[1]) - 1; $i >= 0; $i--) { $suma += (int) $m[1][$i] * $f; $f = $f === 7 ? 2 : $f + 1; }
                $dv = 11 - $suma % 11; $dv = $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string) $dv);
                if ($dv !== $m[2]) return $mal('El RUT no es correcto: el dígito verificador debería ser ' . $dv . '.');
                $r['formateado'] = number_format((int) $m[1], 0, ',', '.') . '-' . $m[2];
                break;

            case 'dni_ar':
                if (!preg_match('/^\d{7,8}$/', $n)) return $mal('El DNI argentino tiene 7 u 8 dígitos.');
                $r['formateado'] = number_format((int) $n, 0, ',', '.');
                break;

            case 'cuil_ar':
                if (!preg_match('/^(20|23|24|27)\d{9}$/', $n)) return $mal('El CUIL tiene 11 dígitos y empieza con 20, 23, 24 o 27.');
                $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]; $suma = 0;
                for ($i = 0; $i < 10; $i++) { $suma += (int) $n[$i] * $pesos[$i]; }
                $dv = 11 - $suma % 11; $dv = $dv === 11 ? 0 : $dv;
                if ($dv === 10 || $dv !== (int) $n[10]) return $mal('El CUIL no es correcto: el último dígito (verificador) no coincide.');
                $r['formateado'] = substr($n, 0, 2) . '-' . substr($n, 2, 8) . '-' . $n[10];
                break;

            case 'ci_uy':
                if (!preg_match('/^\d{7,8}$/', $n)) return $mal('La cédula uruguaya tiene 7 u 8 dígitos (con el verificador).');
                $base = str_pad(substr($n, 0, -1), 7, '0', STR_PAD_LEFT); $pesos = [2, 9, 8, 7, 6, 3, 4]; $suma = 0;
                for ($i = 0; $i < 7; $i++) { $suma += (int) $base[$i] * $pesos[$i]; }
                if ((10 - $suma % 10) % 10 !== (int) substr($n, -1)) return $mal('La cédula no es correcta: el dígito verificador no coincide.');
                $r['formateado'] = number_format((int) substr($n, 0, -1), 0, ',', '.') . '-' . substr($n, -1);
                break;

            case 'ci_py':
                if (!preg_match('/^\d{5,8}$/', $n)) return $mal('La cédula paraguaya tiene de 5 a 8 dígitos.');
                $r['formateado'] = number_format((int) $n, 0, ',', '.');
                break;

            default:
                if (strlen($n) < 3) return $mal('El número es muy corto.');
        }
        return $r;
    }

    /** Texto para la pantalla: "✓ Formato válido · …". */
    public static function textoOk(array $r): string
    {
        return '✓ Número válido' . ($r['resumen'] ? ' · ' . $r['resumen'] : '');
    }
}
