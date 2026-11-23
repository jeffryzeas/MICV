<?php
namespace App\Services;
use App\Models\{TikTokCuenta,TikTokLive,TikTokComentario};
use Illuminate\Support\Facades\DB;

/** Solo recibe mensajes del proceso hijo local: no existe endpoint público de ingestión. */
class TikTokRegistro {
    private function texto($v,int $limite): string{return mb_substr(str_replace("\0",'',is_scalar($v)?(string)$v:''),0,$limite);}
    public function registrar(TikTokCuenta $c,array $eventos): int {
        $c->refresh();$insertados=0;$lotes=[];$salas=[];
        DB::transaction(function()use($c,$eventos,&$insertados,&$lotes,&$salas){
            foreach($eventos as $e){
                if(($e['v']??null)!==1)continue;
                $ms=filter_var($e['capturado_ms']??null,FILTER_VALIDATE_INT);
                if(!$ms||$ms<1000000000000||$ms>TikTokConsulta::ahora()+60000)continue;
                $tipo=$e['tipo']??'';
                if($tipo==='estado'){
                    if(!$c->monitorear)continue;
                    $estado=$e['estado']??'';
                    if(!array_key_exists($estado,TikTokCuenta::ESTADOS))continue;
                    $datos=['estado'=>$estado,'latido_ms'=>TikTokConsulta::ahora(),'detalle'=>$this->texto($e['detalle']??'',300)];
                    if($estado==='restringido')$datos['monitorear']=false;
                    $espera=filter_var($e['espera_segundos']??null,FILTER_VALIDATE_INT);
                    if($espera!==false&&$espera>=60)$datos['reintentar_ms']=max($c->reintentar_ms??0,TikTokConsulta::ahora()+min(86400,$espera)*1000);
                    $c->update($datos);continue;
                }
                if($tipo==='latido'){$c->update(['latido_ms'=>TikTokConsulta::ahora()]);continue;}
                $sala=$this->texto($e['sala']??'',40);if(!preg_match('/^\d{5,40}$/D',$sala))continue;
                if($tipo==='inicio'){
                    if(!$c->monitorear)continue;
                    $c->lives()->where('estado','en_live')->where('sala','!=',$sala)->update(['estado'=>'interrumpido','terminado_ms'=>$ms]);
                    $live=TikTokLive::firstOrCreate(['cuenta_id'=>$c->id,'sala'=>$sala],['titulo'=>$this->texto($e['titulo']??'',255),'iniciado_ms'=>$ms,'ultimo_ms'=>$ms,'estado'=>'en_live','comentarios'=>0]);
                    $salas[$sala]=$live;
                    $live->update(['estado'=>'en_live','terminado_ms'=>null,'ultimo_ms'=>$ms]);
                    $perfil=is_array($e['perfil']??null)?$e['perfil']:[];
                    $c->update(['estado'=>'en_live','latido_ms'=>TikTokConsulta::ahora(),'detalle'=>null,'perfil_publico'=>['nombre'=>$this->texto($perfil['nombre']??'',150),'bio'=>$this->texto($perfil['bio']??'',500)]]);continue;
                }
                $live=$salas[$sala]??$c->lives()->where('sala',$sala)->first();if(!$live||$c->estado==='restringido')continue;$salas[$sala]=$live;
                if($tipo==='fin'){$live->update(['estado'=>'terminado','terminado_ms'=>$ms,'ultimo_ms'=>$ms]);if($c->monitorear)$c->update(['estado'=>'sin_live','detalle'=>'La transmisión terminó. Se comprobará nuevamente más adelante.']);continue;}
                if($tipo==='espectadores'){$n=filter_var($e['cantidad']??null,FILTER_VALIDATE_INT);if($n!==false&&$n>=0&&$n<=100000000)$live->update(['max_espectadores'=>max($live->max_espectadores??0,$n),'ultimo_ms'=>$ms]);continue;}
                if($tipo!=='comentario')continue;
                $id=$this->texto($e['id']??'',150);$texto=$this->texto($e['texto']??'',4000);if($id===''||trim($texto)==='')continue;
                $ocurrido=filter_var($e['ocurrido_ms']??$ms,FILTER_VALIDATE_INT);if(!$ocurrido||$ocurrido<1000000000000||$ocurrido>$ms+60000)$ocurrido=$ms;
                $lotes[$live->id]['live']=$live;$lotes[$live->id]['ultimo']=max($lotes[$live->id]['ultimo']??0,$ms);
                $lotes[$live->id]['filas'][]=['cuenta_id'=>$c->id,'live_id'=>$live->id,'publicacion_id'=>null,'clave'=>hash('sha256',$c->id.':'.$sala.':'.$id),'autor'=>$this->texto($e['autor']??'',100)?:'sin_identificador','nombre'=>$this->texto($e['nombre']??'',150),'texto'=>$texto,'origen'=>'live_publico','ocurrido_ms'=>$ocurrido,'capturado_ms'=>$ms];
            }
            foreach($lotes as$lote){$n=0;foreach(array_chunk($lote['filas'],200)as$filas)$n+=TikTokComentario::insertOrIgnore($filas);$live=$lote['live'];if($n){$live->increment('comentarios',$n);$insertados+=$n;}$live->refresh()->update(['ultimo_ms'=>max($live->ultimo_ms,$lote['ultimo'])]);}
        });return $insertados;
    }
}
