<?php
namespace App\Services;
use App\Models\{TikTokCuenta,TikTokFuente,TikTokLive};
use Illuminate\Support\Facades\DB;

/** Cada conexión tiene estado propio; la cuenta resume todas sus fuentes. */
class TikTokFuentes {
 public static function cerrar(TikTokCuenta $c,?int $fuenteId,string $motivo,int $ms,string $estadoLive='interrumpido'): void {
  $q=DB::table('tiktok_sesiones')->where('cuenta_id',$c->id)->whereNull('fin_ms');$fuenteId===null?$q->whereNull('fuente_id'):$q->where('fuente_id',$fuenteId);
  $ids=(clone $q)->pluck('live_id')->unique();$q->update(['fin_ms'=>$ms,'motivo'=>$motivo]);
  foreach($ids as$id)if(!DB::table('tiktok_sesiones')->where('live_id',$id)->whereNull('fin_ms')->exists())TikTokLive::whereKey($id)->where('estado','en_live')->update(['estado'=>$estadoLive,'terminado_ms'=>$ms]);
 }
 public static function resumir(TikTokCuenta $c): void {
  $actual=TikTokCuenta::find($c->id);if(!$actual)return;$c->setRawAttributes($actual->getAttributes(),true);
  $fs=$c->fuentes()->get();if($fs->isEmpty())return;
  if(!$c->monitorear){TikTokCuenta::whereKey($c->id)->where('monitorear',false)->update(['estado'=>'inactivo']);return;}
  $activas=$fs->where('estado','en_live')->count();$estado='pendiente';
  foreach(['en_live','conectando','pendiente','acceso_pendiente','limitado','error','no_determinado','sin_live','inactivo']as$e)if($fs->contains('estado',$e)){$estado=$e;break;}
  $espera=$fs->pluck('reintentar_ms')->filter(fn($v)=>$v&&$v>TikTokConsulta::ahora())->min();
  TikTokCuenta::whereKey($c->id)->where('monitorear',true)->update(['estado'=>$estado,'detalle'=>$activas?($activas.' de '.$fs->count().' fuentes con chat conectado.'):('Monitoreo activado para '.$fs->count().' fuentes. Revise el estado de cada sala.'),'latido_ms'=>$fs->max('latido_ms'),'reintentar_ms'=>$espera]);
  if($actual=TikTokCuenta::find($c->id))$c->setRawAttributes($actual->getAttributes(),true);
 }
 public static function filas(TikTokCuenta $c): array {
  return $c->fuentes()->orderBy('orden')->orderBy('id')->get()->map(fn($f)=>['id'=>$f->id,'usuario'=>$f->usuario,'estado'=>$c->monitorear?$f->estado:'inactivo','etiqueta'=>$c->monitorear?($f->estado==='en_live'?'Chat conectado':(TikTokCuenta::ESTADOS[$f->estado]??$f->estado)):'Pausado','detalle'=>$f->detalle,'sala'=>$f->sala,'reintento'=>$f->reintentar_ms&&$f->reintentar_ms>TikTokConsulta::ahora()?TikTokConsulta::fecha($f->reintentar_ms):null])->all();
 }
}
