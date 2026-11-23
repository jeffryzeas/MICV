<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokComentario extends Model {
    protected $table='tiktok_comentarios';public $timestamps=false;protected $guarded=['id'];
    protected function casts(): array{return ['ocurrido_ms'=>'integer','capturado_ms'=>'integer'];}
    public function live(){return $this->belongsTo(TikTokLive::class,'live_id');}
    public function publicacion(){return $this->belongsTo(TikTokPublicacion::class,'publicacion_id');}
}
