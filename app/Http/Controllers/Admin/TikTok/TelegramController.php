<?php
namespace App\Http\Controllers\Admin\TikTok;
use App\Http\Controllers\Controller;
use App\Models\{Actividad,TikTokCuenta,TikTokTelegramConfig as Config,TikTokTelegramEnvio as Envio};
use App\Services\TikTokTelegram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class TelegramController extends Controller {
 private function admin(Request $r): void {abort_unless($r->user()?->esAdmin()&&$r->user()->activo,403);}
 public function index(Request $r){$this->admin($r);$cfg=Config::actual();$cuentas=TikTokCuenta::orderBy('usuario')->get();$envios=Envio::with('cuenta')->latest('id')->paginate(25);$resumen=Envio::selectRaw('estado, COUNT(*) AS total')->groupBy('estado')->pluck('total','estado');$vista='telegram';return response()->view('admin.tiktok.telegram',compact('cfg','cuentas','envios','resumen','vista'))->header('Cache-Control','private, no-store');}
 public function guardar(Request $r){
  $this->admin($r);$d=$r->validate(['telegram_token'=>['nullable','string','regex:/^[0-9]{5,20}:[a-zA-Z0-9_-]{20,100}$/D'],'chat_id'=>['nullable','string',Rule::in([Config::DESTINO])],'alcance'=>'required|in:todas,seleccionadas','cuentas'=>'nullable|array|max:1000','cuentas.*'=>['integer',Rule::exists('tiktok_cuentas','id')]]);
  $d['chat_id']=Config::DESTINO;
  if($d['alcance']==='seleccionadas'&&empty($d['cuentas']))throw ValidationException::withMessages(['cuentas'=>'Seleccione al menos un perfil.']);
  DB::transaction(function()use($d){$cfg=Config::actual();$cfg=Config::whereKey($cfg->id)->lockForUpdate()->firstOrFail();$tokenNuevo=filled($d['telegram_token']??null);$credenciales=$tokenNuevo||$cfg->chat_id!==$d['chat_id'];if(!$tokenNuevo&&!filled($cfg->getRawOriginal('bot_token')))throw ValidationException::withMessages(['telegram_token'=>'Guarde el token del bot obtenido de @BotFather.']);
   $ids=$d['alcance']==='todas'?null:array_values(array_unique(array_map('intval',$d['cuentas'])));$datos=['chat_id'=>$d['chat_id'],'alcance'=>$d['alcance'],'cuentas'=>$ids];
   if($credenciales)$datos+=['activo'=>false,'verificada_en'=>null,'bot_usuario'=>null,'chat_tipo'=>null,'ultimo_error'=>null];if($tokenNuevo)$datos['bot_token']=$d['telegram_token'];
   $cambio=$credenciales||$cfg->alcance!==$datos['alcance']||$cfg->cuentas!==$ids;
   if($cambio){$datos['revision']=(string)Str::uuid();Envio::where('estado','pendiente')->update(['estado'=>'cancelado','detalle'=>'Cambió la configuración. No se enviará al nuevo destinatario.']);}
   $cfg->update($datos);
  });
  Actividad::registrar('tiktok_telegram_configurar','Configuración del bot actualizada; sin registrar credenciales.');return back()->with('mensaje','Configuración guardada. Compruebe la conexión antes de activar los envíos.');
 }
 public function verificar(Request $r,TikTokTelegram $servicio){
  $this->admin($r);$cfg=Config::actual();if(!filled($cfg->getRawOriginal('bot_token'))||!$cfg->chat_id)return back()->withErrors(['telegram'=>'Guarde primero el token del bot y el ID del chat.']);
  try{$datos=$servicio->verificar($cfg);}catch(\Throwable){return back()->withErrors(['telegram'=>'No se pudo comprobar el bot o el chat. Revise el token e inicie el bot desde @jeffryzeasni. Solo se permite su chat privado.']);}
  Config::whereKey(1)->where('revision',$cfg->revision)->update($datos+['verificada_en'=>now(),'ultimo_error'=>null]);return back()->with('mensaje','Bot y chat verificados. Esta comprobación no envió mensajes.');
 }
 public function activar(Request $r){
  $this->admin($r);$r->validate(['activo'=>'required|boolean']);$activo=$r->boolean('activo');
  DB::transaction(function()use($activo){$cfg=Config::actual();$cfg=Config::whereKey($cfg->id)->lockForUpdate()->firstOrFail();if($activo&&!$cfg->verificada_en)throw ValidationException::withMessages(['telegram'=>'Compruebe el bot y el chat antes de activar los envíos.']);if($cfg->activo===$activo)return;
   $cfg->update(['activo'=>$activo,'revision'=>(string)Str::uuid(),'ultimo_error'=>null]);Envio::where('estado','pendiente')->update(['estado'=>'cancelado','detalle'=>'Cambió la activación del bot. Solo se enviarán comentarios nuevos.']);
  });
  Actividad::registrar($activo?'tiktok_telegram_activar':'tiktok_telegram_pausar','Salida de comentarios por Telegram');return back()->with('mensaje',$activo?'Telegram activado para los comentarios nuevos.':'Telegram pausado. Las capturas se conservan.');
 }
}
