<?php

namespace App\Models;

use App\Services\Correo;
use App\Services\CvEnvioRetencion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Postulacion extends Model
{
    protected $table = 'postulaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $hidden = ['cv_ruta', 'correo_html'];

    public const ESTADOS = ['borrador' => 'Pendiente de confirmar', 'en_cola' => 'En cola', 'enviando' => 'Enviando', 'enviado' => 'Aceptado por Gmail', 'fallido' => 'No enviado', 'incierto' => 'Revisar envío', 'caducado' => 'Caducado'];

    protected function casts(): array
    {
        return ['cv_opciones' => 'array', 'encolado_en' => 'datetime', 'iniciado_en' => 'datetime', 'enviado_en' => 'datetime', 'cv_eliminado_en' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $p) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($p->cv_ruta));
        });
        static::updated(function (self $p) {
            if ($p->wasChanged('estado') && in_array($p->estado, CvEnvioRetencion::TERMINALES, true)) {
                DB::afterCommit(fn () => app(CvEnvioRetencion::class)->borrar($p));
            }
        });
    }

    public function scopeVisibles(Builder $q, User $u): Builder
    {
        return $u->esAdmin() ? $q : $q->where(fn ($w) => $w->where('user_id', $u->id)->orWhereHas('cliente', fn ($c) => $c->where('user_id', $u->id)));
    }

    public function cuenta()
    {
        return $this->belongsTo(GmailCuenta::class, 'gmail_cuenta_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** El texto sigue la misma copia revisada que el HTML; conserva los envíos anteriores. */
    public function textoCorreo(): string
    {
        return str_contains((string) $this->correo_html, '<!-- micv-postulacion-formal-v2 -->')
            ? Correo::aTexto($this->correo_html)
            : (string) $this->mensaje;
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function comprobarTiempo(): void
    {
        if ($this->estado === 'enviando' && $this->iniciado_en?->lt(now()->subMinutes(3))) {
            self::whereKey($this->id)->where('estado', 'enviando')->where('iniciado_en', '<', now()->subMinutes(3))->update([
                'estado' => 'incierto', 'detalle_estado' => 'El proceso se interrumpió. Revise la carpeta Enviados de Gmail antes de preparar otro envío.',
            ]);
            $this->refresh();
        }
    }
}
