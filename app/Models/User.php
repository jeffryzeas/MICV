<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Usuarios del sistema:
 *  - admin : todo (usuarios, configuración, borrar clientes)
 *  - cyber : atiende clientes y genera sus CV
 *  - cliente : consulta sus datos, trabajos y conversaciones
 */
class User extends Authenticatable
{
    use Notifiable;

    public const ROLES = [
        "admin" => "Administrador general",
        "cyber" => "Usuario de negocio",
        "cliente" => "Usuario registrado (hace su propio CV)",
    ];

    protected $fillable = [
        "encargado_id",
        "permisos",
        "edicion_cliente",
        "cyber_id",
        "usuario",
        "name",
        "email",
        "password",
        "rol",
        "activo",
        "ultimo_acceso",
        "terminos_version",
        "terminos_aceptados_en",
    ];

    protected $hidden = ["password", "remember_token"];

    protected function casts(): array
    {
        return [
            "permisos" => "array",
            "edicion_cliente" => "array",
            "email_verified_at" => "datetime",
            "ultimo_acceso" => "datetime",
            "terminos_aceptados_en" => "datetime",
            "password" => "hashed",
            "activo" => "boolean",
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $u) {
            if (!Schema::hasTable("jz_chats")) {
                return;
            }
            $ids = JzChat::where("uno_id", $u->id)
                ->orWhere("dos_id", $u->id)
                ->pluck("id");
            $files = JzMensaje::whereIn("chat_id", $ids)
                ->get(["archivo", "miniatura"])
                ->flatMap(fn($m) => [$m->archivo, $m->miniatura])
                ->filter()
                ->all();
            if($u->chat_foto)$files[]=$u->chat_foto;
            $u->setRelation("chatArchivosAlEliminar", $files);
        });
        static::deleted(function (self $u) {
            $files = $u->relationLoaded("chatArchivosAlEliminar")
                ? $u->getRelation("chatArchivosAlEliminar")
                : [];
            if ($files) {
                DB::afterCommit(fn() => Storage::disk("local")->delete($files));
            }
        });
    }

    public function cyber(): BelongsTo
    {
        return $this->belongsTo(Cyber::class);
    }

    public function esEncargado(): bool
    {
        return $this->rol === "cyber" && $this->encargado_id === null;
    }

    public function tienePermiso(string $permiso): bool
    {
        if ($this->esAdmin()) {
            return true;
        }
        if ($this->esCliente()) {
            return false;
        }
        if ($permiso === "clientes.eliminar") return false;
        if (in_array($permiso,["personal","clientes.acceso","clientes.permisos"],true) && !$this->esEncargado()) {
            return false;
        }
        $modulo = \App\Support\Negocios::moduloPermiso($permiso);
        if ($modulo && !$this->cyber?->moduloHabilitado($modulo)) return false;
        $lista =
            $this->permisos ??
            Permisos::perfil($this->esEncargado() ? "encargado" : "cajero");

        return in_array($permiso, Permisos::completar($lista), true);
    }

    public function encargado(): BelongsTo
    {
        return $this->belongsTo(self::class, "encargado_id");
    }

    public function cajeros(): HasMany
    {
        return $this->hasMany(self::class, "encargado_id");
    }

    public function esAdmin(): bool
    {
        return $this->rol === "admin";
    }

    public function esCliente(): bool
    {
        return $this->rol === "cliente";
    }

    public function esCyber(): bool
    {
        return $this->rol === "cyber";
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class, "user_id");
    }

    public function verificado(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function rolLegible(): string
    {
        return self::ROLES[$this->rol] ?? ucfirst((string) $this->rol);
    }

    public function iniciales(): string
    {
        $partes = preg_split("/\s+/", trim((string) $this->name)) ?: [];
        $ini = "";
        foreach (array_slice($partes, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $ini ?: "U";
    }
}
