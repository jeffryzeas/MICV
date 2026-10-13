<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuarios del sistema. Solo hay dos roles:
 *  - admin : todo (usuarios, configuración, borrar clientes)
 *  - cyber : atiende clientes y genera sus CV
 */
class User extends Authenticatable
{
    use Notifiable;

    public const ROLES = [
        'admin'   => 'Administrador general',
        'cyber'   => 'Usuario de Cyber',
        'cliente' => 'Usuario registrado (hace su propio CV)',
    ];

    protected $fillable = [
        'cyber_id', 'usuario', 'name', 'email', 'password', 'rol', 'activo', 'ultimo_acceso',
        'terminos_version', 'terminos_aceptados_en',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso'     => 'datetime',
            'terminos_aceptados_en' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function cyber(): BelongsTo
    {
        return $this->belongsTo(Cyber::class);
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esCliente(): bool
    {
        return $this->rol === 'cliente';
    }

    public function esCyber(): bool
    {
        return $this->rol === 'cyber';
    }

    public function cliente(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cliente::class, 'user_id');
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
        $partes = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $ini = '';
        foreach (array_slice($partes, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini ?: 'U';
    }
}
