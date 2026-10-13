<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudEliminacion extends Model
{
    protected $table = 'solicitudes_eliminacion';

    public const ESTADOS = [
        'pendiente' => 'Pendiente', 'aprobada' => 'Aprobada (cuenta eliminada)',
        'rechazada' => 'Rechazada', 'cancelada' => 'Cancelada por el usuario',
    ];

    protected $fillable = ['user_id', 'email', 'nombre', 'motivo', 'origen', 'estado', 'nota_admin', 'atendida_por', 'atendida_en', 'ip'];

    protected function casts(): array
    {
        return ['atendida_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }
}
