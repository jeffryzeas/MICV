@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $num = fn ($k, $min, $max, $paso = 1) => '<input type="number" name="cv['.$k.']" value="'.e(old("cv.$k", $cv[$k])).'" min="'.$min.'" max="'.$max.'" step="'.$paso.'" class="'.$in.'">';
    $color = fn ($k) => '<input type="color" name="cv['.$k.']" value="'.e(old("cv.$k", $cv[$k])).'" class="w-full h-10 border border-gray-300 rounded-lg">';
    $chk = fn ($k, $t) => '<label class="inline-flex items-center gap-2 text-sm mt-6"><input type="checkbox" name="cv['.$k.']" value="1" '.(old("cv.$k", $cv[$k]) ? 'checked' : '').'> '.e($t).'</label>';
@endphp
<x-layout titulo="Configuración y medidas del CV" activo="configuracion">
<form method="POST" action="{{ route('admin.configuracion.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="mb-5 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 text-sm flex items-center gap-2">
        <x-icono name="palette" class="size-4" /> El nombre, el logo, los colores y el contacto del sistema ahora están en <a href="{{ route('admin.marca.edit') }}" class="font-semibold underline">Marca del sistema</a>.
    </div>

    <div class="bg-white rounded-xl shadow p-6 mb-5">
        <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="file-text" class="size-[18px] text-oro-500" /> CV — opciones generales</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div><label class="{{ $lb }}">Diseño por defecto</label>
                <select name="cv[diseno_defecto]" class="{{ $in }}">
                    @foreach(\App\Services\CvDatos::DISENOS as $k => $t)<option value="{{ $k }}" @selected(old('cv.diseno_defecto', $cv['diseno_defecto']) === $k)>{{ $t }}</option>@endforeach
                </select></div>
            <div><label class="{{ $lb }}">Colores por defecto (Pro)</label>
                <select name="cv[pro_paleta]" class="{{ $in }}">
                    @foreach($paletas as $k => $p)<option value="{{ $k }}" @selected(old('cv.pro_paleta', $cv['pro_paleta']) === $k)>{{ $p['nombre'] }}</option>@endforeach
                </select></div>
            <div><label class="{{ $lb }}">Cómo se escribe el nombre (Clásico)</label>
                <select name="cv[nombre_formato]" class="{{ $in }}">
                    @foreach(['mayusculas' => 'TODO EN MAYÚSCULAS', 'capital' => 'Primera Letra Mayúscula', 'tal_cual' => 'Tal como se escribió'] as $k => $t)
                        <option value="{{ $k }}" @selected(old('cv.nombre_formato', $cv['nombre_formato']) === $k)>{{ $t }}</option>
                    @endforeach
                </select></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mb-5">
        <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><x-icono name="ruler" class="size-[18px] text-oro-500" /> Medidas del CV Clásico</h2>
        <p class="text-xs text-gray-500 mb-4">Todas las medidas en puntos (pt). 72 pt = 1 pulgada.</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div><label class="{{ $lb }}">Título "Currículum Vítae"</label>{!! $num('fuente_titulo_tam', 20, 80, .5) !!}</div>
            <div><label class="{{ $lb }}">Títulos de sección</label>{!! $num('fuente_seccion_tam', 8, 30, .5) !!}</div>
            <div><label class="{{ $lb }}">Texto</label>{!! $num('fuente_texto_tam', 7, 18, .5) !!}</div>
            <div><label class="{{ $lb }}">Viñetas</label>{!! $num('fuente_vineta_tam', 7, 18, .5) !!}</div>
            <div><label class="{{ $lb }}">Ancho etiquetas (%)</label>{!! $num('etiqueta_ancho_pct', 20, 60) !!}</div>
            <div><label class="{{ $lb }}">Color del título</label>{!! $color('color_titulo') !!}</div>
            <div><label class="{{ $lb }}">Color de secciones</label>{!! $color('color_seccion') !!}</div>
            <div><label class="{{ $lb }}">Color del texto</label>{!! $color('color_texto') !!}</div>
            <div>{!! $chk('seccion_negrita', 'Secciones en negrita') !!}</div>
            <div>{!! $chk('seccion_subrayado', 'Secciones subrayadas') !!}</div>
            <div>{!! $chk('etiqueta_negrita', 'Etiquetas en negrita') !!}</div>
            <div></div>
            <div><label class="{{ $lb }}">Foto: ancho</label>{!! $num('foto_ancho', 50, 200) !!}</div>
            <div><label class="{{ $lb }}">Foto: alto</label>{!! $num('foto_alto', 50, 250) !!}</div>
            <div><label class="{{ $lb }}">Foto: borde</label>{!! $num('foto_borde', 0, 8, .5) !!}</div>
            <div></div>
            <div>{!! $chk('mostrar_escudo', 'Escudo en las esquinas') !!}</div>
            <div><label class="{{ $lb }}">Tamaño del escudo</label>{!! $num('escudo_tam', 20, 100) !!}</div>
            <div>{!! $chk('marca_agua_activa', 'Marca de agua') !!}</div>
            <div></div>
            <div><label class="{{ $lb }}">Marca de agua: ancho %</label>{!! $num('marca_agua_ancho_pct', 10, 100) !!}</div>
            <div><label class="{{ $lb }}">Marca de agua: opacidad %</label>{!! $num('marca_agua_opacidad', 1, 60) !!}</div>
            <div class="col-span-2">
                <label class="{{ $lb }}">Escudo para personas de "otro país"</label>
                <p class="text-[11px] text-gray-400 mb-1">Nicaragua y Costa Rica usan su escudo oficial automáticamente.</p>
                <input type="file" name="escudo" accept="image/*" class="text-sm">
                @if(\App\Support\Archivos::existe($cfg->escudo))<label class="inline-flex items-center gap-2 text-sm mt-1"><input type="checkbox" name="quitar_escudo" value="1"> Quitar escudo personalizado</label>@endif
            </div>
            <div>{!! $chk('marco_activo', 'Marco de la hoja') !!}</div>
            <div><label class="{{ $lb }}">Distancia al borde</label>{!! $num('marco_margen', 0, 40, .5) !!}</div>
            <div><label class="{{ $lb }}">Separación entre líneas</label>{!! $num('marco_separacion', 0, 20, .5) !!}</div>
            <div></div>
            <div><label class="{{ $lb }}">Línea externa: grosor</label>{!! $num('marco_externo_grosor', 0, 12, .5) !!}</div>
            <div><label class="{{ $lb }}">Línea externa: color</label>{!! $color('marco_externo_color') !!}</div>
            <div><label class="{{ $lb }}">Línea interna: grosor</label>{!! $num('marco_interno_grosor', 0, 8, .1) !!}</div>
            <div><label class="{{ $lb }}">Línea interna: color</label>{!! $color('marco_interno_color') !!}</div>
        </div>
    </div>

    <button type="submit" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
        <x-icono name="check" class="size-4" /> Guardar configuración
    </button>
</form>
<form method="POST" action="{{ route('admin.configuracion.restablecer') }}" class="mt-4" onsubmit="return confRest(event)">
    @csrf
    <button class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="rotate-ccw" class="size-4" /> Restablecer medidas del CV</button>
</form>
@push('scripts')
<script>function confRest(e){e.preventDefault();Swal.fire({title:'¿Restablecer medidas?',text:'Vuelven los valores de fábrica.',icon:'warning',showCancelButton:true,confirmButtonColor:'#0B3C74',confirmButtonText:'Sí',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}</script>
@endpush
</x-layout>
