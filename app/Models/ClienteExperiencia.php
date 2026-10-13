<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteExperiencia extends Model
{
    protected $table = 'cliente_experiencias';

    protected $fillable = ['cliente_id', 'cargo', 'empresa', 'descripcion', 'fecha_inicio', 'fecha_fin', 'orden'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function periodoTexto(): ?string
    {
        if (!$this->fecha_inicio && !$this->fecha_fin) {
            return null;
        }
        $ini = $this->fecha_inicio?->format('m/Y') ?? '?';
        $fin = $this->fecha_fin?->format('m/Y') ?? 'Actualidad';
        return "$ini – $fin";
    }

    public function lineaCv(): string
    {
        $linea = implode(' ', array_filter([
            $this->cargo,
            $this->empresa ? 'en ' . $this->empresa : null,
        ]));
        if ($p = $this->periodoTexto()) {
            $linea .= ($linea ? ' · ' : '') . $p;
        }
        if ($this->descripcion) {
            $linea .= ($linea ? ' — ' : '') . $this->descripcion;
        }
        return $linea ?: 'Experiencia laboral';
    }
}
