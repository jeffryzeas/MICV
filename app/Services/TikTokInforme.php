<?php
namespace App\Services;
use App\Models\{TikTokExportacion,TikTokLive,TikTokAdjunto};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use ZipArchive;

/** Fija un snapshot legible, adjuntos disponibles y PDF; no certifica identidad. */
class TikTokInforme
{
 private array $adjuntos=[];
 private array $archivos=[];
 private string $dir;
 private function guardar(string $rel,string $bytes,string $tipo): void {
  $disk=Storage::disk('local');if(!$disk->put($this->dir.'/'.$rel,$bytes,['visibility'=>'private']))throw new \RuntimeException('No se pudo guardar el informe.');
  $this->archivos[$rel]=['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes),'tipo'=>$tipo];
 }
 private function foto(?TikTokAdjunto $a): ?array {
  if(!$a)return null;if(isset($this->adjuntos[$a->id]))return $this->adjuntos[$a->id];
  $d=['id'=>$a->id,'estado'=>$a->estado,'observado_ms'=>$a->observado_ms,'descargado_ms'=>$a->descargado_ms,'sha256'=>$a->sha256,'bytes'=>$a->bytes,'mime'=>$a->mime,'archivo'=>null,'vista'=>null,'origen_url_sha256'=>$a->url_sha256];$disk=Storage::disk('local');
  if($a->estado==='listo'&&$a->archivo&&$disk->exists($a->archivo)){
   $bytes=$disk->get($a->archivo);if(hash('sha256',$bytes)===$a->sha256){$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$a->mime]??'bin';$d['archivo']='fotos/'.$a->id.'.'.$ext;$this->guardar($d['archivo'],$bytes,'foto_publica_original');
    if($a->miniatura&&$disk->exists($a->miniatura)){$vista=$disk->get($a->miniatura);$i=@getimagesizefromstring($vista);if(strlen($vista)<=65536&&$i&&in_array($i['mime'],['image/jpeg','image/png','image/webp'],true)){$d['vista']='vistas/'.$a->id.'.'.(['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$i['mime']]);$this->guardar($d['vista'],$vista,'representacion_visual');$d['vista_mime']=$i['mime'];}}
   }else $d['estado']='integridad_no_coincide';
  }elseif($a->estado==='listo')$d['estado']='archivo_no_disponible';
  return $this->adjuntos[$a->id]=$d;
 }
 private function datosPdf(Collection $filas): array {
  $autores=[];$imagenes=[];$disk=Storage::disk('local');
  foreach($filas as$f){
   $r=$f['registro'];$key=$r['autor_id']?'id:'.$r['autor_id']:'usuario:'.$r['autor'];
   if(!isset($autores[$key]))$autores[$key]=['autor'=>$r['publicos']['usuario']??$r['autor'],'nombre'=>$r['nombre'],'id'=>$r['autor_id'],'bio'=>$r['publicos']['bio']??null,'comentarios'=>0,'foto'=>$f['foto'],'mensajes'=>[],'es_creador'=>false,'criterio_creador'=>null];
   $autores[$key]['comentarios']++;$autores[$key]['mensajes'][]=$f;
   if($f['contexto']['criterio_creador']??null){$autores[$key]['es_creador']=true;$autores[$key]['criterio_creador']=$f['contexto']['criterio_creador'];}
   if(!$autores[$key]['foto']&&$f['foto'])$autores[$key]['foto']=$f['foto'];
   if(($f['foto']['vista']??null)&&!isset($imagenes[$f['foto']['id']]))$imagenes[$f['foto']['id']]='data:'.$f['foto']['vista_mime'].';base64,'.base64_encode($disk->get($this->dir.'/'.$f['foto']['vista']));
  }
  uasort($autores,fn($a,$b)=>($b['es_creador']<=>$a['es_creador'])?:strnatcasecmp($a['autor'],$b['autor']));return [array_values($autores),$imagenes];
 }
 /** Coincidencia de identificadores públicos, sin afirmar identidad personal. */
 private function criterioCreador($m,string $usuario): ?string {
  if(!$m->live||$m->origen!=='live_publico')return null;
  $dueno=$m->live->perfil_inicio??[];$uid=(string)($dueno['id_usuario']??'');
  if($uid!==''&&$m->autor_id)return hash_equals($uid,(string)$m->autor_id)?'ID público del titular registrado al iniciar el LIVE':null;
  $alias=$m->datos_publicos['usuario']??$m->autor;$titular=(string)($dueno['usuario']??'');
  if($titular!=='')return $alias!==''&&strcasecmp(ltrim($alias,'@'),$titular)===0?'Usuario público coincidente con el titular registrado de la sala; sin cotejo de ID':null;
  if($m->live->deteccion_inicio)return null;
  return $alias!==''&&strcasecmp(ltrim($alias,'@'),$usuario)===0?'Usuario público coincidente con la cuenta monitoreada; sin cotejo de ID':null;
 }

 private function zip(string $path,array $files): void {
  $disk=Storage::disk('local');$zip=new ZipArchive();if($zip->open($disk->path($path),ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('No se pudo crear el paquete.');
  foreach($files as$file)if(!$zip->addFile($disk->path($this->dir.'/'.$file),$file))throw new \RuntimeException('No se pudo adjuntar un archivo del informe.');if(!$zip->close())throw new \RuntimeException('No se pudo cerrar el paquete.');
 }
 public function crear(TikTokExportacion $e,TikTokConsulta $consulta,TikTokPdf $pdf): array {
  $this->dir='tiktok/exportaciones/'.$e->id;$this->adjuntos=[];$this->archivos=[];$disk=Storage::disk('local');$disk->makeDirectory($this->dir);$inicio=TikTokConsulta::ahora();
  $q=$consulta->query($e->cuenta,$e->filtros)->where('id','<=',$e->hasta_id)->with(['avatar','live','publicacion.cuenta'])->orderBy('ocurrido_ms')->orderBy('id');$autores=[];
  $info=['comentarios'=>0,'identificadores'=>0,'fotos_disponibles'=>0,'fotos_pendientes'=>0,'fotos_no_disponibles'=>0,'sellados'=>0,'sin_sello_original'=>0,'hash_no_coincide'=>0,'primero_ms'=>null,'ultimo_ms'=>null,'primera_captura_ms'=>null,'ultima_captura_ms'=>null];
  $fp=fopen($disk->path($this->dir.'/datos.ndjson'),'wb');if(!$fp)throw new \RuntimeException('No se pudo abrir el snapshot.');chmod($disk->path($this->dir.'/datos.ndjson'),0600);$hash=hash_init('sha256');$meta=$e->metadatos??[];$referenciaSala=['cuenta_observada'=>$e->cuenta->usuario,'anfitrion_declarado'=>$e->cuenta->anfitrion?->usuario,'relacion_verificada'=>false];$sesiones=!empty($e->filtros['live_id'])?\Illuminate\Support\Facades\DB::table('tiktok_sesiones')->where('live_id',$e->filtros['live_id'])->orderBy('inicio_ms')->get()->map(fn($s)=>(array)$s)->all():[];
  try{$q->chunk(200,function($filas)use($fp,$hash,&$info,&$autores,$e,$meta,$referenciaSala){foreach($filas as$m){$registro=TikTokIntegridad::original($m);$ok=$m->hash_registro?hash_equals($m->hash_registro,hash('sha256',TikTokIntegridad::json($registro))):null;
    $info[$ok===null?'sin_sello_original':($ok?'sellados':'hash_no_coincide')]++;
    $foto=$this->foto($m->avatar);$dato=['id'=>$m->id,'contexto'=>$referenciaSala+['deteccion_inicio'=>$m->live?->deteccion_inicio,'criterio_creador'=>$this->criterioCreador($m,$e->cuenta->usuario),'sala'=>$m->live?->sala,'live_id'=>$m->live_id,'publicacion_id'=>$m->publicacion_id,'video_id'=>$m->publicacion?->video_id,'publicacion_url'=>$m->publicacion?->url()],'registro'=>$registro,'hash_captura'=>$m->hash_registro,'hash_captura_coincide'=>$ok,'foto'=>$foto];$linea=TikTokIntegridad::json($dato)."\n";if(fwrite($fp,$linea)!==strlen($linea))throw new \RuntimeException('El snapshot no se pudo escribir.');hash_update($hash,$linea);
    $info['comentarios']++;$autores[$m->autor_id?'id:'.$m->autor_id:'usuario:'.$m->autor]=true;
    $info['primero_ms']=min($info['primero_ms']??$m->ocurrido_ms,$m->ocurrido_ms);$info['ultimo_ms']=max($info['ultimo_ms']??0,$m->ocurrido_ms);
    $info['primera_captura_ms']=min($info['primera_captura_ms']??$m->capturado_ms,$m->capturado_ms);$info['ultima_captura_ms']=max($info['ultima_captura_ms']??0,$m->capturado_ms);
   }$e->update(['metadatos'=>array_merge($meta,['fase'=>'datos','preparados'=>$info['comentarios']])]);});}finally{fclose($fp);}
  if(!$info['comentarios'])throw new \RuntimeException('No hay datos para exportar.');$digest=hash_final($hash);$info['identificadores']=count($autores);unset($autores);
  foreach($this->adjuntos as$a)$info[$a['archivo']?'fotos_disponibles':(in_array($a['estado'],['pendiente','procesando'],true)?'fotos_pendientes':'fotos_no_disponibles')]++;
  $this->archivos['datos.ndjson']=['sha256'=>$digest,'bytes'=>$disk->size($this->dir.'/datos.ndjson'),'tipo'=>'snapshot_registros'];
  $live=!empty($e->filtros['live_id'])?TikTokLive::find($e->filtros['live_id']):null;$partes=(int)ceil($info['comentarios']/200);$base=['cuenta'=>$e->cuenta,'live'=>$live,'filtros'=>$e->filtros,'resumen'=>$info,'exportacion'=>$e,'hashDatos'=>$digest,'totalPartes'=>$partes,'generado'=>TikTokIntegridad::utc($inicio),'sesiones'=>$sesiones,'referenciaSala'=>$referenciaSala];
  $fp=fopen($disk->path($this->dir.'/datos.ndjson'),'rb');$lote=[];$parte=0;$procesados=0;$pdfs=[];
  $render=function()use(&$lote,&$parte,&$procesados,&$pdfs,$base,$pdf,$e,$meta,$info){if(!$lote)return;[$autores,$imagenes]=$this->datosPdf(collect($lote));$parte++;$bytes=$pdf->generar($base+['filas'=>collect($lote),'autores'=>$autores,'imagenes'=>$imagenes,'parte'=>$parte]);if(!str_starts_with($bytes,'%PDF-'))throw new \RuntimeException('Chromium no generó un PDF válido.');$file='informe-parte-'.str_pad((string)$parte,3,'0',STR_PAD_LEFT).'.pdf';$this->guardar($file,$bytes,'informe_pdf');$pdfs[]=$file;$procesados+=count($lote);$lote=[];$e->update(['procesados'=>$procesados,'metadatos'=>array_merge($meta,['fase'=>'pdf','resumen'=>$info])]);};
  try{while(($line=fgets($fp))!==false){$lote[]=json_decode($line,true,64,JSON_THROW_ON_ERROR);if(count($lote)>=200)$render();}$render();}finally{fclose($fp);}
  $this->guardar('verificar.py',file_get_contents(resource_path('tiktok/verificar_evidencia.py')),'verificador_sin_red');
  $manifiesto=['formato'=>'MiCV-evidencia-publica-1','id'=>$e->id,'fuente'=>'TikTokLive 7.0.1 / MiCV 2.0','cuenta'=>$e->cuenta->usuario,'sala'=>$live?->sala,'referencia_sala'=>$referenciaSala,'filtros'=>$e->filtros,'hasta_id'=>$e->hasta_id,'solicitado_utc'=>$e->created_at->clone()->utc()->format('Y-m-d\TH:i:s\Z'),'preparacion_inicio_utc'=>TikTokIntegridad::utc($inicio),'preparacion_fin_utc'=>TikTokIntegridad::utc(TikTokConsulta::ahora()),'referencia'=>$meta['referencia']??null,'solicitante'=>$meta['solicitante']??null,'resumen'=>$info,'sesiones'=>$sesiones,'sha256_datos'=>$digest,'archivos'=>$this->archivos,'alcance'=>'Integridad respecto de este manifiesto. Sin firma de TikTok, sello de tiempo externo, certificación de identidad o garantía de exhaustividad. Fotos descargadas después de su observación; el estado disponible se fija durante la preparación.'];
  if(!$disk->put($this->dir.'/manifiesto.json',json_encode($manifiesto,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),['visibility'=>'private']))throw new \RuntimeException('No se pudo guardar el manifiesto.');
  $paquete=$this->dir.'/paquete-evidencia.zip';$this->zip($paquete,array_merge(array_keys($this->archivos),['manifiesto.json']));
  $archivo=$this->dir.'/'.$pdfs[0];if(count($pdfs)>1){$archivo=$this->dir.'/informes-pdf.zip';$this->zip($archivo,$pdfs);}
  if(!TikTokExportacion::whereKey($e->id)->exists())throw new \RuntimeException('El monitoreo fue eliminado.');
  return ['archivo'=>$archivo,'paquete'=>$paquete,'sha256_datos'=>$digest,'total'=>$info['comentarios'],'procesados'=>$info['comentarios'],'metadatos'=>array_merge($meta,['fase'=>'listo','resumen'=>$info])];
 }
}
