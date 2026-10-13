<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Services\CartaGenerator;
use App\Services\CvPdf;
use Illuminate\Http\Request;

/**
 * Cartas en PDF (mismo formato del Sistema JZ). Las usan:
 *  - admin y cyber: para sus clientes;
 *  - el cliente registrado: para él mismo.
 */
class CartaController extends Controller
{
    public function index(Request $request)
    {
        return view('cartas.index', ['tipos' => CartaGenerator::TIPOS]);
    }

    public function create(Request $request, string $tipo)
    {
        abort_unless(array_key_exists($tipo, CartaGenerator::TIPOS), 404);
        $u = $request->user();
        $preseleccionado = null;
        if ($u->esCliente()) {
            $preseleccionado = $u->cliente;
        } elseif ($request->filled('cliente')) {
            $c = Cliente::find($request->integer('cliente'));
            $preseleccionado = $c && $c->esVisiblePara($u) ? $c : null;
        }

        return view('cartas.create', [
            'tipo' => $tipo, 'tituloTipo' => CartaGenerator::TIPOS[$tipo],
            'cantidadModelos' => CartaGenerator::CANTIDAD_MODELOS, 'preseleccionado' => $preseleccionado,
        ]);
    }

    public function buscarClientes(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2 || $request->user()->esCliente()) {
            return response()->json([]);
        }
        $limpio = \App\Services\ClienteGuardar::limpiar($q);
        $clientes = Cliente::visiblesPara($request->user())
            ->where(function ($w) use ($q, $limpio) {
                $w->where('primer_nombre', 'like', "%$q%")->orWhere('primer_apellido', 'like', "%$q%")
                  ->orWhere('segundo_apellido', 'like', "%$q%");
                if ($limpio !== '') {
                    $w->orWhere('numero_documento', 'like', "%$limpio%");
                }
                $w->orWhere('numero_documento', 'like', '%' . strtoupper($q) . '%');
            })->limit(15)->get();

        return response()->json($clientes->map(fn ($c) => ['id' => $c->id, 'texto' => $c->nombreCompleto() . ' · ' . ($c->documentoFormateado() ?: 'sin documento')]));
    }

    public function previsualizar(Request $request, string $tipo)
    {
        [$cliente, $datos] = $this->preparar($request, $tipo);
        return response((new CartaGenerator($cliente, $tipo, $datos))->html(true));
    }

    public function generar(Request $request, string $tipo)
    {
        [$cliente, $datos] = $this->preparar($request, $tipo);
        $carta = new CartaGenerator($cliente, $tipo, $datos);

        try {
            $pdf = CvPdf::desdeHtml($carta->html());
        } catch (\Throwable $e) {
            report($e);
            return response()->view('errores.pdf', ['error' => get_class($e) . ': ' . $e->getMessage()], 500);
        }
        Actividad::registrar('carta_pdf', "$tipo · #{$cliente->id} {$cliente->nombreCompleto()}");

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => ($request->boolean('ver') ? 'inline' : 'attachment') . '; filename="' . $carta->nombreArchivo() . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    protected function preparar(Request $request, string $tipo): array
    {
        abort_unless(array_key_exists($tipo, CartaGenerator::TIPOS), 404);
        $u = $request->user();
        if ($u->esCliente()) {
            $request->merge(['cliente_id' => $u->cliente?->id, 'firmante_cliente_id' => null]);
        }

        $datos = $request->validate([
            'cliente_id'          => ['required', 'integer'],
            'modelo'              => ['nullable', 'integer', 'min:1', 'max:4'],
            'fecha_carta'         => ['nullable', 'date'],
            'fecha_renuncia'      => ['nullable', 'date'],
            'lugar'               => ['nullable', 'string', 'max:100'],
            'empresa'             => ['nullable', 'string', 'max:150'],
            'encargado_nombre'    => ['nullable', 'string', 'max:150'],
            'cargo'               => ['nullable', 'string', 'max:150'],
            'area'                => ['nullable', 'string', 'max:150'],
            'tiempo_laborado'     => ['nullable', 'string', 'max:100'],
            'firmante_cliente_id' => ['nullable', 'integer'],
            'firmante_nombre'     => ['nullable', 'string', 'max:150'],
            'firmante_celular'    => ['nullable', 'string', 'max:40'],
            'firmante_sexo'       => ['nullable', 'in:M,F,O'],
            'relacion'            => ['nullable', 'string', 'max:200'],
        ], ['cliente_id.required' => 'Primero elija a la persona de la carta.']);

        $cliente = Cliente::findOrFail($datos['cliente_id']);
        abort_unless($cliente->esVisiblePara($u), 403, 'Esta persona no pertenece a su Cyber.');

        if (!empty($datos['firmante_cliente_id'])) {
            $f = Cliente::find($datos['firmante_cliente_id']);
            if (!$f || !$f->esVisiblePara($u)) {
                $datos['firmante_cliente_id'] = null;
            }
        }
        return [$cliente, array_filter($datos, fn ($v) => $v !== null && $v !== '')];
    }
}
