<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Storage};
class TikTokExportacion extends Model {
    protected $table='tiktok_exportaciones';public $incrementing=false;protected $keyType='string';protected $guarded=['id'];protected $hidden=['archivo','paquete'];
    protected function casts(): array{return ['expira_en'=>'datetime','limpiado_en'=>'datetime','metadatos'=>'array','filtros'=>'array','user_id'=>'integer','cuenta_id'=>'integer'];}
    public function caducidad(): ?\Illuminate\Support\Carbon {return $this->expira_en??($this->estado==='listo'?$this->updated_at?->copy()->addMinutes(5):null);}
    public function vencida(): bool {return $this->estado==='caducado'||($this->caducidad()?->lte(now())??false);}
    public function restante(): ?int {return $this->caducidad()?max(0,(int)now()->diffInSeconds($this->caducidad(),false)):null;}
    public function cuenta(){return $this->belongsTo(TikTokCuenta::class,'cuenta_id');}
    protected static function booted(): void{static::deleted(fn(self $e)=>DB::afterCommit(fn()=>Storage::disk('local')->deleteDirectory('tiktok/exportaciones/'.$e->id)));}
}
