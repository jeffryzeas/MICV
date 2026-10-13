<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Código de 6 dígitos por correo: vence en 15 minutos y admite 5 intentos. */
class Verificacion
{
    public const MINUTOS = 15;
    public const INTENTOS = 5;
    public const ESPERA_REENVIO = 60; // segundos

    /**
     * Genera y envía un código nuevo.
     * @return array{enviado: bool, error: ?string, codigo_debug: ?string}
     */
    public static function enviar(User $u, string $tipo = 'verificacion'): array
    {
        $codigo = (string) random_int(100000, 999999);

        CodigoVerificacion::where('user_id', $u->id)->where('tipo', $tipo)->whereNull('usado_en')->delete();
        CodigoVerificacion::create([
            'user_id'   => $u->id,
            'tipo'      => $tipo,
            'codigo'    => Hash::make($codigo),
            'expira_en' => now()->addMinutes(self::MINUTOS),
        ]);

        $asunto = $tipo === 'recuperar' ? "Código para cambiar su contraseña: $codigo" : "Su código de verificación: $codigo";
        try {
            Correo::enviar($u->email, $asunto, 'correos.codigo', [
                'nombre' => $u->name, 'codigo' => $codigo, 'minutos' => self::MINUTOS, 'tipo' => $tipo,
            ]);
            return ['enviado' => true, 'error' => null, 'codigo_debug' => null];
        } catch (\Throwable $e) {
            // En modo local (APP_DEBUG=true) se muestra el código para poder probar sin correo.
            return ['enviado' => false, 'error' => $e->getMessage(), 'codigo_debug' => config('app.debug') ? $codigo : null];
        }
    }

    public static function puedeReenviar(User $u, string $tipo = 'verificacion'): int
    {
        $ultimo = CodigoVerificacion::where('user_id', $u->id)->where('tipo', $tipo)->latest('id')->first();
        if (!$ultimo) {
            return 0;
        }
        $falta = self::ESPERA_REENVIO - $ultimo->created_at->diffInSeconds(now());
        return max(0, (int) ceil($falta));
    }

    /** @return string|null  null = correcto; texto = el error */
    public static function comprobar(User $u, string $codigo, string $tipo = 'verificacion'): ?string
    {
        $registro = CodigoVerificacion::where('user_id', $u->id)->where('tipo', $tipo)->whereNull('usado_en')->latest('id')->first();
        if (!$registro) {
            return 'No hay un código vigente. Pida uno nuevo.';
        }
        if ($registro->expira_en->isPast()) {
            return 'El código venció. Pida uno nuevo.';
        }
        if ($registro->intentos >= self::INTENTOS) {
            return 'Demasiados intentos con este código. Pida uno nuevo.';
        }
        if (!Hash::check(preg_replace('/\D/', '', $codigo), $registro->codigo)) {
            $registro->increment('intentos');
            $quedan = self::INTENTOS - $registro->intentos;
            return "Código incorrecto. Le quedan $quedan intento(s).";
        }

        $registro->update(['usado_en' => now()]);
        $u->forceFill(['email_verified_at' => now()])->save();
        return null;
    }
}
