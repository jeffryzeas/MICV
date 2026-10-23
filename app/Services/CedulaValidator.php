<?php

namespace App\Services;

use DateTime;

/**
 * Validador de la cédula de identidad nicaragüense.
 * Formato: MMM-DDMMAA-CCCCL (14 caracteres, sin guiones en BD).
 * Revisa formato, fecha, código catalogado y letra con módulo 23.
 * Es una comprobación local; no acredita identidad, emisión ni vigencia ante el CSE.
 */
class CedulaValidator
{
    private const ALFABETO = "ABCDEFGHJKLMNPQRSTUVWXY"; // 23 letras

    /**
     * Valida una cédula y devuelve un array con toda la info derivada.
     * NOTA: valida estructura/formato, no confirma vigencia en el padrón electoral.
     */
    public static function validar(string $cedulaCruda): array
    {
        $cedula = self::normalizar($cedulaCruda);

        $resultado = [
            "valida" => false,
            "cedula" => $cedula,
            "errores" => [],
            "municipio_codigo" => null,
            "departamento" => null,
            "municipio" => null,
            "fecha_nacimiento" => null,
            "edad" => null,
            "correlativo" => null,
            "letra_esperada" => null,
            "letra_ingresada" => null,
            "codigo_reconocido" => false,
            "codigo_activo" => false,
            "codigo_especial" => false,
            "alcance" =>
                "Comprobación local de formato, fecha, código y letra; no consulta el padrón del CSE.",
        ];

        if (!preg_match('/^(\d{3})(\d{6})(\d{4})([A-Za-z])$/', $cedula, $m)) {
            $resultado["errores"][] =
                "El formato debe tener 13 dígitos seguidos de 1 letra verificadora (14 caracteres).";
            return $resultado;
        }

        [, $codMunicipio, $fechaStr, $correlativo, $letraIngresada] = $m;
        $letraIngresada = strtoupper($letraIngresada);

        $resultado["municipio_codigo"] = $codMunicipio;
        $resultado["correlativo"] = $correlativo;
        $resultado["letra_ingresada"] = $letraIngresada;

        $ubicacion = \App\Support\MunicipiosNicaragua::buscar($codMunicipio);
        $resultado["codigo_reconocido"] = $ubicacion !== null;
        $resultado["codigo_activo"] = (bool) ($ubicacion["activo"] ?? false);
        $resultado["codigo_especial"] =
            (bool) ($ubicacion["especial"] ?? false);
        if (!$ubicacion) {
            $resultado[
                "errores"
            ][] = "El código {$codMunicipio} no está en el catálogo de cédulas. Revise los tres primeros dígitos o solicite revisar el catálogo de municipios.";
        } elseif (!$ubicacion["activo"]) {
            $resultado[
                "errores"
            ][] = "El código {$codMunicipio} está desactivado en el catálogo. Solicite revisar su estado antes de continuar.";
        } elseif (!$resultado["codigo_especial"]) {
            $resultado["departamento"] = $ubicacion["departamento"];
            $resultado["municipio"] = $ubicacion["municipio"];
        }

        $fecha = self::resolverFecha($fechaStr);
        if ($fecha === null) {
            $resultado[
                "errores"
            ][] = "La fecha de nacimiento codificada ({$fechaStr}) no es válida.";
        } else {
            $resultado["fecha_nacimiento"] = $fecha["fecha_iso"];
            $resultado["edad"] = $fecha["edad"];
        }

        $letraEsperada = self::calcularLetraVerificadora(
            $codMunicipio . $fechaStr . $correlativo,
        );
        $resultado["letra_esperada"] = $letraEsperada;
        if ($letraEsperada !== $letraIngresada) {
            $resultado[
                "errores"
            ][] = "La letra verificadora no coincide. Se esperaba '{$letraEsperada}' y se recibió '{$letraIngresada}'.";
        }

        $resultado["valida"] = empty($resultado["errores"]);
        return $resultado;
    }

    public static function normalizar(string $cedula): string
    {
        return preg_replace(
            "/[\s\-\x{2010}-\x{2015}]+/u",
            "",
            strtoupper(trim($cedula)),
        ) ?? "";
    }

    private static function calcularLetraVerificadora(
        string $trecediDigitos,
    ): string {
        $residuo = 0;
        foreach (str_split($trecediDigitos) as $digito) {
            $residuo = ($residuo * 10 + (int) $digito) % 23;
        }
        return self::ALFABETO[$residuo];
    }

    private static function resolverFecha(string $ddmmaa): ?array
    {
        $dia = (int) substr($ddmmaa, 0, 2);
        $mes = (int) substr($ddmmaa, 2, 2);
        $aa = (int) substr($ddmmaa, 4, 2);

        $anioActualCorto = (int) date("y");
        $siglo = $aa > $anioActualCorto ? 1900 : 2000;
        $anioCompleto = $siglo + $aa;

        if (!checkdate($mes, $dia, $anioCompleto)) {
            return null;
        }

        $fechaNac = DateTime::createFromFormat(
            "!Y-m-d",
            sprintf("%04d-%02d-%02d", $anioCompleto, $mes, $dia),
        );
        $hoy = new DateTime("today");
        if ($fechaNac > $hoy) {
            return null;
        }

        return [
            "fecha_iso" => $fechaNac->format("Y-m-d"),
            "edad" => $hoy->diff($fechaNac)->y,
        ];
    }
}
