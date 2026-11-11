<?php

namespace App\Http\Controllers\Pos;

use App\Models\Cliente;
use App\Models\Pos\PosPendiente;
use App\Services\CvPdf;
use App\Services\Pos\Ventas;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendienteController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        $pendientes = PosPendiente::where('cyber_id', $c['cyberPos']->id)
            ->where('user_id', $request->user()->id)
            ->where(fn ($q) => $q->whereNull('venta_id')->orWhere('tipo', 'cotizacion'))
            ->latest('updated_at')->orderBy('id')->paginate(20);
        return view('pos.pendientes', $c + compact('pendientes'));
    }

    public function guardar(Request $request)
    {
        $cfg = $this->config($request);
        $user = $request->user();
        $d = $request->validate(Ventas::reglasItems() + [
            'id' => ['required', 'uuid'],
            'tipo' => ['required', 'in:espera,cotizacion'],
            'nombre' => ['required', 'string', 'max:100'],
            'cliente_id' => ['nullable', 'integer'],
            'cliente_nombre' => ['nullable', 'string', 'max:150'],
        ]);
        if (!empty($d['cliente_id']) && !Cliente::find($d['cliente_id'])?->esVisiblePara($user)) {
            abort(422, 'El cliente de esta venta ya no está disponible.');
        }
        $lineas = Ventas::calcularLineas($d, $cfg, $user);
        $contenido = [
            'nombre' => $d['nombre'],
            'carrito' => collect($d)->only(['items', 'descuento_pct', 'cliente_id', 'cliente_nombre'])->all(),
            'lineas' => array_map(fn ($l) => collect($l)->except(['producto', 'costo'])->all(), $lineas),
            'total' => round(array_sum(array_column($lineas, 'total')), (int) $cfg->decimales),
            'moneda' => $cfg->moneda,
            'simbolo' => $cfg->simbolo,
            'decimales' => (int) $cfg->decimales,
        ];
        DB::transaction(function () use ($d, $cfg, $user, $contenido) {
            $pendiente = PosPendiente::query()->lockForUpdate()->find($d['id']);
            if ($pendiente) {
                abort_unless((int) $pendiente->cyber_id === (int) $cfg->cyber_id && (int) $pendiente->user_id === $user->id, 404);
                abort_if($pendiente->venta_id, 409, 'Este pendiente ya se cobró.');
            } else {
                $pendiente = new PosPendiente(['id' => $d['id'], 'cyber_id' => $cfg->cyber_id, 'user_id' => $user->id]);
            }
            $pendiente->fill(['tipo' => $d['tipo'], 'contenido' => $contenido])->save();
        });
        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request, string $pendiente)
    {
        $c = $this->comunes($request);
        $registro = PosPendiente::where('cyber_id', $c['cyberPos']->id)
            ->where('user_id', $request->user()->id)->where('tipo', 'cotizacion')->findOrFail($pendiente);
        $html = view('exports.cotizacion-pos', $c + [
            'registro' => $registro,
            'datos' => $registro->contenido,
            'logo' => Archivos::dataUri($c['cyberPos']->logo),
            'orientacion' => 'portrait',
        ])->render();

        return response(CvPdf::desdeHtml($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Cotizacion-' . substr($registro->id, 0, 8) . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function descartar(Request $request, string $pendiente)
    {
        $cantidad = PosPendiente::where('cyber_id', $this->cyber($request)->id)
            ->where('user_id', $request->user()->id)->whereNull('venta_id')->whereKey($pendiente)->delete();
        abort_unless($cantidad, 404);
        return redirect()->route('pos.pendientes.index')->with('ok', 'Se descartó el pendiente.');
    }
}
