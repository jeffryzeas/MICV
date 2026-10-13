<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteEstudio extends Model
{
    protected $table = 'cliente_estudios';

    public const NIVELES = [
        'universidad' => 'Universidad',
        'tecnico'     => 'Técnico',
        'secundaria'  => 'Secundaria',
        'primaria'    => 'Primaria',
        'preescolar'  => 'Preescolar',
        'curso'       => 'Curso',
    ];

    protected $fillable = ['cliente_id', 'nivel', 'titulo', 'institucion', 'anio', 'detalle', 'orden'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function nivelLegible(): string
    {
        return self::NIVELES[$this->nivel] ?? ucfirst((string) $this->nivel);
    }

    /** "Lic. en Contaduría en UCR (2020) — detalle" */
    public function lineaCv(): string
    {
        $linea = implode(' ', array_filter([
            $this->titulo,
            $this->institucion ? 'en ' . $this->institucion : null,
            $this->anio ? '(' . $this->anio . ')' : null,
        ]));
        if ($this->detalle) {
            $linea .= ($linea ? ' — ' : '') . $this->detalle;
        }
        return $linea ?: $this->nivelLegible();
    }
}
