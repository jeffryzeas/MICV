@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm'; $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Registrar compra" activo="pos-compras">
    @include('pos._barra')
    @if($productos->isEmpty())
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-4 text-sm">Primero <a href="{{ route('pos.productos.crear') }}" class="underline font-semibold">cree los productos</a> que va a comprar (los servicios no llevan inventario).</div>
    @else
    <form method="POST" action="{{ route('pos.compras.guardar') }}" id="fCompra" class="space-y-4">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2"><label class="{{ $lb }}">Proveedor</label><select name="proveedor_id" id="prov" class="{{ $in }}"><option value="">— Sin proveedor —</option>@foreach($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->nombre }}</option>@endforeach</select>
                <a href="{{ route('pos.proveedores') }}" class="text-[11px] text-azul-800 hover:underline">+ Agregar proveedor</a></div>
            <div><label class="{{ $lb }}">N.º de factura</label><input name="numero_factura" maxlength="60" value="{{ old('numero_factura') }}" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Fecha</label><input type="date" name="fecha" value="{{ old('fecha', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" class="{{ $in }}"></div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5">
            <div class="relative max-w-md">
                <i data-lucide="search" class="size-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="search" id="buscaProd" autocomplete="off" placeholder="Buscar producto o escanear código" class="{{ $in }} pl-9">
                <div id="resProd" class="hidden absolute z-20 left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 max-h-64 overflow-y-auto"></div>
            </div>
            <table class="w-full text-sm mt-4">
                <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2">Producto</th><th class="py-2 w-28">Cantidad</th><th class="py-2 w-36">Costo unitario</th><th class="py-2 text-right w-32">Subtotal</th><th class="w-8"></th></tr></thead>
                <tbody id="filas"><tr id="vacioFilas"><td colspan="5" class="py-8 text-center text-gray-400">Busque los productos que compró.</td></tr></tbody>
                <tfoot><tr class="border-t"><td colspan="3" class="py-3 text-right font-semibold">Total</td><td class="py-3 text-right text-xl font-bold text-azul-950 tabular-nums" id="totalCompra"></td><td></td></tr></tfoot>
            </table>
            <input name="notas" maxlength="300" placeholder="Notas (opcional)" value="{{ old('notas') }}" class="{{ $in }} mt-2">
        </div>
        <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-6 py-3 rounded-lg"><i data-lucide="check" class="size-4"></i> Guardar compra y sumar al inventario</button>
    </form>
    @push('scripts')
    <script>
    (function () {
        const P = @json($productos), dec = @json((int) $cfg->decimales), sim = @json($cfg->simbolo);
        const nf = new Intl.NumberFormat(@json($cfg->locale), { minimumFractionDigits: dec, maximumFractionDigits: dec }), din = n => sim + nf.format(n);
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const filas = document.getElementById('filas'); let n = 0;
        function total() { let t = 0; filas.querySelectorAll('tr[data-i]').forEach(tr => { const s = (+tr.querySelector('.c').value || 0) * (+tr.querySelector('.k').value || 0); tr.querySelector('.s').textContent = din(s); t += s; }); document.getElementById('totalCompra').textContent = din(t); document.getElementById('vacioFilas')?.classList.toggle('hidden', !!filas.querySelector('tr[data-i]')); }
        function agregar(p) {
            const ya = filas.querySelector('tr[data-id="' + p.id + '"]'); if (ya) { const c = ya.querySelector('.c'); c.value = (+c.value || 0) + 1; total(); c.focus(); return; }
            const i = n++, tr = document.createElement('tr'); tr.dataset.i = i; tr.dataset.id = p.id; tr.className = 'border-b';
            tr.innerHTML = `<td class="py-2 pr-2"><input type="hidden" name="items[${i}][producto_id]" value="${p.id}"><span class="font-semibold">${esc(p.nombre)}</span><span class="block text-[11px] text-gray-400">Hay ${+p.stock} ${esc(p.unidad)} · último costo ${din(+p.costo)}</span></td>
                <td class="py-2 pr-2"><input class="c w-full border border-gray-300 rounded-lg px-2 py-1.5 text-right" type="number" min="0.01" step="any" name="items[${i}][cantidad]" value="1" required></td>
                <td class="py-2 pr-2"><input class="k w-full border border-gray-300 rounded-lg px-2 py-1.5 text-right" type="number" min="0" step="any" name="items[${i}][costo]" value="${+p.costo || ''}" required></td>
                <td class="py-2 text-right tabular-nums s"></td><td class="py-2 text-right"><button type="button" class="text-gray-400 hover:text-red-600 quitar">✕</button></td>`;
            filas.appendChild(tr); total(); tr.querySelector('.c').select();
        }
        filas.addEventListener('input', total);
        filas.addEventListener('click', e => { if (e.target.closest('.quitar')) { e.target.closest('tr').remove(); total(); } });
        const busca = document.getElementById('buscaProd'), res = document.getElementById('resProd');
        function buscar() {
            const q = busca.value.trim().toLowerCase(), prov = document.getElementById('prov').value;
            if (!q) { res.classList.add('hidden'); return; }
            const l = P.filter(p => p.nombre.toLowerCase().includes(q) || (p.codigo || '').toLowerCase() === q).sort((a, b) => (String(b.proveedor_id) === prov) - (String(a.proveedor_id) === prov)).slice(0, 12);
            res.innerHTML = l.length ? l.map(p => `<button type="button" data-id="${p.id}" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">${esc(p.nombre)} <span class="text-xs text-gray-400">· hay ${+p.stock}</span></button>`).join('') : '<p class="px-3 py-2 text-sm text-gray-400">No está. Créelo primero en Productos.</p>';
            res.classList.remove('hidden');
        }
        busca.addEventListener('input', buscar);
        busca.addEventListener('keydown', e => { if (e.key !== 'Enter') return; e.preventDefault(); const q = busca.value.trim().toLowerCase(); const p = P.find(x => (x.codigo || '').toLowerCase() === q) || (res.querySelectorAll('[data-id]').length === 1 ? P.find(x => x.id === +res.querySelector('[data-id]').dataset.id) : null); if (p) { agregar(p); busca.value = ''; res.classList.add('hidden'); } });
        res.addEventListener('click', e => { const b = e.target.closest('[data-id]'); if (!b) return; agregar(P.find(p => p.id === +b.dataset.id)); busca.value = ''; res.classList.add('hidden'); busca.focus(); });
        document.getElementById('fCompra').addEventListener('submit', e => { if (!filas.querySelector('tr[data-i]')) { e.preventDefault(); Swal.fire({ icon: 'info', title: 'Agregue al menos un producto', confirmButtonColor: '#0B3C74' }); } });
        total();
    })();
    </script>
    @endpush
    @endif
</x-layout>
