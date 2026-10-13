@php $f = fn ($n) => $cfg->dinero($n); @endphp
<x-layout titulo="Reporte de caja" activo="pos-caja">
    @include('pos._barra')
    <div class="flex flex-wrap gap-2 mb-4 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="printer" class="size-4"></i> Imprimir</button>
        <a href="{{ route('pos.cajas') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="history" class="size-4"></i> Todos los cierres</a>
        @if($caja->estado === 'cerrada')<a href="{{ route('pos.vender') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="lock-open" class="size-4"></i> Abrir otro turno</a>@endif
    </div>
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6 max-w-xl mx-auto print:shadow-none print:ring-0 print:max-w-none">
        <div class="text-center">
            <p class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Reporte de caja</p>
            <h2 class="text-lg font-bold text-azul-950">{{ $cfg->nombre_comercial ?: $cyberPos->nombre }}</h2>
            <p class="text-sm text-gray-500">Turno #{{ $caja->id }} · {{ $caja->estado === 'cerrada' ? 'Cerrado' : 'Abierto' }}</p>
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-y-1 text-sm">
            <dt class="text-gray-500">Abrió</dt><dd class="text-right">{{ $caja->usuario?->name }} · {{ $caja->abierta_en->format('d/m/Y h:i a') }}</dd>
            @if($caja->cerrada_en)<dt class="text-gray-500">Cerró</dt><dd class="text-right">{{ $caja->cerro?->name }} · {{ $caja->cerrada_en->format('d/m/Y h:i a') }}</dd>@endif
        </dl>
        <table class="w-full mt-4 text-sm">
            <tr class="border-t"><td class="py-1.5 text-gray-500">Ventas</td><td class="text-right">{{ $r['ventas'] }} ({{ $r['anuladas'] }} anuladas)</td></tr>
            <tr><td class="py-1.5 text-gray-500">Total vendido</td><td class="text-right font-semibold">{{ $f($r['total']) }}</td></tr>
            <tr><td class="py-1.5 text-gray-500">{{ $cfg->impuesto_nombre }}</td><td class="text-right">{{ $f($r['impuesto']) }}</td></tr>
            <tr class="border-t"><td class="py-1.5 text-gray-500">Fondo inicial</td><td class="text-right">{{ $f($caja->monto_inicial) }}</td></tr>
            @foreach($cfg->metodosActivos() as $m)<tr><td class="py-1.5 text-gray-500">{{ $m['nombre'] }}</td><td class="text-right">{{ $f($r['por_metodo'][$m['clave']] ?? 0) }}</td></tr>@endforeach
            <tr><td class="py-1.5 text-gray-500">Entradas de efectivo</td><td class="text-right">+{{ $f($r['ingresos']) }}</td></tr>
            <tr><td class="py-1.5 text-gray-500">Salidas de efectivo</td><td class="text-right">−{{ $f($r['retiros']) }}</td></tr>
            <tr class="border-t font-bold"><td class="py-1.5">Efectivo esperado</td><td class="text-right">{{ $f($caja->efectivo_esperado ?? $r['esperado']) }}</td></tr>
            @if($caja->estado === 'cerrada')
                <tr class="font-bold"><td class="py-1.5">Efectivo contado</td><td class="text-right">{{ $f($caja->efectivo_contado) }}</td></tr>
                <tr class="font-bold {{ abs((float) $caja->diferencia) < 0.005 ? 'text-emerald-700' : ((float) $caja->diferencia > 0 ? 'text-blue-700' : 'text-red-600') }}"><td class="py-1.5">Diferencia</td><td class="text-right">{{ abs((float) $caja->diferencia) < 0.005 ? 'Cuadra' : ((float) $caja->diferencia > 0 ? 'Sobrante ' : 'Faltante ') . $f(abs((float) $caja->diferencia)) }}</td></tr>
            @endif
        </table>
        @if($caja->conteo)
            <p class="mt-4 text-xs font-semibold uppercase text-gray-500">Conteo</p>
            <ul class="text-sm grid grid-cols-2 gap-x-6">@foreach($caja->conteo as $den => $n)<li class="flex justify-between"><span>{{ $f($den) }} × {{ $n }}</span><span>{{ $f($den * $n) }}</span></li>@endforeach</ul>
        @endif
        @if($movs->isNotEmpty())
            <p class="mt-4 text-xs font-semibold uppercase text-gray-500">Entradas y salidas</p>
            <ul class="text-sm">@foreach($movs as $m)<li class="flex justify-between gap-2"><span>{{ $m->created_at->format('h:i a') }} · {{ $m->motivo }}</span><span>{{ $m->tipo === 'ingreso' ? '+' : '−' }}{{ $f($m->monto) }}</span></li>@endforeach</ul>
        @endif
        @if($caja->notas_cierre)<p class="mt-4 text-sm text-gray-600">Nota: {{ $caja->notas_cierre }}</p>@endif
        <div class="mt-10 grid grid-cols-2 gap-8 text-center text-xs text-gray-500 print:mt-16"><div class="border-t pt-1">Entregó</div><div class="border-t pt-1">Recibió</div></div>
    </div>
</x-layout>
