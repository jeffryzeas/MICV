<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokTelegramEnvio extends Model {
 protected $table='tiktok_telegram_envios';protected $guarded=['id'];
 protected function casts(): array{return ['iniciado_en'=>'datetime','enviado_en'=>'datetime','cuenta_id'=>'integer'];}
 public function comentario(){return $this->belongsTo(TikTokComentario::class,'comentario_id');}
 public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
}
