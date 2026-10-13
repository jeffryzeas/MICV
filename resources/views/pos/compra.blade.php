<x-layout :titulo="'Compra #' . $compra->id" activo="pos-compras">
    @include('pos._barra')
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 max-w-3xl">
        <div class="flex flex-wrap justify-between gap-2">
            <div><p class="text-lg font-bold text-azul-950">Compra #{{ $compra->id }} @if($compra->estado === 'anulada')<span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-600 align-middle">Anulada</span>@endif</p>
                <p class="text-sm text-gray-500">{{ $compra->fecha->format('d/m/Y') }} · {{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}{{ $compra->numero_factura ? ' · Factura ' . $compra->numero_factura : '' }} · registró {{ $compra->usuario?->name }}</p></div>
            <a href="{{ route('pos.compras') }}" class="text-sm font-semibold text-azul-800 hover:underline">← Compras</a>
        </div>
        <table class="w-full text-sm mt-4">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2">Producto</th><th class="py-2 text-right">Cantidad</th><th class="py-2 text-right">Costo</th><th class="py-2 text-right">Subtotal</th></tr></thead>
            <tbody>@foreach($compra->items as $it)<tr class="border-b"><td class="py-2">{{ $it->descripcion }}</td><td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $it->cantidad, 2, '.', ''), '0'), '.') }}</td><td class="py-2 text-right tabular-nums">{{ $cfg->dinero($it->costo) }}</td><td class="py-2 text-right tabular-nums">{{ $cfg->dinero($it->subtotal) }}</td></tr>@endforeach</tbody>
            <tfoot><tr><td colspan="3" class="py-3 text-right font-semibold">Total</td><td class="py-3 text-right text-lg font-bold tabular-nums">{{ $cfg->dinero($compra->total) }}</td></tr></tfoot>
        </table>
        @if($compra->notas)<p class="text-sm text-gray-600">Notas: {{ $compra->notas }}</p>@endif
        @if($compra->estado === 'registrada')
            <form method="POST" action="{{ route('pos.compras.anular', $compra) }}" class="jz-confirmar mt-4 text-right" data-msg="Se rebajan del inventario las cantidades de esta compra." @if(!auth()->user()->tienePermiso('pos.compras.anular')) hidden style="display:none" @endif>@csrf<button class="text-sm text-red-600 hover:underline">Anular compra</button></form>
        @endif
    </div>
</x-layout>
