<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokCuenta extends Model {
    protected $table='tiktok_cuentas';protected $guarded=['id'];
    public const ESTADOS=['inactivo'=>'Pausado','pendiente'=>'Esperando al capturador','conectando'=>'Conectando','en_live'=>'En directo','sin_live'=>'Esperando un LIVE público','restringido'=>'Acceso restringido','error'=>'Conexión pendiente · reintento automático','limitado'=>'Esperando por límite de TikTok'];
    protected function casts(): array{return ['monitorear'=>'boolean','perfil_publico'=>'array','latido_ms'=>'integer','reintentar_ms'=>'integer'];}
    public function lives(){return $this->hasMany(TikTokLive::class,'cuenta_id');}
    public function publicaciones(){return $this->hasMany(TikTokPublicacion::class,'cuenta_id');}
    public function comentarios(){return $this->hasMany(TikTokComentario::class,'cuenta_id');}
    public function perfilUrl(): string{return 'https://www.tiktok.com/@'.$this->usuario;}
    public function liveUrl(): string{return $this->perfilUrl().'/live';}
}
