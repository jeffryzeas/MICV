<?php
namespace App\Services\Desktop;
use Illuminate\Support\Facades\Storage;
class WorkspaceFiles {
 public const FIELDS=['cybers'=>['logo'],'users'=>['chat_foto'],'configuracion'=>['logo','escudo'],'paises'=>['escudo'],'clientes'=>['foto'],'tarjetas'=>['logo'],'pos_productos'=>['foto'],'jz_mensajes'=>['archivo','miniatura']];
 public static function path(string $path):?string {
  abort_unless(preg_match('~^(fotos|productos|logos|marca|escudos|tarjetas|chat)/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp|pdf|webm|ogg|m4a|mp3|wav|docx|xlsx|pptx)$~D',$path),422,'Ruta de archivo no válida.');
  $root=realpath(Storage::disk('local')->path(''));$candidate=Storage::disk('local')->path($path);$real=realpath($candidate);
  if($real===false)return null;
  abort_unless($root && $real===$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path) && !is_link($candidate) && is_file($real),403);
  abort_if(filesize($real)>50*1024*1024,413);return $real;
 }
 public static function describe(array $tables):array {
  $files=[];
  foreach(self::FIELDS as $table=>$fields)foreach($tables[$table]??[] as $row)foreach($fields as $field){
   if(empty($row[$field]))continue;$path=(string)$row[$field];$real=self::path($path);
   $files[]=['table'=>$table,'key'=>$row[$table==='paises'?'codigo':'id'],'field'=>$field,'path'=>$path,'missing'=>$real===null,'size'=>$real===null?0:filesize($real),'sha256'=>$real===null?null:hash_file('sha256',$real)];
  }
  return $files;
 }
}
