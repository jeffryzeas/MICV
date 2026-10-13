<x-layout titulo="Compras" activo="pos-compras">
    @include('pos._barra')
    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-gray-500">Cada compra suma al inventario y actualiza el costo promedio de los productos.</p>
        <a href="{{ route('pos.compras.crear') }}" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="plus" class="size-4" /> Registrar compra</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="p-3">#</th><th class="p-3">Fecha</th><th class="p-3">Proveedor</th><th class="p-3">Factura</th><th class="p-3 text-right">Productos</th><th class="p-3 text-right">Total</th><th></th></tr></thead>
            <tbody>
            @forelse($compras as $c)
                <tr class="border-b last:border-0 {{ $c->estado === 'anulada' ? 'opacity-50' : '' }}">
                    <td class="p-3 font-semibold">#{{ $c->id }} @if($c->estado === 'anulada')<span class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 text-red-600">Anulada</span>@endif</td>
                    <td class="p-3">{{ $c->fecha->format('d/m/Y') }}</td>
                    <td class="p-3">{{ $c->proveedor?->nombre ?? '—' }}</td>
                    <td class="p-3 font-mono text-xs">{{ $c->numero_factura ?: '—' }}</td>
                    <td class="p-3 text-right">{{ $c->items_count }}</td>
                    <td class="p-3 text-right font-semibold tabular-nums">{{ $cfg->dinero($c->total) }}</td>
                    <td class="p-3 text-right"><a href="{{ route('pos.compras.ver', $c) }}" class="text-xs font-semibold text-azul-800 hover:underline">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-gray-400">Todavía no hay compras registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $compras->links() }}</div>
</x-layout>
