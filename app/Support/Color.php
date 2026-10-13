<?php

namespace App\Support;

class Color
{
    /** "#0B3C74" → "11 60 116" (formato que usan las variables del tema). */
    public static function rgb(?string $hex, string $defecto = '#0B3C74'): string
    {
        $hex = ltrim((string) ($hex ?: $defecto), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = ltrim($defecto, '#');
        }
        return hexdec(substr($hex, 0, 2)) . ' ' . hexdec(substr($hex, 2, 2)) . ' ' . hexdec(substr($hex, 4, 2));
    }

    /** Oscurece un color (para el tono 700 del acento). */
    public static function oscuro(?string $hex, float $f = 0.84): string
    {
        [$r, $g, $b] = array_map('intval', explode(' ', self::rgb($hex, '#C9971F')));
        return (int) ($r * $f) . ' ' . (int) ($g * $f) . ' ' . (int) ($b * $f);
    }

    /** Luminancia relativa (0 = negro, 1 = blanco). */
    public static function luminancia(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($v) => (int) $v / 255, explode(' ', self::rgb($hex, '#000000')));
        $f = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }

    /** Texto legible sobre un fondo: blanco o casi negro. */
    public static function textoSobre(string $hex): string
    {
        return self::luminancia($hex) > 0.45 ? '#111827' : '#ffffff';
    }

    /** Oscurece un color hexadecimal y lo devuelve en hexadecimal. */
    public static function oscurecerHex(string $hex, float $f = 0.7): string
    {
        [$r, $g, $b] = array_map('intval', explode(' ', self::rgb($hex, '#000000')));
        return sprintf('#%02x%02x%02x', (int) ($r * $f), (int) ($g * $f), (int) ($b * $f));
    }
}
