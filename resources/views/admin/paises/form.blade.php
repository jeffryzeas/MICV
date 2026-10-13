@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $d = (array) ($fila->datos ?? []);
    $v = fn ($k, $def = '') => old($k, $d[$k] ?? $def);
    $docs = old('docs', (array) ($fila->documentos ?? []));
    if (!$docs && !$deFabrica) { $docs = [['nombre' => 'Cédula de identidad', 'formato' => 'numeros', 'min' => 6, 'max' => 12]]; }
    $tieneEscudo = $pais && (!empty($pais['escudo']) || !empty($pais['escudo_subido']));
    $titulo = $codigo ? ($pais['nombre'] ?? $codigo) : 'Nuevo país';
@endphp
<x-layout :titulo="$titulo" activo="paises">
    <a href="{{ route('admin.paises.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-azul-800 mb-4"><x-icono name="arrow-left" class="size-4" /> Países</a>
    <form method="POST" action="{{ $codigo ? route('admin.paises.update', $codigo) : route('admin.paises.store') }}" enctype="multipart/form-data" class="space-y-5 max-w-4xl">
        @csrf @if($codigo) @method('PUT') @endif

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="globe" class="size-[18px] text-oro-500" /> Datos del país</h2>
            @if($deFabrica)
                <p class="text-sm text-gray-600 mb-3">Este país viene con el sistema: sus datos y la verificación de sus documentos ya están programados. Aquí puede cambiar el escudo, agregar documentos o desactivarlo.</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div><div class="text-[11px] uppercase text-gray-400">Nacionalidad</div>{{ $pais['gentilicio'] }}</div>
                    <div><div class="text-[11px] uppercase text-gray-400">Teléfono</div>{{ $pais['telefono'] ?: '—' }}</div>
                    <div><div class="text-[11px] uppercase text-gray-400">Seguro social</div>{{ $pais['seguro_largo'] ?: 'No se pide' }}</div>
                    <div><div class="text-[11px] uppercase text-gray-400">División</div>{{ $pais['municipio'] }} / {{ $pais['departamento'] }}</div>
                </div>
                <p class="text-xs text-gray-500 mt-3"><b>Documentos de fábrica:</b> {{ implode(' · ', \App\Support\Pais::DATOS[$codigo]['documentos']) }}</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="{{ $lb }}">Código ISO (2 letras) *</label>
                        <input type="text" name="codigo" value="{{ old('codigo', $codigo) }}" @disabled($codigo) required maxlength="2" placeholder="BR" class="{{ $in }} uppercase font-mono" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'')">
                        <p class="text-[11px] text-gray-400 mt-1">Código ISO del país.</p></div>
                    <div class="sm:col-span-2"><label class="{{ $lb }}">Nombre *</label><input type="text" name="nombre" value="{{ $v('nombre') }}" required maxlength="60" placeholder="Brasil" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Nacionalidad (hombre) *</label><input type="text" name="gentilicio" value="{{ $v('gentilicio') }}" required maxlength="40" placeholder="Brasileño" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Nacionalidad (mujer)</label><input type="text" name="gentilicio_f" value="{{ $v('gentilicio_f') }}" maxlength="40" placeholder="Brasileña" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Código de teléfono</label><input type="text" name="telefono" value="{{ $v('telefono') }}" maxlength="5" placeholder="+55" class="{{ $in }} font-mono"></div>
                    <div><label class="{{ $lb }}">Se llama "municipio" *</label><input type="text" name="municipio" value="{{ $v('municipio', 'Municipio') }}" required maxlength="30" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Se llama "departamento" *</label><input type="text" name="departamento" value="{{ $v('departamento', 'Departamento') }}" required maxlength="30" placeholder="Estado / Provincia" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Capital (lugar de las cartas)</label><input type="text" name="ciudad" value="{{ $v('ciudad') }}" maxlength="60" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">N.º de seguro social (etiqueta)</label><input type="text" name="seguro_largo" value="{{ $v('seguro_largo') }}" maxlength="60" placeholder="Vacío = no se pide" class="{{ $in }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lb }}">Compañías de celular (separadas por coma)</label><input type="text" name="companias" value="{{ $v('companias', 'Otro') }}" maxlength="150" placeholder="Vivo, Claro, TIM, Otro" class="{{ $in }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lb }}">Ley laboral (para las cartas de renuncia)</label><input type="text" name="ley" value="{{ $v('ley') }}" maxlength="150" placeholder="la Consolidación de las Leyes del Trabajo" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Nombre oficial (textos legales)</label><input type="text" name="oficial" value="{{ $v('oficial') }}" maxlength="100" placeholder="la República Federativa del Brasil" class="{{ $in }}"></div>
                </div>
            @endif
            <label class="mt-4 flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked(old('activo', $fila->activo ?? true))> Activo (sale en el registro y en los formularios)</label>
        </div>

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><x-icono name="shield" class="size-[18px] text-oro-500" /> Escudo del CV Clásico</h2>
            <p class="text-xs text-gray-500 mb-4">PNG con fondo transparente. Sale arriba y como marca de agua del CV Clásico. Si no hay escudo, el CV sale limpio.</p>
            <div class="flex flex-wrap items-center gap-4">
                <div class="size-24 rounded-xl bg-gray-50 border border-dashed border-gray-300 flex items-center justify-center overflow-hidden" id="vistaEscudo">
                    @if($tieneEscudo)<img src="{{ route('admin.paises.escudo', $codigo) }}" class="max-h-20 max-w-20 object-contain" alt="">@else<span class="text-xs text-gray-400 text-center px-2">Sin escudo</span>@endif
                </div>
                <div class="space-y-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        <x-icono name="upload" class="size-4" /> {{ $tieneEscudo ? 'Cambiar escudo' : 'Subir escudo' }}
                        <input type="file" name="escudo" accept="image/png,image/webp,image/jpeg" class="sr-only" onchange="const f=this.files[0]; if(f){ document.getElementById('vistaEscudo').innerHTML='<img src=&quot;'+window.URL.createObjectURL(f)+'&quot; class=&quot;max-h-20 max-w-20 object-contain&quot;>'; }">
                    </label>
                    @if(!empty($pais['escudo_subido']))<label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="quitar_escudo" value="1"> Quitar el escudo subido{{ !empty($pais['escudo']) ? ' (vuelve el de fábrica)' : '' }}</label>@endif
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2"><x-icono name="id-card" class="size-[18px] text-oro-500" /> {{ $deFabrica ? 'Documentos adicionales' : 'Tipos de documento' }}</h2>
                <button type="button" onclick="jzDocNuevo()" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg"><x-icono name="plus" class="size-3.5" /> Agregar documento</button>
            </div>
            <p class="text-xs text-gray-500 mb-4">El sistema revisa que el número tenga el formato y el largo que indique. {{ $deFabrica ? '' : 'El pasaporte se agrega solo.' }}</p>
            <div id="listaDocs" class="space-y-3">
                @foreach($docs as $i => $doc)
                    <div class="jz-doc grid grid-cols-2 sm:grid-cols-12 gap-2 p-3 bg-gray-50 rounded-lg items-end">
                        <input type="hidden" name="docs[{{ $i }}][clave]" value="{{ $doc['clave'] ?? '' }}">
                        <div class="col-span-2 sm:col-span-4"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nombre</label><input type="text" name="docs[{{ $i }}][nombre]" value="{{ $doc['nombre'] ?? '' }}" maxlength="80" placeholder="Cédula de identidad" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                        <div class="sm:col-span-2"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Formato</label><select name="docs[{{ $i }}][formato]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"><option value="numeros" @selected(($doc['formato'] ?? '') === 'numeros')>Solo números</option><option value="alfanumerico" @selected(($doc['formato'] ?? '') !== 'numeros')>Letras y números</option></select></div>
                        <div class="sm:col-span-1"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Mín.</label><input type="number" name="docs[{{ $i }}][min]" value="{{ $doc['min'] ?? 4 }}" min="1" max="30" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                        <div class="sm:col-span-1"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Máx.</label><input type="number" name="docs[{{ $i }}][max]" value="{{ $doc['max'] ?? 20 }}" min="1" max="30" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                        <div class="col-span-2 sm:col-span-3"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Ayuda (ejemplo)</label><input type="text" name="docs[{{ $i }}][ayuda]" value="{{ $doc['ayuda'] ?? '' }}" maxlength="120" placeholder="11 dígitos, ej. 123.456.789-09" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                        <div class="sm:col-span-1 text-right"><button type="button" onclick="this.closest('.jz-doc').remove()" class="text-gray-400 hover:text-red-600 p-2" title="Quitar"><x-icono name="trash-2" class="size-4" /></button></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-6 py-3 rounded-lg"><x-icono name="check" class="size-4" /> Guardar</button>
        </div>
    </form>
    @if($codigo && $fila->exists)
        <form method="POST" action="{{ route('admin.paises.destroy', $codigo) }}" class="mt-4 max-w-4xl" onsubmit="event.preventDefault(); Swal.fire({icon:'warning', title:'{{ $deFabrica ? '¿Quitar los cambios?' : '¿Borrar el país?' }}', text:'{{ $deFabrica ? 'El país vuelve a como viene de fábrica.' : 'Solo se puede si nadie lo usa.' }}', showCancelButton:true, confirmButtonText:'Sí', cancelButtonText:'Cancelar', confirmButtonColor:'#dc2626'}).then(r => { if (r.isConfirmed) this.submit(); });">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">{{ $deFabrica ? 'Quitar mis cambios a este país' : 'Borrar este país' }}</button>
        </form>
    @endif
    <script>
    function jzDocNuevo() {
        const i = Date.now() % 100000;
        document.getElementById('listaDocs').insertAdjacentHTML('beforeend', `<div class="jz-doc grid grid-cols-2 sm:grid-cols-12 gap-2 p-3 bg-gray-50 rounded-lg items-end">
            <input type="hidden" name="docs[${i}][clave]" value="">
            <div class="col-span-2 sm:col-span-4"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nombre</label><input type="text" name="docs[${i}][nombre]" maxlength="80" placeholder="Cédula de identidad" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            <div class="sm:col-span-2"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Formato</label><select name="docs[${i}][formato]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"><option value="numeros">Solo números</option><option value="alfanumerico">Letras y números</option></select></div>
            <div class="sm:col-span-1"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Mín.</label><input type="number" name="docs[${i}][min]" value="6" min="1" max="30" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            <div class="sm:col-span-1"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Máx.</label><input type="number" name="docs[${i}][max]" value="12" min="1" max="30" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            <div class="col-span-2 sm:col-span-3"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Ayuda (ejemplo)</label><input type="text" name="docs[${i}][ayuda]" maxlength="120" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            <div class="sm:col-span-1 text-right"><button type="button" onclick="this.closest('.jz-doc').remove()" class="text-gray-400 hover:text-red-600 p-2"><x-icono name="trash-2" class="size-4" /></button></div></div>`);
        lucide.createIcons();
    }
    </script>
</x-layout>
