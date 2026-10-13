<x-layout titulo="Cierres de caja" activo="pos-caja">
    @include('pos._barra')
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="p-3">Turno</th><th class="p-3">Abrió</th><th class="p-3">Cerró</th><th class="p-3 text-right">Esperado</th><th class="p-3 text-right">Contado</th><th class="p-3 text-right">Diferencia</th><th></th></tr></thead>
            <tbody>
            @forelse($cajas as $c)
                <tr class="border-b last:border-0">
                    <td class="p-3 font-semibold">#{{ $c->id }} @if($c->estado === 'abierta')<span class="ml-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Abierta</span>@endif</td>
                    <td class="p-3">{{ $c->abierta_en->format('d/m/Y h:i a') }}<span class="block text-xs text-gray-400">{{ $c->usuario?->name }}</span></td>
                    <td class="p-3">{{ $c->cerrada_en?->format('d/m/Y h:i a') ?? '—' }}<span class="block text-xs text-gray-400">{{ $c->cerro?->name }}</span></td>
                    <td class="p-3 text-right tabular-nums">{{ $c->efectivo_esperado !== null ? $cfg->dinero($c->efectivo_esperado) : '—' }}</td>
                    <td class="p-3 text-right tabular-nums">{{ $c->efectivo_contado !== null ? $cfg->dinero($c->efectivo_contado) : '—' }}</td>
                    <td class="p-3 text-right tabular-nums font-semibold {{ $c->diferencia === null ? '' : (abs((float) $c->diferencia) < 0.005 ? 'text-emerald-700' : ((float) $c->diferencia > 0 ? 'text-blue-700' : 'text-red-600')) }}">{{ $c->diferencia === null ? '—' : (abs((float) $c->diferencia) < 0.005 ? 'Cuadra' : $cfg->dinero($c->diferencia)) }}</td>
                    <td class="p-3 text-right"><a href="{{ $c->estado === 'abierta' ? route('pos.caja') : route('pos.cajas.ver', $c) }}" class="text-xs font-semibold text-azul-800 hover:underline">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-gray-400">Todavía no hay turnos de caja.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $cajas->links() }}</div>
</x-layout>
