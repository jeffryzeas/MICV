<?php
namespace App\Jobs;
use App\Models\{TikTokExportacion,User};
use App\Services\{TikTokConsulta,TikTokPdf};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{File,Storage};
use ZipArchive;

class ExportarTikTok implements ShouldQueue {
    use Queueable;
    public int $tries=1;public int $timeout=570;public bool $failOnTimeout=true;
    public function __construct(public string $id){$this->onConnection('tiktok-exportaciones')->onQueue('tiktok-exportaciones');}
    public function handle(TikTokConsulta $consulta,TikTokPdf $pdf): void {
        if(!TikTokExportacion::whereKey($this->id)->where('estado','en_cola')->update(['estado'=>'procesando','updated_at'=>now()]))return;
        $e=TikTokExportacion::find($this->id);if(!$e)return;
        $u=User::find($e->user_id);if(!$u?->activo||!$u->esAdmin()){$e->update(['estado'=>'error','error'=>'La cuenta solicitante ya no tiene acceso.']);return;}
        $dir='tiktok/exportaciones/'.$e->id;$disk=Storage::disk('local');$disk->makeDirectory($dir);$files=[];$parte=0;$procesados=0;
        try{
            $q=$consulta->query($e->cuenta,$e->filtros)->where('id','<=',$e->hasta_id)->orderBy('ocurrido_ms')->orderBy('id');
            $q->chunk(1000,function($filas)use($e,$pdf,$dir,$disk,&$files,&$parte,&$procesados){
                $parte++;$nombre='comentarios-parte-'.str_pad((string)$parte,3,'0',STR_PAD_LEFT).'.pdf';
                $bytes=$pdf->generar(['cuenta'=>$e->cuenta,'filas'=>$filas,'filtros'=>$e->filtros,'parte'=>$parte,'generado'=>TikTokConsulta::fecha(TikTokConsulta::ahora())]);
                if(!str_starts_with($bytes,'%PDF-')||!$disk->put($dir.'/'.$nombre,$bytes,['visibility'=>'private']))throw new \RuntimeException('No se pudo guardar el PDF.');
                $files[]=$dir.'/'.$nombre;$procesados+=$filas->count();$e->update(['procesados'=>$procesados,'updated_at'=>now()]);
            });
            if(!$files)throw new \RuntimeException('No hay comentarios disponibles.');
            $ruta=$files[0];
            if(count($files)>1){$ruta=$dir.'/comentarios-pdf.zip';$zip=new ZipArchive();if($zip->open($disk->path($ruta),ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('No se pudo crear el ZIP.');foreach($files as$f)$zip->addFile($disk->path($f),basename($f));if(!$zip->close())throw new \RuntimeException('No se pudo cerrar el ZIP.');$disk->delete($files);}
            $e->update(['estado'=>'listo','archivo'=>$ruta,'procesados'=>$procesados]);
        }catch(\Throwable $error){$disk->deleteDirectory($dir);$e->update(['estado'=>'error','error'=>'No se pudo generar el informe. Revise el motor de PDF y el servicio micv-tiktok-pdf.']);throw $error;}
    }
    public function failed(?\Throwable $e): void{TikTokExportacion::whereKey($this->id)->whereIn('estado',['en_cola','procesando'])->update(['estado'=>'error','error'=>'La exportación se interrumpió. Revise el trabajador PDF e inténtelo de nuevo.']);Storage::disk('local')->deleteDirectory('tiktok/exportaciones/'.$this->id);}
}
