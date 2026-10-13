@php $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp
<x-layout :titulo="'Movimientos: ' . $p->nombre" activo="pos-productos">
    @include('pos._barra')
    <p class="text-sm text-gray-500 mb-3">Existencia actual: <b class="text-azul-950">{{ $cant($p->stock) }} {{ $p->unidad }}</b> · Costo promedio: <b class="text-azul-950">{{ $cfg->dinero($p->costo) }}</b> · <a href="{{ route('pos.productos') }}" class="text-azul-800 font-semibold hover:underline">Volver</a></p>
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="p-3">Fecha</th><th class="p-3">Tipo</th><th class="p-3">Detalle</th><th class="p-3 text-right">Entra / sale</th><th class="p-3 text-right">Queda</th><th class="p-3">Usuario</th></tr></thead>
            <tbody>
            @forelse($movs as $m)
                <tr class="border-b last:border-0">
                    <td class="p-3 whitespace-nowrap">{{ $m->created_at->format('d/m/Y h:i a') }}</td>
                    <td class="p-3">{{ \App\Models\Pos\PosMovimiento::TIPOS[$m->tipo] ?? $m->tipo }}</td>
                    <td class="p-3 text-gray-500">{{ $m->referencia }}</td>
                    <td class="p-3 text-right font-semibold tabular-nums {{ (float) $m->cantidad >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ (float) $m->cantidad >= 0 ? '+' : '' }}{{ $cant($m->cantidad) }}</td>
                    <td class="p-3 text-right tabular-nums">{{ $cant($m->stock_despues) }}</td>
                    <td class="p-3 text-gray-500">{{ $m->usuario?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-gray-400">Sin movimientos todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movs->links() }}</div>
</x-layout>
