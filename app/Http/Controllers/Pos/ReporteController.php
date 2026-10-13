<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosProducto;
use App\Models\Pos\PosVenta;
use App\Models\Pos\PosVentaItem;
use App\Models\Pos\PosVentaPago;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Reportes: ventas, ganancia, métodos de pago, productos más vendidos, inventario. */
class ReporteController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        [$desde, $hasta] = $this->rango($request);
        $id = $c['cyberPos']->id;
        $ventas = PosVenta::where('cyber_id', $id)->where('estado', 'pagada')->whereBetween('created_at', [$desde, $hasta]);
        $ids = (clone $ventas)->pluck('id');

        $tot = (clone $ventas)->selectRaw('COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(impuesto),0) impuesto, COALESCE(SUM(descuento),0) descuento, COALESCE(SUM(costo_total),0) costo, COALESCE(SUM(cambio),0) cambio')->first();
        $porMetodo = PosVentaPago::whereIn('venta_id', $ids)->selectRaw('metodo, SUM(monto) total')->groupBy('metodo')->pluck('total', 'metodo')->map(fn ($v) => (float) $v)->all();
        if (isset($porMetodo['efectivo'])) {
            $porMetodo['efectivo'] -= (float) $tot->cambio;
        }

        // Ventas por día (se agrupa en PHP: funciona igual en SQLite y MySQL).
        $porDia = [];
        foreach ((clone $ventas)->get(['created_at', 'total']) as $v) {
            $k = $v->created_at->format('Y-m-d');
            $porDia[$k] = ($porDia[$k] ?? 0) + (float) $v->total;
        }
        $dias = [];
        for ($d = $desde->copy(); $d <= $hasta && count($dias) < 62; $d->addDay()) {
            $dias[$d->format('Y-m-d')] = $porDia[$d->format('Y-m-d')] ?? 0;
        }

        $top = PosVentaItem::whereIn('venta_id', $ids)->selectRaw('descripcion, SUM(cantidad) cant, SUM(total) total, SUM(costo) costo')
            ->groupBy('descripcion')->orderByDesc('total')->limit(15)->get();
        $porUsuario = (clone $ventas)->selectRaw('user_id, COUNT(*) n, SUM(total) total')->groupBy('user_id')->with('usuario:id,name')->get();

        $base = (float) $tot->total - (float) $tot->impuesto;
        return view('pos.reportes', $c + [
            'desde' => $desde, 'hasta' => $hasta, 'tot' => $tot, 'porMetodo' => $porMetodo, 'dias' => $dias, 'top' => $top, 'porUsuario' => $porUsuario,
            'ganancia' => $base - (float) $tot->costo, 'base' => $base,
            'anuladas' => PosVenta::where('cyber_id', $id)->where('estado', 'anulada')->whereBetween('created_at', [$desde, $hasta])->count(),
            'bajos' => PosProducto::where('cyber_id', $id)->where('activo', true)->where('controla_stock', true)->whereColumn('stock', '<=', 'stock_minimo')->orderBy('stock')->limit(15)->get(),
        ]);
    }

    /** Detalle de ventas en CSV (abre en Excel). */
    public function csv(Request $request)
    {
        $cfg = $this->config($request);
        [$desde, $hasta] = $this->rango($request);
        $ventas = PosVenta::where('cyber_id', $cfg->cyber_id)->whereBetween('created_at', [$desde, $hasta])->with('pagos', 'usuario', 'cliente')->orderBy('id')->get();
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes
        fputcsv($out, ['Número', 'Fecha', 'Hora', 'Cliente', 'Cajero', 'Subtotal', 'Descuento', $cfg->impuesto_nombre, 'Total', 'Costo', 'Pagos', 'Estado', 'Motivo anulación'], ';');
        foreach ($ventas as $v) {
            fputcsv($out, [
                $v->numero, $v->created_at->format('d/m/Y'), $v->created_at->format('H:i'), $v->nombreCliente(), $v->usuario?->name,
                $v->subtotal, $v->descuento, $v->impuesto, $v->total, $v->costo_total,
                $v->pagos->map(fn ($p) => $cfg->nombreMetodo($p->metodo) . ' ' . $p->monto)->implode(' + '),
                $v->estado, $v->motivo_anulacion,
            ], ';');
        }
        rewind($out);
        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ventas_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.csv"',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function rango(Request $request): array
    {
        $periodo = $request->input('periodo', 'mes');
        [$d, $h] = match ($periodo) {
            'hoy' => [today(), today()],
            'ayer' => [today()->subDay(), today()->subDay()],
            'semana' => [today()->startOfWeek(), today()],
            'mes_pasado' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'rango' => [$request->date('desde') ?? today()->startOfMonth(), $request->date('hasta') ?? today()],
            default => [today()->startOfMonth(), today()],
        };
        if ($h < $d) {
            [$d, $h] = [$h, $d];
        }
        return [$d->copy()->startOfDay(), $h->copy()->endOfDay()];
    }
}
