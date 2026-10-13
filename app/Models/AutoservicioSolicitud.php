<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Datos que una persona llenó desde su celular con el QR del Cyber. Se borran solos a los 7 días. */
class AutoservicioSolicitud extends Model
{
    protected $table = 'autoservicio_solicitudes';

    protected $fillable = ['cyber_id', 'fecha', 'turno', 'nombre', 'numero_documento', 'datos', 'estado', 'cliente_id', 'atendida_por', 'atendida_en'];

    public const DIAS_GUARDADO = 7;

    protected function casts(): array
    {
        return ['datos' => 'array', 'fecha' => 'date', 'atendida_en' => 'datetime'];
    }

    public function cyber(): BelongsTo
    {
        return $this->belongsTo(Cyber::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Lo que el usuario (admin o cyber) puede ver. */
    public function scopeDe($q, User $u)
    {
        return $u->esAdmin() ? $q : $q->where('cyber_id', $u->cyber_id);
    }

    public static function siguienteTurno(int $cyberId): int
    {
        return (int) static::where('cyber_id', $cyberId)->whereDate('fecha', today())->max('turno') + 1;
    }
}
