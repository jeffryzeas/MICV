<?php

namespace App\Services;

use App\Models\TelegramCvAcceso as Acceso;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class TelegramCvQr
{
    protected function proceso(InputStream $input): Process
    {
        return (new Process([config('telegram_cv.python'), '-u', resource_path('telegram-cv/qr.py')], base_path(), ['PYTHONDONTWRITEBYTECODE' => '1']))->setInput($input);
    }

    public function recibir(string $id, array $e): bool
    {
        if (($e['v'] ?? null) !== 1) {
            return false;
        }
        $ref = Acceso::find($id);
        if (! $ref) {
            return false;
        }

        return DB::transaction(function () use ($ref, $e) {
            $c = Cuenta::whereKey($ref->telegram_cuenta_id)->lockForUpdate()->first();
            $a = Acceso::whereKey($ref->id)->lockForUpdate()->first();
            $u = $a?->user_id ? User::find($a->user_id) : null;
            if (! $a?->activo() || ! $c?->sistema || $a->revision !== $c->revision || ! $u?->activo || ! $u->esAdmin()) {
                return false;
            }
            $tipo = $e['tipo'] ?? '';
            if ($tipo === 'qr') {
                $url = $e['url'] ?? '';
                $ms = $e['expira_ms'] ?? 0;
                if (! is_string($url) || ! preg_match('~^tg://login\?token=[a-zA-Z0-9_-]{16,512}$~D', $url) || ! is_numeric($ms)) {
                    return false;
                }
                $vence = Carbon::createFromTimestampMs((int) $ms)->setTimezone(config('app.timezone'));
                if ($vence->isPast() || $vence->gt(now()->addMinutes(3))) {
                    return false;
                }
                $a->update(['estado' => 'esperando', 'qr_url' => $url, 'qr_expira_en' => $vence->min($a->expira_en), 'latido_en' => now(), 'detalle' => null]);

                return true;
            }
            if (in_array($tipo, ['clave', 'conectada'], true)) {
                $sesion = $e['sesion'] ?? '';
                if (! is_string($sesion) || strlen($sesion) < 16 || strlen($sesion) > 4096) {
                    return false;
                }
                $datos = ['sesion' => $sesion, 'codigo_en' => now(), 'codigo_hash' => 'qr', 'estado' => 'clave'];
                if ($tipo === 'conectada') {
                    if (! preg_match('/^[1-9][0-9]{0,23}$/D', (string) ($e['id'] ?? '')) || ! preg_match('/^\+[1-9][0-9]{7,14}$/D', (string) ($e['telefono'] ?? ''))) {
                        return false;
                    }
                    $datos = ['sesion' => $sesion, 'estado' => 'conectada', 'telegram_id' => (string) $e['id'], 'telefono' => $e['telefono'], 'nombre' => mb_substr((string) ($e['nombre'] ?? ''), 0, 200), 'usuario' => mb_substr((string) ($e['usuario'] ?? ''), 0, 64), 'codigo_hash' => null, 'codigo_en' => null, 'conectada_en' => now(), 'ultimo_error' => null];
                }
                $c->update($datos);
                $a->update(['estado' => $tipo, 'qr_url' => null, 'qr_expira_en' => null, 'latido_en' => now(), 'detalle' => null]);

                return true;
            }

            return false;
        });
    }

    public function fallar(string $id, string $codigo = 'servicio', int $espera = 0, string $diagnostico = ''): void
    {
        $ref = Acceso::find($id);
        if (! $ref) {
            return;
        }
        $codigo = in_array($codigo, ['credenciales', 'sesion', 'cuenta', 'limite', 'conexion', 'telegram_temporal', 'qr_cancelado', 'qr_caducado', 'python', 'php_proceso'], true) ? $codigo : 'servicio';
        $msg = (new TelegramCvError($codigo, $espera, $diagnostico))->getMessage();
        DB::transaction(function () use ($ref, $codigo, $espera, $msg) {
            $c = Cuenta::whereKey($ref->telegram_cuenta_id)->lockForUpdate()->first();
            $a = Acceso::whereKey($ref->id)->lockForUpdate()->first();
            if (! $a || ! in_array($a->estado, ['en_cola', 'generando', 'esperando'], true)) {
                return;
            }
            $a->update(['estado' => $codigo === 'qr_caducado' ? 'caducada' : ($codigo === 'qr_cancelado' ? 'cancelada' : 'error'), 'qr_url' => null, 'qr_expira_en' => null, 'detalle' => $msg]);
            if ($c && $a->revision === $c->revision) {
                $datos = ['estado' => 'desconectada', 'ultimo_error' => $msg];
                if ($codigo === 'limite') {
                    $datos['no_antes_de'] = now()->addSeconds(max(60, min(86400, $espera)));
                }$c->update($datos);
            }
        });
    }

    public function ejecutar(string $id): void
    {
        $a = Acceso::find($id);
        if (! $a || $a->estado !== 'en_cola') {
            return;
        }if (! $a->activo()) {
            $this->fallar($id, 'qr_caducado');

            return;
        }
        $c = $a->cuenta;
        if (! $c || $c->revision !== $a->revision) {
            $this->fallar($id, 'qr_cancelado');

            return;
        }
        if (Acceso::whereKey($id)->where('estado', 'en_cola')->update(['estado' => 'generando', 'iniciado_en' => now(), 'latido_en' => now()]) !== 1) {
            return;
        }
        $dir = config('telegram_cv.directorio');
        File::ensureDirectoryExists($dir, 0700);
        $lock = fopen($dir.'/cuenta-'.$c->id.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }$this->fallar($id, 'servicio');

            return;
        }
        $p = null;
        $input = null;
        try {
            if (! function_exists('proc_open')) {
                throw new TelegramCvError('php_proceso', 0, 'PHP_PROC_OPEN');
            }
            if (! is_executable(config('telegram_cv.python'))) {
                throw new TelegramCvError('python', 0, 'PYTHON_NO_DISPONIBLE');
            }
            $input = new InputStream;
            $input->write(json_encode(['v' => 1, 'api_id' => $c->api_id, 'api_hash' => $c->api_hash, 'expira_ms' => $a->expira_en->getTimestampMs()], JSON_THROW_ON_ERROR)."\n");
            $p = $this->proceso($input);
            $p->setTimeout(330);
            $p->start();
            $buffer = '';
            $fin = false;
            $ultimo = microtime(true);
            while (true) {
                $vivo = $p->isRunning();
                $buffer .= $p->getIncrementalOutput();
                $p->clearOutput();
                $p->clearErrorOutput();
                if (strlen($buffer) > 32768) {
                    throw new TelegramCvError('servicio', 0, 'RESPUESTA_INVALIDA');
                }
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $e = json_decode($line, true);
                    if (! is_array($e) || ($e['v'] ?? null) !== 1) {
                        throw new TelegramCvError('servicio', 0, 'RESPUESTA_INVALIDA');
                    }
                    if (($e['tipo'] ?? '') === 'error') {
                        $this->fallar($id, (string) ($e['error'] ?? 'servicio'), (int) ($e['espera'] ?? 0), (string) ($e['diagnostico'] ?? ''));
                        $fin = true;
                    } elseif ($this->recibir($id, $e)) {
                        if (in_array($e['tipo'], ['conectada', 'clave'], true)) {
                            $input->write("{\"accion\":\"aceptar\"}\n");
                            $fin = true;
                        }
                    } else {
                        $input->write("{\"accion\":\"cancelar\"}\n");
                        $fin = true;
                    }
                }
                $actual = Acceso::find($id);
                if (! $fin && (! $actual?->activo() || $c->fresh()?->revision !== $a->revision)) {
                    $input->write("{\"accion\":\"cancelar\"}\n");
                    $this->fallar($id, $actual?->expira_en?->isPast() ? 'qr_caducado' : 'qr_cancelado');
                    $fin = true;
                }
                if (microtime(true) - $ultimo >= 5) {
                    Acceso::whereKey($id)->whereIn('estado', ['generando', 'esperando'])->update(['latido_en' => now()]);
                    $ultimo = microtime(true);
                }
                if ($fin) {
                    $input->close();
                    $p->wait();
                    break;
                }
                if (! $vivo) {
                    break;
                }
                $p->checkTimeout();
                usleep(200000);
            }
            $this->fallar($id, 'servicio', 0, 'PROCESO_SALIDA');
        } catch (TelegramCvError $e) {
            $this->fallar($id, $e->motivo, $e->espera, $e->diagnostico);
        } catch (\Throwable) {
            $this->fallar($id, 'servicio', 0, 'PROCESO_SALIDA');
        } finally {
            if ($p?->isRunning()) {
                $input?->write("{\"accion\":\"cancelar\"}\n");
                $input?->close();
                $p->stop(8);
            }flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
