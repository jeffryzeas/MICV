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

    public function exportar(Request $request)
    {
        $request->validate(['formato'=>'required|in:pdf,excel']);
        return $request->input('formato')==='pdf' ? $this->pdf($request) : $this->excel($request);
    }

    public function pdf(Request $request)
    {
        $datos = $this->index($request)->getData();
        $datos['orientacion'] = \App\Support\Exportacion::orientacion($request, 3);
        $datos["logo"] = \App\Support\Archivos::dataUri($datos["cyberPos"]->logo);
        $html = view("exports.ventas", $datos)->render();
        return response(\App\Services\CvPdf::desdeHtml($html), 200, ["Content-Type" => "application/pdf", "Content-Disposition" => 'attachment; filename="Reporte_ventas.pdf"', "Cache-Control" => "private, no-store"]);
    }

    /** Excel con resumen, ventas, pagos, inventario y compras. */
    public function excel(Request $request)
    {
        $cfg=$this->config($request); [$desde,$hasta]=$this->rango($request);
        $ventas=PosVenta::where('cyber_id',$cfg->cyber_id)->whereBetween('created_at',[$desde,$hasta])->with('pagos','usuario','cliente')->orderBy('id')->get();
        $datos=[
            'orientacion'=>\App\Support\Exportacion::orientacion($request,8),
            'negocio'=>$cfg->nombre_comercial ?: $cfg->cyber->nombre, 'moneda'=>$cfg->moneda, 'simbolo'=>$cfg->simbolo,
            'periodo'=>$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'), 'generado'=>now()->format('d/m/Y H:i'),
            'ventas'=>$ventas->map(fn($v)=>[
                'numero'=>$v->numero,'fecha'=>$v->created_at->format('Y-m-d\TH:i:s'),'cliente'=>$v->nombreCliente(),'cajero'=>$v->usuario?->name??'',
                'subtotal'=>(float)$v->subtotal,'descuento'=>(float)$v->descuento,'impuesto'=>(float)$v->impuesto,'total'=>(float)$v->total,'costo'=>(float)$v->costo_total,'cambio'=>(float)$v->cambio,
                'estado'=>$v->estado,'motivo'=>$v->motivo_anulacion,'pagos'=>$v->pagos->map(fn($p)=>['clave'=>$p->metodo,'metodo'=>$cfg->nombreMetodo($p->metodo),'monto'=>(float)$p->monto,'referencia'=>$p->referencia])->all(),
            ])->all(),
            'inventario'=>PosProducto::where('cyber_id',$cfg->cyber_id)->where('controla_stock',true)->with('categoria')->orderBy('nombre')->get()->map(fn($p)=>[
                'codigo'=>$p->codigo,'nombre'=>$p->nombre,'categoria'=>$p->categoria?->nombre,'stock'=>(float)$p->stock,'unidad'=>$p->unidad,'minimo'=>(float)$p->stock_minimo,'costo'=>(float)$p->costo,'precio'=>(float)$p->precio,'activo'=>(bool)$p->activo,
            ])->all(),
            'compras'=>\App\Models\Pos\PosCompra::where('cyber_id',$cfg->cyber_id)->whereBetween('fecha',[$desde->toDateString(),$hasta->toDateString()])->with('proveedor','usuario')->orderBy('fecha')->get()->map(fn($c)=>[
                'id'=>$c->id,'fecha'=>$c->fecha->format('Y-m-d').'T00:00:00','factura'=>$c->numero_factura,'proveedor'=>$c->proveedor?->nombre,'usuario'=>$c->usuario?->name,'total'=>(float)$c->total,'estado'=>$c->estado,
            ])->all(),
        ];
        $dir=storage_path('app/tmp-excel'); if(!is_dir($dir)) mkdir($dir,0775,true);
        $salida=$dir.'/'.\Illuminate\Support\Str::random(24).'.xlsx';
        try {
            \App\Services\Nodo::correr(resource_path('reports/pos-excel.cjs'),[$salida],120,json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $bin=file_get_contents($salida);
        } finally { @unlink($salida); }
        return response($bin,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="Reporte_POS_'.$desde->format('Ymd').'_'.$hasta->format('Ymd').'.xlsx"','Cache-Control'=>'no-store']);
    }

    /** Detalle de ventas en CSV (abre en Excel). */
    public function csv(Request $request)
    {
        return $this->excel($request);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function rango(Request $request): array
    {
        $request->validate(['periodo' => 'nullable|in:hoy,ayer,semana,mes,mes_pasado,rango', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d']);
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
