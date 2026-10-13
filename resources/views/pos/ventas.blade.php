@php $in = 'border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<x-layout titulo="Ventas y comprobantes" activo="pos-ventas">
    @include('pos._barra')
    <form class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-3 flex flex-wrap items-end gap-2 mb-4">
        <label class="text-xs text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="{{ $in }} block"></label>
        <label class="text-xs text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="{{ $in }} block"></label>
        <label class="text-xs text-gray-500">Pago<select name="metodo" class="{{ $in }} block"><option value="">Todos</option>@foreach($cfg->metodosActivos() as $m)<option value="{{ $m['clave'] }}" @selected(request('metodo') === $m['clave'])>{{ $m['nombre'] }}</option>@endforeach</select></label>
        <label class="text-xs text-gray-500">Estado<select name="estado" class="{{ $in }} block"><option value="">Todas</option><option value="pagada" @selected(request('estado') === 'pagada')>Pagadas</option><option value="anulada" @selected(request('estado') === 'anulada')>Anuladas</option></select></label>
        <label class="text-xs text-gray-500 flex-1 min-w-[160px]">Buscar<input type="search" name="q" value="{{ request('q') }}" placeholder="Número o cliente" class="{{ $in }} block w-full"></label>
        <button class="bg-azul-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filtrar</button>
    </form>
    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-4"><p class="text-[11px] uppercase font-bold text-gray-500">Ventas</p><p class="text-2xl font-bold text-azul-950">{{ $totales->n }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-4"><p class="text-[11px] uppercase font-bold text-gray-500">Total</p><p class="text-2xl font-bold text-azul-950 tabular-nums">{{ $cfg->dinero($totales->total) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-4"><p class="text-[11px] uppercase font-bold text-gray-500">{{ $cfg->impuesto_nombre }}</p><p class="text-2xl font-bold text-azul-950 tabular-nums">{{ $cfg->dinero($totales->impuesto) }}</p></div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="p-3">Número</th><th class="p-3">Fecha</th><th class="p-3">Cliente</th><th class="p-3">Pago</th><th class="p-3 text-right">Total</th><th></th></tr></thead>
            <tbody>
            @forelse($ventas as $v)
                <tr class="border-b last:border-0 {{ $v->estado === 'anulada' ? 'opacity-50' : '' }}">
                    <td class="p-3 font-mono font-semibold">{{ $v->numero }} @if($v->estado === 'anulada')<span class="ml-1 text-[10px] font-sans font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-600">Anulada</span>@endif</td>
                    <td class="p-3">{{ $v->created_at->format('d/m/Y h:i a') }}<span class="block text-xs text-gray-400">{{ $v->usuario?->name }}</span></td>
                    <td class="p-3">{{ $v->nombreCliente() }}</td>
                    <td class="p-3 text-xs">{{ $v->pagos->map(fn ($p) => $cfg->nombreMetodo($p->metodo))->unique()->implode(' + ') }}</td>
                    <td class="p-3 text-right font-semibold tabular-nums">{{ $cfg->dinero($v->total) }}</td>
                    <td class="p-3 text-right whitespace-nowrap"><a href="{{ route('pos.ventas.show', $v) }}" class="text-xs font-semibold text-azul-800 hover:underline">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-gray-400">No hay ventas en esas fechas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $ventas->links() }}</div>
</x-layout>
