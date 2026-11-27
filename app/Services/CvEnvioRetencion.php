<?php

namespace App\Services;

use App\Models\Postulacion;
use App\Models\TelegramCvEnvio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CvEnvioRetencion
{
    public const TERMINALES = ['enviado', 'fallido', 'incierto', 'caducado'];

    public function terminal(Model $r): bool
    {
        return in_array($r->estado, self::TERMINALES, true);
    }

    private function ruta(Model $r): ?string
    {
        if (! Str::isUuid((string) $r->id)) {
            return null;
        }

        return match (true) {
            $r instanceof Postulacion => 'postulaciones/'.$r->id.'/cv.pdf',$r instanceof TelegramCvEnvio && $r->tipo === 'cv' => 'telegram-cv/envios/'.$r->id.'/cv.pdf',default => null
        };
    }

    public function borrar(Model $registro): bool
    {
        try {
            return DB::transaction(function () use ($registro) {
                $r = $registro->newQuery()->whereKey($registro->id)->lockForUpdate()->first();
                if (! $r || ! $this->terminal($r) || $r->cv_eliminado_en) {
                    return false;
                }
                $ruta = $this->ruta($r);
                if ($ruta && $r->cv_ruta !== $ruta) {
                    Log::warning('Ruta de CV temporal inesperada', ['registro' => (string) $r->id]);

                    return false;
                }
                try {
                    if ($ruta) {
                        $d = Storage::disk('local');
                        $dir = dirname($ruta);
                        if ($d->exists($dir) && ! $d->deleteDirectory($dir)) {
                            return false;
                        }
                    }
                } catch (\Throwable) {
                    Log::warning('No se pudo limpiar un CV temporal', ['registro' => (string) $r->id]);

                    return false;
                }
                $r->update(['cv_eliminado_en' => now()]);
                $registro->setRawAttributes($r->getAttributes(), true);

                return true;
            });
        } catch (\Throwable) {
            Log::warning('Limpieza de CV pendiente', ['registro' => (string) $registro->id]);

            return false;
        }
    }

    public function limpiar(): int
    {
        $total = 0;
        foreach ([Postulacion::class, TelegramCvEnvio::class] as $clase) {
            $modelo = new $clase;
            if (! Schema::hasTable($modelo->getTable()) || ! Schema::hasColumn($modelo->getTable(), 'cv_eliminado_en')) {
                continue;
            }
            $clase::whereNull('cv_eliminado_en')->where(function ($q) {
                $q->whereIn('estado', self::TERMINALES)->orWhere(fn ($w) => $w->where('estado', 'borrador')->where('created_at', '<=', now()->subDay()))->orWhere(fn ($w) => $w->where('estado', 'en_cola')->where('encolado_en', '<=', now()->subDay()))->orWhere(fn ($w) => $w->where('estado', 'enviando')->where('iniciado_en', '<=', now()->subMinutes(15)));
            })->chunkById(100, function ($rs) use (&$total) {
                foreach ($rs as $r) {
                    DB::transaction(function () use ($r) {
                        $actual = $r->newQuery()->whereKey($r->id)->lockForUpdate()->first();
                        if (! $actual || $actual->cv_eliminado_en) {
                            return;
                        }$campo = $actual instanceof Postulacion ? 'detalle_estado' : 'detalle';
                        if (in_array($actual->estado, ['borrador', 'en_cola'], true)) {
                            if (($actual->estado === 'borrador' ? $actual->created_at : $actual->encolado_en)?->gt(now()->subDay())) {
                                return;
                            }$actual->update(['estado' => 'caducado', $campo => 'El envío pendiente caducó después de 24 horas. Prepare uno nuevo.']);
                        } elseif ($actual->estado === 'enviando') {
                            if ($actual->iniciado_en?->gt(now()->subMinutes(15))) {
                                return;
                            }$actual->update(['estado' => 'incierto', $campo => 'El proceso se interrumpió. Revise el destino antes de preparar otro envío.']);
                        }
                    });
                    $r->refresh();
                    if ($this->borrar($r) || $r->cv_eliminado_en) {
                        $total++;
                    }
                }
            });
        }

        return $total;
    }

    public function respuesta(Model $r, User $actor, bool $descargar = true)
    {
        $ruta = $this->ruta($r);
        abort_unless($ruta && $r->cv_ruta === $ruta, 404);
        if (! $this->terminal($r)) {
            abort_unless(Storage::disk('local')->exists($ruta), 410, 'El PDF temporal ya no está disponible. Prepare un nuevo envío.');

            return $descargar ? Storage::disk('local')->download($ruta, $r->cv_nombre, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']) : response()->file(Storage::disk('local')->path($ruta), ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        }
        $this->borrar($r);
        $cliente = $r->cliente;
        abort_unless($cliente && $cliente->activo && $cliente->esVisiblePara($actor), 403, 'No tiene acceso al perfil actual para regenerar el CV.');
        try {
            $bytes = app(PostulacionPdf::class)->generar($cliente, $actor, $r->cv_opciones);
        } catch (\Throwable) {
            abort(503, 'No se pudo regenerar el CV. Revise el motor de PDF del servidor.');
        }
        abort_unless(str_starts_with($bytes, '%PDF-') && strlen($bytes) <= 18 * 1024 * 1024, 503, 'No se pudo generar un PDF válido.');

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition($descargar ? 'attachment' : 'inline', 'CV_actual_'.Str::limit(Str::slug($cliente->nombreCompleto(), '_'), 90, '').'_'.$r->cv_opciones['diseno'].'.pdf', 'CV.pdf'), 'Cache-Control' => 'private, no-store', 'X-MiCV-CV' => 'regenerado-datos-actuales']);
    }
}
