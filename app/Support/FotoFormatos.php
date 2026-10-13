<?php

namespace App\Support;

/** Tamaños de foto carné y de papel, y cómo se acomodan en la hoja. */
class FotoFormatos
{
    /** clave => [nombre, ancho mm, alto mm, nota] */
    public const FORMATOS = [
        'carne'     => ['Carné 2,5 × 3 cm', 25, 30, 'Tamaño "infantil" o carné.'],
        't3x4'      => ['3 × 4 cm', 30, 40, ''],
        'pasaporte' => ['Pasaporte 3,5 × 4,5 cm', 35, 45, 'Medida usada en muchos pasaportes y visas.'],
        't4x5'      => ['4 × 5 cm', 40, 50, ''],
        'eeuu'      => ['EE. UU. 2 × 2 pulgadas (5,1 cm)', 51, 51, 'Visa y pasaporte de Estados Unidos.'],
        'propio'    => ['Otra medida', 30, 40, ''],
    ];

    /** clave => [nombre, ancho mm, alto mm, margen mm] */
    public const PAPELES = [
        'carta' => ['Hoja carta', 215.9, 279.4, 6],
        'a4'    => ['Hoja A4', 210, 297, 6],
        'foto'  => ['Papel fotográfico 10 × 15 cm (4 × 6")', 101.6, 152.4, 3],
    ];

    /** Fondos ofrecidos (el admin o el cyber pueden escoger otro color). */
    public const FONDOS = [
        '#ffffff' => 'Blanco',
        '#dbe9f7' => 'Celeste',
        '#e5e7eb' => 'Gris claro',
        'original' => 'Dejar el fondo original',
    ];

    public const SEPARACION = 3; // mm entre fotos, para cortar con tijera o guillotina

    /** @return array{0: float, 1: float} ancho y alto en mm */
    public static function medida(string $formato, ?float $ancho = null, ?float $alto = null): array
    {
        if ($formato === 'propio') {
            return [max(15, min(100, (float) $ancho ?: 30)), max(15, min(120, (float) $alto ?: 40))];
        }
        $f = self::FORMATOS[$formato] ?? self::FORMATOS['carne'];
        return [(float) $f[1], (float) $f[2]];
    }

    /**
     * Posiciones (en mm) de cada foto en la hoja, centradas.
     * @return array{columnas: int, filas: int, maximo: int, posiciones: array<int, array{0: float, 1: float}>}
     */
    public static function acomodo(float $w, float $h, string $papel): array
    {
        [, $pw, $ph, $m] = self::PAPELES[$papel] ?? self::PAPELES['carta'];
        $g = self::SEPARACION;
        $cols = max(0, (int) floor(($pw - 2 * $m + $g) / ($w + $g)));
        $filas = max(0, (int) floor(($ph - 2 * $m + $g) / ($h + $g)));
        $ancho = $cols * $w + max(0, $cols - 1) * $g;
        $alto = $filas * $h + max(0, $filas - 1) * $g;
        $x0 = ($pw - $ancho) / 2;
        $y0 = ($ph - $alto) / 2;
        $pos = [];
        for ($f = 0; $f < $filas; $f++) {
            for ($c = 0; $c < $cols; $c++) {
                $pos[] = [round($x0 + $c * ($w + $g), 2), round($y0 + $f * ($h + $g), 2)];
            }
        }
        return ['columnas' => $cols, 'filas' => $filas, 'maximo' => count($pos), 'posiciones' => $pos];
    }

    /** Para el JavaScript de la pantalla. */
    public static function paraJs(): array
    {
        $out = ['formatos' => [], 'papeles' => []];
        foreach (self::FORMATOS as $k => [$n, $w, $h, $nota]) {
            $out['formatos'][$k] = ['nombre' => $n, 'w' => $w, 'h' => $h, 'nota' => $nota];
        }
        foreach (self::PAPELES as $k => [$n, $w, $h, $m]) {
            $out['papeles'][$k] = ['nombre' => $n, 'w' => $w, 'h' => $h, 'm' => $m];
        }
        $out['separacion'] = self::SEPARACION;
        return $out;
    }
}
