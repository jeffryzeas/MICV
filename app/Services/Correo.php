<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envía correos con la cuenta configurada por el admin (Gmail u otro SMTP).
 * La configuración se arma "al vuelo" desde la base de datos, así no hay
 * que tocar el .env ni reiniciar nada para cambiar de correo.
 */
class Correo
{
    private const MAILER = 'micv_smtp';

    public static function configurado(): bool
    {
        return (bool) Configuracion::actualSegura()?->correoListo();
    }

    /** @throws RuntimeException si no hay correo configurado o Google lo rechaza. */
    /** @param array<int, array{0: string, 1: string, 2: string}> $adjuntos  [contenido, nombre, tipo] */
    public static function enviar(string $para, string $asunto, string $vista, array $datos = [], ?Configuracion $cfg = null, array $adjuntos = []): void
    {
        $cfg ??= Configuracion::actual();
        if (!$cfg->smtp_host || !$cfg->smtp_desde_correo) {
            throw new RuntimeException('El correo del sistema todavía no está configurado (Admin → Correo Gmail).');
        }

        $puerto = (int) ($cfg->smtp_puerto ?: 587);
        config([
            'mail.mailers.' . self::MAILER => [
                'transport'  => 'smtp',
                'scheme'     => ($cfg->smtp_encriptacion === 'ssl' || $puerto === 465) ? 'smtps' : 'smtp',
                'host'       => $cfg->smtp_host,
                'port'       => $puerto,
                'username'   => $cfg->smtp_usuario ?: $cfg->smtp_desde_correo,
                'password'   => $cfg->passwordSmtp(),
                'timeout'    => 20,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
            ],
        ]);
        Mail::purge(self::MAILER);

        $html = view($vista, $datos + [
            'marca'  => $cfg->nombre_negocio,
            'colorP' => $cfg->color_primario ?: '#0B3C74',
            'colorS' => $cfg->color_secundario ?: '#C9971F',
        ])->render();

        try {
            Mail::mailer(self::MAILER)->html($html, function ($m) use ($para, $asunto, $cfg, $adjuntos) {
                $m->to($para)->subject($asunto)
                  ->from($cfg->smtp_desde_correo, $cfg->smtp_desde_nombre ?: $cfg->nombre_negocio);
                foreach ($adjuntos as [$contenido, $nombre, $tipo]) {
                    $m->attachData($contenido, $nombre, ['mime' => $tipo]);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $msg = $e->getMessage();
            if (str_contains($msg, '535') || str_contains(strtolower($msg), 'username and password')) {
                $msg = 'Gmail rechazó el usuario o la contraseña. Use una "contraseña de aplicación" de 16 letras (no su contraseña normal).';
            }
            throw new RuntimeException('No se pudo enviar el correo: ' . $msg, 0, $e);
        }
    }
}
