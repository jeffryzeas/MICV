<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokLive extends Model {
    protected $table='tiktok_lives';protected $guarded=['id'];
    protected function casts(): array{return ['perfil_inicio'=>'array','cuenta_id'=>'integer','comentarios'=>'integer','max_espectadores'=>'integer','iniciado_ms'=>'integer','ultimo_ms'=>'integer','terminado_ms'=>'integer'];}
    public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
    public function mensajes(){return $this->hasMany(TikTokComentario::class,'live_id');}
}
