<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailCuenta extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['clave'];
    protected function casts(): array
    {
        return ['clave' => 'encrypted', 'activo' => 'boolean', 'verificada_en' => 'datetime'];
    }
    public function user() { return $this->belongsTo(User::class); }
    public function lista(): bool { return $this->activo && $this->verificada_en !== null && filled($this->getRawOriginal('clave')); }
}
