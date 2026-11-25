<?php

namespace App\Http\Controllers\Admin\TelegramCv;

use App\Http\Controllers\Controller;
use App\Jobs\ConectarTelegramQr;
use App\Models\TelegramCvAcceso as Acceso;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Services\TelegramCvQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QrController extends Controller
{
    private function cuenta(Request $r): Cuenta
    {
        abort_unless($r->user()?->activo && $r->user()->esAdmin(), 403);

        return Cuenta::sistema();
    }

    public function crear(Request $r)
    {
        $c = $this->cuenta($r);
        $d = $r->validate(['tg_api_id' => 'required|integer|min:1|max:2147483647', 'tg_api_hash' => 'nullable|string|regex:/^[a-fA-F0-9]{32}$/D', 'confirmar' => 'accepted']);
        $a = DB::transaction(function () use ($c, $d, $r) {
            $c = Cuenta::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($c->lista()) {
                throw ValidationException::withMessages(['cuenta' => 'Telegram del sistema ya está conectado.']);
            }if ($c->no_antes_de?->isFuture()) {
                throw ValidationException::withMessages(['cuenta' => 'Respete el plazo de espera indicado por Telegram.']);
            }
            $previo = Acceso::where('telegram_cuenta_id', $c->id)->whereIn('estado', ['en_cola', 'generando', 'esperando'])->lockForUpdate()->latest()->first();
            if ($previo?->activo()) {
                return $previo;
            }
            if ($previo) {
                $previo->update(['estado' => 'caducada', 'qr_url' => null, 'qr_expira_en' => null]);
            }
            if (empty($d['tg_api_hash']) && ! filled($c->getRawOriginal('api_hash'))) {
                throw ValidationException::withMessages(['tg_api_hash' => 'Introduzca el api_hash de su aplicación de Telegram.']);
            }
            $c->update(['api_id' => (int) $d['tg_api_id'], 'api_hash' => ($d['tg_api_hash'] ?? null) ?: $c->api_hash, 'estado' => 'qr', 'sesion' => null, 'codigo_hash' => null, 'codigo_en' => null, 'telegram_id' => null, 'conectada_en' => null, 'revision' => (string) Str::uuid(), 'ultimo_error' => null]);
            Envio::where('telegram_cuenta_id', $c->id)->whereIn('estado', ['borrador', 'en_cola'])->update(['estado' => 'fallido', 'detalle' => 'Se cambió la conexión de Telegram del sistema.']);
            $a = new Acceso(['telegram_cuenta_id' => $c->id, 'user_id' => $r->user()->id, 'revision' => $c->revision, 'estado' => 'en_cola', 'expira_en' => now()->addMinutes(5)]);
            $a->id = (string) Str::uuid();
            $a->save();

            return $a;
        });
        if ($a->wasRecentlyCreated) {
            try {
                ConectarTelegramQr::dispatch($a->id);
            } catch (\Throwable) {
                app(TelegramCvQr::class)->fallar($a->id);

                return back()->withErrors(['cuenta' => 'No se pudo iniciar el trabajador de vinculación. Revise micv-telegram-login.']);
            }
        }

        return redirect()->route('admin.telegram-cv.cuenta')->with('mensaje', 'Preparando la vinculación QR de Telegram del sistema.');
    }

    public function estado(Request $r, string $id, TelegramCvQr $qr)
    {
        $c = $this->cuenta($r);
        $a = Acceso::where('telegram_cuenta_id', $c->id)->findOrFail($id);
        if ($a->expira_en->isPast() && $a->activo() === false && in_array($a->estado, ['en_cola', 'generando', 'esperando'], true)) {
            $qr->fallar($a->id, 'qr_caducado');
            $a->refresh();
        }
        if (in_array($a->estado, ['generando', 'esperando'], true) && $a->latido_en?->lt(now()->subSeconds(30))) {
            $qr->fallar($a->id, 'servicio', 0, 'PROCESO_SALIDA');
            $a->refresh();
        }
        $visible = $a->activo() && $a->estado === 'esperando' && $a->qr_expira_en?->isFuture();

        return response()->json(['estado' => $a->estado, 'qr_url' => $visible ? $a->qr_url : null, 'restante' => $visible ? max(0, (int) now()->diffInSeconds($a->qr_expira_en)) : 0, 'detalle' => $a->detalle, 'espera' => $a->estado === 'en_cola' && $a->created_at->lt(now()->subSeconds(30))])->header('Cache-Control', 'private, no-store, max-age=0')->header('Referrer-Policy', 'no-referrer');
    }

    public function cancelar(Request $r, string $id)
    {
        $c = $this->cuenta($r);
        $r->validate(['confirmar' => 'accepted']);
        DB::transaction(function () use ($c, $id) {
            $c = Cuenta::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $a = Acceso::where('telegram_cuenta_id', $c->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if (in_array($a->estado, ['en_cola', 'generando', 'esperando', 'clave'], true)) {
                $a->update(['estado' => 'cancelada', 'qr_url' => null, 'qr_expira_en' => null]);
                if ($a->revision === $c->revision) {
                    $c->update(['estado' => 'desconectada', 'sesion' => null, 'codigo_hash' => null, 'codigo_en' => null, 'revision' => (string) Str::uuid()]);
                }
            }
        });

        return redirect()->route('admin.telegram-cv.cuenta')->with('mensaje', 'Vinculación QR cancelada.');
    }
}
