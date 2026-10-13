<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guardado de imágenes (fotos, logos) en storage/app/private.
 * No depende de "php artisan storage:link": todo se sirve por rutas
 * con sesión o se incrusta como base64 en el PDF.
 */
class Archivos
{
    public const DISCO = 'local';

    /**
     * Guarda una imagen redimensionada (máx. $max px) como JPG o PNG.
     * Si GD no está disponible, la guarda tal cual.
     */
    public static function guardarImagen(UploadedFile $archivo, string $carpeta, int $max = 900, bool $conTransparencia = false): string
    {
        $contenido = file_get_contents($archivo->getRealPath());
        $ext = strtolower($archivo->getClientOriginalExtension() ?: 'jpg');

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($contenido);
            if ($img !== false) {
                $img = self::corregirOrientacion($img, $archivo->getRealPath());
                $w = imagesx($img);
                $h = imagesy($img);
                $escala = min(1, $max / max($w, $h));
                $nw = max(1, (int) round($w * $escala));
                $nh = max(1, (int) round($h * $escala));

                $lienzo = imagecreatetruecolor($nw, $nh);
                if ($conTransparencia) {
                    imagealphablending($lienzo, false);
                    imagesavealpha($lienzo, true);
                    imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
                } else {
                    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
                }
                imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

                ob_start();
                if ($conTransparencia) {
                    imagepng($lienzo, null, 7);
                    $ext = 'png';
                } else {
                    imagejpeg($lienzo, null, 88);
                    $ext = 'jpg';
                }
                $contenido = ob_get_clean();
                imagedestroy($img);
                imagedestroy($lienzo);
            }
        }

        $ruta = trim($carpeta, '/') . '/' . Str::random(32) . '.' . $ext;
        Storage::disk(self::DISCO)->put($ruta, $contenido);

        return $ruta;
    }

    /** Guarda bytes ya procesados (p. ej. una foto recortada en el navegador). */
    public static function guardarBinario(string $contenido, string $carpeta, string $ext = 'jpg'): string
    {
        $ruta = trim($carpeta, '/') . '/' . Str::random(32) . '.' . $ext;
        Storage::disk(self::DISCO)->put($ruta, $contenido);
        return $ruta;
    }

    /** data:image/...;base64,... → [bytes, extensión] o null si no es una imagen válida. */
    public static function desdeDataUri(?string $uri, int $maxBytes = 12_000_000): ?array
    {
        if (!$uri || !preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#s', $uri, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) > $maxBytes || !@getimagesizefromstring($bin)) {
            return null;
        }
        return [$bin, $m[1] === 'jpeg' ? 'jpg' : $m[1]];
    }

    public static function existe(?string $ruta): bool
    {
        try {
            return filled($ruta) && Storage::disk(self::DISCO)->exists($ruta);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function borrar(?string $ruta): void
    {
        if (self::existe($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    /** Imagen guardada → data URI (para el PDF y pantallas públicas). */
    public static function dataUri(?string $ruta): ?string
    {
        if (!self::existe($ruta)) {
            return null;
        }
        $bin = Storage::disk(self::DISCO)->get($ruta);
        return 'data:' . self::mime($ruta, $bin) . ';base64,' . base64_encode($bin);
    }

    /** Archivo del proyecto (resources/...) → data URI. */
    public static function dataUriLocal(string $rutaAbsoluta): ?string
    {
        if (!is_file($rutaAbsoluta)) {
            return null;
        }
        $bin = file_get_contents($rutaAbsoluta);
        return 'data:' . self::mime($rutaAbsoluta, $bin) . ';base64,' . base64_encode($bin);
    }

    public static function mime(string $ruta, string $bin): string
    {
        return match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ttf'   => 'font/ttf',
            'woff2' => 'font/woff2',
            'jpg', 'jpeg' => 'image/jpeg',
            default => (new \finfo(FILEINFO_MIME_TYPE))->buffer($bin) ?: 'application/octet-stream',
        };
    }

    /** Las fotos de celular vienen giradas según EXIF: se enderezan. */
    protected static function corregirOrientacion(\GdImage $img, string $ruta): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($ruta);
        $o = (int) ($exif['Orientation'] ?? 1);
        $grados = match ($o) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($grados !== 0) {
            $rot = imagerotate($img, $grados, 0);
            if ($rot !== false) {
                imagedestroy($img);
                return $rot;
            }
        }
        return $img;
    }
}
