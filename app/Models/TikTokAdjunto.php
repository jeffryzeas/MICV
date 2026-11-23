<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokAdjunto extends Model {
 protected $table='tiktok_adjuntos';public $incrementing=false;protected $keyType='string';protected $guarded=[];protected $hidden=['origen_url','archivo','miniatura'];
 protected function casts(): array{return ['origen_url'=>'encrypted','cuenta_id'=>'integer','live_id'=>'integer','observado_ms'=>'integer','descargado_ms'=>'integer','bytes'=>'integer'];}
}
