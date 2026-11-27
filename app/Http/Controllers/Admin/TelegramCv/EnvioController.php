<?php

namespace App\Http\Controllers\Admin\TelegramCv;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarTelegramCv;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Services\CvDatos;
use App\Services\CvEnvioRetencion;
use App\Services\PostulacionPdf;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnvioController extends Controller
{
    private function cuenta(Request $r): Cuenta
    {
        $u = $r->user();
        abort_unless($u?->activo && ($u->esAdmin() || $u->esCliente() || $u->tienePermiso('cv')), 403);

        return Cuenta::para($r->user());
    }

    private function envio(Request $r, string $id): Envio
    {
        $c = $this->cuenta($r);
        $e = Envio::query()->when(! $r->user()->esAdmin(), fn ($q) => $q->where('user_id', $r->user()->id))->findOrFail($id);
        $e->comprobarTiempo();

        return $e;
    }

    private function lista(Cuenta $c): void
    {
        if (! $c->lista()) {
            throw ValidationException::withMessages(['cuenta' => 'Telegram del sistema todavía no está disponible. La administración debe conectar la cuenta.']);
        }
    }

    public function index(Request $r)
    {
        $c = $this->cuenta($r);
        $envios = Envio::when(! $r->user()->esAdmin(), fn ($q) => $q->where('user_id', $r->user()->id))->latest()->paginate(20);
        foreach ($envios as $e) {
            $e->comprobarTiempo();
        }

        return response()->view('telegram-cv.index', compact('c', 'envios'))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $r)
    {
        $cuenta = $this->cuenta($r);
        $clientes = Cliente::visiblesPara($r->user())->where('activo', true)->orderBy('primer_nombre')->get();

        return response()->view($r->user()->esAdmin() ? 'telegram-cv.crear' : 'telegram-cv.mi-envio', compact('cuenta', 'clientes'))->header('Cache-Control', 'private, no-store');
    }

    public function preparar(Request $r, TelegramCvCliente $telegram, PostulacionPdf $pdf)
    {
        $c = $this->cuenta($r);
        $this->lista($c);
        if (! $r->user()->esAdmin()) {
            $persona = Cliente::visiblesPara($r->user())->where('activo', true)->findOrFail($r->input('cliente_id', $r->user()->cliente?->id));
            $r->merge(['cliente_id' => $persona->id, 'tipo' => 'cv', 'contacto' => mb_substr('CV de '.$persona->nombreCompleto(), 0, 80), 'mensaje' => 'Saludos cordiales. Le compartimos el currículum de '.mb_substr($persona->nombreCompleto(), 0, 200).'.', 'diseno' => $r->input('diseno', Configuracion::actual()->cv()['diseno_defecto']), 'idioma' => $r->input('idioma', 'es')]);
        }
        $d = $r->validate(['telefono' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/D'], 'contacto' => 'required|string|max:80', 'tipo' => 'required|in:mensaje,cv', 'mensaje' => 'required|string|max:4096', 'cliente_id' => 'nullable|required_if:tipo,cv|integer', 'diseno' => ['nullable', 'required_if:tipo,cv', Rule::in(array_keys(CvDatos::DISENOS))], 'paleta' => ['nullable', Rule::in(array_keys(Configuracion::PALETAS_PRO))], 'idioma' => 'nullable|required_if:tipo,cv|in:es,en', 'foto' => 'nullable|boolean', 'confirmar_contacto' => 'accepted']);
        if (intdiv(strlen(mb_convert_encoding($d['mensaje'], 'UTF-16LE', 'UTF-8')), 2) > ($d['tipo'] === 'cv' ? 1024 : 4096)) {
            throw ValidationException::withMessages(['mensaje' => $d['tipo'] === 'cv' ? 'El texto que acompaña al PDF admite hasta 1024 caracteres de Telegram; algunos símbolos cuentan como dos.' : 'El mensaje supera el límite de Telegram.']);
        }
        $persona = null;
        $bytes = null;
        $op = null;
        if ($d['tipo'] === 'cv') {
            $persona = Cliente::findOrFail($d['cliente_id']);
            abort_unless($persona->activo && $persona->esVisiblePara($r->user()), 403);
            $op = ['diseno' => $d['diseno'], 'paleta' => $d['paleta'] ?? 'cyber', 'foto' => $d['diseno'] !== 'ats' && $r->boolean('foto'), 'idioma' => $d['idioma']];
        }
        try {
            $o = $telegram->operar($c, 'resolver', ['destino' => $d['telefono'], 'contacto' => $d['contacto']]);
        } catch (TelegramCvError $e) {
            return back()->withInput($r->except(['_token']))->withErrors(['telefono' => $e->getMessage()]);
        }
        if (! preg_match('/^[1-9][0-9]{0,23}$/D', (string) ($o['id'] ?? '')) || ! preg_match('/^-?[0-9]{1,24}$/D', (string) ($o['access_hash'] ?? ''))) {
            throw ValidationException::withMessages(['telefono' => 'Telegram no devolvió una identidad suficiente para este número.']);
        }
        if ($persona) {
            try {
                $bytes = $pdf->generar($persona, $r->user(), $op);
            } catch (\Throwable) {
                return back()->withInput()->withErrors(['cv' => 'No se pudo generar el PDF. No se envió ningún mensaje.']);
            }
            if (! str_starts_with($bytes, '%PDF-') || strlen($bytes) > 18 * 1024 * 1024) {
                throw ValidationException::withMessages(['cv' => 'El CV debe ser un PDF válido de hasta 18 MB.']);
            }
        }
        $id = (string) Str::uuid();
        $ruta = $bytes ? 'telegram-cv/envios/'.$id.'/cv.pdf' : null;
        $e = new Envio(['user_id' => $r->user()->id, 'telegram_cuenta_id' => $c->id, 'cliente_id' => $persona?->id, 'cuenta_revision' => $c->revision, 'remitente_id' => $c->telegram_id, 'remitente' => $c->telefono, 'telefono' => $d['telefono'], 'destinatario' => mb_substr((string) ($o['nombre'] ?? $d['contacto']), 0, 200), 'destinatario_id' => (string) $o['id'], 'peer' => ['id' => (string) $o['id'], 'access_hash' => (string) $o['access_hash']], 'tipo' => $d['tipo'], 'mensaje' => $d['mensaje'], 'cv_ruta' => $ruta, 'cv_nombre' => $bytes ? 'CV_'.Str::limit(Str::slug($persona->nombreCompleto(), '_'), 90, '').'_'.substr($id, 0, 8).'.pdf' : null, 'candidato' => $persona?->nombreCompleto(), 'cv_opciones' => $op, 'cv_sha256' => $bytes ? hash('sha256', $bytes) : null, 'cv_bytes' => $bytes ? strlen($bytes) : null, 'random_id' => (string) random_int(1, PHP_INT_MAX), 'estado' => 'borrador']);
        $e->id = $id;
        if ($bytes && ! Storage::disk('local')->put($ruta, $bytes, ['visibility' => 'private'])) {
            throw ValidationException::withMessages(['cv' => 'No se pudo guardar la copia del CV.']);
        }
        try {
            $e->save();
        } catch (\Throwable $error) {
            if ($ruta) {
                Storage::disk('local')->delete($ruta);
            }throw $error;
        }
        Actividad::registrar('telegram_cv_preparar', 'Borrador #'.$id.' / usuario #'.$r->user()->id);

        return redirect()->route('admin.telegram-cv.ver', $e)->with('mensaje', 'Borrador preparado. Revise el destinatario real y el contenido antes de confirmar. Todavía no se envió.');
    }

    public function ver(Request $r, string $id)
    {
        $e = $this->envio($r, $id);

        return response()->view('telegram-cv.ver', compact('e'))->header('Cache-Control', 'private, no-store');
    }

    public function confirmar(Request $r, string $id)
    {
        $e = $this->envio($r, $id);
        $r->validate(['confirmar' => 'accepted']);
        $c = $this->cuenta($r);
        $this->lista($c);
        if ($c->revision !== $e->cuenta_revision || $c->telegram_id !== $e->remitente_id) {
            throw ValidationException::withMessages(['cuenta' => 'La cuenta cambió. Prepare un borrador nuevo.']);
        }
        if ($e->tipo === 'cv') {
            abort_unless($e->cliente?->activo && $e->cliente->esVisiblePara($r->user()), 403);
        }
        $n = Envio::whereKey($e->id)->where('estado', 'borrador')->update(['estado' => 'en_cola', 'encolado_en' => now(), 'detalle' => null]);
        if ($n) {
            try {
                EnviarTelegramCv::dispatch($e->id);
            } catch (\Throwable) {
                Envio::whereKey($e->id)->where('estado', 'en_cola')->update(['estado' => 'fallido', 'detalle' => 'No se pudo registrar el trabajo de envío. Revise micv-telegram-cv.']);

                return back()->withErrors(['cola' => 'No se pudo iniciar el envío.']);
            }Actividad::registrar('telegram_cv_confirmar', 'Envío #'.$e->id);
        }

        return redirect()->route('admin.telegram-cv.ver', $e)->with('mensaje', $n ? 'Envío confirmado. Puede seguir el estado aquí.' : 'Este envío ya fue procesado; no se duplicará.');
    }

    public function estado(Request $r, string $id)
    {
        $e = $this->envio($r, $id);

        return response()->json(['estado' => $e->estado, 'etiqueta' => Envio::ESTADOS[$e->estado] ?? $e->estado, 'detalle' => $e->detalle, 'fecha' => $e->enviado_en?->timezone('America/Costa_Rica')->format('d/m/Y h:i:s A'), 'espera' => $e->estado === 'en_cola' && $e->encolado_en?->lt(now()->subMinutes(2))])->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $r, string $id)
    {
        $e = $this->envio($r, $id);

        return app(CvEnvioRetencion::class)->respuesta($e, $r->user(), true);
    }

    public function eliminar(Request $r, string $id)
    {
        $e = $this->envio($r, $id);
        DB::transaction(function () use ($e) {
            $a = Envio::whereKey($e->id)->lockForUpdate()->firstOrFail();
            abort_unless($a->estado === 'borrador', 409);
            $a->delete();
        });
        if ($e->cv_ruta) {
            Storage::disk('local')->deleteDirectory('telegram-cv/envios/'.$e->id);
        }

        return redirect()->route('admin.telegram-cv.index')->with('mensaje', 'Borrador descartado.');
    }
}
