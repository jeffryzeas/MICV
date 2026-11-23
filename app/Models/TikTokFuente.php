<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokFuente extends Model {
 protected $table='tiktok_fuentes';protected $guarded=['id'];
 protected function casts(): array{return ['cuenta_id'=>'integer','latido_ms'=>'integer','reintentar_ms'=>'integer','orden'=>'integer'];}
 public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
}
