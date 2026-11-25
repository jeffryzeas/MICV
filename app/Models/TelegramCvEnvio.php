<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TelegramCvEnvio extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (self $e) {
            DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('telegram-cv/envios/'.$e->id));
        });
    }

    protected $table = 'telegram_cv_envios';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $hidden = ['peer', 'cv_ruta'];

    public const ESTADOS = ['borrador' => 'Pendiente de confirmar', 'en_cola' => 'En cola', 'enviando' => 'Enviando', 'enviado' => 'Aceptado por Telegram', 'fallido' => 'No enviado', 'incierto' => 'Revisar en Telegram'];

    protected function casts(): array
    {
        return ['peer' => 'encrypted:array', 'cv_opciones' => 'array', 'encolado_en' => 'datetime', 'iniciado_en' => 'datetime', 'enviado_en' => 'datetime'];
    }

    public function cuenta()
    {
        return $this->belongsTo(TelegramCvCuenta::class, 'telegram_cuenta_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comprobarTiempo(): void
    {
        if ($this->estado === 'enviando' && $this->iniciado_en?->lt(now()->subMinutes(5))) {
            self::whereKey($this->id)->where('estado', 'enviando')->where('iniciado_en', '<', now()->subMinutes(5))->update(['estado' => 'incierto', 'detalle' => 'El proceso se interrumpió. Revise su conversación en Telegram antes de preparar otro envío.']);
            $this->refresh();
        }
    }
}
