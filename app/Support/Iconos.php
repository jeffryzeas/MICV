<?php

namespace App\Support;

/** Iconos SVG pequeños para los CV (sin depender de internet). */
class Iconos
{
    private const P = [
        'tel'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'casa'  => '<path d="M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'id'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 13h3"/>',
        'cal'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'flag'  => '<path d="M5 21V4h11l-1.5 4L16 12H5"/>',
    ];

    public static function svg(string $k, string $extra = ''): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" ' . $extra . '>' . (self::P[$k] ?? '') . '</svg>';
    }
}
