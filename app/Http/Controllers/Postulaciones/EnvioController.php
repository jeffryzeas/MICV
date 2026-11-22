<?php
namespace App\Http\Controllers\Postulaciones;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarPostulacion;
use App\Models\{Actividad, Cliente, Configuracion, GmailCuenta, Postulacion, PostulacionEmpresa};
use App\Services\{CvDatos, PostulacionPdf, PostulacionRedaccion};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnvioController extends Controller
{
    use Acceso;
    private function envio(Request $r, string $id): Postulacion
    {
        $p=Postulacion::visibles($this->actor($r))->findOrFail($id);
        $p->comprobarTiempo();
        return $p;
    }
    private function cuenta(Request $r, int $id): GmailCuenta
    {
        $u=$this->actor($r);$cuenta=GmailCuenta::findOrFail($id);
        abort_unless($u->esAdmin() || (int)$cuenta->user_id===(int)$u->id,403);
        if (!$cuenta->lista() || !$cuenta->user?->activo) throw ValidationException::withMessages(['gmail_cuenta_id'=>'Guarde y compruebe una cuenta activa de Gmail antes de preparar el envío.']);
        return $cuenta;
    }
    private function empresa(Request $r, int $id): PostulacionEmpresa
    {
        return PostulacionEmpresa::visibles($this->actor($r))->where('activa',true)->findOrFail($id);
    }
    public function index(Request $r)
    {
        $u=$this->actor($r);$q=is_string($r->query('q'))?trim($r->query('q')):'';
        $estado=is_string($r->query('estado'))?$r->query('estado'):'';
        $envios=Postulacion::visibles($u)->with('user:id,name')
            ->when(array_key_exists($estado,Postulacion::ESTADOS),fn($w)=>$w->where('estado',$estado))
            ->when($q!=='',fn($w)=>$w->where(fn($w)=>$w->where('empresa','like','%'.$q.'%')->orWhere('destinatario','like','%'.$q.'%')->orWhere('candidato','like','%'.$q.'%')))
            ->latest()->paginate(20)->withQueryString();
        foreach($envios as $p) $p->comprobarTiempo();
        return $this->privado(response()->view('postulaciones.index',compact('envios','q','estado')));
    }
    public function create(Request $r)
    {
        $u=$this->actor($r);
        $cuentas=GmailCuenta::with('user:id,name')->where('activo',true)->whereNotNull('verificada_en')->whereNotNull('clave')
            ->whereHas('user',fn($w)=>$w->where('activo',true))->when(!$u->esAdmin(),fn($w)=>$w->where('user_id',$u->id))->orderBy('correo')->get();
        $clientes=Cliente::visiblesPara($u)->where('activo',true)->orderBy('primer_nombre')->get();
        $empresas=PostulacionEmpresa::visibles($u)->where('activa',true)->orderBy('nombre')->get();
        return $this->privado(response()->view('postulaciones.crear',compact('cuentas','clientes','empresas')));
    }
    public function redactar(Request $r, PostulacionRedaccion $redaccion)
    {
        $r->validate(['cliente_id'=>'required|integer','empresa_id'=>'required|integer','cargo'=>'nullable|string|max:150']);
        return $this->privado(response()->json($redaccion->generar($this->candidato($r,(int)$r->cliente_id),$this->empresa($r,(int)$r->empresa_id),($r->exists('cargo') ? (string)$r->input('cargo') : null))));
    }
    public function preparar(Request $r, PostulacionPdf $pdf)
    {
        $u=$this->actor($r);
        $d=$r->validate([
            'cliente_id'=>'required|integer','empresa_id'=>'required|integer','gmail_cuenta_id'=>'required|integer',
            'cargo'=>['nullable','string','max:150','not_regex:/[\r\n]/'],
            'asunto'=>['required','string','max:200','not_regex:/[\r\n]/'],
            'mensaje'=>'required|string|max:10000',
            'diseno'=>['required',Rule::in(array_keys(CvDatos::DISENOS))],
            'paleta'=>['nullable',Rule::in(array_keys(Configuracion::PALETAS_PRO))],
            'idioma'=>['required',Rule::in(['es','en'])],'foto'=>'nullable|boolean',
        ]);
        $c=$this->candidato($r,(int)$d['cliente_id']);$e=$this->empresa($r,(int)$d['empresa_id']);$cuenta=$this->cuenta($r,(int)$d['gmail_cuenta_id']);
        $op=['diseno'=>$d['diseno'],'paleta'=>$d['paleta']??'cyber','foto'=>$d['diseno']!=='ats' && $r->boolean('foto'),'idioma'=>$d['idioma']];
        try { $bytes=$pdf->generar($c,$u,$op); }
        catch (\Throwable $error) { report($error); return back()->withInput($r->except(['gmail_clave']))->withErrors(['cv'=>'No se pudo generar el PDF. Revise el motor de PDF del servidor e inténtelo de nuevo. No se envió ningún correo.']); }
        if (!str_starts_with($bytes,'%PDF-') || strlen($bytes)>18*1024*1024) throw ValidationException::withMessages(['cv'=>'El CV debe ser un PDF válido y pesar hasta 18 MB.']);
        $id=(string)Str::uuid();$ruta='postulaciones/'.$id.'/cv.pdf';
        $nombre='CV_'.Str::limit(Str::slug($c->nombreCompleto(),'_'),90,'').'_'.$d['diseno'].'_'.substr($id,0,8).'.pdf';
        $p=new Postulacion([
            'user_id'=>$cuenta->user_id,'creado_por'=>$u->id,'cliente_id'=>$c->id,'empresa_id'=>$e->id,'gmail_cuenta_id'=>$cuenta->id,'cuenta_revision'=>$cuenta->revision,
            'remitente'=>$cuenta->correo,'remitente_nombre'=>$cuenta->nombre,'candidato'=>mb_substr($c->nombreCompleto(),0,200),
            'empresa'=>$e->nombre,'destinatario'=>$e->correo,'contacto'=>$e->contacto,'cargo'=>$d['cargo']??null,
            'asunto'=>$d['asunto'],'mensaje'=>$d['mensaje'],'cv_ruta'=>$ruta,'cv_nombre'=>$nombre,'cv_sha256'=>hash('sha256',$bytes),'cv_bytes'=>strlen($bytes),'cv_opciones'=>$op,'estado'=>'borrador',
        ]);
        $p->id=$id;
        $p->correo_html=view('postulaciones.correo',['p'=>$p])->render();
        if (!Storage::disk('local')->put($ruta,$bytes,['visibility'=>'private'])) throw ValidationException::withMessages(['cv'=>'No se pudo guardar la versión del CV. Revise los permisos de storage del servidor.']);
        try { $p->save(); } catch (\Throwable $error) { Storage::disk('local')->delete($ruta);throw $error; }
        Actividad::registrar('postulacion_preparar', '#'.$p->id.' / cliente #'.$c->id.' / remitente #'.$cuenta->user_id);
        return redirect()->route('postulaciones.ver',$p)->with('mensaje','Borrador preparado. Revise el mensaje y el PDF antes de confirmar el envío.');
    }
    public function ver(Request $r, string $id)
    {
        $p=$this->envio($r,$id);
        return $this->privado(response()->view('postulaciones.ver',compact('p')));
    }
    public function confirmar(Request $r, string $id)
    {
        $p=$this->envio($r,$id);$u=$r->user();
        abort_unless($u->esAdmin() || (int)$p->user_id===(int)$u->id,403);
        $r->validate(['confirmar'=>'accepted']);
        if ($p->estado!=='borrador') return redirect()->route('postulaciones.ver',$p)->with('mensaje','Este envío ya fue procesado. No se volverá a enviar.');
        $cuenta=$this->cuenta($r,(int)$p->gmail_cuenta_id);
        $c=$p->cliente;
        abort_unless($c && $c->activo && $c->esVisiblePara($u),403);
        if ($p->cuenta_revision!==$cuenta->revision) return back()->withErrors(['cuenta'=>'Gmail cambió desde la preparación del borrador. Prepare un nuevo envío.']);
        $changed=Postulacion::whereKey($p->id)->where('estado','borrador')->update(['estado'=>'en_cola','encolado_en'=>now(),'confirmado_por'=>$u->id,'detalle_estado'=>null]);
        if ($changed) {
            try { EnviarPostulacion::dispatch($p->id); }
            catch (\Throwable) { Postulacion::whereKey($p->id)->where('estado','en_cola')->update(['estado'=>'fallido','detalle_estado'=>'No se pudo registrar el trabajo de envío. No se envió el correo.']);return back()->withErrors(['cola'=>'No se pudo iniciar el envío. Revise la cola de postulaciones del servidor.']); }
            Actividad::registrar('postulacion_confirmar','#'.$p->id.' / Gmail #'.$cuenta->user_id);
        }
        return redirect()->route('postulaciones.ver',$p)->with('mensaje','Envío confirmado. Puede seguir su estado en esta página.');
    }
    public function estado(Request $r, string $id)
    {
        $p=$this->envio($r,$id);
        return $this->privado(response()->json(['estado'=>$p->estado,'etiqueta'=>$p->estadoLegible(),'detalle'=>$p->detalle_estado,'fecha'=>$p->enviado_en?->timezone('America/Costa_Rica')->format('d/m/Y H:i'),'espera'=>$p->estado==='en_cola' && $p->encolado_en?->lt(now()->subMinutes(2))]));
    }
    public function pdf(Request $r, string $id)
    {
        $p=$this->envio($r,$id);abort_unless(Storage::disk('local')->exists($p->cv_ruta),404);
        $response=$r->boolean('descargar') ? Storage::disk('local')->download($p->cv_ruta,$p->cv_nombre,['Content-Type'=>'application/pdf']) : response()->file(Storage::disk('local')->path($p->cv_ruta),['Content-Type'=>'application/pdf']);
        return $this->privado($response);
    }
    public function correo(Request $r, string $id)
    {
        $p=$this->envio($r,$id);
        return $this->privado(response($p->correo_html)->header('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox; frame-ancestors 'self'"));
    }
    public function eliminar(Request $r, string $id)
    {
        $p=$this->envio($r,$id);abort_unless($r->user()->esAdmin() || (int)$p->user_id===(int)$r->user()->id,403);
        abort_unless($p->estado==='borrador',409,'Solo se pueden descartar borradores sin enviar.');
        $deleted=DB::transaction(function()use($p){$actual=Postulacion::lockForUpdate()->findOrFail($p->id);abort_unless($actual->estado==='borrador',409);return $actual->delete();});
        return redirect()->route('postulaciones.index')->with('mensaje','Borrador descartado.');
    }
}
