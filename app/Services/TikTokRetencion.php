<?php
namespace App\Services;
use App\Models\TikTokExportacion;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

/** Elimina únicamente copias temporales generadas: nunca las capturas o fotos originales. */
class TikTokRetencion {
 public function caducar(TikTokExportacion $e): bool {
  if(!Str::isUuid($e->id))return false;
  return DB::transaction(function()use($e){
   $actual=TikTokExportacion::whereKey($e->id)->lockForUpdate()->first();
   if(!$actual||!in_array($actual->estado,['listo','caducado'],true)||!$actual->vencida())return false;
   if($actual->estado!=='caducado')$actual->update(['estado'=>'caducado','archivo'=>null,'paquete'=>null,'error'=>null]);
   $e->setRawAttributes($actual->getAttributes(),true);
   if(!$actual->limpiado_en){$d=Storage::disk('local');$ruta='tiktok/exportaciones/'.$actual->id;try{if($d->exists($ruta)&&!$d->deleteDirectory($ruta))throw new \RuntimeException();}catch(\Throwable){$actual->update(['error'=>'No se pudieron limpiar los archivos temporales. Revise los permisos del servicio.']);$e->setRawAttributes($actual->getAttributes(),true);return false;}$actual->update(['limpiado_en'=>now(),'error'=>null]);}
   $e->setRawAttributes($actual->getAttributes(),true);return true;
  });
 }
 public function limpiar(): int {
  $n=0;TikTokExportacion::whereIn('estado',['listo','caducado'])->whereNull('limpiado_en')->where('expira_en','<=',now())->orderBy('id')->chunkById(100,function($rs)use(&$n){foreach($rs as$e)if($this->caducar($e))$n++;});return $n;
 }
}
