<?php
namespace App\Http\Controllers\Postulaciones;
use App\Http\Controllers\Controller;
use App\Models\PostulacionEmpresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    use Acceso;
    public function index(Request $r)
    {
        $u=$this->actor($r); $q=is_string($r->query('q'))?trim($r->query('q')):'';
        $empresas=PostulacionEmpresa::visibles($u)->when($q!=='',fn($w)=>$w->where(fn($w)=>$w->where('nombre','like','%'.$q.'%')->orWhere('correo','like','%'.$q.'%')->orWhere('cargo','like','%'.$q.'%')))->orderBy('nombre')->paginate(20)->withQueryString();
        return $this->privado(response()->view('postulaciones.empresas',compact('empresas','q')));
    }
    public function create(Request $r) { $this->actor($r);return $this->privado(response()->view('postulaciones.empresa-form',['empresa'=>new PostulacionEmpresa(['activa'=>true])])); }
    public function edit(Request $r, PostulacionEmpresa $empresa)
    {
        abort_unless($empresa->puedeEditar($this->actor($r)),403);
        return $this->privado(response()->view('postulaciones.empresa-form',compact('empresa')));
    }
    private function datos(Request $r): array
    {
        $u=$this->actor($r);
        $d=$r->validate(['nombre'=>'required|string|max:150','correo'=>['required','email:rfc','max:150','not_regex:/[\r\n]/'],'contacto'=>'nullable|string|max:150','cargo'=>'nullable|string|max:150','notas'=>'nullable|string|max:2000','activa'=>'nullable|boolean','compartida'=>'nullable|boolean']);
        $d['correo']=mb_strtolower($d['correo']);$d['activa']=$r->boolean('activa');$d['compartida']=$u->esAdmin() && $r->boolean('compartida');
        return $d;
    }
    public function store(Request $r)
    {
        $d=$this->datos($r);$d['user_id']=$r->user()->id;
        PostulacionEmpresa::create($d);
        return redirect()->route('postulaciones.empresas')->with('mensaje','Empresa registrada.');
    }
    public function update(Request $r, PostulacionEmpresa $empresa)
    {
        abort_unless($empresa->puedeEditar($this->actor($r)),403);
        $empresa->update($this->datos($r));
        return redirect()->route('postulaciones.empresas')->with('mensaje','Empresa actualizada. Los envíos anteriores conservan sus datos originales.');
    }
}
