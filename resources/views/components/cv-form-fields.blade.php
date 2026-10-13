{{--
  Campos del CV para el formulario de editar cliente.
  Uso: <x-cv-form-fields :cliente="$cliente" />
  Requiere que $cliente tenga cargadas las relaciones estudios, experiencias, referencias, habilidades.
--}}
@php
    $estudios = old('estudios', $cliente->estudios->map(fn ($e) => [
        'nivel' => $e->nivel, 'titulo' => $e->titulo, 'institucion' => $e->institucion,
        'anio' => $e->anio, 'detalle' => $e->detalle,
    ])->values()->all());
    if (empty($estudios)) {
        $estudios = [['nivel' => '', 'titulo' => '', 'institucion' => '', 'anio' => '', 'detalle' => '']];
    }

    $experiencias = old('experiencias', $cliente->experiencias->map(fn ($e) => [
        'cargo' => $e->cargo, 'empresa' => $e->empresa, 'descripcion' => $e->descripcion,
        'fecha_inicio' => optional($e->fecha_inicio)->format('Y-m-d'),
        'fecha_fin' => optional($e->fecha_fin)->format('Y-m-d'),
    ])->values()->all());
    if (empty($experiencias)) {
        $experiencias = [['cargo' => '', 'empresa' => '', 'descripcion' => '', 'fecha_inicio' => '', 'fecha_fin' => '']];
    }

    $referencias = old('referencias', $cliente->referencias->map(fn ($r) => [
        'tratamiento' => $r->tratamiento, 'nombre' => $r->nombre,
        'telefono' => $r->telefono, 'correo' => $r->correo,
    ])->values()->all());
    if (empty($referencias)) {
        $referencias = [['tratamiento' => '', 'nombre' => '', 'telefono' => '', 'correo' => '']];
    }

    $idiomas = old('idiomas', $cliente->idiomas->map(fn ($x) => ['idioma' => $x->idioma, 'nivel' => $x->nivel])->values()->all());
    if (empty($idiomas)) { $idiomas = [['idioma' => '', 'nivel' => 'intermedio']]; }
    $nivelesIdioma = \App\Models\ClienteIdioma::NIVELES;

    $habilidades = old('habilidades', $cliente->habilidades->pluck('habilidad')->values()->all());
    if (empty($habilidades)) { $habilidades = ['']; }
@endphp

{{-- ========== PERFIL PROFESIONAL ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Perfil" id="sec-perfil">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-2">
        <i data-lucide="user-round-pen" class="size-4 text-oro-500"></i> Perfil profesional
    </h3>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <p class="text-xs text-gray-500">Un párrafo corto sobre la persona. Sale destacado en el CV Pro (opcional).</p>
        <button type="button" onclick="jzIdeasPerfil()" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-lg">
            <i data-lucide="lightbulb" class="size-3.5"></i> Ideas para el perfil
        </button>
    </div>
    <textarea name="perfil_profesional" rows="3" maxlength="1500" placeholder="Ej: Persona responsable y puntual, con experiencia en atención al cliente…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">{{ old('perfil_profesional', $cliente->perfil_profesional) }}</textarea>
    <details class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2" @if(old('perfil_en', $cliente->perfil_en) || old('profesion_en', $cliente->profesion_en)) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-azul-800 flex items-center gap-2"><span>🇺🇸</span> Para el CV en inglés (opcional)</summary>
        <p class="text-xs text-gray-500 mt-2 mb-2">Los títulos del CV se traducen solos. Lo que usted escribe (perfil y oficio) se traduce aquí; si lo deja vacío, sale en español.</p>
        <label class="block text-[11px] font-semibold uppercase text-gray-500 mb-1">Profesión u oficio en inglés</label>
        <input type="text" name="profesion_en" maxlength="120" value="{{ old('profesion_en', $cliente->profesion_en) }}" placeholder="Ej: Cashier, Accounting assistant" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2">
        <label class="block text-[11px] font-semibold uppercase text-gray-500 mb-1">Perfil en inglés</label>
        <textarea name="perfil_en" rows="3" maxlength="1500" placeholder="Ej: Responsible and punctual person with experience in customer service…" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('perfil_en', $cliente->perfil_en) }}</textarea>
    </details>
