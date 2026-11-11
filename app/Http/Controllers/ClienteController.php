<?php

namespace App\Http\Controllers;

use App\Models\{Actividad, AutoservicioSolicitud};
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Cyber;
use App\Services\ClienteGuardar;
use App\Services\Desktop\{LocalBusinessJournal, LocalClientJournal};
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $clientesQuery = (clone $base)
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
            ->latest();

        if ($request->routeIs('clientes.exportar')) {
            return $this->exportarResultado($request, $clientesQuery);
        }
        $clientes = $clientesQuery->paginate(20)->withQueryString();

        return view('clientes.index', [
            'clientes'     => $clientes,
            'q'            => $q, 'sexo' => $sexo, 'tipoDoc' => $tipoDoc, 'cyberId' => $cyberId,
            'totalHombres' => (clone $base)->where('sexo', 'M')->count(),
            'totalMujeres' => (clone $base)->where('sexo', 'F')->count(),
            'total'        => (clone $base)->count(),
            'cybers'       => $u->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(),
        ]);
    }

    public function exportar(Request $request)
    {
        abort_unless($request->user()->esAdmin() || ($request->user()->esCyber() && $request->user()->tienePermiso('clientes.ver')), 403);
        $request->validate(['formato' => 'required|in:pdf,excel']);
        return $this->index($request);
    }

    private function exportarResultado(Request $request, $consulta)
    {
        abort_if((clone $consulta)->count() > 5000, 422, 'Use los filtros para exportar hasta 5,000 clientes por archivo.');
        $u = $request->user();
        $cfg = Configuracion::actualSegura();
        $marca = $u->esAdmin() ? $cfg : $u->cyber;
        $negocio = $u->esAdmin() ? ($cfg?->nombre_negocio ?: 'Mi CV') : ($marca?->nombre ?: 'Mi CV');
        $logo = Archivos::dataUri($marca?->logo);
        $clientes = $consulta->get();
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->input('formato') === 'pdf') {
            $orientacion = \App\Support\Exportacion::orientacion($request, 7);
            $html = view('exports.clientes', compact('clientes', 'negocio', 'logo', 'orientacion'))->render();
            return response(app(\App\Services\ReportePdf::class)->generar($html), 200, $headers + ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Clientes.pdf"']);
        }
        $datos = ['negocio' => $negocio, 'logo' => $logo, 'orientacion' => \App\Support\Exportacion::orientacion($request,7), 'generado' => now()->format('d/m/Y H:i'), 'clientes' => $clientes->map(fn ($c) => [$c->nombreCompleto(), $c->documentoFormateado(), $c->telefono, $c->correo, $c->datosPais()['nombre'], $c->nacionalidad, $c->residenciaLegible()])->all()];
        $dir = storage_path('app/tmp-excel');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $salida = $dir.'/'.\Illuminate\Support\Str::random(24).'.xlsx';
        try {
            \App\Services\Nodo::correr(resource_path('reports/clientes-excel.cjs'), [$salida], 120, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $bin = file_get_contents($salida);
        } finally { @unlink($salida); }
        return response($bin, 200, $headers + ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="Clientes.xlsx"']);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        if (!$u->esAdmin() && !$u->cyber_id) {
            return redirect()->route('dashboard')->withErrors(['general' => 'Su usuario no tiene un Cyber asignado. Hable con el administrador.']);
        }

        // Atender un turno del autoservicio: el formulario se abre lleno con lo que la persona escribió.
        $solicitud = null;
        if ($request->filled('autoservicio')) {
            abort_unless($u->tienePermiso('autoservicio'), 403);
            $local = $request->attributes->has('local_permissions');
            $solicitud = AutoservicioSolicitud::query()->de($u)->where('estado', 'pendiente')
                ->when($local, fn ($q) => $q->where('cyber_id', (int) getenv('MICV_LOCAL_TENANT')))
                ->find($request->integer('autoservicio'));
            abort_unless($solicitud, 409, 'El turno ya no está pendiente en este negocio.');
            if ((int) $request->session()->getOldInput('autoservicio_id') !== $solicitud->id) {
                $request->session()->now('_old_input', [
                    'autoservicio_id' => $solicitud->id,
                    'cyber_id' => $solicitud->cyber_id,
                ] + $solicitud->datos);
            }
        }

        return view('clientes.create', [
            'solicitud'    => $solicitud,
            'clienteNuevo' => new Cliente(),
            'cybers'       => $u->esAdmin() ? Cyber::where('activo', true)->orderBy('nombre')->get() : collect(),
        ]);
    }

    public function store(Request $request, ClienteGuardar $guardar)
    {
        $local = $request->attributes->has('local_permissions');
        $tenant = (int) getenv('MICV_LOCAL_TENANT');
        $request->validate(['autoservicio_id' => 'nullable|integer|min:1']);
        $turno = $request->integer('autoservicio_id');
        $user = $request->user();
        abort_if($turno && !$user->tienePermiso('autoservicio'), 403);

        if ($local) {
            abort_unless($tenant > 0, 422, 'Use el espacio del negocio para registrar una ficha nueva.');
            if ($user->esAdmin()) {
                $request->merge(['cyber_id' => $tenant]);
            }
            $request->attributes->set('desktop_create_only', true);
        }
        $r = DB::transaction(function () use ($request, $guardar, $local, $tenant, $turno, $user) {
            $solicitud = $turno
                ? AutoservicioSolicitud::bloquearPendiente($turno, $user, $local ? $tenant : null)
                : null;
            if ($solicitud && $user->esAdmin()) {
                $request->merge(['cyber_id' => $solicitud->cyber_id]);
            }
            $payload = $local ? LocalClientJournal::payload($request) : [];
            $resultado = $guardar->registrar($request);
            $cliente = $resultado['cliente'];
            $solicitud?->resolver($user, $cliente);
            if ($local) {
                $payload['local_client_id'] = $cliente->id;
                if ($solicitud) {
                    $payload['autoservicio_id'] = $solicitud->id;
                }
                LocalBusinessJournal::record($request, $tenant, 'client.full.create', $cliente->id, $payload);
            }
            return $resultado;
        });
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
        abort_unless($request->user()->tienePermiso("clientes.editar"),403);
        $this->autorizar($request, $cliente);
        $cliente->load(['estudios', 'experiencias', 'idiomas', 'habilidades', 'referencias', 'cybers']);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'cybers'  => $request->user()->esAdmin() ? Cyber::orderBy('nombre')->get() : collect(),
        ]);
    }

    public function update(Request $request, Cliente $cliente, ClienteGuardar $guardar)
    {
        abort_unless($request->user()->tienePermiso("clientes.editar"),403);
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
        $teniaCuenta = (bool) $cliente->user_id;
        \App\Services\Cuenta::borrarTodo($cliente);
        Actividad::registrar('cliente_eliminar', '#' . $cliente->id); // sin datos personales

        return redirect()->route('clientes.index')->with('mensaje', "Se eliminó a $nombre" . ($teniaCuenta ? ' y su usuario del sistema' : '') . '. No quedó ningún dato suyo.');
    }

    protected function autorizar(Request $request, Cliente $cliente): void
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'Esta persona no pertenece a su Cyber.');
    }
}
