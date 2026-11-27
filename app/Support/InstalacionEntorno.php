<?php

namespace App\Support;

use Dotenv\Dotenv;

class InstalacionEntorno
{
    public static function leer(string $raiz): array
    {
        return is_file($raiz.'/.env') ? Dotenv::parse(file_get_contents($raiz.'/.env')) : [];
    }

    public static function configurar(string $raiz, array $op): void
    {
        $nuevo = (bool) ($op['nuevo'] ?? false);
        $dominio = (string) ($op['dominio'] ?? '');
        foreach (['node', 'npm', 'chrome'] as $clave) {
            if (! preg_match('~^/[A-Za-z0-9_./+-]+$~D', (string) ($op[$clave] ?? ''))) {
                throw new \RuntimeException('Ruta de ejecutable inválida.');
            }
        }
        if ($dominio !== '' && ! preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D', $dominio)) {
            throw new \RuntimeException('Dominio inválido.');
        }
        if ($nuevo && $dominio === '') {
            throw new \RuntimeException('Para una instalación nueva indique --dominio.');
        }
        $ruta = $raiz.'/.env';
        if (! is_file($ruta)) {
            if (! is_file($raiz.'/.env.example')) {
                throw new \RuntimeException('Falta .env.example.');
            }copy($raiz.'/.env.example', $ruta);
            chmod($ruta, 0600);
        }
        $texto = file_get_contents($ruta);
        $actual = Dotenv::parse($texto);
        $cambios = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'QUEUE_CONNECTION' => 'database', 'MICV_NODE_BINARY' => $op['node'], 'MICV_NPM_BINARY' => $op['npm'], 'MICV_CHROME_PATH' => $op['chrome'], 'MICV_CHROME_NO_SANDBOX' => 'true', 'TIKTOK_PYTHON' => $raiz.'/storage/app/tiktok-venv/bin/python', 'TELEGRAM_CV_PYTHON' => $raiz.'/storage/app/telegram-cv-venv/bin/python'];
        if ($dominio !== '') {
            $cambios['APP_URL'] = (! empty($op['https']) || str_starts_with((string) ($actual['APP_URL'] ?? ''), 'https://') && ! $nuevo ? 'https' : 'http').'://'.$dominio;
        }
        if ($nuevo) {
            $db = $raiz.'/storage/app/private/database.sqlite';
            if (! is_dir(dirname($db))) {
                mkdir(dirname($db), 0700, true);
            }if (! is_file($db)) {
                touch($db);
            }chmod($db, 0600);
            $cambios += ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'database', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => ! empty($op['https']) ? 'true' : 'false'];
        }
        if (empty($actual['APP_TIMEZONE'])) {
            $cambios['APP_TIMEZONE'] = 'America/Costa_Rica';
        }
        if (! empty($op['https'])) {
            $cambios['SESSION_SECURE_COOKIE'] = 'true';
        }
        foreach ($cambios as $clave => $valor) {
            $valor = '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ''], (string) $valor).'"';
            $linea = $clave.'='.$valor;
            $patron = '/^'.preg_quote($clave, '/').'\s*=.*$/m';
            $texto = preg_match($patron, $texto) ? preg_replace_callback($patron, fn () => $linea, $texto) : rtrim($texto)."\n".$linea."\n";
        }
        if ($texto !== file_get_contents($ruta)) {
            $respaldo = $raiz.'/storage/app/private/instalacion';
            if (! is_dir($respaldo)) {
                mkdir($respaldo, 0700, true);
            }$copia = $respaldo.'/.env.'.date('YmdHis').'.'.bin2hex(random_bytes(3)).'.bak';
            copy($ruta, $copia);
            chmod($copia, 0600);
            $tmp = $ruta.'.micv-temporal';
            file_put_contents($tmp, $texto);
            chmod($tmp, 0600);
            rename($tmp, $ruta);
        }
    }
}
