<?php

namespace App\Jobs;

use App\Models\TelegramCvCuenta;
use App\Models\TelegramCvEnvio;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EnviarTelegramCv implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 250;

    public function __construct(public string $id)
    {
        $this->onConnection('telegram-cv')->onQueue('telegram-cv');
    }

    public function handle(TelegramCvCliente $cliente): void
    {
        $pre = TelegramCvEnvio::find($this->id)?->cuenta;
        if ($pre?->no_antes_de?->isFuture()) {
            $seg = now()->diffInSeconds($pre->no_antes_de);
            if ($seg <= 3) {
                usleep((int) (($seg + 0.1) * 1000000));
            }
        }
        $e = DB::transaction(function () {
            $e = TelegramCvEnvio::whereKey($this->id)->lockForUpdate()->first();
            if (! $e || $e->estado !== 'en_cola') {
                return null;
            }$c = TelegramCvCuenta::whereKey($e->telegram_cuenta_id)->lockForUpdate()->first();
            if (! $c?->lista() || ! $c->user?->activo || ! $c->user->esAdmin() || (int) $c->user_id !== (int) $e->user_id || $c->revision !== $e->cuenta_revision || $c->telegram_id !== $e->remitente_id) {
                $e->update(['estado' => 'fallido', 'detalle' => 'La cuenta cambió o dejó de estar disponible. Prepare un nuevo borrador.']);

                return null;
            }
            if ($c->no_antes_de?->isFuture()) {
                $e->update(['estado' => 'fallido', 'detalle' => 'Telegram solicitó una espera. Revise la cuenta antes de preparar otro envío.']);

                return null;
            }
            if ($e->tipo === 'cv' && (! $e->cliente?->activo || ! $e->cliente->esVisiblePara($c->user))) {
                $e->update(['estado' => 'fallido', 'detalle' => 'El CV ya no está disponible para esta cuenta.']);

                return null;
            }
            $e->update(['estado' => 'enviando', 'iniciado_en' => now()]);

            return $e;
        });
        if (! $e) {
            return;
        }
        $c = $e->cuenta->fresh();
        if (! is_array($e->peer) || ($e->peer['id'] ?? null) !== $e->destinatario_id) {
            $e->update(['estado' => 'fallido', 'detalle' => 'El destinatario interno no coincide con la cuenta revisada. Prepare un nuevo borrador.']);

            return;
        }
        $datos = ['peer' => $e->peer, 'mensaje' => $e->mensaje, 'random_id' => $e->random_id, 'tipo' => $e->tipo];
        if ($e->tipo === 'cv') {
            $ruta = 'telegram-cv/envios/'.$e->id.'/cv.pdf';
            $d = Storage::disk('local');
            if ($e->cv_ruta !== $ruta || ! $d->exists($ruta) || ! hash_equals((string) $e->cv_sha256, hash_file('sha256', $d->path($ruta))) || $d->size($ruta) > 18 * 1024 * 1024) {
                $e->update(['estado' => 'fallido', 'detalle' => TelegramCvError::texto('archivo')]);

                return;
            }
            $datos += ['archivo' => $d->path($ruta), 'archivo_sha256' => $e->cv_sha256, 'archivo_nombre' => $e->cv_nombre];
        }
        try {
            $r = $cliente->operar($c, 'enviar', $datos);
            if (! isset($r['mensaje_id']) || ! preg_match('/^[0-9]{1,24}$/D', (string) $r['mensaje_id'])) {
                throw new TelegramCvError;
            }$e->update(['estado' => 'enviado', 'mensaje_id' => (string) $r['mensaje_id'], 'enviado_en' => now(), 'detalle' => 'Telegram aceptó el envío. No se ha confirmado su lectura.']);
        } catch (TelegramCvError $error) {
            $e->update(['estado' => in_array($error->motivo, ['servicio', 'ocupada', 'conexion'], true) ? 'incierto' : 'fallido', 'detalle' => $error->getMessage()]);
        } catch (\Throwable) {
            $e->update(['estado' => 'incierto', 'detalle' => 'No se pudo confirmar el resultado. Revise la conversación en Telegram antes de preparar otro envío.']);
        }
    }

    public function failed(?\Throwable $error): void
    {
        TelegramCvEnvio::whereKey($this->id)->whereIn('estado', ['en_cola', 'enviando'])->get()->each(fn ($e) => $e->update(['estado' => $e->estado === 'enviando' ? 'incierto' : 'fallido', 'detalle' => 'El trabajador se interrumpió. Revise Telegram antes de preparar otro envío.']));
    }
}
