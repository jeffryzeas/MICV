<?php
namespace App\Services;
use App\Models\TikTokComentario;
class TikTokIntegridad {
 public static function json(array $datos): string {
  $ordenar=function($v)use(&$ordenar){if(!is_array($v))return$v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as$k=>$x)$v[$k]=$ordenar($x);return$v;};
  return json_encode($ordenar($datos),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_LINE_TERMINATORS|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
 }
 public static function registro(array $fila,array $publicos): array {
  return ['version'=>1,'cuenta_id'=>(int)$fila['cuenta_id'],'live_id'=>isset($fila['live_id'])?(int)$fila['live_id']:null,'clave'=>$fila['clave'],'autor'=>$fila['autor'],'autor_id'=>$fila['autor_id']??null,'nombre'=>$fila['nombre']??null,'texto'=>$fila['texto'],'origen'=>$fila['origen'],'ocurrido_ms'=>(int)$fila['ocurrido_ms'],'capturado_ms'=>(int)$fila['capturado_ms'],'publicos'=>$publicos];
 }
 public static function original(TikTokComentario $m): array{return self::registro($m->getAttributes(),$m->datos_publicos??[]);}
 public static function utc(?int $ms): string{return $ms?\Carbon\CarbonImmutable::createFromTimestampMs($ms,'UTC')->format('Y-m-d\TH:i:s.v\Z'):'No registrado';}
}
