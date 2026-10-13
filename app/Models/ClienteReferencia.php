<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteReferencia extends Model
{
    protected $table = 'cliente_referencias';

    protected $fillable = ['detalle', 'cliente_id', 'tratamiento', 'nombre', 'telefono', 'correo', 'orden'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function nombreConTratamiento(): string
    {
        return trim(($this->tratamiento ? $this->tratamiento . ' ' : '') . $this->nombre);
    }

    /** "Lic. Juan Pérez · 8888-8888 · correo@ejemplo.com" */
    public function lineaCv(): string
    {
        return implode(' · ', array_filter([$this->nombreConTratamiento(), $this->telefono, $this->correo]));
    }
}
