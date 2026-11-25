<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TelegramCvCuenta extends Model
{
    protected $table = 'telegram_cv_cuentas';

    protected $guarded = ['id'];

    protected $hidden = ['api_hash', 'sesion', 'codigo_hash'];

    protected function casts(): array
    {
        return ['api_id' => 'integer', 'api_hash' => 'encrypted', 'sesion' => 'encrypted', 'codigo_hash' => 'encrypted', 'codigo_en' => 'datetime', 'conectada_en' => 'datetime', 'no_antes_de' => 'datetime'];
    }

    public static function para(User $u): self
    {
        return self::firstOrCreate(['user_id' => $u->id], ['revision' => (string) Str::uuid()]);
    }

    public function lista(): bool
    {
        return $this->estado === 'conectada' && filled($this->telegram_id) && filled($this->getRawOriginal('sesion'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
