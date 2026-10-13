@php $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5'; $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm'; @endphp
<x-layout titulo="Caja" activo="pos-caja">
    @include('pos._barra')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500">Abierta por <b class="text-azul-950">{{ $caja->usuario?->name }}</b> {{ $caja->abierta_en->locale('es')->isoFormat('dddd D [a las] h:mm a') }} con {{ $cfg->dinero($caja->monto_inicial) }}.</p>
        <div class="flex gap-2">
            <a href="{{ route('pos.vender') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="scan-barcode" class="size-4"></i> Seguir vendiendo</a>
            <a href="{{ route('pos.cajas') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="history" class="size-4"></i> Cierres anteriores</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([['Ventas del turno', $r['ventas'], 'receipt-text', 'text-azul-800 bg-blue-50', false], ['Vendido', $r['total'], 'trending-up', 'text-emerald-700 bg-emerald-50', true], ['Efectivo en caja (debería haber)', $r['esperado'], 'wallet', 'text-oro-700 bg-amber-50', true], ['Anuladas', $r['anuladas'], 'ban', 'text-red-600 bg-red-50', false]] as [$t, $n, $ic, $col, $dinero])
            <div class="{{ $card }} p-4"><div class="flex items-center justify-between"><span class="text-[11px] uppercase font-bold text-gray-500">{{ $t }}</span><span class="size-8 rounded-lg {{ $col }} flex items-center justify-center"><i data-lucide="{{ $ic }}" class="size-4"></i></span></div>
                <div class="text-2xl font-bold text-azul-950 mt-2 tabular-nums">{{ $dinero ? $cfg->dinero($n) : $n }}</div></div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mt-5">
        <section class="{{ $card }} p-4 sm:p-5">
            <h2 class="font-semibold text-azul-950 mb-3">Por forma de pago</h2>
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between"><span class="text-gray-500">Fondo inicial</span><span class="tabular-nums">{{ $cfg->dinero($caja->monto_inicial) }}</span></li>
                @foreach($cfg->metodosActivos() as $m)
                    <li class="flex justify-between"><span>{{ $m['nombre'] }}@if($m['clave'] === 'efectivo') <span class="text-gray-400 text-xs">(ya sin cambio)</span>@endif</span><span class="font-semibold tabular-nums">{{ $cfg->dinero($r['por_metodo'][$m['clave']] ?? 0) }}</span></li>
                @endforeach
                <li class="flex justify-between"><span class="text-gray-500">Entradas de efectivo</span><span class="tabular-nums text-emerald-700">+{{ $cfg->dinero($r['ingresos']) }}</span></li>
                <li class="flex justify-between"><span class="text-gray-500">Salidas de efectivo</span><span class="tabular-nums text-red-600">−{{ $cfg->dinero($r['retiros']) }}</span></li>
                <li class="flex justify-between border-t pt-2 font-bold text-azul-950"><span>Efectivo esperado</span><span class="tabular-nums">{{ $cfg->dinero($r['esperado']) }}</span></li>
            </ul>
            <form method="POST" action="{{ route('pos.caja.movimiento') }}" class="mt-5 space-y-2 border-t pt-4">
                @csrf
                <p class="text-sm font-semibold text-azul-950">Entrada o salida de efectivo</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer"><input type="radio" name="tipo" value="retiro" class="peer sr-only" checked><span class="block text-center border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-red-400 peer-checked:text-red-600">Salida (gasto, retiro)</span></label>
                    <label class="cursor-pointer"><input type="radio" name="tipo" value="ingreso" class="peer sr-only"><span class="block text-center border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-emerald-500 peer-checked:text-emerald-700">Entrada</span></label>
                </div>
                <input type="number" name="monto" min="0" step="any" required placeholder="Monto" class="{{ $in }}">
                <input type="text" name="motivo" maxlength="200" required placeholder="Motivo (ej: compra de tinta, pago de agua)" class="{{ $in }}">
                <button class="w-full bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg">Registrar</button>
            </form>
        </section>

        <section class="{{ $card }} p-4 sm:p-5">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><i data-lucide="lock" class="size-[18px] text-oro-500"></i> Cerrar caja</h2>
            <p class="text-xs text-gray-500 mb-3">Cuente los billetes y monedas. El sistema compara con lo que debería haber.</p>
            <form method="POST" action="{{ route('pos.caja.cerrar') }}" id="fCierre" onsubmit="return jzCerrar(event)">
                @csrf
                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                    @foreach($billetes as $b)
                        <label class="flex items-center gap-2 text-sm"><span class="w-20 text-right tabular-nums text-gray-600">{{ $cfg->dinero($b) }}</span><span class="text-gray-400">×</span>
                            <input type="number" min="0" step="1" name="conteo[{{ $b }}]" data-valor="{{ $b }}" class="jz-conteo w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right"></label>
                    @endforeach
                </div>
                <div class="mt-3"><label class="block text-[11px] font-semibold uppercase text-azul-800 mb-1">Efectivo contado</label>
                    <input type="number" name="efectivo_contado" id="contado" min="0" step="any" required class="{{ $in }} text-xl font-bold text-right tabular-nums"></div>
                <div id="dif" class="mt-2 rounded-xl p-3 text-sm font-semibold hidden"></div>
                <input type="text" name="notas_cierre" maxlength="300" placeholder="Nota (opcional)" class="{{ $in }} mt-2">
                <button class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold py-3 rounded-xl"><i data-lucide="lock" class="size-4"></i> Cerrar caja</button>
            </form>
        </section>

        <section class="{{ $card }} p-4 sm:p-5">
            <h2 class="font-semibold text-azul-950 mb-3">Últimos movimientos</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse($ventas as $v)
                    <li class="py-2 flex items-center justify-between gap-2 {{ $v->estado === 'anulada' ? 'opacity-50 line-through' : '' }}"><a href="{{ route('pos.ventas.show', $v) }}" class="min-w-0"><span class="font-mono text-xs text-gray-400">{{ $v->numero }}</span> <span class="truncate">{{ $v->nombreCliente() }}</span><span class="block text-[11px] text-gray-400">{{ $v->created_at->format('h:i a') }} · {{ $v->usuario?->name }}</span></a><span class="font-semibold tabular-nums">{{ $cfg->dinero($v->total) }}</span></li>
                @empty
                    <li class="py-6 text-center text-gray-400">Todavía no hay ventas en este turno.</li>
                @endforelse
                @foreach($movs as $m)
                    <li class="py-2 flex items-center justify-between gap-2"><span class="min-w-0">{{ $m->tipo === 'ingreso' ? 'Entrada' : 'Salida' }}: {{ $m->motivo }}<span class="block text-[11px] text-gray-400">{{ $m->created_at->format('h:i a') }} · {{ $m->usuario?->name }}</span></span><span class="font-semibold tabular-nums {{ $m->tipo === 'ingreso' ? 'text-emerald-700' : 'text-red-600' }}">{{ $m->tipo === 'ingreso' ? '+' : '−' }}{{ $cfg->dinero($m->monto) }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>

    @push('scripts')
    <script>
    (function () {
        const esperado = @json($r['esperado']), dec = @json((int) $cfg->decimales);
        const nf = new Intl.NumberFormat(@json($cfg->locale), { minimumFractionDigits: dec, maximumFractionDigits: dec }), din = n => (n < 0 ? '-' : '') + @json($cfg->simbolo) + nf.format(Math.abs(n));
        const contado = document.getElementById('contado'), dif = document.getElementById('dif');
        function sumar() { let t = 0, alguno = false; document.querySelectorAll('.jz-conteo').forEach(i => { if (i.value !== '') { alguno = true; t += (+i.value) * (+i.dataset.valor); } }); if (alguno) contado.value = Math.round(t * 100) / 100; ver(); }
        function ver() {
            if (contado.value === '') { dif.classList.add('hidden'); return; }
            const d = Math.round(((+contado.value) - esperado) * 100) / 100; dif.classList.remove('hidden');
            dif.className = 'mt-2 rounded-xl p-3 text-sm font-semibold ' + (Math.abs(d) < 0.005 ? 'bg-emerald-50 text-emerald-700' : (d > 0 ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700'));
            dif.textContent = Math.abs(d) < 0.005 ? '✓ Cuadra exacto con lo esperado.' : (d > 0 ? 'Sobran ' + din(d) : 'Faltan ' + din(-d)) + ' (esperado ' + din(esperado) + ')';
        }
        document.querySelectorAll('.jz-conteo').forEach(i => i.addEventListener('input', sumar)); contado.addEventListener('input', ver);
        window.jzCerrar = function (e) { e.preventDefault(); Swal.fire({ title: '¿Cerrar la caja?', text: 'Después de cerrar ya no se puede vender hasta abrir otro turno.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, cerrar', cancelButtonText: 'No', confirmButtonColor: '#0B3C74' }).then(r => { if (r.isConfirmed) e.target.submit(); }); return false; };
    })();
    </script>
    @endpush
</x-layout>
