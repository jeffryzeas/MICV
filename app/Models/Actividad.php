<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Bitácora sencilla: quién hizo qué. Nunca debe romper la acción principal. */
class Actividad extends Model
{
    protected $table = 'actividad';

    public $timestamps = false;

    protected $fillable = ['user_id', 'accion', 'detalle', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function registrar(string $accion, ?string $detalle = null): void
    {
        try {
            static::create([
                'user_id'    => Auth::id(),
                'accion'     => $accion,
                'detalle'    => $detalle ? mb_substr($detalle, 0, 255) : null,
                'ip'         => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
