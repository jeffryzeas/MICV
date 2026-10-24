<?php
namespace App\Support;
use Illuminate\Http\Request;
class Exportacion {
 public static function orientacion(Request $r,int $columnas=5): string {
  $d=$r->validate(['orientacion'=>'nullable|in:auto,vertical,horizontal']);
  return match($d['orientacion']??'auto'){'vertical'=>'portrait','horizontal'=>'landscape',default=>$columnas>4?'landscape':'portrait'};
 }
}
