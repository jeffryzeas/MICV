<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Datos declarados para el CV; no determina el derecho legal a trabajar. */
class SituacionLaboral
{
    public static function normalizar(?string $texto): string
    {
        return Str::lower(Str::ascii(trim(preg_replace('/\s+/u', ' ', $texto ?? ''))));
    }

    public static function codigoNacionalidad(?string $texto): ?string
    {
        $texto = self::normalizar($texto);
        if ($texto === '') {
            return null;
        }
        foreach (Nacionalidades::lista() as $codigo => $datos) {
            foreach ($datos as $valor) {
                if ($texto === self::normalizar($valor)) {
                    return $codigo;
                }
            }
        }
        return null;
    }

    public static function esExtranjero(string $residencia, ?string $nacionalidad): bool
    {
        return $residencia !== '' && $residencia !== 'OT' && filled($nacionalidad)
            && self::codigoNacionalidad($nacionalidad) !== $residencia;
    }

    /** La nacionalidad siempre se declara; no se deduce del documento o residencia. */
    public static function nacionalidadDeclarada(?string $texto, ?string $sexo = null): string
    {
        $nacionalidad = trim(preg_replace('/\s+/u', ' ', $texto ?? ''));
        if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .’'\-]{2,59}$/u", $nacionalidad)) {
            throw ValidationException::withMessages(['nacionalidad' => 'Indique su nacionalidad real. No se obtiene del país de su documento ni de donde reside.']);
        }
        $codigo = self::codigoNacionalidad($nacionalidad);
        return $codigo ? Nacionalidades::de($codigo, $sexo) : $nacionalidad;
    }

    public static function validar(array $datos): array
    {
        $residencia = $datos['pais_residencia'] ?? null;
        if (!$residencia || $residencia === 'OT' || !array_key_exists($residencia, Pais::todos())) {
            throw ValidationException::withMessages(['pais_residencia' => 'Seleccione el país donde reside actualmente.']);
        }
        $datos['nacionalidad'] = self::nacionalidadDeclarada($datos['nacionalidad'] ?? null, $datos['sexo'] ?? null);
        $datos['pais_residencia'] = $residencia;
        if (self::esExtranjero($residencia, $datos['nacionalidad'])) {
            $permiso = trim($datos['autorizacion_laboral'] ?? '');
            if (mb_strlen($permiso) < 3 || !preg_match('/\p{L}/u', $permiso)) {
                throw ValidationException::withMessages(['autorizacion_laboral' => 'Indique su situación laboral en el país de residencia: permiso vigente, en trámite, sin autorización u otra situación.']);
            }
            $datos['autorizacion_laboral'] = $permiso;
        } else {
            // Un cambio de nacionalidad/residencia no debe dejar un permiso anterior en el CV.
            $datos['autorizacion_laboral'] = null;
        }
        return $datos;
    }
}
