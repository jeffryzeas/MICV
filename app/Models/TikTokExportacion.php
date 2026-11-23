<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Storage};
class TikTokExportacion extends Model {
    protected $table='tiktok_exportaciones';public $incrementing=false;protected $keyType='string';protected $guarded=['id'];protected $hidden=['archivo','paquete'];
    protected function casts(): array{return ['metadatos'=>'array','filtros'=>'array','user_id'=>'integer','cuenta_id'=>'integer'];}
    public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
    protected static function booted(): void{static::deleted(fn(self $e)=>DB::afterCommit(fn()=>Storage::disk('local')->deleteDirectory('tiktok/exportaciones/'.$e->id)));}
}
