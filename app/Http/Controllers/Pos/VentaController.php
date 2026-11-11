<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosVenta;
use App\Services\Correo;
use App\Services\CvPdf;
use App\Services\Pos\Ventas;
use App\Support\Archivos;
use Illuminate\Http\Request;

class VentaController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        $desde = $request->date('desde') ?? today();
        $hasta = $request->date('hasta') ?? today();
        $q = PosVenta::where('cyber_id', $c['cyberPos']->id)
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('metodo'), fn ($q) => $q->whereHas('pagos', fn ($p) => $p->where('metodo', $request->input('metodo'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = trim($request->input('q'));
                $q->where(fn ($w) => $w->where('numero', 'like', "%$t%")->orWhere('cliente_nombre', 'like', "%$t%")
                    ->orWhereHas('cliente', fn ($cl) => $cl->where('primer_nombre', 'like', "%$t%")->orWhere('primer_apellido', 'like', "%$t%")));
            });
        $totales = (clone $q)->where('estado', 'pagada')->selectRaw('COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(impuesto),0) impuesto')->first();
        return view('pos.ventas', $c + [
            'ventas' => $q->with('usuario', 'cliente', 'pagos')->latest('id')->paginate(30)->withQueryString(),
            'totales' => $totales, 'desde' => $desde, 'hasta' => $hasta,
        ]);
    }

    public function show(Request $request, PosVenta $venta)
    {
        $this->delCyber($request, $venta);
        return view('pos.venta', $this->comunes($request) + ['v' => $venta->load('items', 'pagos', 'usuario', 'cliente', 'anulo', 'caja')]);
    }

    /** Comprobante en HTML para la impresora de tiquetes (80 o 58 mm). ?imprimir=1 abre el diálogo de impresión. */
    public function tiquete(Request $request, PosVenta $venta)
    {
        $this->delCyber($request, $venta);
        return response($this->html($request, $venta, $request->boolean('imprimir') ? 'imprimir' : 'pantalla'));
    }

    /** Comprobante como imagen (para enviar por WhatsApp). */
    public function imagen(Request $request, PosVenta $venta)
    {
        $this->delCyber($request, $venta);
        $png = CvPdf::png($this->html($request, $venta, 'imagen'), '.tiq', 3);
        return response($png, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="Comprobante_' . $venta->numero . '.png"', 'Cache-Control' => 'no-store']);
    }

    public function correo(Request $request, PosVenta $venta)
    {
        $this->delCyber($request, $venta);
        $d = $request->validate(['correo' => ['required', 'email', 'max:150']]);
        if (!Correo::configurado()) {
            return response()->json(['ok' => false, 'mensaje' => 'El correo del sistema todavía no está configurado.'], 422);
        }
        $cfg = $this->config($request);
        try {
            $png = CvPdf::png($this->html($request, $venta, 'imagen'), '.tiq', 3);
            Correo::enviar($d['correo'], 'Comprobante ' . $venta->numero . ' — ' . ($cfg->nombre_comercial ?: $venta->cyber->nombre), 'correos.aviso', [
                'nombre' => $venta->cliente?->primer_nombre ?: 'cliente',
                'parrafos' => [
                    'Gracias por su compra en ' . ($cfg->nombre_comercial ?: $venta->cyber->nombre) . '.',
                    'Comprobante ' . $venta->numero . ' del ' . $venta->created_at->format('d/m/Y H:i') . ' por ' . $cfg->dinero($venta->total) . '. Va adjunto como imagen.',
                ],
            ], null, [[$png, 'Comprobante_' . $venta->numero . '.png', 'image/png']]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }
        Actividad::registrar('pos_correo', $venta->numero);
        return response()->json(['ok' => true, 'mensaje' => 'Comprobante enviado a ' . $d['correo'] . '.']);
    }

    public function anular(Request $request, PosVenta $venta)
    {
        $this->delCyber($request, $venta);
        $d = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:200']], [], ['motivo' => 'motivo']);
        if ($venta->estado === 'anulada') {
            return back()->withErrors(['motivo' => 'Esta venta ya estaba anulada.']);
        }
        // Con la caja ya cerrada el arqueo quedó hecho: solo el administrador puede anular.
        if ($venta->caja && $venta->caja->estado === 'cerrada' && !$request->user()->esAdmin()) {
            return back()->withErrors(['motivo' => 'La caja de esta venta ya se cerró. Solo el administrador puede anularla.']);
        }
        Ventas::anular($venta, $request->user(), $d['motivo']);
        \App\Services\Desktop\LocalBusinessJournal::record($request,(int)$venta->cyber_id,'sale.void',$venta->id,[
            'id'=>\App\Services\Desktop\LocalBusinessJournal::reference($request,'sale.create',$venta->id),
            'motivo'=>$d['motivo'],
        ]);
        return back()->with('mensaje', 'Venta anulada. El inventario se devolvió.');
    }

    protected function html(Request $request, PosVenta $venta, string $modo): string
    {
        $cfg = $this->config($request);
        return view('pos.tiquete', [
            'v' => $venta->load('items', 'pagos', 'usuario', 'cliente', 'cyber'),
            'cfg' => $cfg,
            'logo' => Archivos::dataUri($venta->cyber->logo),
            'modo' => $modo,
        ])->render();
    }
}
