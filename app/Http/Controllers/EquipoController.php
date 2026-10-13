<?php
namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cyber;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EquipoController extends Controller
{
    private function negocio(Request $r): Cyber
    {
        abort_unless($r->user()->esAdmin() || ($r->user()->esEncargado() && $r->user()->tienePermiso('personal')), 403);
        if (!$r->user()->esAdmin()) return $r->user()->cyber()->firstOrFail();
        return Cyber::when($r->filled('cyber_id'), fn ($q) => $q->whereKey($r->integer('cyber_id')))->orderBy('nombre')->firstOrFail();
    }
    private function alcance(Request $r, Cyber $cy)
    {
        return User::where('cyber_id', $cy->id)->where('rol','cyber')->whereNotNull('encargado_id')
            ->when(!$r->user()->esAdmin(), fn ($q) => $q->where('encargado_id', $r->user()->id));
    }
    public function index(Request $r)
    {
        $cy = $this->negocio($r);
        return view('equipo.index', ['cyber'=>$cy, 'cybers'=>$r->user()->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(), 'usuarios'=>$this->alcance($r,$cy)->orderBy('name')->get()]);
    }
    public function crear(Request $r)
    {
        return $this->formulario($r, new User(['activo'=>true, 'permisos'=>Permisos::perfil('cajero')]));
    }
    public function editar(Request $r, User $usuario)
    {
        $cy = $this->negocio($r);
        $this->alcance($r,$cy)->whereKey($usuario->id)->firstOrFail();
        return $this->formulario($r,$usuario);
    }
    private function formulario(Request $r, User $usuario)
    {
        $cy = $this->negocio($r);
        return view('equipo.form', ['usuario'=>$usuario, 'cyber'=>$cy, 'encargados'=>User::where('rol','cyber')->where('cyber_id',$cy->id)->whereNull('encargado_id')->where('activo',true)->get()]);
    }
    private function datos(Request $r, Cyber $cy, ?User $u = null): array
    {
        $r->merge(['usuario'=>mb_strtoupper(trim((string)$r->input('usuario'))), 'email'=>mb_strtolower(trim((string)$r->input('email')))]);
        $d=$r->validate([
            'name'=>['required','string','max:150'], 'usuario'=>['required','string','min:3','max:40','regex:/^[A-Z0-9._-]+$/',Rule::unique('users','usuario')->ignore($u?->id)],
            'email'=>['required','email','max:150',Rule::unique('users','email')->ignore($u?->id)],
            'password'=>[$u ? 'nullable':'required','confirmed',Password::min(8)],
            'permisos'=>['nullable','array','max:40'], 'permisos.*'=>['string',Rule::in(array_diff(array_keys(Permisos::LISTA),['personal']))],
            'encargado_id'=>['nullable','integer'],
        ]);
        $encargado=$r->user()->esAdmin() ? User::where('cyber_id',$cy->id)->where('rol','cyber')->whereNull('encargado_id')->where('activo',true)->find($d['encargado_id']??0) : $r->user();
        abort_unless($encargado,422,'Seleccione un encargado activo de este Cyber. Puede crearlo desde Usuarios.');
        $d['encargado_id']=$encargado->id; $d['cyber_id']=$cy->id; $d['rol']='cyber'; $d['activo']=$r->boolean('activo');
        $d['permisos']=$r->has('permisos_enviados') ? ($d['permisos']??[]) : ($u?->permisos ?? Permisos::perfil('cajero'));
        if (!$r->user()->esAdmin() && collect($d['permisos']??[])->contains(fn($p)=>!$r->user()->tienePermiso($p))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['permisos'=>'Solo puede conceder los permisos que tiene habilitados.']);
        }
        $d['permisos']=Permisos::completar(array_values($d['permisos']??[]));
        if (blank($d['password']??null)) unset($d['password']);
        return $d;
    }
    public function guardar(Request $r)
    {
        $cy=$this->negocio($r); $u=User::create($this->datos($r,$cy));
        Actividad::registrar('cajero_crear',$u->usuario.' · '.$cy->nombre);
        return redirect()->route('equipo.index',['cyber_id'=>$cy->id])->with('mensaje','Cajero creado con sus permisos.');
    }
    public function actualizar(Request $r, User $usuario)
    {
        $cy=$this->negocio($r); $this->alcance($r,$cy)->whereKey($usuario->id)->firstOrFail();
        $usuario->update($this->datos($r,$cy,$usuario));
        Actividad::registrar('cajero_editar',$usuario->usuario.' · permisos actualizados');
        return redirect()->route('equipo.index',['cyber_id'=>$cy->id])->with('mensaje','Cajero actualizado.');
    }
}