</div>

{{-- ========== ESTUDIOS ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Estudios" id="bloque-estudios">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2">
            <i data-lucide="graduation-cap" class="size-4 text-oro-500"></i> Información académica
        </h3>
        <button type="button" onclick="agregarFila('estudios')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg">
            <i data-lucide="plus" class="size-3.5"></i> Agregar estudio / curso
        </button>
    </div>
    <p class="text-xs text-gray-500 mb-3">Los más recientes primero. Deja en blanco las filas que no uses.</p>
    <div id="lista-estudios" class="space-y-3">
        @foreach($estudios as $i => $est)
        <div class="flex gap-2 items-start fila-repetible">
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nivel</label>
                <select name="estudios[{{ $i }}][nivel]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">—</option>
                    @foreach(\App\Models\ClienteEstudio::NIVELES as $val => $lab)
                        <option value="{{ $val }}" @selected(($est['nivel'] ?? '') === $val)>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Título / nombre</label>
                <input type="text" name="estudios[{{ $i }}][titulo]" value="{{ $est['titulo'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" placeholder="Lic. en Enfermería">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Institución</label>
                <input type="text" name="estudios[{{ $i }}][institucion]" value="{{ $est['institucion'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Año</label>
                <input type="number" name="estudios[{{ $i }}][anio]" value="{{ $est['anio'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" min="1950" max="2100">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Detalle</label>
                <input type="text" name="estudios[{{ $i }}][detalle]" value="{{ $est['detalle'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
        </div>
        <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
            <i data-lucide="trash-2" class="size-4"></i>
        </button>
        </div>
        @endforeach
    </div>
</div>

{{-- ========== EXPERIENCIA ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Experiencia" id="bloque-experiencias">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2">
            <i data-lucide="briefcase" class="size-4 text-oro-500"></i> Experiencia laboral
        </h3>
        <button type="button" onclick="agregarFila('experiencias')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg">
            <i data-lucide="plus" class="size-3.5"></i> Agregar experiencia
        </button>
    </div>
    <div id="lista-experiencias" class="space-y-3">
        @foreach($experiencias as $i => $exp)
        <div class="flex gap-2 items-start fila-repetible">
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Cargo</label>
                <input type="text" name="experiencias[{{ $i }}][cargo]" value="{{ $exp['cargo'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" placeholder="Agricultor">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Empresa / lugar</label>
                <input type="text" name="experiencias[{{ $i }}][empresa]" value="{{ $exp['empresa'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Desde</label>
                <input type="date" name="experiencias[{{ $i }}][fecha_inicio]" value="{{ $exp['fecha_inicio'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Hasta (vacío = actual)</label>
                <input type="date" name="experiencias[{{ $i }}][fecha_fin]" value="{{ $exp['fecha_fin'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Descripción</label>
                <input type="text" name="experiencias[{{ $i }}][descripcion]" value="{{ $exp['descripcion'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
        </div>
        <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
            <i data-lucide="trash-2" class="size-4"></i>
        </button>
        </div>
        @endforeach
    </div>
</div>

{{-- ========== IDIOMAS ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Idiomas" id="bloque-idiomas">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2">
            <i data-lucide="languages" class="size-4 text-oro-500"></i> Idiomas
        </h3>
        <button type="button" onclick="agregarFila('idiomas')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg">
            <i data-lucide="plus" class="size-3.5"></i> Agregar idioma
        </button>
    </div>
    <p class="text-xs text-gray-500 mb-3">Los idiomas que habla y qué tan bien. Salen en el CV con su nivel.</p>
    <datalist id="jzListaIdiomas">@foreach(array_keys(\App\Models\ClienteIdioma::COMUNES) as $li)<option value="{{ $li }}">@endforeach</datalist>
    <div id="lista-idiomas" class="space-y-2">
        @foreach($idiomas as $i => $idi)
        <div class="flex gap-2 items-center fila-repetible">
            <i data-lucide="languages" class="size-4 text-gray-300 shrink-0"></i>
            <input type="text" name="idiomas[{{ $i }}][idioma]" value="{{ $idi['idioma'] ?? '' }}" list="jzListaIdiomas" placeholder="Ej: Inglés"
                   class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <select name="idiomas[{{ $i }}][nivel]" class="w-36 sm:w-44 border border-gray-300 rounded-lg px-2 py-2 text-sm">
                @foreach($nivelesIdioma as $nk => $nv)<option value="{{ $nk }}" @selected(($idi['nivel'] ?? 'intermedio') === $nk)>{{ $nv[0] }}</option>@endforeach
            </select>
            <button type="button" onclick="jzEliminarFila(this)" title="Quitar" class="text-gray-400 hover:text-red-600 shrink-0">
                <i data-lucide="trash-2" class="size-4"></i>
            </button>
        </div>
        @endforeach
    </div>
</div>

{{-- ========== HABILIDADES (debajo de experiencia laboral) ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Habilidades" id="bloque-habilidades">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2">
            <i data-lucide="sparkles" class="size-4 text-oro-500"></i> Habilidades
        </h3>
        <button type="button" onclick="agregarFila('habilidades')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg">
            <i data-lucide="plus" class="size-3.5"></i> Agregar habilidad
        </button>
    </div>
    <div class="mb-3 p-3 rounded-lg bg-amber-50/60 border border-amber-100">
        <p class="text-xs text-amber-900 mb-2 flex items-center gap-1.5"><i data-lucide="lightbulb" class="size-3.5"></i> <span>Toque para agregar. <span id="jzSugOficio" class="font-semibold"></span></span></p>
        <div id="jzSugHabs" class="flex flex-wrap gap-1.5"></div>
    </div>
    <div id="lista-habilidades" class="space-y-2">
        @foreach($habilidades as $i => $hab)
        <div class="flex gap-2 items-center fila-repetible">
            <i data-lucide="star" class="size-4 text-gray-300 shrink-0"></i>
            <input type="text" name="habilidades[]" value="{{ $hab }}" placeholder="Ej: Atención al cliente"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <button type="button" onclick="jzEliminarFila(this)" title="Quitar" class="text-gray-400 hover:text-red-600 shrink-0">
                <i data-lucide="trash-2" class="size-4"></i>
            </button>
        </div>
        @endforeach
    </div>
</div>

{{-- ========== REFERENCIAS ========== --}}
<div class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Referencias" id="bloque-referencias">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2">
            <i data-lucide="contact" class="size-4 text-oro-500"></i> Referencias
        </h3>
        <button type="button" onclick="agregarFila('referencias')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-azul-800 px-3 py-1.5 rounded-lg">
            <i data-lucide="plus" class="size-3.5"></i> Agregar referencia
        </button>
    </div>
    <div id="lista-referencias" class="space-y-3">
        @foreach($referencias as $i => $ref)
        <div class="flex gap-2 items-start fila-repetible">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Tratamiento</label>
                <input type="text" name="referencias[{{ $i }}][tratamiento]" value="{{ $ref['tratamiento'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" placeholder="Lic. / Sr. / Dra.">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nombre y apellido</label>
                <input type="text" name="referencias[{{ $i }}][nombre]" value="{{ $ref['nombre'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Celular</label>
                <input type="text" name="referencias[{{ $i }}][telefono]" value="{{ $ref['telefono'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Correo (opcional)</label>
                <input type="email" name="referencias[{{ $i }}][correo]" value="{{ $ref['correo'] ?? '' }}"
                       class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
            </div>
        </div>
        <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
            <i data-lucide="trash-2" class="size-4"></i>
        </button>
        </div>
        @endforeach
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    const plantillas = {
        estudios: () => {
            const i = document.querySelectorAll('#lista-estudios .fila-repetible').length;
            return `<div class="flex gap-2 items-start fila-repetible">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nivel</label>
                <select name="estudios[${i}][nivel]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">—</option>
                    @foreach(\App\Models\ClienteEstudio::NIVELES as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach
                </select></div>
                <div class="sm:col-span-2"><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Título / nombre</label>
                <input type="text" name="estudios[${i}][titulo]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Institución</label>
                <input type="text" name="estudios[${i}][institucion]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Año</label>
                <input type="number" name="estudios[${i}][anio]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" min="1950" max="2100"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Detalle</label>
                <input type="text" name="estudios[${i}][detalle]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            </div>
            <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
                <i data-lucide="trash-2" class="size-4"></i>
            </button>
            </div>`;
        },
        experiencias: () => {
            const i = document.querySelectorAll('#lista-experiencias .fila-repetible').length;
            return `<div class="flex gap-2 items-start fila-repetible">
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Cargo</label>
                <input type="text" name="experiencias[${i}][cargo]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Empresa / lugar</label>
                <input type="text" name="experiencias[${i}][empresa]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Desde</label>
                <input type="date" name="experiencias[${i}][fecha_inicio]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Hasta</label>
                <input type="date" name="experiencias[${i}][fecha_fin]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Descripción</label>
                <input type="text" name="experiencias[${i}][descripcion]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            </div>
            <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
                <i data-lucide="trash-2" class="size-4"></i>
            </button>
            </div>`;
        },
        referencias: () => {
            const i = document.querySelectorAll('#lista-referencias .fila-repetible').length;
            return `<div class="flex gap-2 items-start fila-repetible">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 p-3 bg-gray-50 rounded-lg flex-1">
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Tratamiento</label>
                <input type="text" name="referencias[${i}][tratamiento]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm" placeholder="Lic. / Sr."></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Nombre y apellido</label>
                <input type="text" name="referencias[${i}][nombre]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Celular</label>
                <input type="text" name="referencias[${i}][telefono]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
                <div><label class="block text-[10px] font-semibold uppercase text-gray-500 mb-0.5">Correo</label>
                <input type="email" name="referencias[${i}][correo]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm"></div>
            </div>
            <button type="button" onclick="jzEliminarFila(this)" title="Quitar esta fila" class="mt-6 text-gray-400 hover:text-red-600 shrink-0">
                <i data-lucide="trash-2" class="size-4"></i>
            </button>
            </div>`;
        },
        idiomas: () => {
            const i = Date.now() % 100000;
            return `<div class="flex gap-2 items-center fila-repetible">
                <i data-lucide="languages" class="size-4 text-gray-300 shrink-0"></i>
                <input type="text" name="idiomas[${i}][idioma]" list="jzListaIdiomas" placeholder="Ej: Inglés" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <select name="idiomas[${i}][nivel]" class="w-36 sm:w-44 border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    @foreach(\App\Models\ClienteIdioma::NIVELES as $nk => $nv)<option value="{{ $nk }}" @selected($nk === 'intermedio')>{{ $nv[0] }}</option>@endforeach
                </select>
                <button type="button" onclick="jzEliminarFila(this)" title="Quitar" class="text-gray-400 hover:text-red-600 shrink-0"><i data-lucide="trash-2" class="size-4"></i></button>
            </div>`;
        },
        habilidades: () => {
            return `<div class="flex gap-2 items-center fila-repetible">
                <i data-lucide="star" class="size-4 text-gray-300 shrink-0"></i>
                <input type="text" name="habilidades[]" placeholder="Ej: Atención al cliente" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <button type="button" onclick="jzEliminarFila(this)" title="Quitar" class="text-gray-400 hover:text-red-600 shrink-0">
                    <i data-lucide="trash-2" class="size-4"></i>
                </button>
            </div>`;
        }
    };
    window.agregarFila = function (tipo) {
        const cont = document.getElementById('lista-' + tipo);
        if (!cont || !plantillas[tipo]) return;
        cont.insertAdjacentHTML('beforeend', plantillas[tipo]());
        if (window.lucide) lucide.createIcons();
    };
    // ── Ayudas para escribir: perfil y habilidades según el oficio ──
    const SUG = @json(\App\Support\Sugerencias::paraJs());
    const norm = t => (t || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    function oficio() {
        const p = norm(document.querySelector('[name="profesion"]')?.value);
        if (!p) return null;
        return SUG.oficios.find(o => o.c.some(c => p.includes(norm(c)))) || null;
    }
    function habilidadesActuales() {
        return [...document.querySelectorAll('#lista-habilidades input[name="habilidades[]"]')].map(i => norm(i.value.trim())).filter(Boolean);
    }
    window.jzPintarSugerencias = function () {
        const cont = document.getElementById('jzSugHabs'); if (!cont) return;
        const o = oficio(), ya = habilidadesActuales();
        const lista = [...new Set([...(o ? o.h : []), ...SUG.generales])].filter(h => !ya.includes(norm(h))).slice(0, 14);
        document.getElementById('jzSugOficio').textContent = o ? 'Ideas para ' + o.n + '.' : 'Escriba su oficio en "Trabajo" y le damos ideas para ese oficio.';
        cont.innerHTML = lista.map(h => '<button type="button" class="jz-sug text-xs bg-white border border-amber-300 text-gray-700 hover:bg-amber-100 px-2.5 py-1 rounded-full">+ ' + h.replace(/</g, '&lt;') + '</button>').join('');
    };
    document.addEventListener('click', function (e) {
        const b = e.target.closest('.jz-sug'); if (!b) return;
        const texto = b.textContent.replace(/^\+\s*/, '');
        let vacio = [...document.querySelectorAll('#lista-habilidades input[name="habilidades[]"]')].find(i => !i.value.trim());
        if (!vacio) { agregarFila('habilidades'); vacio = [...document.querySelectorAll('#lista-habilidades input[name="habilidades[]"]')].pop(); }
        vacio.value = texto; vacio.dispatchEvent(new Event('input', { bubbles: true }));
        jzPintarSugerencias();
    });
    document.addEventListener('change', function (e) { if (e.target.matches('[name="profesion"], #lista-habilidades input')) jzPintarSugerencias(); });
    document.addEventListener('DOMContentLoaded', jzPintarSugerencias);

    window.jzIdeasPerfil = function () {
        const o = oficio(), area = document.querySelector('[name="perfil_profesional"]');
        const frases = o ? o.p : SUG.perfil;
        const opciones = SUG.oficios.map(x => '<option value="' + x.k + '"' + (o && o.k === x.k ? ' selected' : '') + '>' + x.n + '</option>').join('');
        const pintar = lista => lista.map((f, i) => '<button type="button" data-frase="' + i + '" class="jz-frase block w-full text-left text-sm border border-gray-200 hover:border-amber-400 hover:bg-amber-50 rounded-lg p-3 mb-2">' + f + '</button>').join('');
        let actuales = frases;
        Swal.fire({
            title: 'Ideas para su perfil',
            html: '<label class="block text-left text-xs font-semibold uppercase text-gray-500 mb-1">Oficio</label>'
                + '<select id="jzOfSel" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm mb-3"><option value="">General (cualquier trabajo)</option>' + opciones + '</select>'
                + '<p class="text-left text-xs text-gray-500 mb-2">Toque una frase para usarla. Luego puede cambiarla a su gusto.</p><div id="jzFrases">' + pintar(frases) + '</div>',
            showConfirmButton: false, showCancelButton: true, cancelButtonText: 'Cerrar', width: 560,
            didOpen: function (pop) {
                pop.querySelector('#jzOfSel').addEventListener('change', function () {
                    const x = SUG.oficios.find(y => y.k === this.value); actuales = x ? x.p : SUG.perfil;
                    pop.querySelector('#jzFrases').innerHTML = pintar(actuales);
                });
                pop.addEventListener('click', function (e) {
                    const b = e.target.closest('.jz-frase'); if (!b) return;
                    const f = actuales[+b.dataset.frase];
                    area.value = area.value.trim() ? area.value.trim() + ' ' + f : f;
                    area.dispatchEvent(new Event('input', { bubbles: true }));
                    Swal.close(); area.focus();
                });
            }
        });
    };

    window.jzEliminarFila = function (boton) {
        const fila = boton.closest('.fila-repetible');
        const lista = fila?.parentElement;
        if (!fila || !lista) return;
        // Siempre deja al menos una fila (para que el formulario no quede vacío de golpe).
        if (lista.querySelectorAll('.fila-repetible').length <= 1) {
            fila.querySelectorAll('input, select').forEach(el => { el.value = ''; });
            return;
        }
        fila.remove();
    };
})();
</script>
@endpush
@endonce
