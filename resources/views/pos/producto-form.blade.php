@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm';
    $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1';
    $nuevo = !$p->exists;
    $imp = old('impuesto', $p->exento ? 'exento' : ($p->impuesto_tasa !== null ? 'propio' : 'general'));
    $tipo = old('tipo', $p->tipo);
@endphp
<x-layout :titulo="$nuevo ? 'Nuevo ' . ($tipo === 'servicio' ? 'servicio' : 'producto') : $p->nombre" activo="pos-productos">
    @include('pos._barra')
    <form method="POST" enctype="multipart/form-data" action="{{ $nuevo ? route('pos.productos.guardar') : route('pos.productos.actualizar', $p) }}" class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6 max-w-3xl space-y-5">
        @csrf @unless($nuevo) @method('PUT') @endunless
        <fieldset class="border border-gray-200 rounded-xl p-4"><legend class="text-sm font-bold">Foto del producto</legend>
            @if($p->foto)<img src="{{ $p->fotoUrl() }}" alt="{{ $p->nombre }}" class="w-24 h-24 object-cover rounded-lg mb-3"><label class="text-sm"><input type="checkbox" name="quitar_foto" value="1"> Quitar foto actual</label>@endif
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="block text-sm mt-2"><p class="text-xs text-gray-500 mt-1">JPG, PNG o WebP. Máximo 50 MB.</p>
        </fieldset>
        <div class="grid grid-cols-2 gap-2 max-w-sm">
            @foreach(['producto' => ['package', 'Producto (se cuenta)'], 'servicio' => ['hand-helping', 'Servicio']] as $k => [$ic, $t])
                <label class="cursor-pointer"><input type="radio" name="tipo" value="{{ $k }}" class="peer sr-only" @checked($tipo === $k)>
                    <span class="flex items-center justify-center gap-2 border-2 border-gray-200 rounded-xl py-2.5 text-sm font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:text-azul-800 peer-checked:bg-blue-50"><i data-lucide="{{ $ic }}" class="size-4"></i>{{ $t }}</span></label>
            @endforeach
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2"><label class="{{ $lb }}">Nombre *</label><input type="text" name="nombre" required maxlength="150" value="{{ old('nombre', $p->nombre) }}" autofocus class="{{ $in }}" placeholder="Ej: Tijera escolar Maped"></div>
            <div><label class="{{ $lb }}">Código de barras</label><input type="text" name="codigo" maxlength="60" value="{{ old('codigo', $p->codigo) }}" class="{{ $in }} font-mono" placeholder="Escanéelo aquí"></div>
            <div><label class="{{ $lb }}">Precio de venta * ({{ $cfg->precios_con_impuesto ? 'con' : 'sin' }} {{ $cfg->impuesto_nombre }})</label><input type="number" name="precio" required min="0" step="any" value="{{ old('precio', $p->exists ? (float) $p->precio : '') }}" class="{{ $in }} text-right font-semibold"></div>
            <div><label class="{{ $lb }}">Costo (lo que le cuesta)</label><input type="number" name="costo" min="0" step="any" value="{{ old('costo', $p->exists ? (float) $p->costo : '') }}" class="{{ $in }} text-right"></div>
            <div><label class="{{ $lb }}">Ganancia</label><p id="ganancia" class="py-2.5 text-sm font-semibold text-gray-500">—</p></div>
            <div><label class="{{ $lb }}">Categoría</label><select name="categoria_id" class="{{ $in }}"><option value="">Sin categoría</option>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected(old('categoria_id', $p->categoria_id) == $c->id)>{{ $c->nombre }}</option>@endforeach</select></div>
            <div><label class="{{ $lb }}">Proveedor</label><select name="proveedor_id" class="{{ $in }}"><option value="">—</option>@foreach($proveedores as $pr)<option value="{{ $pr->id }}" @selected(old('proveedor_id', $p->proveedor_id) == $pr->id)>{{ $pr->nombre }}</option>@endforeach</select></div>
            <div><label class="{{ $lb }}">Unidad</label><input type="text" name="unidad" maxlength="15" value="{{ old('unidad', $p->unidad) }}" list="unidades" class="{{ $in }}"><datalist id="unidades">@foreach(['unid.', 'paquete', 'caja', 'resma', 'hoja', 'metro', 'serv.', 'hora'] as $u)<option value="{{ $u }}">@endforeach</datalist></div>
        </div>

        <fieldset class="rounded-xl border border-gray-200 p-4">
            <legend class="px-1 text-sm font-semibold text-azul-950">{{ $cfg->impuesto_nombre }}</legend>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="radio" name="impuesto" value="general" @checked($imp === 'general')> La tasa general ({{ rtrim(rtrim((string) $cfg->impuesto_tasa, '0'), '.') }} %)</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="impuesto" value="exento" @checked($imp === 'exento')> Exento</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="impuesto" value="propio" @checked($imp === 'propio')> Otra tasa: <input type="number" name="impuesto_tasa" min="0" max="100" step="any" value="{{ old('impuesto_tasa', $p->impuesto_tasa !== null ? (float) $p->impuesto_tasa : '') }}" class="w-20 border border-gray-300 rounded-lg px-2 py-1 text-sm"> %</label>
            </div>
        </fieldset>

        <fieldset class="rounded-xl border border-gray-200 p-4" id="bloqueStock">
            <legend class="px-1 text-sm font-semibold text-azul-950">Inventario</legend>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="controla_stock" value="1" @checked(old('controla_stock', $p->exists ? $p->controla_stock : true))> Llevar el control de existencias (avisa cuando se acaba)</label>
            <div class="grid grid-cols-2 gap-4 mt-3 max-w-md">
                @if($nuevo)<div><label class="{{ $lb }}">Cantidad que tiene ahora</label><input type="number" name="stock_inicial" min="0" step="any" value="{{ old('stock_inicial') }}" class="{{ $in }}"></div>
                @else<div><label class="{{ $lb }}">Existencia</label><p class="py-2.5 text-sm font-semibold">{{ rtrim(rtrim(number_format((float) $p->stock, 2, '.', ''), '0'), '.') }} {{ $p->unidad }} <span class="text-xs font-normal text-gray-400">(se cambia con "Ajustar" o con una compra)</span></p></div>@endif
                <div><label class="{{ $lb }}">Avisar cuando queden</label><input type="number" name="stock_minimo" min="0" step="any" value="{{ old('stock_minimo', $p->exists ? (float) $p->stock_minimo : '') }}" class="{{ $in }}"></div>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="favorito" value="1" @checked(old('favorito', $p->favorito))> <x-icono name="star" class="size-4"/> Favorito (sale primero en la caja)</label>
            <input type="hidden" name="activo" value="0"><label class="inline-flex items-center gap-2"><input type="checkbox" name="activo" value="1" @checked(old('activo', $p->activo ?? true))> Activo</label>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2 border-t">
            <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><x-icono name="check" class="size-4" /> Guardar</button>
            @if($nuevo)<button name="otro" value="1" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-5 py-2.5 rounded-lg">Guardar y agregar otro</button>@endif
            <a href="{{ route('pos.productos') }}" class="text-sm text-gray-500 hover:text-azul-800 px-3">Cancelar</a>
        </div>
    </form>
    @unless($nuevo)
        <form method="POST" action="{{ route('pos.productos.borrar', $p) }}" class="jz-confirmar mt-3 max-w-3xl text-right" data-msg="Si tiene ventas o movimientos solo se desactiva.">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:underline">Borrar o desactivar</button></form>
    @endunless
    @push('scripts')
    <script>
    (function () {
        const precio = document.querySelector('[name=precio]'), costo = document.querySelector('[name=costo]'), g = document.getElementById('ganancia');
        const dec = @json((int) $cfg->decimales), nf = new Intl.NumberFormat(@json($cfg->locale), { minimumFractionDigits: dec, maximumFractionDigits: dec });
        const incluido = @json((bool) $cfg->precios_con_impuesto), tasaGen = @json((float) $cfg->impuesto_tasa);
        function ver() {
            const p = +precio.value || 0, c = +costo.value || 0; if (!p || !c) { g.textContent = '—'; return; }
            const modo = document.querySelector('[name=impuesto]:checked').value, t = modo === 'exento' ? 0 : (modo === 'propio' ? (+document.querySelector('[name=impuesto_tasa]').value || 0) : tasaGen);
            const base = incluido ? p / (1 + t / 100) : p, gan = base - c;
            g.textContent = @json($cfg->simbolo) + nf.format(gan) + ' (' + Math.round(gan / base * 100) + ' %)'; g.className = 'py-2.5 text-sm font-semibold ' + (gan >= 0 ? 'text-emerald-700' : 'text-red-600');
        }
        document.querySelectorAll('[name=precio],[name=costo],[name=impuesto],[name=impuesto_tasa]').forEach(i => i.addEventListener('input', ver)); ver();
        const tipo = () => document.querySelector('[name=tipo]:checked').value;
        function bloque() { document.getElementById('bloqueStock').classList.toggle('hidden', tipo() === 'servicio'); }
        document.querySelectorAll('[name=tipo]').forEach(i => i.addEventListener('change', bloque)); bloque();
    })();
    </script>
    @endpush
</x-layout>
