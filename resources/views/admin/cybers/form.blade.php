@php $editando = $cyber->exists; @endphp
<x-layout :titulo="$editando ? 'Editar Cyber' : 'Registrar Cyber'" activo="cybers">
    <a href="{{ route('admin.cybers.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors mb-5">
        <i data-lucide="arrow-left" class="size-4"></i> Volver
    </a>
    <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
        <form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('admin.cybers.update', $cyber) : route('admin.cybers.store') }}">
            @csrf @if($editando) @method('PUT') @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo', $cyber->codigo) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 uppercase font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nombre del Cyber</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $cyber->nombre) }}" required placeholder="Ej: Cyber San Nicolás" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
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
                <i data-lucide="check" class="size-4"></i> {{ $editando ? 'Guardar cambios' : 'Registrar Cyber' }}
            </button>
        </form>
    </div>
</x-layout>
