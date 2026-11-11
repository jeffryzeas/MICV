@php $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5'; $in = 'border border-gray-300 rounded-lg px-3 py-2 text-sm'; $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp
<x-layout titulo="Productos e inventario" activo="pos-productos">
    @include('pos._barra')
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="{{ $card }} p-4"><p class="text-[11px] uppercase font-bold text-gray-500">Productos y servicios</p><p class="text-2xl font-bold text-azul-950">{{ $total }}</p></div>
        <a href="?ver=bajo" class="{{ $card }} p-4 hover:ring-red-200"><p class="text-[11px] uppercase font-bold text-gray-500">Por agotarse</p><p class="text-2xl font-bold {{ $bajos ? 'text-red-600' : 'text-azul-950' }}">{{ $bajos }}</p></a>
        <div class="{{ $card }} p-4"><p class="text-[11px] uppercase font-bold text-gray-500">Inventario al costo</p><p class="text-2xl font-bold text-azul-950 tabular-nums">{{ $cfg->dinero($valor->costo) }}</p></div>
        <div class="{{ $card }} p-4"><p class="text-[11px] uppercase font-bold text-gray-500">Inventario a precio de venta</p><p class="text-2xl font-bold text-azul-950 tabular-nums">{{ $cfg->dinero($valor->venta) }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-3">
        <a href="{{ route('pos.productos.crear') }}" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="plus" class="size-4" /> Producto</a>
        <a href="{{ route('pos.productos.crear', ['tipo' => 'servicio']) }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="plus" class="size-4" /> Servicio</a>
        <a href="{{ route('pos.compras.crear') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="truck" class="size-4" /> Registrar compra</a>
        @if(($cyberPos->tipo_negocio ?? 'cyber')==='cyber')<form method="POST" action="{{ route('pos.productos.sugeridos') }}">@csrf<button class="inline-flex items-center gap-2 bg-white border border-dashed border-gray-300 hover:border-azul-800 text-gray-600 text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="sparkles" class="size-4" /> Agregar servicios de cyber</button></form>@endif
        <form class="ml-auto flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o código" class="{{ $in }}">
            <select name="categoria" class="{{ $in }}" onchange="this.form.submit()"><option value="">Todas las categorías</option>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->nombre }}</option>@endforeach</select>
            <select name="ver" class="{{ $in }}" onchange="this.form.submit()"><option value="">Activos</option><option value="bajo" @selected(request('ver') === 'bajo')>Por agotarse</option><option value="inactivos" @selected(request('ver') === 'inactivos')>Desactivados</option></select>
        </form>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">
        <div class="{{ $card }} overflow-x-auto xl:col-span-3">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="p-3">Nombre</th><th class="p-3">Categoría</th><th class="p-3 text-right">Precio</th><th class="p-3 text-right">Costo</th><th class="p-3 text-right">Existencia</th><th></th></tr></thead>
                <tbody>
                @forelse($productos as $p)
                    <tr class="border-b last:border-0">
                        <td class="p-3">@if($p->foto)<img src="{{ $p->fotoUrl() }}" loading="lazy" alt="" class="w-12 h-12 rounded-lg object-cover mb-2">@endif<a href="{{ route('pos.productos.editar', $p) }}" class="font-semibold text-azul-950 hover:text-azul-800">@if($p->favorito)<span class="text-oro-500"><x-icono name="star" class="size-4"/></span> @endif{{ $p->nombre }}</a>
                            <span class="block text-xs text-gray-400">{{ $p->tipo === 'servicio' ? 'Servicio' : 'Producto' }}@if($p->codigo) · <span class="font-mono">{{ $p->codigo }}</span>@endif @if($p->exento) · Exento @endif</span></td>
                        <td class="p-3">@if($p->categoria)<span class="inline-flex items-center gap-1.5 text-xs"><span class="size-2 rounded-full" style="background: {{ $p->categoria->color }}"></span>{{ $p->categoria->nombre }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="p-3 text-right font-semibold tabular-nums {{ (float) $p->precio == 0 ? 'text-red-500' : '' }}">{{ $cfg->dinero($p->precio) }}</td>
                        <td class="p-3 text-right tabular-nums text-gray-500">{{ (float) $p->costo ? $cfg->dinero($p->costo) : '—' }}</td>
                        <td class="p-3 text-right">@if($p->controla_stock)<span class="font-semibold tabular-nums {{ $p->stockBajo() ? 'text-red-600' : 'text-azul-950' }}">{{ $cant($p->stock) }}</span> <span class="text-xs text-gray-400">{{ $p->unidad }}</span>@else<span class="text-xs text-gray-300">No se controla</span>@endif</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @php $faltan = round((float) $p->stock_minimo - (float) $p->stock, 2); @endphp
                            @if($p->activo && $p->tipo === 'producto' && $p->controla_stock && $faltan > 0)
                                <span class="block text-xs text-gray-500">Faltan {{ $cant($faltan) }} para el mínimo</span>
                                @if(auth()->user()->tienePermiso('pos.compras.editar'))
                                    <a href="{{ route('pos.compras.crear', ['reponer' => $p->id]) }}" class="block text-xs font-semibold text-azul-800 hover:underline"><x-icono name="truck" class="size-4"/> Reponer</a>
                                @endif
                            @endif
                            @if($p->tipo === 'producto')<button type="button" class="jz-ajuste text-xs font-semibold text-azul-800 hover:underline mr-2" data-url="{{ route('pos.productos.ajuste', $p) }}" data-nombre="{{ $p->nombre }}" data-stock="{{ $cant($p->stock) }}">Ajustar</button>
                            <a href="{{ route('pos.productos.kardex', $p) }}" class="text-xs font-semibold text-azul-800 hover:underline mr-2">Movimientos</a>@endif
                            <a href="{{ route('pos.productos.editar', $p) }}" class="text-xs font-semibold text-azul-800 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-10 text-center text-gray-400"><x-icono name="package-open" class="size-10 mx-auto text-gray-300" /><p class="mt-2">No hay productos aquí todavía.</p></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $productos->links() }}</div>
        </div>

        <aside class="{{ $card }} p-4 h-max">
            <h2 class="font-semibold text-azul-950 mb-2">Categorías</h2>
            <ul class="space-y-1 text-sm mb-3">
                @foreach($categorias as $c)
                    <li class="flex items-center gap-2 group"><span class="size-3 rounded-full shrink-0" style="background: {{ $c->color }}"></span><a href="?categoria={{ $c->id }}" class="flex-1 truncate hover:text-azul-800">{{ $c->nombre }}</a><span class="text-xs text-gray-400">{{ $c->productos_count }}</span>
                        <form method="POST" action="{{ route('pos.categorias.borrar', $c) }}" class="jz-confirmar" data-msg="Los productos de «{{ $c->nombre }}» quedan sin categoría.">@csrf @method('DELETE')<button class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-600" title="Borrar"><x-icono name="x" class="size-3.5" /></button></form></li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('pos.categorias.guardar') }}" class="flex gap-2">@csrf
                <input type="color" name="color" value="#0B3C74" class="h-9 w-10 border border-gray-300 rounded-lg shrink-0">
                <input type="text" name="nombre" required maxlength="60" placeholder="Nueva categoría" class="{{ $in }} flex-1 min-w-0">
                <button class="bg-gray-100 hover:bg-gray-200 rounded-lg px-3"><x-icono name="plus" class="size-4" /></button>
            </form>
        </aside>
    </div>

    @push('scripts')
    <script>
    document.querySelectorAll('.jz-ajuste').forEach(b => b.addEventListener('click', async () => {
        const r = await Swal.fire({ title: 'Ajustar «' + b.dataset.nombre + '»', html:
            '<p class="text-sm text-gray-500 mb-2">Existencia actual: <b>' + b.dataset.stock + '</b></p>' +
            '<select id="aModo" class="swal2-select" style="display:flex;margin:6px auto"><option value="sumar">Sumar (entró mercadería)</option><option value="restar">Restar (se dañó, se perdió, uso interno)</option><option value="fijar">Poner la cantidad que conté</option></select>' +
            '<input id="aCant" type="number" min="0" step="any" class="swal2-input" placeholder="Cantidad">' +
            '<input id="aMotivo" class="swal2-input" maxlength="120" placeholder="Motivo (ej: conteo físico)">',
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74', focusConfirm: false,
            preConfirm: () => { const c = document.getElementById('aCant').value, m = document.getElementById('aMotivo').value.trim(); if (c === '' || !m) { Swal.showValidationMessage('Escriba la cantidad y el motivo'); return false; } return { modo: document.getElementById('aModo').value, cantidad: c, motivo: m }; } });
        if (!r.isConfirmed) return;
        const f = document.createElement('form'); f.method = 'POST'; f.action = b.dataset.url;
        Object.entries(Object.assign({ _token: document.querySelector('meta[name=csrf-token]').content }, r.value)).forEach(([k, v]) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
    }));
    </script>
    @endpush
</x-layout>
