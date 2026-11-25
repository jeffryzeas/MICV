<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramCvAcceso extends Model
{
    protected $table = 'telegram_cv_accesos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $hidden = ['qr_url'];

    protected function casts(): array
    {
        return ['qr_url' => 'encrypted', 'qr_expira_en' => 'datetime', 'expira_en' => 'datetime', 'iniciado_en' => 'datetime', 'latido_en' => 'datetime'];
    }

    public function cuenta()
    {
        return $this->belongsTo(TelegramCvCuenta::class, 'telegram_cuenta_id');
    }

    public function activo(): bool
    {
        return in_array($this->estado, ['en_cola', 'generando', 'esperando'], true) && $this->expira_en->isFuture();
    }
}
