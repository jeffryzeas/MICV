@php $f = fn ($n) => $cfg->dinero($n); $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5'; @endphp
<x-layout :titulo="'Venta ' . $v->numero" activo="pos-ventas">
    @include('pos._barra')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <section class="{{ $card }} p-4 sm:p-5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><p class="font-mono text-lg font-bold text-azul-950">{{ $v->numero }}</p><p class="text-sm text-gray-500">{{ $v->created_at->locale('es')->isoFormat('dddd D [de] MMMM YYYY, h:mm a') }} · {{ $v->usuario?->name }}</p></div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $v->estado === 'anulada' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $v->estado === 'anulada' ? 'Anulada' : 'Pagada' }}</span>
            </div>
            <p class="mt-3 text-sm"><span class="text-gray-500">Cliente:</span> @if($v->cliente)<a href="{{ route('clientes.show', $v->cliente) }}" class="font-semibold text-azul-800 hover:underline">{{ $v->cliente->nombreCompleto() }}</a>@else<b>{{ $v->nombreCliente() }}</b>@endif</p>
            <table class="w-full text-sm mt-4">
                <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2">Descripción</th><th class="py-2 text-right">Cant.</th><th class="py-2 text-right">Precio</th><th class="py-2 text-right">Desc.</th><th class="py-2 text-right">Total</th></tr></thead>
                <tbody>@foreach($v->items as $it)<tr class="border-b last:border-0"><td class="py-2">{{ $it->descripcion }}</td><td class="py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $it->cantidad, 2, '.', ''), '0'), '.') }}</td><td class="py-2 text-right tabular-nums">{{ $f($it->precio) }}</td><td class="py-2 text-right tabular-nums">{{ (float) $it->descuento ? $f($it->descuento) : '—' }}</td><td class="py-2 text-right font-semibold tabular-nums">{{ $f($it->total) }}</td></tr>@endforeach</tbody>
            </table>
            <div class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="tabular-nums">{{ $f($v->subtotal) }}</span></div>
                @if((float) $v->descuento)<div class="flex justify-between"><span class="text-gray-500">Descuento</span><span class="tabular-nums">−{{ $f($v->descuento) }}</span></div>@endif
                <div class="flex justify-between"><span class="text-gray-500">{{ $cfg->impuesto_nombre }}</span><span class="tabular-nums">{{ $f($v->impuesto) }}</span></div>
                <div class="flex justify-between text-lg font-bold text-azul-950 border-t pt-1"><span>Total</span><span class="tabular-nums">{{ $f($v->total) }}</span></div>
                @foreach($v->pagos as $p)<div class="flex justify-between text-gray-600"><span>{{ $cfg->nombreMetodo($p->metodo) }}{{ $p->referencia ? ' · ' . $p->referencia : '' }}</span><span class="tabular-nums">{{ $f($p->monto) }}</span></div>@endforeach
                @if((float) $v->cambio)<div class="flex justify-between text-gray-600"><span>Cambio</span><span class="tabular-nums">{{ $f($v->cambio) }}</span></div>@endif
                <div class="flex justify-between text-xs text-gray-400 pt-2"><span>Costo / ganancia</span><span class="tabular-nums">{{ $f($v->costo_total) }} / {{ $f((float) $v->total - (float) $v->impuesto - (float) $v->costo_total) }}</span></div>
            </div>
            @if($v->estado === 'anulada')<div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm p-3">Anulada por {{ $v->anulo?->name }} el {{ $v->anulada_en?->format('d/m/Y h:i a') }}. Motivo: {{ $v->motivo_anulacion }}</div>@endif
            @if($v->notas)<p class="mt-3 text-sm text-gray-600">Nota: {{ $v->notas }}</p>@endif
        </section>
        <aside class="space-y-3">
            <div class="{{ $card }} p-3 grid grid-cols-2 gap-2">
                <a href="{{ route('pos.ventas.tiquete', [$v, 'imprimir' => 1]) }}" target="_blank" class="col-span-2 inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold py-3 rounded-lg"><i data-lucide="printer" class="size-4"></i> Imprimir comprobante</a>
                <a href="{{ route('pos.ventas.imagen', $v) }}" target="_blank" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="image" class="size-4"></i> Imagen</a>
                <button type="button" id="btnCorreo" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="mail" class="size-4"></i> Correo</button>
            </div>
            <iframe src="{{ route('pos.ventas.tiquete', $v) }}" class="w-full h-[520px] rounded-2xl ring-1 ring-gray-200 bg-gray-200" title="Comprobante"></iframe>
            @if($v->estado === 'pagada')
                <form method="POST" action="{{ route('pos.ventas.anular', $v) }}" onsubmit="return jzAnular(event)" class="{{ $card }} p-3">
                    @csrf<input type="hidden" name="motivo">
                    <button class="w-full inline-flex items-center justify-center gap-2 text-red-600 hover:bg-red-50 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="ban" class="size-4"></i> Anular venta</button>
                    <p class="text-[11px] text-gray-400 text-center">Devuelve el inventario y la saca de los totales.</p>
                </form>
            @endif
        </aside>
    </div>
    @push('scripts')
    <script>
    window.jzAnular = function (e) {
        e.preventDefault();
        Swal.fire({ title: '¿Anular la venta {{ $v->numero }}?', input: 'text', inputLabel: 'Motivo (queda registrado)', inputPlaceholder: 'Ej: se cobró de más', showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'No', confirmButtonColor: '#c0392b',
            inputValidator: v => (!v || v.trim().length < 3) ? 'Escriba el motivo' : null })
            .then(r => { if (r.isConfirmed) { e.target.motivo.value = r.value; e.target.submit(); } });
        return false;
    };
    document.getElementById('btnCorreo').addEventListener('click', async () => {
        const r = await Swal.fire({ title: 'Enviar comprobante', input: 'email', inputValue: @json($v->cliente?->correo ?? ''), inputPlaceholder: 'correo@ejemplo.com', showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74', showLoaderOnConfirm: true,
            preConfirm: async (correo) => { const x = await fetch(@json(route('pos.ventas.correo', $v)), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ correo }) }); const j = await x.json().catch(() => ({})); if (!x.ok) { Swal.showValidationMessage(j.mensaje || j.message || 'No se pudo enviar'); return false; } return j; } });
        if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Enviado', text: r.value.mensaje, timer: 2000, showConfirmButton: false });
    });
    </script>
    @endpush
</x-layout>
