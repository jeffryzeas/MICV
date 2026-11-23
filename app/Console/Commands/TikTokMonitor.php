<?php
namespace App\Console\Commands;
use App\Models\TikTokCuenta;
use App\Services\{TikTokConsulta,TikTokRegistro,TikTokProceso};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TikTokMonitor extends Command {
    protected $signature='micv:tiktok-monitor {--duracion=0}';protected $description='Captura lives públicos con procesos Python supervisados y los archiva en Laravel';
    public function handle(TikTokRegistro $registro,TikTokProceso $procesos): int {
        $dir=config('tiktok.directorio');File::ensureDirectoryExists($dir,0700);
        $lock=fopen($dir.'/monitor.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){$this->error('Ya hay un capturador activo.');return 1;}
        $seguir=true;$duracion=max(0,(int)$this->option('duracion'));$fin=$duracion?microtime(true)+$duracion:INF;$hijos=[];$ultimo=0;
        if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$seguir){$seguir=false;});pcntl_signal(SIGINT,function()use(&$seguir){$seguir=false;});}
        $leer=function($id)use(&$hijos,$registro){
            $h=&$hijos[$id];$h['buffer'].=$h['p']->getIncrementalOutput();$h['p']->clearOutput();if(strlen($h['buffer'])>2*1024*1024)throw new \RuntimeException('El capturador excedió el búfer permitido.');
            $eventos=[];while(($pos=strpos($h['buffer'],"\n"))!==false){$linea=substr($h['buffer'],0,$pos);$h['buffer']=substr($h['buffer'],$pos+1);if(strlen($linea)>65536)continue;$e=json_decode($linea,true);if(is_array($e)){if(($e['tipo']??null)==='diagnostico')$this->diagnostico($h['c'],$e);else $eventos[]=$e;}if(count($eventos)>=200){$this->lote($registro,$h['c'],$eventos);$eventos=[];}}
            if($eventos)$this->lote($registro,$h['c'],$eventos);
            $h['p']->clearErrorOutput();
        };
        try {
            \Illuminate\Support\Facades\DB::table('tiktok_sesiones')->whereNull('fin_ms')->update(['fin_ms'=>TikTokConsulta::ahora(),'motivo'=>'reinicio_servicio']);
            TikTokCuenta::where('monitorear',true)->where('estado','en_live')->update(['estado'=>'pendiente']);
            while($seguir&&microtime(true)<$fin){
                foreach(array_keys($hijos)as$id){$leer($id);if(!$hijos[$id]['p']->isRunning()){
                    $leer($id);$c=TikTokCuenta::find($id);if($c&&$c->monitorear){if(in_array($c->estado,['conectando','en_live'],true))$c->update(['estado'=>'error','detalle'=>'El proceso de captura terminó sin una conexión activa. Se volverá a comprobar más adelante.']);$espera=$c->estado==='limitado'?300:config('tiktok.intervalo');$c->update(['reintentar_ms'=>max($c->reintentar_ms??0,TikTokConsulta::ahora()+$espera*1000)]);TikTokRegistro::cerrar($c->id,'proceso_finalizado',TikTokConsulta::ahora());$c->lives()->where('estado','en_live')->update(['estado'=>'interrumpido','terminado_ms'=>TikTokConsulta::ahora()]);}unset($hijos[$id]);
                }}
                if(time()-$ultimo>=3){$ultimo=time();$cuentas=TikTokCuenta::where('monitorear',true)->orderBy('reintentar_ms')->orderBy('id')->get();$ids=$cuentas->pluck('id')->all();
                    foreach(array_keys($hijos)as$id)if(!in_array($id,$ids,true)){$hijos[$id]['p']->stop(3);$leer($id);unset($hijos[$id]);}
                    foreach($cuentas as$c){if(count($hijos)>=config('tiktok.simultaneos'))break;if(isset($hijos[$c->id])||($c->reintentar_ms??0)>TikTokConsulta::ahora())continue;
                        if(!is_executable(config('tiktok.python')))throw new \RuntimeException('Falta el entorno Python. Ejecute bin/instalar-tiktok.sh.');
                        $c->update(['estado'=>'conectando','detalle'=>null,'reintentar_ms'=>null]);$p=$procesos->crear($c->usuario);$p->start();$hijos[$c->id]=['c'=>$c,'p'=>$p,'buffer'=>''];
                    }
                    $tmp=$dir.'/estado.tmp';file_put_contents($tmp,json_encode(['ms'=>TikTokConsulta::ahora(),'procesos'=>count($hijos)]),LOCK_EX);chmod($tmp,0600);rename($tmp,$dir.'/estado.json');
                }
                usleep(200000);
            }
            return 0;
        }catch(\Throwable $e){$this->error('Capturador detenido: '.$e->getMessage());return 1;}
        finally{foreach($hijos as$id=>$h){$h['p']->stop(3);try{$leer($id);}catch(\Throwable){}TikTokRegistro::cerrar($h['c']->id,'servicio_detenido',TikTokConsulta::ahora());$h['c']->lives()->where('estado','en_live')->update(['estado'=>'interrumpido','terminado_ms'=>TikTokConsulta::ahora()]);}File::delete($dir.'/estado.json');flock($lock,LOCK_UN);fclose($lock);}
    }
    private function lote(TikTokRegistro $registro,TikTokCuenta $cuenta,array $eventos): void {
        $n=$registro->registrar($cuenta,$eventos);
        foreach($eventos as$e)if(($e['tipo']??null)==='inicio'&&$cuenta->estado==='en_live'&&$cuenta->lives()->where('sala',$e['sala']??'')->exists())$this->info('TikTok @'.$cuenta->usuario.' · LIVE guardado · sala '.$e['sala'].' · '.$n.' comentarios nuevos');
    }
    private function diagnostico(TikTokCuenta $cuenta,array $evento): void {
        if(($evento['v']??null)!==1)return;
        $codigo=$evento['codigo']??'';$clase=$evento['clase']??'';
        if(!is_string($codigo)||!is_string($clase)||!preg_match('/^[a-z_]{1,80}$/D',$codigo)||!preg_match('/^[A-Za-z0-9_]{1,80}$/D',$clase))return;
        $http=filter_var($evento['http']??null,FILTER_VALIDATE_INT);
        $this->line('TikTok @'.$cuenta->usuario.' · '.$codigo.' · '.$clase.($http>=100&&$http<=599?' · HTTP '.$http:''));
    }

}
