<x-layout titulo="Vender" activo="pos-vender">
    @include('pos._barra')
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_390px] gap-4 lg:h-[calc(100vh-128px)]" id="pos">
        {{-- Productos --}}
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 flex flex-col min-h-0">
            <div class="p-3 border-b border-gray-100 space-y-2">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <i data-lucide="scan-barcode" class="size-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input id="buscar" type="search" autocomplete="off" placeholder="Buscar o escanear código…  (F2)" class="w-full border border-gray-300 rounded-xl pl-10 pr-3 py-2.5 text-sm focus:border-azul-800 focus:ring-1 focus:ring-azul-800">
                    </div>
                    <button type="button" id="btnLibre" class="shrink-0 inline-flex items-center gap-1.5 border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-3 rounded-xl" title="Cobrar algo que no está en la lista"><i data-lucide="plus" class="size-4"></i><span class="hidden sm:inline">Otro</span></button>
                </div>
                <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1" id="cats">
                    <button type="button" data-cat="" class="jz-cat shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-azul-800 text-white">Todos</button>
                    <button type="button" data-cat="fav" class="jz-cat shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">★ Favoritos</button>
                    @foreach($categorias as $cat)
                        <button type="button" data-cat="{{ $cat->id }}" class="jz-cat shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600"><span class="inline-block size-2 rounded-full mr-1" style="background: {{ $cat->color }}"></span>{{ $cat->nombre }}</button>
                    @endforeach
                </div>
            </div>
            <div id="grid" class="flex-1 overflow-y-auto p-3 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2 content-start max-h-[60vh] lg:max-h-none"></div>
        </section>

        {{-- Carrito --}}
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 flex flex-col min-h-0" id="carritoCaja">
            <div class="p-3 border-b border-gray-100 relative">
                <div class="flex items-center gap-2">
                    <i data-lucide="user-round" class="size-5 text-gray-400 shrink-0"></i>
                    <input id="cliente" type="text" autocomplete="off" placeholder="Cliente general (buscar por nombre o documento)" class="flex-1 min-w-0 border-0 focus:ring-0 text-sm px-0">
                    <button type="button" id="quitarCliente" class="hidden text-gray-400 hover:text-red-600"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <div id="clientesRes" class="hidden absolute left-2 right-2 top-full z-20 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 max-h-64 overflow-y-auto"></div>
            </div>
            <ul id="lineas" class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-[160px]"></ul>
            <div class="border-t border-gray-100 p-3 space-y-1.5 text-sm">
                <div class="flex justify-between text-gray-500"><span>Subtotal</span><span id="tSub" class="tabular-nums"></span></div>
                <div class="flex justify-between items-center text-gray-500">
                    <button type="button" id="btnDesc" class="inline-flex items-center gap-1 hover:text-azul-800"><i data-lucide="percent" class="size-3.5"></i> Descuento <span id="descPct"></span></button>
                    <span id="tDesc" class="tabular-nums"></span>
                </div>
                <div class="flex justify-between text-gray-500"><span>{{ $cfg->impuesto_nombre }} {{ $cfg->precios_con_impuesto ? '(incluido)' : '' }}</span><span id="tImp" class="tabular-nums"></span></div>
                <div class="flex justify-between items-end pt-1"><span class="font-semibold text-azul-950">Total</span><span id="tTotal" class="text-3xl font-black text-azul-950 tabular-nums"></span></div>
                <div class="grid grid-cols-[auto_1fr] gap-2 pt-2">
                    <button type="button" id="btnVaciar" class="px-3 rounded-xl border border-gray-300 text-gray-500 hover:text-red-600 hover:border-red-300" title="Vaciar"><i data-lucide="trash-2" class="size-5"></i></button>
                    <button type="button" id="btnCobrar" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white font-bold text-lg py-3.5 rounded-xl"><i data-lucide="banknote" class="size-6"></i> Cobrar <span class="text-xs font-semibold opacity-75">(F9)</span></button>
                </div>
                <div class="flex justify-between text-[11px] text-gray-400 pt-1">
                    <span>Caja abierta {{ $caja->abierta_en->diffForHumans() }} · {{ $caja->usuario?->name }}</span>
                    <a href="{{ route('pos.caja') }}" class="font-semibold text-azul-800 hover:underline">Ver caja →</a>
                </div>
            </div>
        </section>
    </div>

    {{-- Cobro --}}
    <div id="modalCobro" class="hidden fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center sm:p-4">
        <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[95vh] overflow-y-auto">
            <div id="pasoPago" class="p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-azul-950">Cobrar</h2>
                    <button type="button" data-cerrar class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="size-6"></i></button>
                </div>
                <div class="text-center my-3"><p class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Total a pagar</p><p id="cTotal" class="text-5xl font-black text-azul-950 tabular-nums"></p></div>
                <div id="metodos" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1" id="lblMonto">Monto</label>
                    <div class="relative"><span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-gray-400">{{ $cfg->simbolo }}</span>
                        <input id="monto" type="number" min="0" step="{{ $cfg->decimales ? '0.01' : '1' }}" class="w-full border border-gray-300 rounded-xl pl-14 pr-4 py-3 text-2xl font-bold text-right tabular-nums"></div>
                    <div id="billetes" class="flex flex-wrap gap-1.5 mt-2"></div>
                    <input id="referencia" type="text" maxlength="60" placeholder="Referencia o comprobante (opcional)" class="hidden mt-2 w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                    <button type="button" id="btnAgregarPago" class="mt-2 text-sm font-semibold text-azul-800 hover:underline">+ Pagar una parte con otro método</button>
                </div>
                <ul id="pagosLista" class="mt-3 space-y-1"></ul>
                <div class="mt-4 rounded-2xl p-3 flex items-center justify-between" id="cajaCambio">
                    <span class="font-semibold" id="lblCambio">Cambio</span><span id="cCambio" class="text-3xl font-black tabular-nums"></span>
                </div>
                <input id="notas" type="text" maxlength="300" placeholder="Nota (opcional)" class="mt-3 w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
                <button type="button" id="btnConfirmar" class="mt-4 w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white font-bold text-lg py-4 rounded-2xl"><i data-lucide="check" class="size-6"></i> Confirmar venta <span class="text-xs opacity-75">(Enter)</span></button>
            </div>
            <div id="pasoListo" class="hidden p-6 text-center">
                <div class="mx-auto size-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center"><i data-lucide="check" class="size-9"></i></div>
                <h2 class="text-xl font-bold text-azul-950 mt-3">Venta <span id="lNumero"></span></h2>
                <p class="text-gray-500"><span id="lTotal"></span></p>
                <div id="lCambioCaja" class="mt-4 rounded-2xl bg-amber-50 text-amber-800 py-4"><p class="text-xs uppercase tracking-widest font-semibold">Entregue de cambio</p><p id="lCambio" class="text-5xl font-black tabular-nums"></p></div>
                <div class="grid grid-cols-2 gap-2 mt-5">
                    <button type="button" id="btnImprimir" class="inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold py-3 rounded-xl"><i data-lucide="printer" class="size-5"></i> Imprimir</button>
                    <button type="button" id="btnWhats" class="inline-flex items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1ebe5b] text-white font-semibold py-3 rounded-xl"><i data-lucide="message-circle" class="size-5"></i> WhatsApp</button>
                    <button type="button" id="btnCorreo" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 font-semibold py-3 rounded-xl"><i data-lucide="mail" class="size-5"></i> Correo</button>
                    <a id="btnVer" href="#" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 font-semibold py-3 rounded-xl"><i data-lucide="receipt-text" class="size-5"></i> Ver</a>
                </div>
                <button type="button" id="btnNueva" class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg py-3.5 rounded-2xl"><i data-lucide="plus" class="size-5"></i> Nueva venta <span class="text-xs opacity-75">(Esc)</span></button>
            </div>
        </div>
    </div>
    <iframe id="impresion" class="hidden" title="Impresión"></iframe>

    {{-- En celular: botón fijo para ir a cobrar --}}
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 p-3 bg-white/95 backdrop-blur border-t border-gray-200">
        <button type="button" onclick="document.getElementById('carritoCaja').scrollIntoView({behavior:'smooth'})" class="w-full flex items-center justify-between bg-azul-800 text-white font-semibold px-4 py-3 rounded-xl"><span><span id="mCant">0</span> artículos</span><span id="mTotal" class="text-lg font-black tabular-nums"></span></button>
    </div>
    <div class="h-20 lg:hidden"></div>

    @push('scripts')
    <script>
    (function () {
        const CFG = @json($cfg->paraJs());
        let PRODUCTOS = @json($productos);
        const URL_VENTA = @json(route('pos.ventas.guardar')), URL_CLIENTES = @json(route('cartas.buscar'));
        const token = document.querySelector('meta[name=csrf-token]').content;
        const $ = id => document.getElementById(id);
        const nf = new Intl.NumberFormat(CFG.locale, { minimumFractionDigits: CFG.decimales, maximumFractionDigits: CFG.decimales });
        const nfFracciones = new Intl.NumberFormat(CFG.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const din = n => (n < 0 ? '-' : '') + CFG.simbolo + (CFG.decimales === 0 && Math.abs(n - Math.round(n)) > 0.004 ? nfFracciones : nf).format(Math.abs(n));
        const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
        const rd = n => { const f = Math.pow(10, CFG.decimales); return Math.round((n + Number.EPSILON) * f) / f; };
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        // Estado de la venta (se guarda en el navegador por si se recarga la página).
        let venta = { lineas: [], descPct: 0, cliente: @json($clientePre), clienteNombre: '' };
        try { const g = JSON.parse(localStorage.getItem('pos-venta-' + @json($cyberPos->id))); if (g && g.lineas && !venta.cliente) venta = Object.assign(venta, g); } catch (e) {}
        const guardarLocal = () => { try { localStorage.setItem('pos-venta-' + @json($cyberPos->id), JSON.stringify(venta)); } catch (e) {} };

        // ── Cálculo (igual que el servidor)
        function linea(l) {
            const bruto = r2(l.precio * l.cant);
            let desc = (l.desc || 0); desc += (bruto - desc) * venta.descPct / 100; desc = Math.max(0, Math.min(bruto, r2(desc)));
            const neto = bruto - desc, t = l.tasa;
            let total, imp;
            if (CFG.incluido) { total = rd(neto); imp = t > 0 ? r2(total - total / (1 + t / 100)) : 0; }
            else { total = rd(neto * (1 + t / 100)); imp = t > 0 ? r2(total - total / (1 + t / 100)) : 0; }
            return { bruto, desc, total, imp };
        }
        function totales() {
            let sub = 0, desc = 0, imp = 0, total = 0;
            venta.lineas.forEach(l => { const c = linea(l); sub += c.bruto; desc += c.desc; imp += c.imp; total += c.total; });
            return { sub: r2(sub), desc: r2(desc), imp: r2(imp), total: rd(total) };
        }

        // ── Productos
        let catActual = '';
        function pintarProductos() {
            const q = $('buscar').value.trim().toLowerCase();
            const lista = PRODUCTOS.filter(p => (!catActual || (catActual === 'fav' ? p.f : String(p.cat) === catActual)) && (!q || p.n.toLowerCase().includes(q) || (p.c || '').toLowerCase() === q)).slice(0, 120);
            $('grid').innerHTML = lista.length ? lista.map(p => `
                <button type="button" data-id="${p.id}" class="jz-prod text-left rounded-xl border border-gray-200 hover:border-azul-800 hover:shadow-md p-3 transition flex flex-col justify-between min-h-[92px] ${p.s !== null && p.s <= 0 ? 'opacity-50' : ''}">
                    <span class="text-sm font-semibold text-azul-950 leading-tight line-clamp-2">${p.f ? '<span class="text-oro-500">★</span> ' : ''}${esc(p.n)}</span>
                    <span class="flex items-end justify-between gap-1 mt-2"><span class="font-bold text-azul-800 tabular-nums">${din(p.p)}</span>
                    ${p.s !== null ? `<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded ${p.s <= 0 ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500'}">${p.s <= 0 ? 'Agotado' : nfCant(p.s) + ' ' + esc(p.u)}</span>` : ''}</span>
                </button>`).join('')
                : `<div class="col-span-full text-center text-gray-400 py-10">${PRODUCTOS.length ? 'Nada coincide con la búsqueda.' : 'Todavía no hay productos. <a class="text-azul-800 underline font-semibold" href="{{ route('pos.productos') }}">Agregar productos</a>'}</div>`;
        }
        const nfCant = n => Number.isInteger(+n) ? String(+n) : (+n).toFixed(2);
        $('grid').addEventListener('click', e => { const b = e.target.closest('.jz-prod'); if (b) agregar(PRODUCTOS.find(p => p.id === +b.dataset.id)); });
        $('cats').addEventListener('click', e => {
            const b = e.target.closest('.jz-cat'); if (!b) return; catActual = b.dataset.cat;
            document.querySelectorAll('.jz-cat').forEach(x => { const on = x === b; x.classList.toggle('bg-azul-800', on); x.classList.toggle('text-white', on); x.classList.toggle('bg-gray-100', !on); x.classList.toggle('text-gray-600', !on); });
            pintarProductos();
        });
        $('buscar').addEventListener('input', pintarProductos);
        $('buscar').addEventListener('keydown', e => {
            if (e.key !== 'Enter') return; e.preventDefault();
            const q = $('buscar').value.trim().toLowerCase(); if (!q) return;
            const exacto = PRODUCTOS.find(p => (p.c || '').toLowerCase() === q);
            const vis = PRODUCTOS.filter(p => p.n.toLowerCase().includes(q));
            const p = exacto || (vis.length === 1 ? vis[0] : null);
            if (p) { agregar(p); $('buscar').value = ''; pintarProductos(); }
            else if (!vis.length) Swal.fire({ toast: true, position: 'top', icon: 'warning', title: 'No hay ningún producto con ese código', showConfirmButton: false, timer: 1800 });
        });

        function agregar(p, extra) {
            if (p.s !== null && p.s !== undefined) {
                const ya = venta.lineas.filter(l => l.id === p.id).reduce((a, l) => a + l.cant, 0);
                if (ya + 1 > p.s) { Swal.fire({ toast: true, position: 'top', icon: 'warning', title: p.s <= 0 ? 'Producto agotado' : 'Solo quedan ' + nfCant(p.s), showConfirmButton: false, timer: 1800 }); return; }
            }
            const igual = venta.lineas.find(l => l.id && l.id === p.id && l.precio === p.p && !l.desc);
            if (igual && !extra) igual.cant = r2(igual.cant + 1);
            else venta.lineas.push(Object.assign({ id: p.id, n: p.n, precio: p.p, cant: 1, desc: 0, tasa: p.tasa, u: p.u }, extra || {}));
            pintarCarrito(true);
        }
        $('btnLibre').addEventListener('click', async () => {
            const r = await Swal.fire({ title: 'Otro artículo o servicio', html:
                '<input id="lDesc" class="swal2-input" placeholder="Descripción (ej: Impresión 3 hojas)" maxlength="150">' +
                '<input id="lPrecio" type="number" min="0" step="any" class="swal2-input" placeholder="Precio unitario">' +
                '<input id="lCant" type="number" min="1" step="any" value="1" class="swal2-input" placeholder="Cantidad">',
                showCancelButton: true, confirmButtonText: 'Agregar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74', focusConfirm: false,
                didOpen: () => document.getElementById('lDesc').focus(),
                preConfirm: () => { const d = document.getElementById('lDesc').value.trim(), p = +document.getElementById('lPrecio').value, c = +document.getElementById('lCant').value || 1;
                    if (!d || !(p >= 0) || document.getElementById('lPrecio').value === '') { Swal.showValidationMessage('Escriba la descripción y el precio'); return false; } return { d, p, c }; } });
            if (r.isConfirmed) agregar({ id: null, n: r.value.d, p: r.value.p, tasa: CFG.tasa, u: '' }, { cant: r.value.c });
        });

        // ── Carrito
        let abierta = -1;
        function pintarCarrito(nuevo) {
            if (nuevo) abierta = venta.lineas.length - 1;
            const ul = $('lineas');
            if (!venta.lineas.length) { ul.innerHTML = '<li class="h-full flex flex-col items-center justify-center text-gray-300 py-10"><i data-lucide="shopping-cart" class="size-12"></i><p class="text-sm mt-2 text-gray-400">Toque un producto para agregarlo</p></li>'; }
            else ul.innerHTML = venta.lineas.map((l, i) => { const c = linea(l); return `
                <li class="px-3 py-2.5 ${i === abierta ? 'bg-blue-50/60' : ''}" data-i="${i}">
                    <div class="flex items-start gap-2">
                        <button type="button" data-acc="abrir" class="flex-1 min-w-0 text-left"><span class="block text-sm font-semibold text-azul-950 truncate">${esc(l.n)}</span>
                            <span class="text-xs text-gray-500 tabular-nums">${nfCant(l.cant)} × ${din(l.precio)}${l.desc ? ' − ' + din(l.desc) : ''}</span></button>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" data-acc="menos" class="size-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-gray-600">−</button>
                            <span class="w-7 text-center text-sm font-bold tabular-nums">${nfCant(l.cant)}</span>
                            <button type="button" data-acc="mas" class="size-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-gray-600">+</button>
                        </div>
                        <span class="w-24 text-right font-bold text-azul-950 tabular-nums text-sm">${din(c.total)}</span>
                    </div>
                    ${i === abierta ? `<div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 mt-2 text-[11px] text-gray-500">
                        <label>Cantidad<input data-campo="cant" type="number" min="0.01" step="any" value="${l.cant}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></label>
                        <label>Precio<input data-campo="precio" type="number" min="0" step="any" value="${l.precio}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></label>
                        <label>Descuento<input data-campo="desc" type="number" min="0" step="any" value="${l.desc || ''}" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></label>
                        <button type="button" data-acc="quitar" class="self-end size-9 rounded-lg text-red-500 hover:bg-red-50 flex items-center justify-center" title="Quitar"><i data-lucide="trash-2" class="size-4"></i></button>
                    </div>` : ''}
                </li>`; }).join('');
            const t = totales();
            $('tSub').textContent = din(t.sub); $('tDesc').textContent = t.desc ? '−' + din(t.desc) : din(0); $('tImp').textContent = din(t.imp); $('tTotal').textContent = din(t.total);
            $('descPct').textContent = venta.descPct ? '(' + venta.descPct + ' %)' : '';
            $('btnCobrar').disabled = !venta.lineas.length;
            $('mCant').textContent = venta.lineas.reduce((a, l) => a + l.cant, 0); $('mTotal').textContent = din(t.total);
            const cl = $('cliente'); if (venta.cliente) { cl.value = venta.cliente.texto; cl.readOnly = true; $('quitarCliente').classList.remove('hidden'); } else { cl.readOnly = false; $('quitarCliente').classList.add('hidden'); if (document.activeElement !== cl) cl.value = venta.clienteNombre || ''; }
            lucide.createIcons(); guardarLocal();
        }
        $('lineas').addEventListener('click', e => {
            const li = e.target.closest('[data-i]'), b = e.target.closest('[data-acc]'); if (!li || !b) return;
            const i = +li.dataset.i, l = venta.lineas[i];
            ({ abrir: () => { abierta = abierta === i ? -1 : i; },
               mas: () => { const p = PRODUCTOS.find(x => x.id === l.id); if (p && p.s !== null && l.cant + 1 > p.s) return; l.cant = r2(l.cant + 1); },
               menos: () => { l.cant = r2(l.cant - 1); if (l.cant <= 0) venta.lineas.splice(i, 1); },
               quitar: () => { venta.lineas.splice(i, 1); abierta = -1; } })[b.dataset.acc]();
            pintarCarrito();
        });
        $('lineas').addEventListener('change', e => {
            const inp = e.target.closest('[data-campo]'), li = e.target.closest('[data-i]'); if (!inp || !li) return;
            const l = venta.lineas[+li.dataset.i], v = Math.max(0, +inp.value || 0);
            if (inp.dataset.campo === 'cant') { if (v <= 0) venta.lineas.splice(+li.dataset.i, 1); else l.cant = r2(v); }
            else l[inp.dataset.campo] = r2(v);
            pintarCarrito();
        });
        $('btnVaciar').addEventListener('click', async () => { if (!venta.lineas.length) return; const r = await Swal.fire({ title: '¿Vaciar la venta?', icon: 'question', showCancelButton: true, confirmButtonText: 'Vaciar', cancelButtonText: 'No', confirmButtonColor: '#c0392b' }); if (r.isConfirmed) nuevaVenta(); });
        $('btnDesc').addEventListener('click', async () => {
            const r = await Swal.fire({ title: 'Descuento a toda la venta', input: 'number', inputValue: venta.descPct || '', inputAttributes: { min: 0, max: 100, step: 'any' }, inputLabel: 'Porcentaje (%)', showCancelButton: true, confirmButtonText: 'Aplicar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74' });
            if (r.isConfirmed) { venta.descPct = Math.max(0, Math.min(100, +r.value || 0)); pintarCarrito(); }
        });

        // ── Cliente
        let tBusca = null;
        $('cliente').addEventListener('input', e => {
            venta.clienteNombre = e.target.value; guardarLocal();
            clearTimeout(tBusca); const q = e.target.value.trim(); if (q.length < 2) { $('clientesRes').classList.add('hidden'); return; }
            tBusca = setTimeout(async () => {
                const r = await fetch(URL_CLIENTES + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }); const lista = await r.json().catch(() => []);
                $('clientesRes').innerHTML = (lista.length ? lista.map(c => `<button type="button" data-id="${c.id}" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">${esc(c.texto)}</button>`).join('') : '') +
                    `<div class="px-3 py-2 text-[11px] text-gray-400 border-t">O deje el nombre escrito: sale así en el comprobante.</div>`;
                $('clientesRes').classList.remove('hidden');
            }, 250);
        });
        $('clientesRes').addEventListener('click', e => { const b = e.target.closest('[data-id]'); if (!b) return; venta.cliente = { id: +b.dataset.id, texto: b.textContent }; venta.clienteNombre = ''; $('clientesRes').classList.add('hidden'); pintarCarrito(); });
        $('quitarCliente').addEventListener('click', () => { venta.cliente = null; venta.clienteNombre = ''; $('cliente').value = ''; pintarCarrito(); });
        document.addEventListener('click', e => { if (!e.target.closest('#clientesRes') && e.target.id !== 'cliente') $('clientesRes').classList.add('hidden'); });

        // ── Cobro
        let pagos = [], metodo = 'efectivo', ultima = null;
        const nombreMetodo = k => (CFG.metodos.find(m => m.clave === k) || { nombre: k }).nombre;
        function abrirCobro() {
            if (!venta.lineas.length) return;
            pagos = []; metodo = 'efectivo'; $('pasoPago').classList.remove('hidden'); $('pasoListo').classList.add('hidden');
            $('modalCobro').classList.remove('hidden'); $('notas').value = '';
            pintarCobro(true);
        }
        function falta() { return r2(totales().total - pagos.reduce((a, p) => a + p.monto, 0)); }
        function pintarCobro(reiniciar) {
            const t = totales().total, f = falta();
            $('cTotal').textContent = din(t);
            $('metodos').innerHTML = CFG.metodos.map(m => `<button type="button" data-m="${m.clave}" class="py-3 rounded-xl border-2 text-sm font-semibold ${m.clave === metodo ? 'border-azul-800 bg-blue-50 text-azul-800' : 'border-gray-200 text-gray-600'}">${esc(m.nombre)}</button>`).join('');
            if (reiniciar) $('monto').value = f > 0 ? f : '';
            $('lblMonto').textContent = metodo === 'efectivo' ? 'Efectivo que entrega el cliente' : 'Monto con ' + nombreMetodo(metodo);
            $('referencia').classList.toggle('hidden', metodo === 'efectivo');
            // Botones de billetes: el exacto y los billetes que alcanzan.
            const bs = metodo === 'efectivo' && f > 0 ? [f].concat(CFG.billetes.filter(b => b >= 1).map(b => Math.ceil(f / b) * b).filter((v, i, a) => v > f && a.indexOf(v) === i).sort((a, b) => a - b).slice(0, 5)) : [];
            $('billetes').innerHTML = bs.map((b, i) => `<button type="button" data-b="${b}" class="px-3 py-2 rounded-lg ${i === 0 ? 'bg-azul-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'} text-sm font-semibold tabular-nums">${i === 0 ? 'Exacto ' : ''}${din(b)}</button>`).join('');
            $('pagosLista').innerHTML = pagos.map((p, i) => `<li class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm"><span>${esc(nombreMetodo(p.metodo))}${p.referencia ? ' · ' + esc(p.referencia) : ''}</span><span class="flex items-center gap-2 font-semibold tabular-nums">${din(p.monto)}<button type="button" data-quitar="${i}" class="text-gray-400 hover:text-red-600">✕</button></span></li>`).join('');
            calcularCambio();
        }
        function calcularCambio() {
            const f = falta(), m = +$('monto').value || 0, resto = r2(f - m);
            const caja = $('cajaCambio');
            if (resto > 0.004) { $('lblCambio').textContent = 'Falta'; $('cCambio').textContent = din(resto); caja.className = 'mt-4 rounded-2xl p-3 flex items-center justify-between bg-red-50 text-red-700'; }
            else { $('lblCambio').textContent = metodo === 'efectivo' ? 'Cambio' : 'Completo'; $('cCambio').textContent = din(metodo === 'efectivo' ? -resto : 0); caja.className = 'mt-4 rounded-2xl p-3 flex items-center justify-between bg-emerald-50 text-emerald-700'; }
            $('btnConfirmar').disabled = resto > 0.004 || (metodo !== 'efectivo' && m > f + 0.004);
        }
        $('metodos').addEventListener('click', e => { const b = e.target.closest('[data-m]'); if (!b) return; metodo = b.dataset.m; pintarCobro(true); $('monto').focus(); });
        $('billetes').addEventListener('click', e => { const b = e.target.closest('[data-b]'); if (!b) return; $('monto').value = b.dataset.b; calcularCambio(); });
        $('monto').addEventListener('input', calcularCambio);
        $('btnAgregarPago').addEventListener('click', () => {
            const m = Math.min(+$('monto').value || 0, metodo === 'efectivo' ? Infinity : falta()); if (m <= 0) return;
            pagos.push({ metodo, monto: r2(m), referencia: $('referencia').value.trim() || null }); $('referencia').value = '';
            metodo = CFG.metodos.find(x => x.clave !== metodo)?.clave || 'efectivo'; pintarCobro(true);
        });
        $('pagosLista').addEventListener('click', e => { const b = e.target.closest('[data-quitar]'); if (b) { pagos.splice(+b.dataset.quitar, 1); pintarCobro(true); } });
        document.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', () => $('modalCobro').classList.add('hidden')));

        $('btnConfirmar').addEventListener('click', async () => {
            const btn = $('btnConfirmar'); if (btn.disabled) return; btn.disabled = true;
            const todos = pagos.slice(); const m = +$('monto').value || 0;
            if (m > 0) todos.push({ metodo, monto: r2(m), referencia: $('referencia').value.trim() || null });
            if (!venta.clave_operacion) {
                venta.clave_operacion = ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
                guardarLocal();
            }
            const cuerpo = { clave_operacion: venta.clave_operacion, items: venta.lineas.map(l => ({ producto_id: l.id, descripcion: l.n, cantidad: l.cant, precio: l.precio, descuento: l.desc || 0 })),
                descuento_pct: venta.descPct, pagos: todos, cliente_id: venta.cliente ? venta.cliente.id : null, cliente_nombre: venta.cliente ? null : (venta.clienteNombre || null), notas: $('notas').value || null };
            try {
                const r = await fetch(URL_VENTA, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(cuerpo) });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.ok) { const err = j.errors ? Object.values(j.errors)[0][0] : (j.mensaje || j.message || 'No se pudo registrar.'); throw new Error(err); }
                ultima = j;
                Object.entries(j.stock || {}).forEach(([id, s]) => { const p = PRODUCTOS.find(x => x.id === +id); if (p) p.s = s; });
                $('lNumero').textContent = j.venta.numero; $('lTotal').textContent = 'Total ' + j.venta.total;
                $('lCambio').textContent = j.venta.cambioTxt; $('lCambioCaja').classList.toggle('hidden', !(j.venta.cambio > 0));
                $('btnVer').href = j.urls.ver;
                $('pasoPago').classList.add('hidden'); $('pasoListo').classList.remove('hidden');
                venta = { lineas: [], descPct: 0, cliente: null, clienteNombre: '' }; abierta = -1; guardarLocal(); pintarCarrito(); pintarProductos();
            } catch (e) { Swal.fire({ icon: 'error', title: 'No se pudo cobrar', text: e.message, confirmButtonColor: '#0B3C74' }); }
            finally { btn.disabled = false; }
        });

        // ── Después de la venta
        $('btnImprimir').addEventListener('click', () => { const f = $('impresion'); f.onload = () => { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { window.open(ultima.urls.tiquete, '_blank'); } }; f.src = ultima.urls.tiquete; });
        $('btnWhats').addEventListener('click', async () => {
            jzMostrarCargando('Preparando el comprobante…');
            try {
                const r = await fetch(ultima.urls.imagen); const blob = await r.blob(); jzOcultarCargando();
                const archivo = new File([blob], 'Comprobante_' + ultima.venta.numero + '.png', { type: 'image/png' });
                if (navigator.canShare && navigator.canShare({ files: [archivo] })) { try { await navigator.share({ files: [archivo], title: 'Comprobante ' + ultima.venta.numero }); return; } catch (e) { if (e.name === 'AbortError') return; } }
                const u = URL.createObjectURL(blob), a = document.createElement('a'); a.href = u; a.download = archivo.name; document.body.appendChild(a); a.click(); a.remove();
                Swal.fire({ icon: 'info', title: 'Imagen descargada', text: 'Adjúntela en WhatsApp desde la galería o documentos.', confirmButtonText: 'Abrir WhatsApp', showCancelButton: true, cancelButtonText: 'Cerrar', confirmButtonColor: '#25D366' })
                    .then(x => { if (x.isConfirmed) window.open('https://wa.me/', '_blank'); });
            } catch (e) { jzOcultarCargando(); }
        });
        $('btnCorreo').addEventListener('click', async () => {
            const r = await Swal.fire({ title: 'Enviar comprobante', input: 'email', inputValue: ultima.correo || '', inputPlaceholder: 'correo@ejemplo.com', showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74', showLoaderOnConfirm: true,
                preConfirm: async (correo) => { const x = await fetch(ultima.urls.correo, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ correo }) }); const j = await x.json().catch(() => ({})); if (!x.ok) { Swal.showValidationMessage(j.mensaje || j.message || 'No se pudo enviar'); return false; } return j; } });
            if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Enviado', text: r.value.mensaje, timer: 2000, showConfirmButton: false });
        });
        function nuevaVenta() { venta = { lineas: [], descPct: 0, cliente: null, clienteNombre: '' }; abierta = -1; $('modalCobro').classList.add('hidden'); pintarCarrito(); $('buscar').focus(); }
        $('btnNueva').addEventListener('click', nuevaVenta);
        $('btnCobrar').addEventListener('click', abrirCobro);

        // ── Teclado
        document.addEventListener('keydown', e => {
            const modal = !$('modalCobro').classList.contains('hidden'), listo = !$('pasoListo').classList.contains('hidden');
            if (document.querySelector('.swal2-popup:not(.swal2-toast)')) return; // un aviso pequeño no bloquea el teclado
            if (e.key === 'F2') { e.preventDefault(); $('buscar').focus(); }
            else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) { e.preventDefault(); if (!modal) abrirCobro(); }
            else if (e.key === 'Escape' && modal) { e.preventDefault(); listo ? nuevaVenta() : $('modalCobro').classList.add('hidden'); }
            else if (e.key === 'Enter' && modal && !listo && e.target.id !== 'referencia' && e.target.id !== 'notas') { e.preventDefault(); $('btnConfirmar').click(); }
        });

        pintarProductos(); pintarCarrito(); $('buscar').focus();
    })();
    </script>
    @endpush
</x-layout>
