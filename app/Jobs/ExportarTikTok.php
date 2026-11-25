<?php
namespace App\Jobs;
use App\Models\{TikTokExportacion,User};
use App\Services\{TikTokConsulta,TikTokPdf,TikTokInforme};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
class ExportarTikTok implements ShouldQueue {
 use Queueable;
 public int $tries=1;public int $timeout=570;public bool $failOnTimeout=true;
 public function __construct(public string $id){$this->onConnection('tiktok-exportaciones')->onQueue('tiktok-exportaciones');}
 public function handle(TikTokConsulta $consulta,TikTokPdf $pdf): void {
  if(!TikTokExportacion::whereKey($this->id)->where('estado','en_cola')->update(['estado'=>'procesando','updated_at'=>now()]))return;
  $e=TikTokExportacion::find($this->id);if(!$e)return;$u=User::find($e->user_id);
  if(!$u?->activo||!$u->esAdmin()){$e->update(['estado'=>'error','error'=>'La cuenta solicitante ya no tiene acceso.']);return;}
  try{$datos=app(TikTokInforme::class)->crear($e,$consulta,$pdf);$e->update($datos+['estado'=>'listo','expira_en'=>now()->addMinutes(5)]);}
  catch(\Throwable $error){Storage::disk('local')->deleteDirectory('tiktok/exportaciones/'.$e->id);$e->update(['estado'=>'error','error'=>'No se pudo generar el informe. Revise Chromium y el servicio micv-tiktok-pdf.']);throw $error;}
 }
 public function failed(?\Throwable $e): void{TikTokExportacion::whereKey($this->id)->whereIn('estado',['en_cola','procesando'])->update(['estado'=>'error','error'=>'La exportación se interrumpió. Revise el trabajador PDF.']);Storage::disk('local')->deleteDirectory('tiktok/exportaciones/'.$this->id);}
}
