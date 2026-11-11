<?php
namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cyber;
use App\Models\User;
use App\Services\Desktop\{FullClientSync, LocalBusinessJournal, WorkspaceSnapshot};
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EquipoController extends Controller
{
    private function negocio(Request $r): Cyber
    {
        abort_unless($r->user()->esAdmin() || ($r->user()->esEncargado() && $r->user()->tienePermiso('personal')), 403);
        if (!$r->user()->esAdmin()) return $r->user()->cyber()->firstOrFail();
        $tenant = (int) getenv('MICV_LOCAL_TENANT');
        if ($r->attributes->has('local_permissions') && $tenant > 0) {
            return Cyber::findOrFail($tenant);
        }
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
            'permisos'=>['nullable','array','max:80'], 'permisos.*'=>['string',Rule::in(array_diff(array_keys(Permisos::LISTA),['personal']))],
            'encargado_id'=>['nullable','integer'],
        ]);
        $encargado=$r->user()->esAdmin() ? User::where('cyber_id',$cy->id)->where('rol','cyber')->whereNull('encargado_id')->where('activo',true)->find($d['encargado_id']??0) : $r->user();
        abort_unless($encargado,422,'Seleccione un encargado activo de este Cyber. Puede crearlo desde Usuarios.');
        $d['encargado_id']=$encargado->id; $d['cyber_id']=$cy->id; $d['rol']='cyber'; $d['activo']=$r->boolean('activo');
        $d['permisos']=$r->has('permisos_enviados') ? ($d['permisos']??[]) : ($u?->permisos ?? Permisos::perfil('cajero'));
        if (!$r->user()->esAdmin() && collect($d['permisos']??[])->contains(fn($p)=>!$r->user()->tienePermiso($p))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['permisos'=>'Solo puede conceder los permisos que tiene habilitados.']);
        }
        $d['permisos']=array_values(array_filter(Permisos::completar(array_values($d['permisos']??[])),fn($p)=>!(($modulo=\App\Support\Negocios::moduloPermiso($p))) || $cy->moduloHabilitado($modulo)));
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
        if ($r->attributes->has('local_permissions')) {
            abort_unless($cy->id === (int) getenv('MICV_LOCAL_TENANT'), 403,
                'Abra el espacio del negocio para editar su equipo.');
            $this->editarDesdeEscritorio($r, $cy, $usuario->id);
        } else {
            DB::transaction(function () use ($r, $cy, $usuario) {
                $cajero = $this->alcance($r, $cy)->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
                $cajero->update($this->datos($r, $cy, $cajero));
            });
        }
        Actividad::registrar('cajero_editar',$usuario->usuario.' · permisos actualizados');
        return redirect()->route('equipo.index',['cyber_id'=>$cy->id])->with('mensaje','Cajero actualizado.');
    }

    public function editarDesdeEscritorio(
        Request $r,
        Cyber $cy,
        int $id,
        ?string $revision = null,
        ?WorkspaceSnapshot $snapshot = null,
    ): void {
        $actor = $r->user();
        $delegables = array_diff(array_keys(Permisos::LISTA), ['personal','clientes.acceso','clientes.permisos','clientes.eliminar']);
        $datos = $r->validate([
            'name' => ['required', 'string', 'max:150'],
            'activo' => ['nullable', 'boolean'],
            'permisos' => ['nullable', 'array', 'max:80'],
            'permisos.*' => ['string', Rule::in(array_keys(Permisos::LISTA))],
        ]);
        $puedeDelegar = fn ($permiso) => in_array($permiso, $delegables, true)
            && $actor->tienePermiso($permiso)
            && (!$snapshot || $snapshot->allows($permiso))
            && (!(($modulo = \App\Support\Negocios::moduloPermiso($permiso))) || $cy->moduloHabilitado($modulo));

        DB::transaction(function () use ($r, $cy, $id, $revision, $datos, $puedeDelegar) {
            $cajero = $this->alcance($r, $cy)->whereKey($id)->lockForUpdate()->firstOrFail();
            $anteriores = Permisos::completar($cajero->permisos ?? Permisos::perfil('cajero'));
            $base = FullClientSync::fingerprint([
                'name' => $cajero->name, 'activo' => $cajero->activo,
                'encargado_id' => $cajero->encargado_id, 'permisos' => $anteriores,
            ]);
            abort_if($revision !== null && !hash_equals($base, $revision), 409, 'El cajero cambió en la web.');
            $solicitados = $datos['permisos'] ?? [];
            if ($revision === null) {
                $conservar = array_filter($anteriores, fn ($permiso) => !$puedeDelegar($permiso));
                $solicitados = array_merge($solicitados, $conservar);
            }
            $permisos = Permisos::completar($solicitados);
            $diferencia = array_merge(array_diff($permisos, $anteriores), array_diff($anteriores, $permisos));
            foreach ($diferencia as $permiso) {
                abort_unless($puedeDelegar($permiso), 403, 'No tiene autorización para cambiar ese permiso.');
            }
            $cambio = ['name' => $datos['name'], 'activo' => $r->boolean('activo'), 'permisos' => $permisos];
            $cajero->fill($cambio);
            if ($cajero->isDirty()) {
                $cajero->save();
                LocalBusinessJournal::record($r, $cy->id, 'team.update', $cajero->id, [
                    'id' => $cajero->id, 'revision' => $base, 'cajero' => $cambio,
                ]);
            }
        });
    }
}
