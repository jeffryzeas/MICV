<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3 (invisible: solo se ve la insignia en la esquina).
 * - Sin llaves o apagado: no verifica nada.
 * - Si Google no responde: deja pasar y lo anota en "Errores del sistema"
 *   (es peor dejar afuera a la gente real por una caída de Google).
 */
class Recaptcha
{
    public static function activo(): bool
    {
        return (bool) Configuracion::actualSegura()?->recaptchaListo();
    }

    public static function siteKey(): ?string
    {
        $c = Configuracion::actualSegura();
        return $c && $c->recaptchaListo() ? $c->recaptcha_site_key : null;
    }

    /** @return string|null null = pasó; texto = motivo del rechazo */
    public static function verificar(?string $token, string $accion): ?string
    {
        $cfg = Configuracion::actualSegura();
        if (!$cfg || !$cfg->recaptchaListo()) {
            return null;
        }
        if (blank($token)) {
            return 'No se recibió la verificación de seguridad (reCAPTCHA). Recargue la página e intente de nuevo.';
        }

        try {
            $d = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $cfg->secretoRecaptcha(), 'response' => $token, 'remoteip' => request()->ip(),
            ])->json();
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA: no se pudo contactar a Google; se permitió el acceso.', ['error' => $e->getMessage()]);
            return null;
        }

        if (!is_array($d)) {
            return null;
        }
        if (empty($d['success'])) {
            $codigos = $d['error-codes'] ?? [];
            if (array_intersect(['invalid-input-secret', 'missing-input-secret', 'bad-request'], $codigos)) {
                Log::error('reCAPTCHA mal configurado (revise las llaves).', ['error-codes' => $codigos]);
                return null;
            }
            return 'No se pudo comprobar que usted es una persona. Intente de nuevo.';
        }
        if (($d['action'] ?? $accion) !== $accion) {
            return 'La verificación de seguridad no corresponde a esta pantalla.';
        }
        $score = (float) ($d['score'] ?? 1);
        if ($score < (float) ($cfg->recaptcha_umbral ?? 0.5)) {
            Log::info('reCAPTCHA rechazó un intento por puntaje bajo.', ['score' => $score, 'accion' => $accion, 'ip' => request()->ip()]);
            return 'Su navegador se comportó de forma sospechosa. Intente de nuevo o use otro navegador.';
        }
        return null;
    }
}
