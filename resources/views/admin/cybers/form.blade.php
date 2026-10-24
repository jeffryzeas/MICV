@php $editando = $cyber->exists; @endphp
<x-layout :titulo="$editando ? 'Editar Negocio' : 'Registrar Negocio'" activo="cybers">
    <a href="{{ route('admin.cybers.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors mb-5">
        <x-icono name="arrow-left" class="size-4" /> Volver
    </a>
    <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
        <form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('admin.cybers.update', $cyber) : route('admin.cybers.store') }}">
            @csrf <input type="hidden" name="activo" value="0"> @if($editando) @method('PUT') @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-3"><h2 class="font-bold mb-2">Perfil del negocio</h2><p class="text-sm text-gray-500">Elija el país y los módulos que utilizará. La moneda se configura inicialmente según el país; revise los impuestos antes de operar.</p></div>
                <label>Tipo de negocio<select class="campo w-full" name="tipo_negocio" required>@foreach(\App\Support\Negocios::TIPOS as $key=>$label)<option value="{{ $key }}" @selected(old('tipo_negocio',$cyber->tipo_negocio ?? 'comercio')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label>Naturaleza<select class="campo w-full" name="naturaleza" required><option value="natural" @selected(old('naturaleza',$cyber->naturaleza)==='natural')>Persona natural</option><option value="juridica" @selected(old('naturaleza',$cyber->naturaleza)==='juridica')>Persona jurídica</option></select></label>
                <label>País<select class="campo w-full" name="pais" id="negocio-pais" required>@foreach(\App\Support\PosPaises::PAISES as $key=>$values)<option value="{{ $key }}" @selected(old('pais',$cyber->pais ?? 'NI')===$key)>{{ \App\Support\Pais::de($key)['nombre'] }} · {{ $values[0] }} {{ $values[1] }}</option>@endforeach</select></label>
                <label class="sm:col-span-2">Identificación fiscal<input class="campo w-full" name="identificacion_fiscal" maxlength="50" value="{{ old('identificacion_fiscal',$cyber->identificacion_fiscal) }}"></label>
                <label>Uso principal<select class="campo w-full" name="perfil_uso" id="negocio-perfil" required>@foreach(\App\Support\Negocios::PERFILES as $key=>$label)<option value="{{ $key }}" @selected(old('perfil_uso',$cyber->perfil_uso ?? 'integral')===$key)>{{ $label }}</option>@endforeach</select></label>
                <fieldset class="sm:col-span-3 border rounded-xl p-4" id="negocio-modulos"><legend>Módulos personalizados</legend><div class="ledger-fields">@foreach(\App\Support\Negocios::MODULOS as $key=>$label)<label><input type="checkbox" name="modulos[]" value="{{ $key }}" @checked(in_array($key,old('modulos',$cyber->modulos ?? array_keys(\App\Support\Negocios::MODULOS)),true))>{{ $label }}</label>@endforeach</div></fieldset>
                <label class="sm:col-span-3"><input type="checkbox" name="contabilidad_pos" value="1" @checked(old('contabilidad_pos',$cyber->contabilidad_pos))>Vincular las ventas nuevas del POS con ingresos y gastos<small class="block text-gray-500">Disponible cuando ambos módulos están habilitados. No importa ventas anteriores ni modifica la moneda de movimientos existentes. Las compras y gastos se registran manualmente.</small></label>

                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo', $cyber->codigo) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 uppercase font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nombre del Negocio</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $cyber->nombre) }}" required placeholder="Ej: Negocio San Nicolás" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $cyber->telefono) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Correo</label>
                    <input type="email" name="correo" value="{{ old('correo', $cyber->correo) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Dirección</label>
                    <input type="text" name="direccion" value="{{ old('direccion', $cyber->direccion) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Color primario</label>
                    <input type="color" name="color_primario" value="{{ old('color_primario', $cyber->color_primario) }}" class="w-full h-10 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Color secundario</label>
                    <input type="color" name="color_secundario" value="{{ old('color_secundario', $cyber->color_secundario) }}" class="w-full h-10 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Estado</label>
                    <label class="inline-flex items-center gap-2 text-sm border border-gray-300 rounded-lg px-3 py-2.5 w-full">
                        <input type="checkbox" name="activo" value="1" @checked(old('activo', $cyber->activo ?? true))> Activo
                    </label>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Logo (opcional)</label>
                    <div class="flex items-center gap-4">
                        @if($cyber->logoUrl())<img src="{{ $cyber->logoUrl() }}" class="h-12 object-contain bg-gray-50 border rounded p-1" alt="">@endif
                        <input type="file" name="logo" accept="image/*" class="text-sm">
                        @if($cyber->logoUrl())<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="quitar_logo" value="1"> Quitar logo</label>@endif
                    </div>
                </div>
            </div>
            <button type="submit" class="mt-6 inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                <x-icono name="check" class="size-4" /> {{ $editando ? 'Guardar cambios' : 'Registrar Negocio' }}
            </button>
        </form>
    </div>
@push('scripts')<script>const perfilNegocio=document.getElementById('negocio-perfil'),modulosNegocio=document.getElementById('negocio-modulos');function mostrarModulos(){modulosNegocio.hidden=perfilNegocio.value!=='personalizado';}perfilNegocio.addEventListener('change',mostrarModulos);mostrarModulos();</script>@endpush
</x-layout>
