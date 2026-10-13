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
    public function contenidoQr(): string
    {
        return json_encode(
            [
                "formato" => "MiCV-correo-v1",
                "nombre" => $this->cliente->nombreCompleto(),
                "correo" => $this->correo,
                "contrasena" => $this->clave_correo,
                "celular" => $this->celular,
                "proveedor" => $this->proveedor,
                "pais" => \App\Support\Pais::de($this->pais)["nombre"],
                "registrado" => $this->created_at
                    ->timezone("America/Costa_Rica")
                    ->format("d/m/Y H:i"),
            ],
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR,
        );
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
