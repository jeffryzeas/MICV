@php
    $personaLaboral = $cliente ?? null;
    $residenciaLaboral = old('pais_residencia', $personaLaboral?->pais_residencia);
    $nacionalidadLaboral = old('nacionalidad', $personaLaboral?->nacionalidad);
    $mostrarLaboral = \App\Support\SituacionLaboral::esExtranjero($residenciaLaboral ?? '', $nacionalidadLaboral);
    $campoLaboral = $in ?? 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm';
@endphp
<div data-situacion-laboral class="space-y-3">
    <label class="block text-sm font-semibold">País de residencia actual
        <select name="pais_residencia" required class="{{ $campoLaboral }}">
            <option value="">Seleccione dónde reside</option>
            @foreach(\App\Support\Pais::todos() as $codigo => $paisLaboral)
                @if($codigo !== 'OT')<option value="{{ $codigo }}" @selected($residenciaLaboral === $codigo)>{{ $paisLaboral['nombre'] }}</option>@endif
            @endforeach
        </select>
    </label>
    <p class="text-xs text-gray-500">Seleccione dónde vive actualmente. Los campos complementarios se muestran cuando su nacionalidad corresponde a otro país.</p>

    <div data-extranjero-laboral class="space-y-3" @if(!$mostrarLaboral) hidden style="display:none" @endif>
        <label data-permiso-laboral class="block text-sm font-semibold">Situación / autorización laboral
            <input name="autorizacion_laboral" type="text" minlength="3" maxlength="255"
                   value="{{ old('autorizacion_laboral', $personaLaboral?->autorizacion_laboral) }}"
                   placeholder="Ej.: Cuento con permiso de trabajo vigente."
                   class="{{ $campoLaboral }}" @disabled(!$mostrarLaboral) @required($mostrarLaboral)>
            <span class="text-xs font-normal text-gray-500">Describa su situación. Otros ejemplos: permiso en trámite o sin autorización.</span>
        </label>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Disponibilidad
                <input type="text" name="disponibilidad" maxlength="255"
                       value="{{ old('disponibilidad', $personaLaboral?->disponibilidad) }}"
                       placeholder="Ej.: Disponibilidad inmediata o a convenir."
                       class="{{ $campoLaboral }}" @disabled(!$mostrarLaboral)>
                <span class="text-xs font-normal text-gray-500">Ejemplo: Cuento con permiso de trabajo vigente y disponibilidad inmediata.</span>
            </label>
            <label class="block text-sm font-semibold">Cualidades (separadas por coma)
                <input type="text" name="cualidades" maxlength="255"
                       value="{{ old('cualidades', $personaLaboral?->cualidades) }}"
                       placeholder="Ej.: Responsable, puntual, trabajo en equipo."
                       class="{{ $campoLaboral }}" @disabled(!$mostrarLaboral)>
            </label>
        </div>
        <p class="text-xs text-gray-500">Disponibilidad y cualidades son opcionales. Los ejemplos son orientativos: escriba su propia información.</p>
    </div>
    @error('pais_residencia')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('autorizacion_laboral')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('nacionalidad')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    <script type="application/json" data-nacionalidades>{!! json_encode(\App\Support\Nacionalidades::paraJs(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</div>
@once
@push('scripts')<script src="{{ asset('js/situacion-laboral.js') }}?v={{ @filemtime(public_path('js/situacion-laboral.js')) }}" defer></script>@endpush
@endonce
