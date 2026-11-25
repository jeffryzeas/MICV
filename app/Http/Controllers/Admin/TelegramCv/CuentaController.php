<?php

namespace App\Http\Controllers\Admin\TelegramCv;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CuentaController extends Controller
{
    private function cuenta(Request $r): Cuenta
    {
        abort_unless($r->user()?->activo && $r->user()->esAdmin(), 403);

        return Cuenta::para($r->user());
    }

    private function guardar(Cuenta $c, array $campos): void
    {
        DB::transaction(function () use ($c, $campos) {
            $a = Cuenta::whereKey($c->id)->lockForUpdate()->first();
            if (! $a || $a->revision !== $c->revision) {
                throw new TelegramCvError('sesion');
            }$a->update($campos);
        });
    }

    public function index(Request $r)
    {
        $cuenta = $this->cuenta($r);

        return response()->view('telegram-cv.cuenta', compact('cuenta'))->header('Cache-Control', 'private, no-store');
    }

    public function codigo(Request $r, TelegramCvCliente $cliente)
    {
        $c = $this->cuenta($r);
        $d = $r->validate(['tg_api_id' => 'required|integer|min:1|max:2147483647', 'tg_api_hash' => 'nullable|string|regex:/^[a-fA-F0-9]{32}$/D', 'tg_telefono' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/D'], 'confirmar' => 'accepted']);
        if ($c->lista()) {
            throw ValidationException::withMessages(['cuenta' => 'Desconecte la cuenta actual antes de conectar otro número.']);
        }
        if ($c->no_antes_de?->isFuture()) {
            throw ValidationException::withMessages(['cuenta' => 'Espere el plazo indicado por Telegram antes de volver a solicitar el código.']);
        }
        if ($c->codigo_en?->gt(now()->subMinute())) {
            throw ValidationException::withMessages(['cuenta' => 'Espere al menos un minuto antes de solicitar otro código.']);
        }
        if (empty($d['tg_api_hash']) && ! filled($c->getRawOriginal('api_hash'))) {
            throw ValidationException::withMessages(['tg_api_hash' => 'Introduzca el api_hash de su aplicación de Telegram.']);
        }
        DB::transaction(function () use ($c, $d) {
            $a = Cuenta::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($a->lista() || $a->codigo_en?->gt(now()->subMinute()) || $a->no_antes_de?->isFuture()) {
                throw ValidationException::withMessages(['cuenta' => 'La conexión cambió o debe esperar antes de solicitar otro código.']);
            }$a->update(['api_id' => $d['tg_api_id'], 'api_hash' => ($d['tg_api_hash'] ?? null) ?: $a->api_hash, 'telefono' => $d['tg_telefono'], 'estado' => 'desconectada', 'sesion' => null, 'codigo_hash' => null, 'telegram_id' => null, 'nombre' => null, 'usuario' => null, 'conectada_en' => null, 'codigo_en' => now(), 'revision' => (string) Str::uuid(), 'ultimo_error' => null]);
            Envio::where('telegram_cuenta_id', $a->id)->whereIn('estado', ['borrador', 'en_cola'])->update(['estado' => 'fallido', 'detalle' => 'Se cambió la conexión de Telegram.']);
        });
        $c->refresh();
        try {
            $o = $cliente->operar($c, 'codigo');
            if (! is_string($o['codigo_hash'] ?? null) || strlen($o['codigo_hash']) > 1024 || empty($o['sesion'])) {
                throw new TelegramCvError;
            }$this->guardar($c, ['codigo_hash' => $o['codigo_hash'], 'estado' => 'codigo', 'codigo_en' => now()]);
        } catch (TelegramCvError $e) {
            return back()->withErrors(['cuenta' => $e->getMessage()]);
        }

        return back()->with('mensaje', 'Revise Telegram en sus dispositivos: introduzca el código recibido. Telegram decide el canal de entrega.');
    }

    public function verificar(Request $r, TelegramCvCliente $cliente)
    {
        $c = $this->cuenta($r);
        $d = $r->validate(['tg_codigo' => 'nullable|string|regex:/^[0-9]{4,8}$/D', 'tg_clave' => 'nullable|string|max:256']);
        if (! in_array($c->estado, ['codigo', 'clave'], true) || ! $c->codigo_en || $c->codigo_en->lt(now()->subMinutes(10))) {
            throw ValidationException::withMessages(['cuenta' => 'La conexión pendiente caducó. Solicite un código nuevo.']);
        }
        if ($c->estado === 'codigo' && empty($d['tg_codigo'])) {
            throw ValidationException::withMessages(['tg_codigo' => 'Introduzca el código de Telegram.']);
        }
        if ($c->estado === 'clave' && empty($d['tg_clave'])) {
            throw ValidationException::withMessages(['tg_clave' => 'Introduzca la contraseña de verificación en dos pasos.']);
        }
        try {
            $o = $cliente->operar($c, $c->estado === 'clave' ? 'clave' : 'verificar', ['codigo' => $d['tg_codigo'] ?? null, 'clave' => $d['tg_clave'] ?? null]);
            if (($o['estado'] ?? null) === 'clave') {
                $this->guardar($c, ['estado' => 'clave']);

                return back()->with('mensaje', 'Su cuenta requiere la contraseña de verificación en dos pasos.');
            }
            if (($o['estado'] ?? null) !== 'conectada' || ! preg_match('/^[1-9][0-9]{0,23}$/D', (string) ($o['id'] ?? '')) || empty($o['sesion'])) {
                throw new TelegramCvError;
            }
            $this->guardar($c, ['estado' => 'conectada', 'telegram_id' => (string) $o['id'], 'nombre' => mb_substr((string) ($o['nombre'] ?? ''), 0, 200), 'usuario' => mb_substr((string) ($o['usuario'] ?? ''), 0, 64), 'codigo_hash' => null, 'codigo_en' => null, 'conectada_en' => now(), 'ultimo_error' => null]);
        } catch (TelegramCvError $e) {
            return back()->withErrors(['cuenta' => $e->getMessage()]);
        }
        Actividad::registrar('telegram_cv_conectar', 'Cuenta personal de Telegram conectada por usuario #'.$r->user()->id);

        return back()->with('mensaje', 'Cuenta conectada. Puede preparar mensajes y CV para un destinatario concreto.');
    }

    public function desconectar(Request $r, TelegramCvCliente $cliente)
    {
        $c = $this->cuenta($r);
        $r->validate(['confirmar' => 'accepted']);
        $remota = false;
        try {
            if (filled($c->getRawOriginal('sesion'))) {
                $cliente->operar($c, 'desconectar');
                $remota = true;
            }
        } catch (TelegramCvError) {
        }
        DB::transaction(function () use ($c) {
            $a = Cuenta::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $a->update(['estado' => 'desconectada', 'api_hash' => null, 'sesion' => null, 'codigo_hash' => null, 'telegram_id' => null, 'nombre' => null, 'usuario' => null, 'codigo_en' => null, 'conectada_en' => null, 'revision' => (string) Str::uuid(), 'ultimo_error' => null]);
            Envio::where('telegram_cuenta_id', $a->id)->whereIn('estado', ['borrador', 'en_cola'])->update(['estado' => 'fallido', 'detalle' => 'La cuenta fue desconectada. No se enviará este borrador.']);
        });

        return back()->with('mensaje', $remota ? 'Sesión de Telegram cerrada y credenciales locales retiradas.' : 'Credenciales locales retiradas. Si había una sesión activa, ciérrela también desde Telegram → Ajustes → Dispositivos.');
    }
}
