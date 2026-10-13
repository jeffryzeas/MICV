@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm'; $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Proveedores" activo="pos-proveedores">
    @include('pos._barra')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-3">
            <form class="flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar proveedor" class="{{ $in }} max-w-xs"><button class="bg-gray-100 hover:bg-gray-200 rounded-lg px-3"><x-icono name="search" class="size-4" /></button></form>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse($proveedores as $pr)
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-4 {{ $pr->activo ? '' : 'opacity-50' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><p class="font-semibold text-azul-950 truncate">{{ $pr->nombre }}</p>@if($pr->identificacion)<p class="text-xs text-gray-400">{{ $pr->identificacion }}</p>@endif</div>
                            <button type="button" class="jz-editar text-xs font-semibold text-azul-800 hover:underline shrink-0" data-p="{{ json_encode($pr->only(['id', 'nombre', 'identificacion', 'contacto', 'telefono', 'correo', 'direccion', 'notas', 'activo'])) }}">Editar</button>
                        </div>
                        <ul class="mt-2 text-sm text-gray-600 space-y-0.5">
                            @if($pr->contacto)<li class="flex items-center gap-1.5"><x-icono name="user-round" class="size-3.5 text-gray-400" />{{ $pr->contacto }}</li>@endif
                            @if($pr->telefono)<li class="flex items-center gap-1.5"><x-icono name="phone" class="size-3.5 text-gray-400" /><a href="tel:{{ $pr->telefono }}" class="hover:underline">{{ $pr->telefono }}</a>
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $pr->telefono) }}" target="_blank" class="text-[#25D366]" title="WhatsApp"><x-icono name="message-circle" class="size-3.5" /></a></li>@endif
                            @if($pr->correo)<li class="flex items-center gap-1.5"><x-icono name="mail" class="size-3.5 text-gray-400" /><a href="mailto:{{ $pr->correo }}" class="hover:underline truncate">{{ $pr->correo }}</a></li>@endif
                        </ul>
                        <p class="mt-2 text-xs text-gray-400">{{ $pr->productos_count }} productos · compras por {{ $cfg->dinero($pr->compras_sum_total ?? 0) }}</p>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm col-span-full">Todavía no hay proveedores.</p>
                @endforelse
            </div>
        </div>
        <form method="POST" action="{{ route('pos.proveedores.guardar') }}" id="fProv" class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 space-y-3 h-max lg:sticky lg:top-20">
            @csrf <input type="hidden" name="id">
            <h2 class="font-semibold text-azul-950" id="tituloProv">Nuevo proveedor</h2>
            <div><label class="{{ $lb }}">Nombre *</label><input name="nombre" required maxlength="150" class="{{ $in }}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $lb }}">Identificación</label><input name="identificacion" maxlength="40" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Contacto</label><input name="contacto" maxlength="120" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Teléfono</label><input name="telefono" maxlength="40" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Correo</label><input name="correo" type="email" maxlength="150" class="{{ $in }}"></div>
            </div>
            <div><label class="{{ $lb }}">Dirección</label><input name="direccion" maxlength="200" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Notas</label><textarea name="notas" rows="2" maxlength="1000" class="{{ $in }}" placeholder="Días de entrega, cuenta para pagos…"></textarea></div>
            <input type="hidden" name="activo" value="0"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked> Activo</label>
            <div class="flex gap-2"><button class="flex-1 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold py-2.5 rounded-lg">Guardar</button><button type="button" id="limpiarProv" class="hidden px-3 text-sm text-gray-500">Nuevo</button></div>
        </form>
    </div>
    @push('scripts')
    <script>
    (function () {
        const f = document.getElementById('fProv');
        document.querySelectorAll('.jz-editar').forEach(b => b.addEventListener('click', () => {
            const p = JSON.parse(b.dataset.p);
            Object.entries(p).forEach(([k, v]) => { const i = f.querySelector('[name=' + k + ']:not([type=hidden][value="0"])'); if (!i) return; if (i.type === 'checkbox') i.checked = !!v; else i.value = v ?? ''; });
            f.querySelector('[name=id]').value = p.id;
            document.getElementById('tituloProv').textContent = 'Editar proveedor'; document.getElementById('limpiarProv').classList.remove('hidden');
            f.scrollIntoView({ behavior: 'smooth' });
        }));
        document.getElementById('limpiarProv').addEventListener('click', () => { f.reset(); f.querySelector('[name=id]').value = ''; document.getElementById('tituloProv').textContent = 'Nuevo proveedor'; });
    })();
    </script>
    @endpush
</x-layout>
