<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro que todavía no confirma su correo. La cuenta (usuario + ficha)
 * solo se crea cuando la persona escribe el código. Si no lo hace en
 * 24 horas, se borra solo y el correo queda libre.
 */
class RegistroPendiente extends Model
{
    protected $table = 'registros_pendientes';

    public const HORAS_VIGENCIA = 24;

    protected $fillable = ['token', 'email', 'datos', 'codigo', 'expira_en', 'intentos', 'enviado_en', 'ip'];

    protected $hidden = ['datos', 'codigo'];

    protected function casts(): array
    {
        return ['datos' => 'encrypted:array', 'expira_en' => 'datetime', 'enviado_en' => 'datetime'];
    }

    /** Borra los registros abandonados. */
    public static function limpiarViejos(): void
    {
        static::where('created_at', '<', now()->subHours(self::HORAS_VIGENCIA))->delete();
    }

    public function nombre(): string
    {
        return trim(($this->datos['primer_nombre'] ?? '') . ' ' . ($this->datos['primer_apellido'] ?? ''));
    }
}
