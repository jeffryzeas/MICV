<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Quita el fondo de una foto de persona (modelo U²-Net corrido en Node). */
class QuitarFondo
{
    public static function modelo(): string
    {
        return (string) config('micv.fondo.modelo');
    }

    public static function disponible(): bool
    {
        return is_file(self::modelo()) && filesize(self::modelo()) > 1_000_000;
    }

    /**
     * @param string $bytes imagen original (jpg/png/webp)
     * @param string $color "#rrggbb" o "transparente"
     * @return string PNG
     */
    public static function procesar(string $bytes, string $color = '#ffffff'): string
    {
        if (!self::disponible()) {
            throw new \RuntimeException('El quitador de fondo no está instalado. El administrador debe correr: php artisan micv:modelo-fondo');
        }
        if ($color !== 'transparente' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#ffffff';
        }
        $dir = storage_path('app/tmp-fotos');
        @mkdir($dir, 0775, true);
        self::limpiarViejos($dir);
        $id = Str::random(24);
        $entrada = "$dir/$id.in";
        $salida = "$dir/$id.png";
        file_put_contents($entrada, $bytes);
        try {
            Nodo::correr(resource_path('fotos/quitar-fondo.cjs'), [$entrada, $salida, self::modelo(), $color], (int) config('micv.fondo.timeout', 120));
            return (string) file_get_contents($salida);
        } finally {
            @unlink($entrada);
            @unlink($salida);
        }
    }

    /** Por si un proceso se cortó: borra temporales de más de una hora. */
    protected static function limpiarViejos(string $dir): void
    {
        foreach (glob("$dir/*") ?: [] as $f) {
            if (@filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
    }
}
