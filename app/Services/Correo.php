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
            // HTML + versión de solo texto: los filtros de spam desconfían de los correos solo HTML.
            $texto = self::aTexto($html);
            Mail::mailer(self::MAILER)->send([], [], function ($m) use ($para, $asunto, $cfg, $adjuntos, $html, $texto) {
                $m->to($para)->subject($asunto)
                  ->from($cfg->smtp_desde_correo, $cfg->smtp_desde_nombre ?: $cfg->nombre_negocio)
                  ->html($html)->text($texto);
                if ($cfg->marca_correo && strcasecmp($cfg->marca_correo, $cfg->smtp_desde_correo) !== 0) {
                    $m->replyTo($cfg->marca_correo, $cfg->nombre_negocio);
                }
                $h = $m->getSymfonyMessage()->getHeaders();
                $h->addTextHeader('X-Entity-Ref-ID', bin2hex(random_bytes(8))); // Gmail no agrupa los códigos en un hilo
                $h->addTextHeader('Auto-Submitted', 'auto-generated');
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

    /** Convierte el HTML del correo en texto limpio (con los enlaces escritos). */
    public static function aTexto(string $html): string
    {
        $html = preg_replace('#<(style|title|head)[^>]*>.*?</\1>#si', '', $html);
        $html = preg_replace('#<div style="display:none[^"]*"[^>]*>.*?</div>#si', '', $html);
        $html = preg_replace_callback('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#si', fn ($m) => trim(strip_tags($m[2])) . ' (' . $m[1] . ')', $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|h[1-6]|li|table)>#i', "\n", $html);
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t\x{200B}\x{00A0}]+/u", ' ', $t);
        $t = preg_replace("/ *\n */", "\n", $t);
        return trim(preg_replace("/\n{3,}/", "\n\n", $t));
    }
}
