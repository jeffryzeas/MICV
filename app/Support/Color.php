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
}
