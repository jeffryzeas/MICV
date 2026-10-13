<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Trampa para robots en formularios públicos (registro, recuperar contraseña,
 * eliminar cuenta): un campo invisible que las personas no llenan y un
 * mínimo de tiempo entre que se abre la página y se envía.
 * Funciona aunque reCAPTCHA no esté configurado.
 */
class AntiBot
{
    public const CAMPO = 'sitio_web_empresa';
    public const SEGUNDOS_MINIMOS = 1;

    public static function marca(): string
    {
        return Crypt::encryptString((string) time());
    }

    /** true = parece un robot. */
    public static function esRobot(Request $request): bool
    {
        if (filled($request->input(self::CAMPO))) {
            return true;
        }
        try {
            $t = (int) Crypt::decryptString((string) $request->input('_t'));
        } catch (\Throwable $e) {
            return true;
        }
        $pasaron = time() - $t;
        return $pasaron < self::SEGUNDOS_MINIMOS || $pasaron > 6 * 3600 + 60;
    }
}
