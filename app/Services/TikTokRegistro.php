<?php
namespace App\Services;
use App\Models\{TikTokCuenta,TikTokLive,TikTokComentario,TikTokAdjunto,TikTokFuente};
use Illuminate\Support\Facades\DB;

/** Solo recibe mensajes del proceso hijo local: no existe endpoint público de ingestión. */
class TikTokRegistro {
    private array $avatares=[];
    private function texto($v,int $limite): string{return mb_substr(str_replace("\0",'',is_scalar($v)?(string)$v:''),0,$limite);}
    private function perfil(array $perfil): array {
        $avatar=is_string($perfil['avatar']??null)?$perfil['avatar']:null;
        return ['id_usuario'=>$this->texto($perfil['id_usuario']??'',80),'usuario'=>$this->texto($perfil['usuario']??'',100),'nombre'=>$this->texto($perfil['nombre']??'',150),'bio'=>$this->texto($perfil['bio']??'',500),'region'=>is_string($perfil['region']??null)&&preg_match('/^[A-Z]{2}$/D',$perfil['region'])?$perfil['region']:null,'avatar'=>$avatar&&app(TikTokVideo::class)->urlValida($avatar)?$avatar:null];
    }
    public static function cerrar(int $cuentaId,string $motivo,int $ms): void {\Illuminate\Support\Facades\DB::table('tiktok_sesiones')->where('cuenta_id',$cuentaId)->whereNull('fin_ms')->update(['fin_ms'=>$ms,'motivo'=>$motivo]);}
    public function registrar(TikTokCuenta $c,array $eventos,?TikTokFuente $fuente=null): int {
        $insertados=0;$lotes=[];$salas=[];$sesiones=[];
        DB::transaction(function()use($c,$eventos,$fuente,&$insertados,&$lotes,&$salas,&$sesiones){
            $actual=TikTokCuenta::whereKey($c->id)->lockForUpdate()->first();if(!$actual)return;
            if($fuente){$f=TikTokFuente::whereKey($fuente->id)->where('cuenta_id',$c->id)->lockForUpdate()->first();if(!$f)return;$fuente->setRawAttributes($f->getAttributes(),true);}
            $c->setRawAttributes($actual->getAttributes(),true);$control=$fuente??$c;$keys=[];foreach($eventos as$ev)if(($ev['tipo']??null)==='comentario')$keys[]=hash('sha256',$c->id.':'.$this->texto($ev['sala']??'',40).':'.$this->texto($ev['id']??'',150));$existentes=TikTokComentario::where('cuenta_id',$c->id)->whereIn('clave',$keys)->pluck('clave')->flip()->all();
            foreach($eventos as $e){
                if(($e['v']??null)!==1)continue;
                $ms=filter_var($e['capturado_ms']??null,FILTER_VALIDATE_INT);
                if(!$ms||$ms<1000000000000||$ms>TikTokConsulta::ahora()+60000)continue;
                $tipo=$e['tipo']??'';
                if($tipo==='estado'){
                    if(!$c->monitorear)continue;
                    $estado=$e['estado']??'';if($estado==='restringido')$estado='acceso_pendiente';
                    if(!array_key_exists($estado,TikTokCuenta::ESTADOS))continue;
                    $datos=['estado'=>$estado,'latido_ms'=>TikTokConsulta::ahora(),'detalle'=>$this->texto($e['detalle']??'',300)];
                    if($estado==='acceso_pendiente'){$datos['detalle']='TikTok no habilitó el acceso a este LIVE para el capturador. Esto no indica que el perfil sea privado. El monitoreo sigue activado y volverá a comprobarlo.';$datos['reintentar_ms']=max($control->reintentar_ms??0,TikTokConsulta::ahora()+300000);}
                    $espera=filter_var($e['espera_segundos']??null,FILTER_VALIDATE_INT);
                    if($espera!==false&&$espera>=60)$datos['reintentar_ms']=max($datos['reintentar_ms']??0,$control->reintentar_ms??0,TikTokConsulta::ahora()+min(86400,$espera)*1000);
                    $control->update($datos);if(in_array($estado,['error','sin_live','no_determinado','restringido','acceso_pendiente','limitado'],true)){if($fuente)TikTokFuentes::cerrar($c,$fuente->id,$estado,$ms);else self::cerrar($c->id,$estado,$ms);}continue;
                }
                if($tipo==='perfil'){if($c->monitorear&&is_array($e['perfil']??null))$c->update(['perfil_publico'=>$this->perfil($e['perfil'])]);continue;}
                if($tipo==='latido'){$q=DB::table('tiktok_sesiones')->where('cuenta_id',$c->id)->whereNull('fin_ms');$fuente?$q->where('fuente_id',$fuente->id):$q->whereNull('fuente_id');$q->update(['ultimo_ms'=>$ms]);$control->update(['latido_ms'=>TikTokConsulta::ahora()]);continue;}
                $sala=$this->texto($e['sala']??'',40);if(!preg_match('/^\d{5,40}$/D',$sala))continue;
                if($tipo==='inicio'){
                    if(!$c->monitorear||in_array($control->estado,['restringido','acceso_pendiente'],true))continue;
                    if(!$fuente)$c->lives()->where('estado','en_live')->where('sala','!=',$sala)->update(['estado'=>'interrumpido','terminado_ms'=>$ms]);
                    $live=TikTokLive::firstOrCreate(['cuenta_id'=>$c->id,'sala'=>$sala],['titulo'=>$this->texto($e['titulo']??'',255),'iniciado_ms'=>$ms,'ultimo_ms'=>$ms,'estado'=>'en_live','comentarios'=>0]);
                    $salas[$sala]=$live;if($fuente)TikTokFuentes::cerrar($c,$fuente->id,'reconexion',$ms);else self::cerrar($c->id,'reconexion',$ms);$sesion=(string)\Illuminate\Support\Str::uuid();DB::table('tiktok_sesiones')->insert(['id'=>$sesion,'cuenta_id'=>$c->id,'live_id'=>$live->id,'fuente_id'=>$fuente?->id,'usuario_sala'=>$fuente?->usuario??$c->sala_publica_usuario,'inicio_ms'=>$ms,'ultimo_ms'=>$ms]);$sesiones[$live->id]=$sesion;
                    $live->refresh();app(TikTokVideo::class)->registrar($live,is_string($e['video_hls']??null)?$e['video_hls']:null);
                    $live->update(['estado'=>'en_live','terminado_ms'=>null,'ultimo_ms'=>$ms]+(!$live->perfil_inicio?['perfil_inicio'=>$this->perfil(is_array($e['perfil']??null)?$e['perfil']:[])]:[]));
                    $d=is_array($e['deteccion']??null)?$e['deteccion']:[];
                    if(!$live->deteccion_inicio&&($d['cuenta_consultada']??null)===$c->usuario&&in_array($d['fuente_sala']??null,['sdk','api_usuario','perfil_publico','sala_publica_indicada'],true))$live->update(['deteccion_inicio'=>['cuenta_consultada'=>$c->usuario,'fuente_sala'=>$d['fuente_sala'],'usuario_sala'=>$this->texto($d['usuario_sala']??'',24)?:null,'solo_comentarios'=>($d['solo_comentarios']??false)===true,'participacion_confirmada'=>false,'observado_ms'=>$ms]]);
                    $perfil=array_key_exists('perfil_objetivo',$e)?$e['perfil_objetivo']:($e['perfil']??[]);
                    $control->update(['estado'=>'en_live','latido_ms'=>TikTokConsulta::ahora(),'detalle'=>null]+($fuente?['sala'=>$sala,'reintentar_ms'=>null]:[]));if(is_array($perfil)&&$perfil)$c->update(['perfil_publico'=>$this->perfil($perfil)]);continue;
                }
                $live=$salas[$sala]??$c->lives()->where('sala',$sala)->first();if(!$live||in_array($control->estado,['restringido','acceso_pendiente'],true))continue;$salas[$sala]=$live;
                if($tipo==='video'){if($c->monitorear&&$live->estado==='en_live')app(TikTokVideo::class)->registrar($live,is_string($e['video_hls']??null)?$e['video_hls']:null);continue;}
                if($tipo==='fin'){if($fuente)TikTokFuentes::cerrar($c,$fuente->id,'fin_live',$ms,'terminado');else{self::cerrar($c->id,'fin_live',$ms);$live->update(['estado'=>'terminado','terminado_ms'=>$ms,'ultimo_ms'=>$ms]);}if($c->monitorear)$control->update(['estado'=>'sin_live','detalle'=>'La transmisión terminó. Se comprobará nuevamente más adelante.']);continue;}
                if($tipo==='espectadores'){$n=filter_var($e['cantidad']??null,FILTER_VALIDATE_INT);if($n!==false&&$n>=0&&$n<=100000000)$live->update(['max_espectadores'=>max($live->max_espectadores??0,$n),'ultimo_ms'=>$ms]);continue;}
                if($tipo!=='comentario')continue;
                $id=$this->texto($e['id']??'',150);$texto=$this->texto($e['texto']??'',4000);if($id===''||trim($texto)==='')continue;$clave=hash('sha256',$c->id.':'.$sala.':'.$id);if(isset($existentes[$clave]))continue;$existentes[$clave]=true;
                $ocurrido=filter_var($e['ocurrido_ms']??$ms,FILTER_VALIDATE_INT);if(!$ocurrido||$ocurrido<1000000000000||$ocurrido>$ms+60000)$ocurrido=$ms;
                $lotes[$live->id]['live']=$live;$lotes[$live->id]['ultimo']=max($lotes[$live->id]['ultimo']??0,$ms);
                $p=is_array($e['publico']??null)?$e['publico']:[];$uid=$this->texto($p['id_usuario']??'',80)?:null;$usuario=$this->texto($p['usuario']??'',100)?:null;
                $avatar=null;$url=is_string($p['avatar']??null)?$p['avatar']:null;$urlHash=null;
                if($url&&app(TikTokVideo::class)->urlValida($url)){
                    $urlHash=hash('sha256',$url);$k=$live->id.':'.$urlHash;
                    if(!isset($this->avatares[$k])){
                        $adj=TikTokAdjunto::firstOrCreate(['live_id'=>$live->id,'url_sha256'=>$urlHash],['id'=>(string)\Illuminate\Support\Str::uuid(),'cuenta_id'=>$c->id,'origen_url'=>$url,'observado_ms'=>$ms,'estado'=>'pendiente']);
                        if($adj->wasRecentlyCreated)\App\Jobs\GuardarTikTokAvatar::dispatch($adj->id);
                        if(count($this->avatares)>=10000)$this->avatares=[];$this->avatares[$k]=$adj->id;
                    }$avatar=$this->avatares[$k];
                }
                $sesiones[$live->id]??=DB::table('tiktok_sesiones')->where('live_id',$live->id)->whereNull('fin_ms')->when($fuente,fn($q)=>$q->where('fuente_id',$fuente->id),fn($q)=>$q->whereNull('fuente_id'))->value('id');
                $publicos=['usuario_sala'=>$this->texto($p['usuario_sala']??'',24)?:null,'solo_comentarios'=>($p['solo_comentarios']??false)===true,'id_evento_fuente'=>in_array($p['id_evento_fuente']??null,['tiktok','generado_por_adaptador'],true)?$p['id_evento_fuente']:'no_declarada','fecha_declarada_original'=>$this->texto($p['fecha_declarada_original']??'',30)?:null,'fecha_evento_origen'=>in_array($p['fecha_evento_origen']??null,['plataforma','reloj_capturador'],true)?$p['fecha_evento_origen']:'no_declarada','hora_ajustada_por_validacion'=>$ocurrido!==filter_var($e['ocurrido_ms']??$ms,FILTER_VALIDATE_INT),'conexion_id'=>$sesiones[$live->id]??null,'id_evento'=>$id,'sala'=>$sala,'id_usuario'=>$uid,'usuario'=>$usuario,'bio'=>$this->texto($p['bio']??'',500)?:null,'idioma_comentario'=>$this->texto($p['idioma_comentario']??'',20)?:null,'avatar_origen_sha256'=>$urlHash,'texto_normalizado_por_validacion'=>is_string($e['texto']??null)&&$texto!==$e['texto'],'texto_recortado'=>($p['texto_recortado']??false)===true,'sha256_texto_original'=>is_string($p['sha256_texto_original']??null)&&preg_match('/^[a-f0-9]{64}$/D',$p['sha256_texto_original'])?$p['sha256_texto_original']:null,'fuente'=>'TikTokLive 7.0.1','adaptador'=>'MiCV 2.0'];
                $fila=['cuenta_id'=>$c->id,'live_id'=>$live->id,'publicacion_id'=>null,'clave'=>$clave,'autor'=>$this->texto($e['autor']??'',100)?:'sin_identificador','autor_id'=>$uid,'nombre'=>$this->texto($e['nombre']??'',150),'texto'=>$texto,'origen'=>'live_publico','ocurrido_ms'=>$ocurrido,'capturado_ms'=>$ms,'avatar_id'=>$avatar];
                $fila['autor_clave']=hash('sha256',$uid?'id:'.$uid:('usuario:'.($usuario?:$fila['autor'])));
                $fila['hash_registro']=hash('sha256',TikTokIntegridad::json(TikTokIntegridad::registro($fila,$publicos)));$fila['datos_publicos']=TikTokIntegridad::json($publicos);
                $lotes[$live->id]['filas'][]=$fila;
            }
            foreach($lotes as$lote){$n=0;foreach(array_chunk($lote['filas'],200)as$filas){$n+=TikTokComentario::insertOrIgnore($filas);app(TikTokTelegram::class)->encolarClaves(array_column($filas,'clave'));}$live=$lote['live'];if($n){$live->increment('comentarios',$n);$insertados+=$n;}DB::table('tiktok_sesiones')->where('live_id',$live->id)->whereNull('fin_ms')->update(['ultimo_ms'=>$lote['ultimo']]);$live->refresh()->update(['ultimo_ms'=>max($live->ultimo_ms,$lote['ultimo'])]);}
            if($fuente)TikTokFuentes::resumir($c);
        });return $insertados;
    }
}
