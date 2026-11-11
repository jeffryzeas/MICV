<?php
namespace App\Http\Controllers;
use App\Models\{Cyber, ContableMovimiento, Actividad};
use App\Models\Pos\PosConfig;
use App\Services\Desktop\LocalBusinessJournal;
use App\Services\{LibroIngresosGastos,CvPdf,Nodo};
use App\Support\{Archivos,Exportacion};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ContabilidadController extends Controller {
 private function negocio(Request $r, string $permiso='contabilidad.ver'): Cyber {
  $u=$r->user();abort_unless($u && ($u->esAdmin() || ($u->esCyber() && $u->tienePermiso($permiso))),403);
  if($u->esAdmin()) {
   if($r->filled('negocio')) $r->session()->put('contabilidad_negocio',$r->integer('negocio'));
   $cy=Cyber::find($r->session()->get('contabilidad_negocio')) ?? Cyber::orderBy('nombre')->first();
  } else $cy=$u->cyber;
  abort_unless($cy && $cy->activo && $cy->moduloHabilitado('contabilidad'),403,'Seleccione un negocio activo con el módulo de contabilidad habilitado.');
  return $cy;
 }
 private function datos(Request $r): array {
  $cy=$this->negocio($r);
  $r->validate(['desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde']);
  $hoy=now(\App\Support\Negocios::ZONAS[$cy->pais]??config('app.timezone'));
  $desde=$r->input('desde') ?: $hoy->copy()->startOfMonth()->toDateString();$hasta=$r->input('hasta') ?: $hoy->toDateString();
  abort_if($hasta<$desde,422,'La fecha final debe ser igual o posterior a la inicial.');
  $todos=ContableMovimiento::where('cyber_id',$cy->id)->where('estado','activo');
  $periodo=(clone $todos)->whereDate('fecha','>=',$desde)->whereDate('fecha','<=',$hasta);
  $ingresos=(clone $periodo)->where('tipo','ingreso')->sum('monto');$gastos=(clone $periodo)->where('tipo','gasto')->sum('monto');
  $anterior=(clone $todos)->whereDate('fecha','<',$desde)->selectRaw("COALESCE(SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END),0) saldo")->value('saldo');
  return ['negocio'=>$cy,'cfg'=>PosConfig::para($cy),'desde'=>$desde,'hasta'=>$hasta,'ingresos'=>$ingresos,'gastos'=>$gastos,'saldoAnterior'=>$anterior,'saldo'=>(float)$anterior+(float)$ingresos-(float)$gastos,'consulta'=>ContableMovimiento::where('cyber_id',$cy->id)->whereDate('fecha','>=',$desde)->whereDate('fecha','<=',$hasta)->with('usuario')->orderBy('fecha')->orderBy('id')];
 }
 public function index(Request $r) {
  $d=$this->datos($r);$d['movimientos']=$d['consulta']->paginate(30)->withQueryString();unset($d['consulta']);$d['negocios']=$r->user()->esAdmin()?Cyber::where('activo',true)->orderBy('nombre')->get():collect();
  return response()->view('contabilidad.index',$d)->header('Cache-Control','private, no-store');
 }
 public function create(Request $r) {
  $negocio=$this->negocio($r,'contabilidad.registrar');$cfg=PosConfig::para($negocio);
  return view('contabilidad.create',compact('negocio','cfg'));
 }
 public function store(Request $r) {
  $cy=$this->negocio($r,'contabilidad.registrar');
  $d=$r->validate(['clave_operacion'=>'required|uuid','fecha'=>'required|date_format:Y-m-d','tipo'=>'required|in:ingreso,gasto','categoria'=>'required|string|max:80','concepto'=>'required|string|max:250','monto'=>['required','numeric','min:0.01','max:999999999999.99','regex:/^\d{1,12}(\.\d{1,2})?$/'],'referencia'=>'nullable|string|max:100']);
  $m=LibroIngresosGastos::manual($cy,$r->user(),$d);
  LocalBusinessJournal::record($r,$cy->id,'ledger.create',$m->id,$d+['moneda'=>$m->moneda],$d['clave_operacion']);
  Actividad::registrar('contabilidad_registro','#'.$m->id.' · '.$m->tipo);
  return redirect()->route('contabilidad.index',['negocio'=>$cy->id])->with('mensaje','Movimiento registrado.');
 }
 public function anular(Request $r, ContableMovimiento $movimiento) {
  $cy=$this->negocio($r,'contabilidad.anular');abort_unless((int)$movimiento->cyber_id===(int)$cy->id,404);
  $d=$r->validate(['motivo'=>'required|string|min:8|max:500']);
  DB::transaction(function()use($movimiento,$r,$d){$m=ContableMovimiento::whereKey($movimiento->id)->lockForUpdate()->firstOrFail();abort_unless($m->origen==='manual',422,'Las ventas se anulan desde el POS para mantener ambos módulos consistentes.');abort_unless($m->estado==='activo',422,'Este movimiento ya fue anulado.');$m->update(['estado'=>'anulado','motivo_anulacion'=>$d['motivo'],'anulada_por'=>$r->user()->id,'anulada_en'=>now()]);});
  LocalBusinessJournal::record($r,$cy->id,'ledger.void',$movimiento->id,[
   'id'=>LocalBusinessJournal::reference($r,'ledger.create',$movimiento->id),'motivo'=>$d['motivo'],
  ]);
  Actividad::registrar('contabilidad_anular','#'.$movimiento->id);return back()->with('mensaje','Movimiento anulado; se conserva su historial.');
 }
 public function exportar(Request $r) {
  $this->negocio($r,'contabilidad.exportar');$r->validate(['formato'=>'required|in:pdf,excel']);$d=$this->datos($r);
  abort_if((clone $d['consulta'])->count()>10000,422,'Reduzca el período para exportar hasta 10,000 movimientos.');
  $d['movimientos']=$d['consulta']->get();unset($d['consulta']);$d['logo']=Archivos::dataUri($d['negocio']->logo);$d['orientacion']=Exportacion::orientacion($r,7);
  $headers=['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'];
  if($r->input('formato')==='pdf')return response(CvPdf::desdeHtml(view('exports.contabilidad',$d)->render()),200,$headers+['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="Libro_ingresos_gastos.pdf"']);
  $data=['negocio'=>$d['negocio']->nombre,'logo'=>$d['logo'],'generado'=>now()->format('d/m/Y H:i'),'periodo'=>$d['desde'].' — '.$d['hasta'],'orientacion'=>$d['orientacion'],'moneda'=>$d['cfg']->moneda,'filas'=>$d['movimientos']->map(fn($m)=>[$m->fecha->format('d/m/Y'),$m->tipo,$m->categoria,$m->concepto,$m->monto,$m->moneda,$m->origen,$m->estado,$m->referencia,$m->motivo_anulacion])->all(),'resumen'=>[['Saldo anterior',$d['saldoAnterior']],['Ingresos',$d['ingresos']],['Gastos',$d['gastos']],['Saldo al cierre',$d['saldo']]]];
  $dir=storage_path('app/tmp-excel');if(!is_dir($dir))mkdir($dir,0775,true);$file=$dir.'/'.Str::random(24).'.xlsx';
  try {Nodo::correr(resource_path('reports/contabilidad-excel.cjs'),[$file],120,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$bin=file_get_contents($file);}finally{@unlink($file);}
  return response($bin,200,$headers+['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="Libro_ingresos_gastos.xlsx"']);
 }
}
