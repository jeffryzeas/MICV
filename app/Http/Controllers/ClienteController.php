<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Cyber;
use App\Services\ClienteGuardar;
use App\Support\Archivos;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $q = trim((string) $request->query('q'));
        $sexo = (string) $request->query('sexo', '');
        $tipoDoc = (string) $request->query('tipo_documento', '');
        $cyberId = $u->esAdmin() ? (string) $request->query('cyber_id', '') : '';
        $limpio = ClienteGuardar::limpiar($q);

        $base = Cliente::query()->visiblesPara($u)
            ->when($cyberId !== '', fn ($w) => $w->whereHas('cybers', fn ($x) => $x->where('cybers.id', $cyberId)));

        $clientes = (clone $base)
            ->when($q !== '', function ($w) use ($q, $limpio) {
                $w->where(function ($x) use ($q, $limpio) {
                    foreach (preg_split('/\s+/', $q) as $p) {
                        $x->where(fn ($y) => $y->where('primer_nombre', 'like', "%$p%")
                            ->orWhere('segundo_nombre', 'like', "%$p%")
                            ->orWhere('primer_apellido', 'like', "%$p%")
                            ->orWhere('segundo_apellido', 'like', "%$p%"));
                    }
                    if ($limpio !== '') {
                        $x->orWhere('numero_documento', 'like', "%$limpio%");
                    }
                    $x->orWhere('numero_documento', 'like', '%' . strtoupper($q) . '%'); // con guiones (Panamá)
                    $x->orWhere('correo', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%");
                });
            })
            ->when($sexo !== '', fn ($w) => $w->where('sexo', $sexo))
            ->when($tipoDoc !== '', fn ($w) => $w->where('tipo_documento', $tipoDoc))
            ->with('cybers')
            ->latest()->paginate(20)->withQueryString();

        return view('clientes.index', [
            'clientes'     => $clientes,
            'q'            => $q, 'sexo' => $sexo, 'tipoDoc' => $tipoDoc, 'cyberId' => $cyberId,
            'totalHombres' => (clone $base)->where('sexo', 'M')->count(),
            'totalMujeres' => (clone $base)->where('sexo', 'F')->count(),
            'total'        => (clone $base)->count(),
            'cybers'       => $u->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        if (!$u->esAdmin() && !$u->cyber_id) {
            return redirect()->route('dashboard')->withErrors(['general' => 'Su usuario no tiene un Cyber asignado. Hable con el administrador.']);
        }

        return view('clientes.create', [
            'clienteNuevo' => new Cliente(),
            'cybers'       => $u->esAdmin() ? Cyber::where('activo', true)->orderBy('nombre')->get() : collect(),
        ]);
    }

    public function store(Request $request, ClienteGuardar $guardar)
    {
        $r = $guardar->registrar($request);
        $c = $r['cliente'];
        Actividad::registrar($r['ya_existia'] ? 'cliente_vincular' : 'cliente_crear', "#{$c->id} {$c->nombreCompleto()}");

        $msg = $r['ya_existia']
            ? 'Esta persona ya estaba en el sistema: se actualizaron sus datos y ahora aparece en su lista.'
            : 'Cliente registrado correctamente. Ya puede generar su CV.';

        return redirect()->route('clientes.show', $c)->with('mensaje', $msg);
    }

    public function show(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $cliente->load(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'creador', 'cybers']);
        $cfg = Configuracion::actual();

        return view('clientes.show', [
            'cliente' => $cliente,
            'cv'      => $cfg->cv(),
            'paletas' => Configuracion::PALETAS_PRO,
        ]);
    }

    public function edit(Request $request, Cliente $cliente)
    {
        $this->autorizar($request, $cliente);
        $cliente->load(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'cybers']);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'cybers'  => $request->user()->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(),
        ]);
    }

    public function update(Request $request, Cliente $cliente, ClienteGuardar $guardar)
    {
        $this->autorizar($request, $cliente);
        $guardar->actualizar($request, $cliente);
        Actividad::registrar('cliente_editar', "#{$cliente->id} {$cliente->nombreCompleto()}");

        return redirect()->route('clientes.show', $cliente)->with('mensaje', 'Cambios guardados.');
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        $u = $request->user();

        abort_unless($u->esAdmin(), 403, 'Solo el administrador puede eliminar clientes.');

        $nombre = $cliente->nombreCompleto();
        Archivos::borrar($cliente->foto);
        $cliente->delete();
        Actividad::registrar('cliente_eliminar', $nombre);

        return redirect()->route('clientes.index')->with('mensaje', "Cliente $nombre eliminado del sistema.");
    }

    protected function autorizar(Request $request, Cliente $cliente): void
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'Esta persona no pertenece a su Cyber.');
    }
}
