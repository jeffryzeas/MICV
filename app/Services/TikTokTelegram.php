<?php
namespace App\Services;
use App\Models\{TikTokTelegramConfig as Config,TikTokTelegramEnvio as Envio,TikTokComentario};
use Illuminate\Support\Facades\{DB,Http};

/** Salida de texto a un chat configurado. Nunca envía imágenes ni registra el token. */
class TikTokTelegram {
 public function encolarClaves(array $claves): void {
  $cfg=Config::query()->find(1);if(!$cfg?->activo||!$cfg->verificada_en||$cfg->chat_id!==Config::DESTINO)return;
  foreach(TikTokComentario::whereIn('clave',$claves)->where('origen','live_publico')->with(['live','cuenta'])->get()as$m){
   if(!$m->live||!$m->cuenta?->monitorear||!$cfg->incluye((int)$m->cuenta_id))continue;
   $p=$m->datos_publicos??[];$evento=($p['id_evento_fuente']??null)==='tiktok'&&!empty($p['id_evento'])?'sala:'.$m->live->sala.':evento:'.$p['id_evento']:'registro:'.$m->id;
   Envio::firstOrCreate(['clave'=>hash('sha256',$cfg->revision.':'.$evento)],['comentario_id'=>$m->id,'cuenta_id'=>$m->cuenta_id,'revision'=>$cfg->revision,'destino'=>$cfg->chat_id,'estado'=>'pendiente']);
  }
 }
 private function pedir(Config $cfg,string $metodo,array $datos=[]): \Illuminate\Http\Client\Response {
  if($cfg->chat_id!==Config::DESTINO||(isset($datos['chat_id'])&&(string)$datos['chat_id']!==Config::DESTINO)||($metodo==='sendMessage'&&($datos['chat_id']??null)!==Config::DESTINO))throw new \RuntimeException('El destinatario no está autorizado.');
  try{$token=$cfg->bot_token;if(!$token)throw new \RuntimeException();return Http::timeout(15)->connectTimeout(8)->post('https://api.telegram.org/bot'.$token.'/'.$metodo,$datos);}
  catch(\Throwable){throw new \RuntimeException('Telegram no devolvió una confirmación. Revise la conexión y el chat de destino.');}
 }
 public function verificar(Config $cfg): array {
  $bot=$this->pedir($cfg,'getMe')->json();if(!is_array($bot)||!($bot['ok']??false)||!($bot['result']['is_bot']??false))throw new \RuntimeException('No se pudo verificar el bot.');$chat=$this->pedir($cfg,'getChat',['chat_id'=>Config::DESTINO])->json();
  if(!is_array($bot)||!($bot['ok']??false)||!($bot['result']['is_bot']??false)||!is_array($chat)||!($chat['ok']??false)||(string)($chat['result']['id']??'')!==Config::DESTINO||($chat['result']['type']??null)!=='private')throw new \RuntimeException('No se pudo verificar el bot o el chat. Revise el token e inicie el bot desde la cuenta @jeffryzeasni. Solo se permite su chat privado.');
  return ['bot_usuario'=>mb_substr((string)($bot['result']['username']??''),0,64),'chat_tipo'=>in_array($chat['result']['type']??null,['private','group','supergroup','channel'],true)?$chat['result']['type']:'desconocido'];
 }
 public function texto(TikTokComentario $m): string {
  $p=$m->datos_publicos??[];$usuario=$p['usuario']??$m->autor;$usuario=preg_match('/^[a-zA-Z0-9_.]{2,100}$/D',(string)$usuario)?'@'.$usuario:(string)$usuario;
  $chat=$p['usuario_sala']??$m->live?->deteccion_inicio['usuario_sala']??null;
  $limpiar=fn($v)=>preg_replace('/[\r\n\t\x00-\x1f]+/u',' ',(string)$v);
  $cabecera="Comentario de LIVE\nNombre: ".$limpiar($m->nombre?:'No entregado')."\nUsuario: ".$limpiar($usuario)."\nPerfil monitoreado: @".$m->cuenta->usuario."\nChat de origen: ".($chat?'@'.$chat:'No entregado')."\nID de sala / LIVE: ".($m->live?->sala?:'No entregado')."\nHora registrada: ".TikTokConsulta::fecha($m->ocurrido_ms).' · '.TikTokConsulta::zona()."\n\nComentario:\n";
  $unidades=fn($s)=>intdiv(strlen(mb_convert_encoding($s,'UTF-16LE','UTF-8')),2);$texto=$m->texto;
  if($unidades($cabecera.$texto)>4096){$fin="\n[Texto recortado para Telegram; completo en Auditoría.]";$limite=4096-$unidades($cabecera.$fin);$salida='';$usadas=0;foreach(preg_split('//u',$texto,-1,PREG_SPLIT_NO_EMPTY)as$letra){$n=$unidades($letra);if($usadas+$n>$limite)break;$salida.=$letra;$usadas+=$n;}$texto=$salida.$fin;}
  return $cabecera.$texto;
 }
 public function procesarUno(): bool {
  // Una interrupción durante la petición no permite asumir que Telegram no la recibió.
  Envio::where('estado','enviando')->where('iniciado_en','<',now()->subMinute())->update(['estado'=>'indeterminado','detalle'=>'Sin confirmación tras una interrupción. Revise Telegram antes de repetir el envío.']);
  $reserva=DB::transaction(function(){
   $cfg=Config::whereKey(1)->lockForUpdate()->first();if($cfg&&$cfg->chat_id!==Config::DESTINO){$cfg->update(['activo'=>false,'verificada_en'=>null,'ultimo_error'=>'El destino no está autorizado. Solo se permite el chat privado de @jeffryzeasni.']);Envio::where('estado','pendiente')->update(['estado'=>'cancelado','detalle'=>'Destino no autorizado.']);return null;}if(!$cfg?->activo||!$cfg->verificada_en||$cfg->no_antes_de?->isFuture())return null;
   Envio::where('estado','pendiente')->where('revision','!=',$cfg->revision)->update(['estado'=>'cancelado','detalle'=>'Cambió la configuración del destinatario o del alcance.']);
   $e=Envio::where('estado','pendiente')->where('revision',$cfg->revision)->orderBy('id')->lockForUpdate()->first();if(!$e)return null;
   $m=$e->comentario()->with(['live','cuenta'])->first();
   if(!$m||!$m->cuenta?->monitorear||!$cfg->incluye((int)$m->cuenta_id)||$e->destino!==Config::DESTINO||$cfg->chat_id!==Config::DESTINO){$e->update(['estado'=>'cancelado','detalle'=>'El perfil está pausado o ya no pertenece al destino configurado.']);return null;}
   $e->update(['estado'=>'enviando','iniciado_en'=>now(),'intentos'=>$e->intentos+1]);$cfg->update(['no_antes_de'=>now()->addSeconds($cfg->chat_tipo==='private'?2:4)]);return [$e,$cfg,$m];
  });
  if(!$reserva)return false;[$e,$cfg,$m]=$reserva;
  if($m->hash_registro&&!hash_equals($m->hash_registro,hash('sha256',TikTokIntegridad::json(TikTokIntegridad::original($m))))){$e->update(['estado'=>'fallido','detalle'=>'El registro no coincide con su hash de captura. No se envió.']);return true;}
  try{$r=$this->pedir($cfg,'sendMessage',['chat_id'=>$e->destino,'text'=>$this->texto($m),'link_preview_options'=>['is_disabled'=>true]]);}
  catch(\Throwable){$e->update(['estado'=>'indeterminado','detalle'=>'No se recibió confirmación de Telegram. Revise el chat antes de repetir el envío.']);return true;}
  $d=$r->json();
  if($r->successful()&&is_array($d)&&($d['ok']??false)&&isset($d['result']['message_id'])){$e->update(['estado'=>'enviado','mensaje_id'=>(string)$d['result']['message_id'],'enviado_en'=>now(),'detalle'=>null]);return true;}
  if($r->status()===429||($d['error_code']??null)===429){$segundos=max(1,min(86400,(int)($d['parameters']['retry_after']??60)));$e->update(['estado'=>'pendiente','detalle'=>'Telegram solicitó una espera antes de reintentar.']);Config::whereKey(1)->where('revision',$cfg->revision)->update(['no_antes_de'=>now()->addSeconds($segundos+1),'ultimo_error'=>'Telegram está limitando la frecuencia. Se respetará la espera indicada.']);return true;}
  $cierto=in_array($r->status(),[400,401,403,404],true)||is_array($d)&&($d['ok']??null)===false&&$r->status()<500;
  $e->update(['estado'=>$cierto?'fallido':'indeterminado','detalle'=>$cierto?'Telegram rechazó el mensaje. Revise el bot, el ID del chat y sus permisos.':'La respuesta no confirmó el envío. Revise el chat antes de repetirlo.']);
  if($cierto)Config::whereKey(1)->where('revision',$cfg->revision)->update(['activo'=>false,'verificada_en'=>null,'ultimo_error'=>'Telegram rechazó el envío. La salida del bot se pausó; la captura de comentarios continúa.']);
  return true;
 }
}
