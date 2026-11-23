<?php
namespace App\Console\Commands;
use App\Models\{TikTokCuenta,TikTokFuente};
use App\Services\{TikTokConsulta,TikTokRegistro,TikTokProceso,TikTokFuentes};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{File,DB};

class TikTokMonitor extends Command {
    protected $signature='micv:tiktok-monitor {--duracion=0}';
    protected $description='Captura salas públicas con conexiones y reintentos independientes';
    public function handle(TikTokRegistro $registro,TikTokProceso $procesos): int {
        $dir=config('tiktok.directorio');File::ensureDirectoryExists($dir,0700);
        $lock=fopen($dir.'/monitor.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){$this->error('Ya hay un capturador activo.');return 1;}
        $seguir=true;$duracion=max(0,(int)$this->option('duracion'));$fin=$duracion?microtime(true)+$duracion:INF;$hijos=[];$ultimo=0;
        if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$seguir){$seguir=false;});pcntl_signal(SIGINT,function()use(&$seguir){$seguir=false;});}
        $leer=function($key)use(&$hijos,$registro){
            $h=&$hijos[$key];$h['buffer'].=$h['p']->getIncrementalOutput();$h['p']->clearOutput();if(strlen($h['buffer'])>2*1024*1024)throw new \RuntimeException('El capturador excedió el búfer permitido.');
            $eventos=[];
            while(($pos=strpos($h['buffer'],"\n"))!==false){
                $linea=substr($h['buffer'],0,$pos);$h['buffer']=substr($h['buffer'],$pos+1);if(strlen($linea)>65536)continue;
                $e=json_decode($linea,true);if(is_array($e)){if(($e['tipo']??null)==='diagnostico')$this->diagnostico($h['c'],$e,$h['src']);else $eventos[]=$e;}
                if(count($eventos)>=200){$this->lote($registro,$h['c'],$eventos,$h['f']);$eventos=[];}
            }
            if($eventos)$this->lote($registro,$h['c'],$eventos,$h['f']);$h['p']->clearErrorOutput();
        };
        $terminar=function($key,string $motivo,bool $reprogramar=false)use(&$hijos){
            $h=$hijos[$key];$c=TikTokCuenta::find($h['c']->id);
            if($c){
                TikTokFuentes::cerrar($c,$h['f']?->id,$motivo,TikTokConsulta::ahora());
                $control=$h['f']?TikTokFuente::find($h['f']->id):($c->fuentes()->exists()||$c->sala_publica_usuario!==$h['src']?null:$c);
                if($control){
                    if($c->monitorear&&$reprogramar){
                        if(in_array($control->estado,['conectando','en_live'],true))$control->update(['estado'=>'error','detalle'=>'La conexión terminó. Se volverá a comprobar esta fuente.']);
                        $espera=$control->estado==='limitado'?300:config('tiktok.intervalo');$control->update(['reintentar_ms'=>max($control->reintentar_ms??0,TikTokConsulta::ahora()+$espera*1000)]);
                    }elseif($h['f'])$control->update(['estado'=>$c->monitorear?'pendiente':'inactivo']);
                }
                TikTokFuentes::resumir($c);
            }
            unset($hijos[$key]);
        };
        try {
            DB::table('tiktok_sesiones')->whereNull('fin_ms')->update(['fin_ms'=>TikTokConsulta::ahora(),'motivo'=>'reinicio_servicio']);
            TikTokFuente::whereIn('estado',['en_live','conectando'])->update(['estado'=>'pendiente']);
            TikTokCuenta::where('monitorear',true)->where('estado','en_live')->update(['estado'=>'pendiente']);
            while($seguir&&microtime(true)<$fin){
                foreach(array_keys($hijos)as$key){$leer($key);if(!$hijos[$key]['p']->isRunning()){$leer($key);$terminar($key,'proceso_finalizado',true);}}
                if(time()-$ultimo>=3){
                    $ultimo=time();$trabajos=[];$cuentas=TikTokCuenta::where('monitorear',true)->with(['fuentes'=>fn($q)=>$q->orderBy('orden')->orderBy('id')])->orderBy('id')->get();
                    foreach($cuentas as$c){
                        if($c->fuentes->isEmpty())$trabajos['c:'.$c->id]=['c'=>$c,'f'=>null,'src'=>$c->sala_publica_usuario,'espera'=>$c->reintentar_ms??0];
                        else foreach($c->fuentes as$f)$trabajos['f:'.$f->id]=['c'=>$c,'f'=>$f,'src'=>$f->usuario,'espera'=>$f->reintentar_ms??0];
                    }
                    foreach(array_keys($hijos)as$key)if(!isset($trabajos[$key])||$hijos[$key]['src']!==$trabajos[$key]['src']){$hijos[$key]['p']->stop(3);$leer($key);$terminar($key,'cambio_o_pausa');}
                    uasort($trabajos,fn($a,$b)=>$a['espera']<=>$b['espera']);
                    foreach($trabajos as$key=>$t){
                        if(count($hijos)>=config('tiktok.simultaneos'))break;if(isset($hijos[$key])||$t['espera']>TikTokConsulta::ahora())continue;
                        if(!is_executable(config('tiktok.python')))throw new \RuntimeException('Falta el entorno Python. Ejecute bin/instalar-tiktok.sh.');
                        $control=$t['f']??$t['c'];$control->update(['estado'=>'conectando','detalle'=>null,'reintentar_ms'=>null]);
                        $p=$t['src']?$procesos->crear($t['c']->usuario,$t['src']):$procesos->crear($t['c']->usuario);$p->start();$hijos[$key]=$t+['p'=>$p,'buffer'=>''];TikTokFuentes::resumir($t['c']);
                    }
                    $tmp=$dir.'/estado.tmp';file_put_contents($tmp,json_encode(['ms'=>TikTokConsulta::ahora(),'procesos'=>count($hijos)]),LOCK_EX);chmod($tmp,0600);rename($tmp,$dir.'/estado.json');
                }
                usleep(200000);
            }
            return 0;
        }catch(\Throwable $e){$this->error('Capturador detenido: '.$e->getMessage());return 1;}
        finally{foreach(array_keys($hijos)as$key){$hijos[$key]['p']->stop(3);try{$leer($key);}catch(\Throwable){}$terminar($key,'servicio_detenido');}File::delete($dir.'/estado.json');flock($lock,LOCK_UN);fclose($lock);}
    }
    private function lote(TikTokRegistro $registro,TikTokCuenta $cuenta,array $eventos,?TikTokFuente $fuente=null): void {
        $n=$registro->registrar($cuenta,$eventos,$fuente);
        foreach($eventos as$e)if(($e['tipo']??null)==='inicio'&&($fuente?$fuente->fresh()?->estado:$cuenta->estado)==='en_live'&&$cuenta->lives()->where('sala',$e['sala']??'')->exists())$this->info('TikTok @'.$cuenta->usuario.($fuente?' · fuente @'.$fuente->usuario:'').' · LIVE guardado · sala '.$e['sala'].' · '.$n.' comentarios nuevos');
    }
    private function diagnostico(TikTokCuenta $cuenta,array $evento,?string $fuente=null): void {
        if(($evento['v']??null)!==1)return;$codigo=$evento['codigo']??'';$clase=$evento['clase']??'';
        if(!is_string($codigo)||!is_string($clase)||!preg_match('/^[a-z_]{1,80}$/D',$codigo)||!preg_match('/^[A-Za-z0-9_]{1,80}$/D',$clase))return;
        $http=filter_var($evento['http']??null,FILTER_VALIDATE_INT);$this->line('TikTok @'.$cuenta->usuario.($fuente?' · fuente @'.$fuente:'').' · '.$codigo.' · '.$clase.($http>=100&&$http<=599?' · HTTP '.$http:''));
    }
}
