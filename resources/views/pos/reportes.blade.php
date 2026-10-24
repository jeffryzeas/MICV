@php
    $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5';
    $max = max(1, max($dias ?: [0]));
    $periodo = request('periodo', 'mes');
    $margen = $base > 0 ? round($ganancia / $base * 100) : 0;
@endphp
<x-layout titulo="Reportes de ventas" activo="pos-reportes">
    @include('pos._barra')
    <form class="flex flex-wrap items-end gap-2 mb-4" id="fPeriodo">
        <div class="flex flex-wrap gap-1 bg-white rounded-xl p-1 ring-1 ring-gray-900/5">
            @foreach(['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado', 'rango' => 'Fechas…'] as $k => $t)
                <label class="cursor-pointer"><input type="radio" name="periodo" value="{{ $k }}" class="peer sr-only" @checked($periodo === $k) onchange="if(this.value!=='rango')this.form.submit(); document.getElementById('rango').classList.toggle('hidden', this.value!=='rango')">
                    <span class="block px-3 py-1.5 rounded-lg text-sm font-semibold text-gray-600 peer-checked:bg-azul-800 peer-checked:text-white">{{ $t }}</span></label>
            @endforeach
        </div>
        <div id="rango" class="{{ $periodo === 'rango' ? '' : 'hidden' }} flex items-end gap-2">
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <button class="bg-azul-800 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">Ver</button>
        </div>
        <a href="{{ route('pos.reportes.excel', request()->query()) }}" class="ml-auto inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg"><x-icono name="file-spreadsheet" class="size-4" /> Descargar para Excel</a>
        <a class="btn btn-pri" href="{{ route('pos.reportes.pdf', request()->query()) }}"><x-icono name="file-text"/>Descargar PDF</a>
    </form>
    <p class="text-sm text-gray-500 mb-3">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach([['Ventas', number_format($tot->n), 'receipt-text', 'text-azul-800 bg-blue-50'], ['Vendido', $cfg->dinero($tot->total), 'trending-up', 'text-emerald-700 bg-emerald-50'], ['Ticket promedio', $cfg->dinero($tot->n ? $tot->total / $tot->n : 0), 'calculator', 'text-violet-700 bg-violet-50'], ['Ganancia bruta', $cfg->dinero($ganancia) . ' · ' . $margen . ' %', 'piggy-bank', 'text-oro-700 bg-amber-50'], [$cfg->impuesto_nombre . ' cobrado', $cfg->dinero($tot->impuesto), 'landmark', 'text-gray-700 bg-gray-100']] as [$t, $n, $ic, $col])
            <div class="{{ $card }} p-4"><div class="flex items-center justify-between"><span class="text-[11px] uppercase font-bold text-gray-500">{{ $t }}</span><span class="size-8 rounded-lg {{ $col }} flex items-center justify-center"><i data-lucide="{{ $ic }}" class="size-4"></i></span></div>
                <div class="text-xl font-bold text-azul-950 mt-2 tabular-nums">{{ $n }}</div></div>
        @endforeach
    </div>
    <p class="text-[11px] text-gray-400 mt-2">Ganancia bruta = ventas sin {{ $cfg->impuesto_nombre }} − costo de lo vendido. Los servicios sin costo cuentan completos. {{ $anuladas ? $anuladas . ' venta(s) anulada(s) no se cuentan.' : '' }}</p>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mt-5">
        <section class="{{ $card }} p-4 sm:p-5 xl:col-span-2">
            <h2 class="font-semibold text-azul-950 mb-3">Ventas por día</h2>
            <div class="flex items-end gap-1 h-48" role="img" aria-label="Ventas por día">
                @foreach($dias as $dia => $monto)
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative min-w-0">
                        <span class="hidden group-hover:block absolute -top-7 whitespace-nowrap text-[11px] bg-azul-950 text-white px-2 py-0.5 rounded z-10">{{ \Illuminate\Support\Carbon::parse($dia)->format('d/m') }} · {{ $cfg->dinero($monto) }}</span>
                        <span class="w-full max-w-6 rounded-t bg-azul-800 group-hover:bg-oro-500" style="height: {{ $monto > 0 ? max(2, round($monto / $max * 100)) : 0 }}%"></span>
                        @if(count($dias) <= 16 || $loop->first || $loop->last || \Illuminate\Support\Carbon::parse($dia)->day % 5 === 0)<span class="text-[9px] text-gray-400 mt-1">{{ \Illuminate\Support\Carbon::parse($dia)->format('d') }}</span>@else<span class="text-[9px] mt-1">&nbsp;</span>@endif
                    </div>
                @endforeach
            </div>
        </section>
        <section class="{{ $card }} p-4 sm:p-5">
            <h2 class="font-semibold text-azul-950 mb-3">Por forma de pago</h2>
            @php $sumM = max(0.01, array_sum($porMetodo)); @endphp
            @forelse($porMetodo as $m => $monto)
                <div class="mb-3"><div class="flex justify-between text-sm"><span>{{ $cfg->nombreMetodo($m) }}</span><span class="font-semibold tabular-nums">{{ $cfg->dinero($monto) }}</span></div>
                    <div class="h-2 rounded-full bg-gray-100 mt-1"><div class="h-2 rounded-full bg-azul-800" style="width: {{ round($monto / $sumM * 100) }}%"></div></div></div>
            @empty<p class="text-sm text-gray-400">Sin ventas.</p>@endforelse
            <h2 class="font-semibold text-azul-950 mb-2 mt-5">Por usuario</h2>
            @foreach($porUsuario as $u)<div class="flex justify-between text-sm py-1"><span>{{ $u->usuario?->name ?? '—' }} <span class="text-gray-400">({{ $u->n }})</span></span><span class="font-semibold tabular-nums">{{ $cfg->dinero($u->total) }}</span></div>@endforeach
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mt-5">
        <section class="{{ $card }} p-4 sm:p-5 xl:col-span-2 overflow-x-auto">
            <h2 class="font-semibold text-azul-950 mb-3">Lo más vendido</h2>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2">Producto o servicio</th><th class="py-2 text-right">Cantidad</th><th class="py-2 text-right">Vendido</th><th class="py-2 text-right">Ganancia</th></tr></thead>
                <tbody>@forelse($top as $t)<tr class="border-b last:border-0"><td class="py-2">{{ $t->descripcion }}</td><td class="py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $t->cant, 2, '.', ''), '0'), '.') }}</td><td class="py-2 text-right font-semibold tabular-nums">{{ $cfg->dinero($t->total) }}</td><td class="py-2 text-right tabular-nums text-gray-500">{{ (float) $t->costo ? $cfg->dinero(($cfg->precios_con_impuesto ? $t->total / (1 + $cfg->impuesto_tasa / 100) : $t->total) - $t->costo) : '—' }}</td></tr>@empty<tr><td colspan="4" class="py-6 text-center text-gray-400">Sin ventas en el período.</td></tr>@endforelse</tbody>
            </table>
        </section>
        <section class="{{ $card }} p-4 sm:p-5">
            <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><x-icono name="triangle-alert" class="size-[18px] text-red-500" /> Por agotarse</h2>
            @forelse($bajos as $p)
                <a href="{{ route('pos.productos.kardex', $p) }}" class="flex justify-between text-sm py-1.5 hover:text-azul-800"><span class="truncate">{{ $p->nombre }}</span><span class="font-semibold text-red-600 tabular-nums shrink-0 ml-2">{{ rtrim(rtrim(number_format((float) $p->stock, 2, '.', ''), '0'), '.') }} {{ $p->unidad }}</span></a>
            @empty<p class="text-sm text-gray-400">Todo con existencias. <x-icono name="circle-check" class="size-4"/></p>@endforelse
            @if($bajos->isNotEmpty())<a href="{{ route('pos.compras.crear') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-azul-800 hover:underline"><x-icono name="truck" class="size-4" /> Registrar compra</a>@endif
        </section>
    </div>
</x-layout>
