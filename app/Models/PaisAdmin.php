<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Países creados o ajustados por el administrador (ver App\Support\Pais::todos()). */
class PaisAdmin extends Model
{
    protected $table = 'paises';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['codigo', 'nuevo', 'activo', 'datos', 'documentos', 'escudo'];

    protected function casts(): array
    {
        return ['nuevo' => 'boolean', 'activo' => 'boolean', 'datos' => 'array', 'documentos' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\Pais::limpiarCache());
        static::deleted(fn () => \App\Support\Pais::limpiarCache());
    }
}
