<?php
namespace App\Http\Controllers\Admin\TikTok;
use App\Http\Controllers\Controller;
use App\Models\{TikTokCuenta,TikTokComentario};
use App\Services\{TikTokConsulta,TikTokIntegridad};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller {
 private function admin(Request $r): void {abort_unless($r->user()?->esAdmin()&&$r->user()->activo,403);}
 public function index(Request $r,TikTokCuenta $cuenta,TikTokConsulta $consulta){
  $this->admin($r);$d=$r->validate(['vista'=>'nullable|in:mensajes,participantes,salas','actor'=>'nullable|string|max:100','corte'=>'nullable|integer|min:0','orden'=>'nullable|in:reciente,antiguo']);
  $min=$cuenta->comentarios()->min('ocurrido_ms');$max=$cuenta->comentarios()->max('ocurrido_ms');$r->mergeIfMissing(['desde'=>TikTokConsulta::fecha($min?:TikTokConsulta::ahora(),'Y-m-d'),'hasta'=>TikTokConsulta::fecha($max?:TikTokConsulta::ahora(),'Y-m-d')]);
  $f=$consulta->filtros($r,$cuenta);$vista=$d['vista']??'mensajes';$actor=$d['actor']??null;$corte=min((int)($d['corte']??PHP_INT_MAX),(int)($cuenta->comentarios()->max('id')??0));$orden=$d['orden']??'reciente';
  $base=$consulta->query($cuenta,$f)->where('id','<=',$corte)->when($actor!==null&&$actor!=='',fn($q)=>$q->where(fn($w)=>$w->where('autor_clave',$actor)->orWhere(fn($a)=>$a->whereNull('autor_clave')->where('autor',$actor))));
  $parametros=array_merge($f,['vista'=>$vista,'corte'=>$corte,'orden'=>$orden],$actor!==null?['actor'=>$actor]:[]);$r->merge($parametros);
  $resumen=['mensajes'=>(clone$base)->count(),'participantes'=>DB::query()->fromSub((clone$base)->selectRaw('COALESCE(autor_clave, autor) AS identidad')->distinct()->toBase(),'identidades')->count(),'salas'=>(clone$base)->whereNotNull('live_id')->distinct()->count('live_id')];
  $registros=null;$grupos=null;$perfiles=collect();$salas=null;$lives=collect();$seleccion=$actor?(clone$base)->with('avatar')->latest('id')->first():null;
  if($vista==='mensajes')$registros=(clone$base)->with(['avatar','live'])->orderBy('ocurrido_ms',$orden==='antiguo'?'asc':'desc')->orderBy('id',$orden==='antiguo'?'asc':'desc')->paginate(40)->appends($parametros);
  if($vista==='participantes'){$grupos=(clone$base)->selectRaw('COALESCE(autor_clave, autor) AS identidad, COUNT(*) AS mensajes, MAX(id) AS ultimo_id, MIN(ocurrido_ms) AS primero_ms, MAX(ocurrido_ms) AS ultimo_ms')->groupByRaw('COALESCE(autor_clave, autor)')->orderByDesc('mensajes')->orderBy('identidad')->paginate(30)->appends($parametros);$perfiles=TikTokComentario::where('cuenta_id',$cuenta->id)->whereIn('id',$grupos->pluck('ultimo_id'))->with('avatar')->get()->keyBy('id');}
  if($vista==='salas'){$salas=(clone$base)->whereNotNull('live_id')->selectRaw('live_id, COUNT(*) AS mensajes, MIN(ocurrido_ms) AS primero_ms, MAX(ocurrido_ms) AS ultimo_ms')->groupBy('live_id')->orderByDesc('ultimo_ms')->paginate(30)->appends($parametros);$lives=$cuenta->lives()->whereIn('id',$salas->pluck('live_id'))->get()->keyBy('id');}
  $perfil=$consulta->perfil($cuenta);
  return response()->view('admin.tiktok.auditoria',compact('cuenta','f','vista','actor','corte','orden','parametros','resumen','registros','grupos','perfiles','salas','lives','perfil','seleccion'))->header('Cache-Control','private, no-store');
 }
 public function registro(Request $r,TikTokCuenta $cuenta,TikTokComentario $comentario,TikTokConsulta $consulta){
  $this->admin($r);abort_unless((int)$comentario->cuenta_id===(int)$cuenta->id,404);$comentario->load(['avatar','live','publicacion']);$fila=$consulta->fila($comentario);$original=TikTokIntegridad::original($comentario);$actual=hash('sha256',TikTokIntegridad::json($original));$integridad=$comentario->hash_registro?(hash_equals($comentario->hash_registro,$actual)?'coincide':'difiere'):'sin_sello';$vista='registro';
  return response()->view('admin.tiktok.registro',compact('cuenta','comentario','fila','actual','integridad','vista'))->header('Cache-Control','private, no-store');
 }
}
