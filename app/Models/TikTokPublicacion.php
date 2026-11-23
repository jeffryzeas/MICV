<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokPublicacion extends Model {
    protected $table='tiktok_publicaciones';protected $guarded=['id'];
    protected function casts(): array{return ['cuenta_id'=>'integer'];}
    public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
    public function comentarios(){return $this->hasMany(TikTokComentario::class,'publicacion_id');}
    public function url(): string{return $this->cuenta->perfilUrl().'/video/'.$this->video_id;}
}
