<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CorreoServicio extends Model
{
    protected $guarded = [];
    protected $hidden = ["clave_correo"];
    protected function casts(): array
    {
        return ["clave_correo" => "encrypted"];
    }
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    public function cyber()
    {
        return $this->belongsTo(Cyber::class);
    }
    public function loginUrl(): string
    {
        return match ($this->proveedor) {
            "Gmail" => "https://accounts.google.com/",
            "Outlook" => "https://login.live.com/",
            "Yahoo" => "https://login.yahoo.com/",
            "Proton" => "https://account.proton.me/",
            "iCloud" => "https://www.icloud.com/mail",
            default => "https://www.google.com/search?q=iniciar+sesion+correo",
        };
    }
}
