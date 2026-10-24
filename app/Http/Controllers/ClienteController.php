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
            $orientacion = \App\Support\Exportacion::orientacion($request, 5);
            $html = view('exports.clientes', compact('clientes', 'negocio', 'logo', 'orientacion'))->render();
            return response(app(\App\Services\ReportePdf::class)->generar($html), 200, $headers + ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Clientes.pdf"']);
        }
        $datos = ['negocio' => $negocio, 'logo' => $logo, 'orientacion' => \App\Support\Exportacion::orientacion($request,5), 'generado' => now()->format('d/m/Y H:i'), 'clientes' => $clientes->map(fn ($c) => [$c->nombreCompleto(), $c->documentoFormateado(), $c->telefono, $c->correo, $c->pais])->all()];
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
            $solicitud = \App\Models\AutoservicioSolicitud::query()->de($u)->where('estado', 'pendiente')->find($request->integer('autoservicio'));
            if ($solicitud) {
                $request->session()->now('_old_input', $solicitud->datos + ['autoservicio_id' => $solicitud->id, 'cyber_id' => $solicitud->cyber_id]);
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
        $r = $guardar->registrar($request);
        $c = $r['cliente'];
        if ($request->filled('autoservicio_id')) {
            \App\Models\AutoservicioSolicitud::query()->de($request->user())->whereKey($request->integer('autoservicio_id'))
                ->update(['estado' => 'atendida', 'cliente_id' => $c->id, 'atendida_por' => $request->user()->id, 'atendida_en' => now()]);
        }
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
