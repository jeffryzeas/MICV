<?php

namespace App\Models;

use App\Support\Archivos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cada Cyber (negocio) tiene sus propios usuarios y ve solo sus clientes. */
class Cyber extends Model
{
    protected $table = "cybers";

    protected $fillable = [
        "codigo",
        "nombre",
        "logo",
        "color_primario",
        "color_secundario",
        "direccion",
        "telefono",
        "correo",
        "activo",
        "autoservicio",
        "autoservicio_hasta",
    ];

    protected function casts(): array
    {
        return [
            "autoservicio_hasta" => "datetime",
            "activo" => "boolean",
            "autoservicio" => "boolean",
        ];
    }

    public function qrAbierto(): bool
    {
        return $this->activo &&
            $this->autoservicio &&
            $this->autoservicio_hasta &&
            $this->autoservicio_hasta->isFuture();
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, "cliente_cyber")
            ->withPivot("registrado_por")
            ->withTimestamps();
    }

    public function logoUrl(): ?string
    {
        return Archivos::existe($this->logo)
            ? route("cybers.logo", $this) .
                    "?v=" .
                    $this->updated_at?->timestamp
            : null;
    }
}
