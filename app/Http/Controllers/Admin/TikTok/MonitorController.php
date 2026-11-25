<?php
namespace App\Http\Controllers\Admin\TikTok;
use App\Http\Controllers\Controller;
use App\Jobs\ExportarTikTok;
use App\Models\{Actividad,TikTokCuenta,TikTokLive,TikTokPublicacion,TikTokComentario,TikTokExportacion,TikTokAdjunto};
use App\Services\{TikTokConsulta,TikTokVideo};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonitorController extends Controller {
    private function admin(Request $r): void{abort_unless($r->user()?->esAdmin()&&$r->user()->activo,403);}
    private function privado($r){$r->headers->set('Cache-Control','private, no-store');return $r;}
    public function index(Request $r,TikTokConsulta $consulta){$this->admin($r);$cuentas=TikTokCuenta::withCount(['lives','comentarios'])->orderBy('usuario')->paginate(20);$salud=$consulta->salud();return $this->privado(response()->view('admin.tiktok.index',compact('cuentas','salud')));}
    public function store(Request $r){
        $this->admin($r);if(is_string($r->input('usuario')))$r->merge(['usuario'=>mb_strtolower(ltrim(trim($r->input('usuario')),'@'))]);
        $d=$r->validate(['usuario'=>['required','string','regex:/^[a-z0-9_.]{2,24}$/D','unique:tiktok_cuentas,usuario'],'nombre'=>'nullable|string|max:150','notas'=>'nullable|string|max:1000']);
        $c=TikTokCuenta::create($d);Actividad::registrar('tiktok_cuenta_crear','@'.$c->usuario);return redirect()->route('admin.tiktok.cuenta',$c)->with('mensaje','Cuenta registrada. Active la captura cuando quiera comenzar a guardar sus lives públicos.');
    }
    public function cuenta(Request $r,TikTokCuenta $cuenta,TikTokConsulta $consulta){
        $this->admin($r);$f=$consulta->filtros($r,$cuenta);$q=$consulta->query($cuenta,$f);$total=(clone$q)->count();$cursor=(clone$q)->max('id')??0;
        $comentarios=$q->with('avatar')->orderBy('ocurrido_ms')->orderBy('id')->paginate(100)->withQueryString();$lives=$consulta->lives($cuenta,$r)->paginate(15,['*'],'lives_page')->withQueryString();$publicaciones=$cuenta->publicaciones()->latest()->get();
        app(\App\Services\TikTokRetencion::class)->limpiar();$exportaciones=TikTokExportacion::where('cuenta_id',$cuenta->id)->where('user_id',$r->user()->id)->latest()->limit(10)->get();$salud=$consulta->salud();$liveActual=$cuenta->lives()->where('estado','en_live')->latest('iniciado_ms')->first();$video=app(TikTokVideo::class)->estado($cuenta,$liveActual);$perfil=$consulta->perfil($cuenta);$resumen=$consulta->resumen($cuenta);$liveInforme=!empty($f['publicacion_id'])?null:(!empty($f['live_id'])?$cuenta->lives()->find($f['live_id']):$liveActual);
        return $this->privado(response()->view('admin.tiktok.cuenta',compact('cuenta','f','comentarios','lives','publicaciones','exportaciones','total','cursor','salud','video','perfil','resumen','liveActual','liveInforme')));
    }
    public function salaPublica(Request $r,TikTokCuenta $cuenta){
        $this->admin($r);$campo=$r->has('salas_publicas')?'salas_publicas':'sala_publica_usuario';$d=$r->validate([$campo=>'nullable|string|max:2000']);
        $nombres=preg_split('/[\s,;]+/u',trim($d[$campo]??''),-1,PREG_SPLIT_NO_EMPTY);$nombres=array_values(array_unique(array_map(fn($v)=>mb_strtolower(ltrim($v,'@')),$nombres)));
        if(count($nombres)>20||collect($nombres)->contains(fn($v)=>!preg_match('/^[a-z0-9_.]{2,24}$/D',$v)))throw ValidationException::withMessages([$campo=>'Escriba hasta 20 @usuarios válidos, uno por línea o separados por comas.']);
        DB::transaction(function()use($cuenta,$nombres){$cuenta=TikTokCuenta::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();$anteriores=$cuenta->salasPublicas();$esperaInicial=$cuenta->fuentes()->exists()?null:$cuenta->reintentar_ms;
            foreach($cuenta->fuentes()->whereNotIn('usuario',$nombres)->get()as$f){\App\Services\TikTokFuentes::cerrar($cuenta,$f->id,'fuente_retirada',TikTokConsulta::ahora());$f->delete();}
            foreach($nombres as$i=>$usuario){$f=$cuenta->fuentes()->firstOrCreate(['usuario'=>$usuario],['orden'=>$i,'estado'=>$cuenta->monitorear?'pendiente':'inactivo','reintentar_ms'=>$esperaInicial]);if($f->orden!==$i)$f->update(['orden'=>$i]);}
            $cambio=$anteriores!==$nombres;$cuenta->update(['sala_publica_usuario'=>$nombres[0]??null]);
            if($cambio&&$cuenta->monitorear){if($nombres)\App\Services\TikTokFuentes::resumir($cuenta);else $cuenta->update(['estado'=>'pendiente','detalle'=>'Esperando la búsqueda automática desde el perfil.']);}
        });
        Actividad::registrar('tiktok_salas_publicas','@'.$cuenta->usuario.' / '.implode(',',$nombres));
        return back()->with('mensaje',$nombres?'Salas públicas guardadas. Cada una conserva su estado; active la captura si está pausada.':'Restablecida la búsqueda automática desde el perfil.');
    }
    public function toggle(Request $r,TikTokCuenta $cuenta){
        $this->admin($r);$r->validate(['monitorear'=>'required|boolean']);$activo=$r->boolean('monitorear');
        $cuenta->update(['monitorear'=>$activo,'estado'=>$activo?'pendiente':'inactivo','detalle'=>null,'reintentar_ms'=>($cuenta->reintentar_ms??0)>TikTokConsulta::ahora()?$cuenta->reintentar_ms:null]);
        $cuenta->fuentes()->update(['estado'=>$activo?'pendiente':'inactivo']);
        if(!$activo)\App\Services\TikTokRegistro::cerrar($cuenta->id,'pausa_admin',TikTokConsulta::ahora());
        if(!$activo)$cuenta->lives()->where('estado','en_live')->update(['estado'=>'interrumpido','terminado_ms'=>TikTokConsulta::ahora()]);
        Actividad::registrar('tiktok_captura_'.($activo?'activar':'pausar'),'@'.$cuenta->usuario);
        return back()->with('mensaje',$activo?'Captura activada. Se guardarán comentarios de los lives públicos que se detecten a partir de ahora.':'Captura pausada. El historial guardado sigue disponible.');
    }
    public function eventos(Request $r,TikTokCuenta $cuenta,TikTokConsulta $consulta){
        $this->admin($r);$r->validate(['cursor'=>'nullable|integer|min:0','lives_page'=>'nullable|integer|min:1|max:100000','visibles'=>'nullable|string|max:3500|regex:/^\d+(,\d+)*$/']);$f=$consulta->filtros($r,$cuenta);$cursor=(int)$r->input('cursor',0);
        $filas=$consulta->query($cuenta,$f)->where('id','>',$cursor)->orderBy('id')->with('avatar')->limit(200)->get();$liveActual=$cuenta->lives()->where('estado','en_live')->latest('iniciado_ms')->first();$liveInforme=!empty($f['publicacion_id'])?null:(!empty($f['live_id'])?$cuenta->lives()->find($f['live_id']):$liveActual);$historial=$consulta->lives($cuenta,$r)->paginate(15,['*'],'lives_page');$ids=array_slice(array_filter(explode(',',(string)$r->input('visibles',''))),0,300);$fotos=TikTokComentario::where('cuenta_id',$cuenta->id)->whereIn('id',$ids)->with('avatar')->get(['id','avatar_id'])->map(fn($m)=>['id'=>$m->id,'estado'=>$m->avatar?->estado??'no_recibida','url'=>$m->avatar&&$m->avatar->estado==='listo'?route('admin.tiktok.adjunto',$m->avatar):null]);
        return $this->privado(response()->json(['fuentes'=>\App\Services\TikTokFuentes::filas($cuenta),'comentarios'=>$filas->map(fn($m)=>$consulta->fila($m)),'cursor'=>$filas->max('id')??$cursor,'estado'=>$cuenta->estado,'etiqueta'=>$cuenta->etiquetaEstado(),'detalle'=>$cuenta->detalle,'monitorear'=>$cuenta->monitorear,'salud'=>$consulta->salud(),'total'=>$consulta->query($cuenta,$f)->count(),'reintento'=>($cuenta->reintentar_ms??0)>TikTokConsulta::ahora()?TikTokConsulta::fecha($cuenta->reintentar_ms):null,'live_actual'=>$liveActual?->id,'video'=>app(TikTokVideo::class)->estado($cuenta,$liveActual),'perfil'=>$consulta->perfil($cuenta),'lives'=>$historial->map(fn($l)=>$consulta->liveFila($l)),'lives_total'=>$historial->total(),'fotos'=>$fotos,'resumen'=>$consulta->resumen($cuenta),'exportar_live_url'=>$liveInforme?route('admin.tiktok.exportar-live',[$cuenta,$liveInforme]):null,'exportar_live_etiqueta'=>!empty($f['live_id'])?'PDF del LIVE seleccionado':'PDF del LIVE actual','comentarios_live_informe'=>$liveInforme?->comentarios??0,'comentarios_live_actual'=>$liveActual?->comentarios??0,'exportar_actual'=>$liveActual?route('admin.tiktok.exportar-live',[$cuenta,$liveActual]):null]));
    }
    public function eliminar(Request $r,TikTokCuenta $cuenta){
        $this->admin($r);$usuario=$cuenta->usuario;
        DB::transaction(function()use($cuenta){
            $c=TikTokCuenta::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();$ids=$c->lives()->pluck('id')->all();$fotos=TikTokAdjunto::where('cuenta_id',$c->id)->pluck('id')->all();
            TikTokExportacion::where('cuenta_id',$c->id)->eachById(fn($e)=>$e->delete());$c->delete();
            DB::afterCommit(function()use($ids,$fotos){app(TikTokVideo::class)->eliminar($ids);foreach($fotos as$id)Storage::disk('local')->deleteDirectory('tiktok/avatares/'.$id);});
        });
        Actividad::registrar('tiktok_cuenta_eliminar','@'.$usuario);
        return redirect()->route('admin.tiktok.index')->with('mensaje','Cuenta de monitoreo eliminada junto con su historial local.');
    }
    public function video(Request $r,TikTokCuenta $cuenta,TikTokLive $live,TikTokVideo $video){$this->admin($r);abort_unless($live->cuenta_id===$cuenta->id,404);return $video->servir($cuenta,$live,null,$r->header('Range'));}
    public function videoRecurso(Request $r,TikTokCuenta $cuenta,TikTokLive $live,TikTokVideo $video){$this->admin($r);abort_unless($live->cuenta_id===$cuenta->id,404);$r->validate(['t'=>'required|string|max:6000']);return $video->servir($cuenta,$live,$r->input('t'),$r->header('Range'));}
    public function avatar(Request $r,TikTokCuenta $cuenta,TikTokVideo $video){$this->admin($r);return $video->avatar($cuenta);}
    public function publicacion(Request $r,TikTokCuenta $cuenta){
        $this->admin($r);$d=$r->validate(['url'=>'required|url|max:300','titulo'=>'nullable|string|max:255']);
        $patron='~^https://(?:www\.)?tiktok\.com/@'.preg_quote($cuenta->usuario,'~').'/video/(\d{5,30})/?(?:\?[^#]*)?$~iD';
        if(!preg_match($patron,$d['url'],$m))throw ValidationException::withMessages(['url'=>'Use la URL pública completa de un video de esta cuenta: https://www.tiktok.com/@usuario/video/ID.']);
        TikTokPublicacion::updateOrCreate(['cuenta_id'=>$cuenta->id,'video_id'=>$m[1]],['titulo'=>$d['titulo']??null,'origen'=>'registro_manual']);
        return back()->with('mensaje','Publicación registrada. Su disponibilidad y privacidad siguen dependiendo de TikTok.');
    }
    public function importar(Request $r,TikTokCuenta $cuenta,TikTokPublicacion $publicacion){
        $this->admin($r);abort_unless($publicacion->cuenta_id===$cuenta->id,404);$r->validate(['archivo'=>'required|file|max:5120','confirmo'=>'accepted']);$file=$r->file('archivo');$extension=mb_strtolower($file->getClientOriginalExtension());
        $filas=[];
        try{
            if($extension==='json'){$data=json_decode(file_get_contents($file->getRealPath()),true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||!array_is_list($data))throw new \RuntimeException();$filas=$data;}
            elseif($extension==='csv'){$h=fopen($file->getRealPath(),'r');$cabecera=fgetcsv($h,0,',','"','');if(!$cabecera)throw new \RuntimeException();$cabecera[0]=ltrim($cabecera[0],"\xEF\xBB\xBF");while(($linea=fgetcsv($h,0,',','"',''))!==false){if(count($filas)>=5000)throw new \RuntimeException();if(count($linea)!==count($cabecera))throw new \RuntimeException();$filas[]=array_combine($cabecera,$linea);}fclose($h);}
            else throw new \RuntimeException();
            if(count($filas)>5000)throw new \RuntimeException();
            $n=0;DB::transaction(function()use($filas,$cuenta,$publicacion,&$n){foreach($filas as$i=>$fila){
                $d=validator($fila,['id'=>'required|string|max:150','autor'=>'required|string|max:100','nombre'=>'nullable|string|max:150','texto'=>'required|string|max:4000','fecha'=>'required|date'])->validate();
                $fecha=CarbonImmutable::parse($d['fecha'],TikTokConsulta::zona())->getTimestampMs();if($fecha>TikTokConsulta::ahora()+60000||$fecha<1000000000000)throw new \RuntimeException();
                $n+=TikTokComentario::insertOrIgnore(['cuenta_id'=>$cuenta->id,'live_id'=>null,'publicacion_id'=>$publicacion->id,'clave'=>hash('sha256','publicacion:'.$publicacion->id.':'.$d['id']),'autor'=>$d['autor'],'nombre'=>$d['nombre']??null,'texto'=>$d['texto'],'origen'=>'importacion_autorizada','ocurrido_ms'=>$fecha,'capturado_ms'=>TikTokConsulta::ahora()]);
            }});
        }catch(\Throwable){throw ValidationException::withMessages(['archivo'=>'Archivo inválido. Use JSON o CSV con id, autor, nombre, texto y fecha; máximo 5000 filas, 5 MB y fechas válidas. No se guardó una importación parcial.']);}
        Actividad::registrar('tiktok_importar_publicacion','Video #'.$publicacion->video_id.' / '.$n.' comentarios');return back()->with('mensaje',$n.' comentarios importados. Los duplicados se omitieron.');
    }
    public function exportar(Request $r,TikTokCuenta $cuenta,TikTokConsulta $consulta){$this->admin($r);return $this->preparar($r,$cuenta,$consulta,$consulta->filtros($r,$cuenta));}
    public function exportarLive(Request $r,TikTokCuenta $cuenta,TikTokLive $live,TikTokConsulta $consulta){
        $this->admin($r);abort_unless($live->cuenta_id===$cuenta->id,404);$q=TikTokComentario::where('cuenta_id',$cuenta->id)->where('live_id',$live->id);
        $min=(clone$q)->min('ocurrido_ms');$max=(clone$q)->max('ocurrido_ms');
        if(!$min)return back()->withErrors(['exportar'=>'Este LIVE aún no tiene comentarios guardados.']);
        return $this->preparar($r,$cuenta,$consulta,['desde'=>TikTokConsulta::fecha($min,'Y-m-d'),'hasta'=>TikTokConsulta::fecha($max,'Y-m-d'),'live_id'=>$live->id,'zona'=>TikTokConsulta::zona()]);
    }
    private function preparar(Request $r,TikTokCuenta $cuenta,TikTokConsulta $consulta,array $f){
        $d=$r->validate(['referencia'=>'nullable|string|max:120','observaciones'=>'nullable|string|max:1000']);$q=$consulta->query($cuenta,$f);$hasta=(clone$q)->max('id')??0;$total=(clone$q)->where('id','<=',$hasta)->count();
        if(!$total)return back()->withErrors(['exportar'=>'No hay comentarios para exportar con estos filtros.']);
        $e=new TikTokExportacion(['user_id'=>$r->user()->id,'cuenta_id'=>$cuenta->id,'filtros'=>$f,'hasta_id'=>$hasta,'total'=>$total,'estado'=>'en_cola','metadatos'=>['referencia'=>$d['referencia']??null,'observaciones'=>$d['observaciones']??null,'solicitante'=>['id'=>$r->user()->id,'nombre'=>$r->user()->name],'fase'=>'en_cola']]);$e->id=(string)Str::uuid();$e->save();
        try{ExportarTikTok::dispatch($e->id);Actividad::registrar('tiktok_exportar','@'.$cuenta->usuario.' / '.$total.' comentarios / '.$e->id);}catch(\Throwable){$e->update(['estado'=>'error','error'=>'No se pudo registrar la exportación. Revise el servicio PDF.']);}
        return redirect()->route('admin.tiktok.cuenta',[$cuenta]+$f)->with('mensaje','Informe registrado. Encontrará el PDF y el paquete de evidencia en Informes.');
    }
    public function adjunto(Request $r,TikTokAdjunto $adjunto){
        $this->admin($r);abort_unless($adjunto->estado==='listo',404);$disk=Storage::disk('local');$ruta=$r->boolean('descarga')?$adjunto->archivo:($adjunto->miniatura?:$adjunto->archivo);abort_unless($ruta&&$disk->exists($ruta),404);
        if($ruta===$adjunto->archivo)abort_unless(hash_file('sha256',$disk->path($ruta))===$adjunto->sha256,409,'El archivo no coincide con su hash registrado.');
        if($r->boolean('descarga'))return $disk->download($ruta,'Foto-publica-'.$adjunto->id.'.'.pathinfo($ruta,PATHINFO_EXTENSION),['Content-Type'=>$adjunto->mime,'Cache-Control'=>'private, no-store']);
        return $disk->response($ruta,null,['Cache-Control'=>'private, max-age=86400','X-Content-Type-Options'=>'nosniff']);
    }
    public function exportacion(Request $r,TikTokExportacion $exportacion){
        $this->admin($r);abort_unless($exportacion->user_id===$r->user()->id,404);
        app(\App\Services\TikTokRetencion::class)->caducar($exportacion);
        if($exportacion->estado==='procesando'&&$exportacion->updated_at->lt(now()->subMinutes(12)))$exportacion->update(['estado'=>'error','error'=>'La exportación se interrumpió. Revise micv-tiktok-pdf.']);
        return $this->privado(response()->json(['restante_segundos'=>$exportacion->restante(),'estado'=>$exportacion->estado,'procesados'=>$exportacion->procesados,'total'=>$exportacion->total,'error'=>$exportacion->error,'descarga'=>$exportacion->estado==='listo'?route('admin.tiktok.descargar',$exportacion):null,'paquete'=>$exportacion->estado==='listo'&&$exportacion->paquete?route('admin.tiktok.paquete',$exportacion):null,'fase'=>$exportacion->metadatos['fase']??$exportacion->estado,'formato'=>str_ends_with($exportacion->archivo??'','.zip')?'zip':'pdf','sha256'=>$exportacion->sha256_datos,'espera'=>$exportacion->estado==='en_cola'&&$exportacion->created_at->lt(now()->subMinutes(2))]));
    }
    public function paquete(Request $r,TikTokExportacion $exportacion){
        $this->admin($r);abort_unless($exportacion->user_id===$r->user()->id,404);app(\App\Services\TikTokRetencion::class)->caducar($exportacion);if($exportacion->vencida())return $this->privado(response()->view('admin.tiktok.caducado',['cuenta'=>$exportacion->cuenta],410));abort_unless($exportacion->estado==='listo'&&$exportacion->paquete&&Storage::disk('local')->exists($exportacion->paquete),404);
        return $this->privado(Storage::disk('local')->download($exportacion->paquete,'Evidencia-'.$exportacion->id.'.zip'));
    }
    public function descargar(Request $r,TikTokExportacion $exportacion){
        $this->admin($r);abort_unless($exportacion->user_id===$r->user()->id,404);app(\App\Services\TikTokRetencion::class)->caducar($exportacion);if($exportacion->vencida())return $this->privado(response()->view('admin.tiktok.caducado',['cuenta'=>$exportacion->cuenta],410));abort_unless($exportacion->estado==='listo'&&Storage::disk('local')->exists($exportacion->archivo),404);
        $ext=str_ends_with($exportacion->archivo,'.zip')?'zip':'pdf';return $this->privado(Storage::disk('local')->download($exportacion->archivo,'TikTok-'.$exportacion->cuenta->usuario.'-'.$exportacion->filtros['desde'].'.'.$ext));
    }
}
