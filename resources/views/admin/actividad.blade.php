@php $in = 'border border-gray-300 rounded-lg px-3 py-2.5 text-sm'; @endphp
<x-layout titulo="Auditoría" activo="actividad">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-oro-500"><div class="text-xs uppercase font-bold text-azul-800">Movimientos hoy</div><div class="text-2xl font-bold text-azul-950 mt-1">{{ $hoy }}</div></div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $fallidos > 0 ? 'border-red-500' : 'border-gray-300' }}"><div class="text-xs uppercase font-bold text-azul-800">Logins fallidos (24 h)</div><div class="text-2xl font-bold text-azul-950 mt-1">{{ $fallidos }}</div></div>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end mb-5">
            <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Acción</label>
                <select name="accion" class="{{ $in }}"><option value="">Todas</option>
                    @foreach($acciones as $a)<option value="{{ $a }}" @selected($accion === $a)>{{ str_replace('_', ' ', $a) }}</option>@endforeach
                </select></div>
            <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Usuario o detalle</label><input type="search" name="usuario" value="{{ $usuario }}" class="{{ $in }}"></div>
            <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Desde</label><input type="date" name="desde" value="{{ $desde }}" class="{{ $in }}"></div>
            <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Hasta</label><input type="date" name="hasta" value="{{ $hasta }}" class="{{ $in }}"></div>
            <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="search" class="size-4" /> Filtrar</button>
            @if($accion || $usuario || $desde || $hasta)<a href="{{ route('admin.actividad') }}" class="text-sm text-gray-500 px-3 py-2.5">Limpiar</a>@endif
        </form>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2 pr-3">Fecha</th><th class="py-2 pr-3">Usuario</th><th class="py-2 pr-3">Tipo</th><th class="py-2 pr-3">Acción</th><th class="py-2 pr-3">Detalle</th><th class="py-2">IP</th></tr></thead>
            <tbody>
            @forelse ($registros as $r)
                @php $mala = in_array($r->accion, ['login_fallido', 'verificacion_fallida']); @endphp
                <tr class="border-b last:border-0 {{ $mala ? 'bg-red-50/50' : '' }}">
                    <td class="py-2 pr-3 whitespace-nowrap text-xs">{{ $r->created_at?->format('d/m/Y h:i A') }}</td>
                    <td class="py-2 pr-3 font-mono text-xs">{{ $r->usuario?->usuario ?? '—' }}</td>
                    <td class="py-2 pr-3 text-xs">{{ $r->usuario ? ($r->usuario->esAdmin() ? 'Admin' : ($r->usuario->esCliente() ? 'Cliente' : ($r->usuario->cyber?->nombre ?? 'Negocio'))) : '—' }}</td>
                    <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full text-xs {{ $mala ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }}">{{ str_replace('_', ' ', $r->accion) }}</span></td>
                    <td class="py-2 pr-3 text-xs">{{ $r->detalle }}</td>
                    <td class="py-2 text-xs text-gray-400">{{ $r->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-400">Sin movimientos con ese filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.paginacion', ['p' => $registros])
    </div>
</x-layout>
