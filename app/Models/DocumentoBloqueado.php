<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** "Lista privada": documentos que ningún Cyber puede registrar ni ver. */
class DocumentoBloqueado extends Model
{
    protected $table = 'documentos_bloqueados';

    protected $fillable = ['numero', 'motivo', 'creado_por'];

    public const MENSAJE = 'Este documento no se puede registrar en el sistema. Si cree que es un error, comuníquese con el administrador.';

    public static function normalizar(?string $numero): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $numero));
    }

    /** @return array<int, string> */
    public static function lista(): array
    {
        return Cache::remember('documentos_bloqueados', 300, fn () => static::pluck('numero')->all());
    }

    public static function estaBloqueado(?string $numero): bool
    {
        $n = self::normalizar($numero);
        return $n !== '' && in_array($n, self::lista(), true);
    }

    protected static function booted(): void
    {
        $limpiar = fn () => Cache::forget('documentos_bloqueados');
        static::saved($limpiar);
        static::deleted($limpiar);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
