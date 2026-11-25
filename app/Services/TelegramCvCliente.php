<?php

namespace App\Services;

use App\Models\TelegramCvCuenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Credenciales por stdin; nunca en argumentos, logs, archivos de sesión o excepciones del proceso. */
class TelegramCvCliente
{
    private function actualizar(TelegramCvCuenta $original, array $campos): void
    {
        DB::transaction(function () use ($original, $campos) {
            $a = TelegramCvCuenta::whereKey($original->id)->lockForUpdate()->first();
            if (! $a || $a->revision !== $original->revision) {
                throw new TelegramCvError('sesion');
            }$a->update($campos);
        });
    }

    public function operar(TelegramCvCuenta $cuenta, string $accion, array $datos = []): array
    {
        $dir = config('telegram_cv.directorio');
        File::ensureDirectoryExists($dir, 0700);
        $lock = fopen($dir.'/cuenta-'.$cuenta->id.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }throw new TelegramCvError('ocupada');
        }
        try {
            $actual = $cuenta->fresh();
            if (! $actual || $actual->revision !== $cuenta->revision) {
                throw new TelegramCvError('sesion');
            }
            if ($actual->no_antes_de?->isFuture()) {
                throw new TelegramCvError('limite', max(1, (int) now()->diffInSeconds($actual->no_antes_de)));
            }
            $entrada = ['v' => 1, 'accion' => $accion, 'api_id' => $actual->api_id, 'api_hash' => $actual->api_hash, 'sesion' => $actual->sesion, 'telefono' => $actual->telefono, 'codigo_hash' => $actual->codigo_hash, 'cuenta_id' => $actual->telegram_id] + $datos;
            $r = $this->proceso($entrada, $accion === 'enviar' ? 230 : 45);
            // Solo campos de estado conocidos; ninguna descripción de excepción del proveedor llega al log.
            if (! is_array($r) || ($r['v'] ?? null) !== 1) {
                throw new TelegramCvError;
            }
            if ($actual->fresh()?->revision !== $actual->revision) {
                throw new TelegramCvError('sesion');
            }
            if (($r['ok'] ?? false) !== true) {
                $codigo = in_array($r['error'] ?? '', ['credenciales', 'codigo', 'codigo_caducado', 'clave', 'sesion', 'cuenta', 'destino', 'privacidad', 'limite', 'archivo'], true) ? $r['error'] : 'servicio';
                $espera = max(0, min(86400, (int) ($r['espera'] ?? 0)));
                if ($codigo === 'limite') {
                    $this->actualizar($actual, ['no_antes_de' => now()->addSeconds(max(60, $espera))]);
                }
                if (in_array($codigo, ['sesion', 'cuenta'], true)) {
                    $this->actualizar($actual, ['estado' => 'desconectada', 'sesion' => null, 'codigo_hash' => null, 'conectada_en' => null]);
                }throw new TelegramCvError($codigo, $espera);
            }
            $cambios = [];
            if (isset($r['sesion']) && is_string($r['sesion']) && strlen($r['sesion']) <= 4096) {
                $cambios['sesion'] = $r['sesion'];
            }
            if ($accion === 'enviar') {
                $cambios['no_antes_de'] = now()->addSeconds(3);
            }
            if ($cambios) {
                $this->actualizar($actual, $cambios);
            }

            return $r;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function proceso(array $entrada, int $timeout): array
    {
        try {
            $p = new Process([config('telegram_cv.python'), '-u', resource_path('telegram-cv/cliente.py')], base_path(), ['PYTHONDONTWRITEBYTECODE' => '1']);
            $p->setTimeout($timeout);
            $p->setInput(json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $p->run();
            if (! $p->isSuccessful() || strlen($p->getOutput()) > 32768) {
                throw new \RuntimeException;
            }
            $r = json_decode($p->getOutput(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($r)) {
                throw new \RuntimeException;
            }

            return $r;
        } catch (\Throwable) {
            throw new TelegramCvError;
        }
    }
}
