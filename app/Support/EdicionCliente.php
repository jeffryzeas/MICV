<?php
namespace App\Support;
use App\Models\{User,Cliente};
use Illuminate\Http\Request;
class EdicionCliente {
 public const GRUPOS=['datos'=>'Datos personales, contacto y perfil','documento'=>'País y documento de identidad','foto'=>'Foto del currículum y chat','cv'=>'Estudios, experiencia, idiomas, habilidades y referencias'];
 public static function campos(string $grupo): array {
  return match($grupo){'documento'=>['pais','tipo_documento','numero_documento','fecha_nacimiento','municipio_nacimiento','departamento_nacimiento'],'foto'=>['foto','quitar_foto'],'cv'=>['estudios','experiencias','idiomas','habilidades','referencias'],'datos'=>array_values(array_diff(\App\Services\ClienteGuardar::CAMPOS,['notas','fecha_nacimiento','municipio_nacimiento','departamento_nacimiento'])),default=>[]};
 }
 public static function permitidos(User $u): array {return $u->edicion_cliente===null?array_keys(self::GRUPOS):array_values(array_intersect(array_keys(self::GRUPOS),$u->edicion_cliente));}
 public static function puede(User $u,string $grupo): bool {return $u->esCliente() && in_array($grupo,self::permitidos($u),true);}
 public static function mapa(User $u): array {$m=[];foreach(self::GRUPOS as $k=>$label)foreach(self::campos($k) as $campo)$m[$campo]=self::puede($u,$k);return $m;}
 public static function validarPeticion(Request $r,Cliente $c): array {
  $u=$r->user();abort_unless($u->esCliente() && (int)$c->user_id===(int)$u->id,403);
  $grupos=self::permitidos($u);abort_unless($grupos,403,'Su cuenta tiene acceso de consulta. Solicite permiso de edición.');
  $campos=[];foreach($grupos as $g)$campos=array_merge($campos,self::campos($g));
  foreach(array_keys($r->all()) as $key)abort_unless(in_array($key,array_merge($campos,['_token','_method']),true),403,'No tiene permiso para modificar este campo.');
  return $campos;
 }
}
