<?php
namespace App\Services;
use App\Models\{TikTokCuenta,TikTokComentario};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TikTokConsulta {
    public static function ahora(): int{return (int)round(microtime(true)*1000);}
    public static function zona(): string{$z=(string)config('tiktok.zona');return in_array($z,timezone_identifiers_list(),true)?$z:'America/Costa_Rica';}
    public static function fecha(?int $ms,string $formato='d/m/Y H:i:s'): string{return $ms?CarbonImmutable::createFromTimestampMs($ms,'UTC')->setTimezone(self::zona())->format($formato):'—';}
    public function filtros(Request $r,TikTokCuenta $cuenta): array {
        $d=$r->validate(['desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde','live_id'=>'nullable|integer|min:1','publicacion_id'=>'nullable|integer|min:1','q'=>'nullable|string|max:100']);
        $hoy=CarbonImmutable::now(self::zona())->format('Y-m-d');$d['desde']=$d['desde']??$hoy;$d['hasta']=$d['hasta']??$d['desde'];
        abort_if($d['hasta']<$d['desde'],422,'Revise el intervalo de fechas.');
        if(!empty($d['live_id']))abort_unless($cuenta->lives()->whereKey($d['live_id'])->exists(),404);
        if(!empty($d['publicacion_id']))abort_unless($cuenta->publicaciones()->whereKey($d['publicacion_id'])->exists(),404);
        abort_if(!empty($d['live_id'])&&!empty($d['publicacion_id']),422,'Seleccione un live o una publicación.');
        return array_filter($d,fn($v)=>$v!==null&&$v!=='');
    }
    public function query(TikTokCuenta $c,array $f) {
        $inicio=CarbonImmutable::parse($f['desde'],self::zona())->startOfDay()->getTimestampMs();$fin=CarbonImmutable::parse($f['hasta'],self::zona())->addDay()->startOfDay()->getTimestampMs()-1;
        return TikTokComentario::where('cuenta_id',$c->id)->whereBetween('ocurrido_ms',[$inicio,$fin])
            ->when(!empty($f['live_id']),fn($q)=>$q->where('live_id',$f['live_id']))->when(!empty($f['publicacion_id']),fn($q)=>$q->where('publicacion_id',$f['publicacion_id']))
            ->when(!empty($f['q']),fn($q)=>$q->where(fn($w)=>$w->where('texto','like','%'.$f['q'].'%')->orWhere('autor','like','%'.$f['q'].'%')));
    }
    public function fila(TikTokComentario $m): array {
        return ['id'=>$m->id,'ms'=>$m->ocurrido_ms,'fecha'=>self::fecha($m->ocurrido_ms),'autor'=>$m->autor,'nombre'=>$m->nombre,'texto'=>$m->texto,'live_id'=>$m->live_id,'publicacion_id'=>$m->publicacion_id,'origen'=>$m->origen];
    }
    public function salud(): array {
        $path=config('tiktok.directorio').'/estado.json';$data=is_file($path)?json_decode((string)@file_get_contents($path),true):null;
        return ['activo'=>is_array($data)&&($data['ms']??0)>self::ahora()-25000,'simultaneos'=>(int)config('tiktok.simultaneos'),'actualizado'=>$data['ms']??null];
    }
}
