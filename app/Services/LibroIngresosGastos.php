<?php
namespace App\Services;
use App\Models\{Cyber, ContableMovimiento, User};
use App\Models\Pos\PosVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class LibroIngresosGastos {
 public static function venta(PosVenta $v): void {
  $negocio=Cyber::findOrFail($v->cyber_id);
  if(!$negocio->contabilidad_pos || !$negocio->moduloHabilitado('contabilidad')) return;
  $cfg=\App\Models\Pos\PosConfig::para($negocio);
  ContableMovimiento::firstOrCreate(['venta_id'=>$v->id],['cyber_id'=>$v->cyber_id,'user_id'=>$v->user_id,'clave_operacion'=>(string)Str::uuid(),'fecha'=>$v->created_at->copy()->timezone(\App\Support\Negocios::ZONAS[$negocio->pais]??config('app.timezone'))->toDateString(),'tipo'=>'ingreso','categoria'=>'Ventas POS','concepto'=>'Venta '.$v->numero,'monto'=>$v->total,'moneda'=>$cfg->moneda,'referencia'=>$v->numero,'origen'=>'pos']);
 }
 public static function anularVenta(PosVenta $v, User $u, string $motivo): void {
  ContableMovimiento::where('cyber_id',$v->cyber_id)->where('venta_id',$v->id)->where('estado','activo')->update(['estado'=>'anulado','motivo_anulacion'=>'POS: '.$motivo,'anulada_por'=>$u->id,'anulada_en'=>now()]);
 }
 public static function manual(Cyber $negocio, User $u, array $d): ContableMovimiento {
  return DB::transaction(function()use($negocio,$u,$d){
   Cyber::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
   $anterior=ContableMovimiento::where('cyber_id',$negocio->id)->where('clave_operacion',$d['clave_operacion'])->first();
   if($anterior)return $anterior;
   $cfg=\App\Models\Pos\PosConfig::para($negocio);
   return ContableMovimiento::create($d+['cyber_id'=>$negocio->id,'user_id'=>$u->id,'moneda'=>$cfg->moneda,'origen'=>'manual']);
  });
 }
}
