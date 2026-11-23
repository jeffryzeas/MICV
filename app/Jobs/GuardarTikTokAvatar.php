<?php
namespace App\Jobs;
use App\Models\TikTokAdjunto;
use App\Services\{TikTokConsulta,TikTokVideo};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
class GuardarTikTokAvatar implements ShouldQueue {
 use Queueable;
 public int $tries=1;public int $timeout=45;public bool $failOnTimeout=true;
 public function __construct(public string $id){$this->onConnection('tiktok-evidencias')->onQueue('tiktok-evidencias');}
 public function handle(TikTokVideo $video): void {
  if(!TikTokAdjunto::whereKey($this->id)->where('estado','pendiente')->update(['estado'=>'procesando']))return;
  $a=TikTokAdjunto::find($this->id);if(!$a)return;$disk=Storage::disk('local');$dir='tiktok/avatares/'.$a->id;
  try{
   [$bytes,$mime]=$video->descargarImagen($a->origen_url);$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime];$archivo=$dir.'/original.'.$ext;
   if(!$disk->put($archivo,$bytes,['visibility'=>'private']))throw new \RuntimeException();$miniatura=null;
   $vista=app(\App\Services\TikTokMiniatura::class)->crear($bytes,$mime);
   if($vista){$previewExt=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$vista[1]];$miniatura=$dir.'/vista.'.$previewExt;if(!$disk->put($miniatura,$vista[0],['visibility'=>'private']))$miniatura=null;}
   if(!TikTokAdjunto::whereKey($a->id)->exists()){$disk->deleteDirectory($dir);return;}
   $a->update(['estado'=>'listo','archivo'=>$archivo,'miniatura'=>$miniatura,'sha256'=>hash('sha256',$bytes),'mime'=>$mime,'bytes'=>strlen($bytes),'descargado_ms'=>TikTokConsulta::ahora(),'error'=>null]);
  }catch(\Throwable){$disk->deleteDirectory($dir);TikTokAdjunto::whereKey($this->id)->update(['estado'=>'error','error'=>'No se pudo conservar esta foto pública. El comentario se conserva.']);}
 }
 public function failed(?\Throwable $e): void {TikTokAdjunto::whereKey($this->id)->whereIn('estado',['pendiente','procesando'])->update(['estado'=>'error','error'=>'La descarga de la foto se interrumpió.']);Storage::disk('local')->deleteDirectory('tiktok/avatares/'.$this->id);}
}
