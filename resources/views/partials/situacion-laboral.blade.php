@php $personaLaboral = $cliente ?? null; @endphp
<div data-situacion-laboral class="space-y-3">
    <label class="block text-sm font-semibold">País de residencia actual
        <select name="pais_residencia" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Seleccione dónde reside</option>
            @foreach(\App\Support\Pais::todos() as $codigo => $paisLaboral)
                @if($codigo !== 'OT')<option value="{{ $codigo }}" @selected(old('pais_residencia', $personaLaboral?->pais_residencia) === $codigo)>{{ $paisLaboral['nombre'] }}</option>@endif
            @endforeach
        </select>
    </label>
    <p class="text-xs text-gray-500">El país de residencia puede ser distinto al del documento. Confirme dónde vive actualmente.</p>
    <label data-permiso-laboral class="text-sm font-semibold" hidden style="display:none">Situación / autorización laboral
        <input name="autorizacion_laboral" type="text" minlength="3" maxlength="255" value="{{ old('autorizacion_laboral', $personaLaboral?->autorizacion_laboral) }}" placeholder="Permiso vigente, en trámite, sin autorización…" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" disabled>
        <span class="text-xs font-normal text-gray-500">Indique su situación real en el país donde reside. Este dato es una declaración para su CV.</span>
    </label>
    @error('pais_residencia')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('autorizacion_laboral')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('nacionalidad')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    <script type="application/json" data-nacionalidades>{!! json_encode(\App\Support\Nacionalidades::paraJs(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</div>
@once
@push('scripts')<script src="{{ asset('js/situacion-laboral.js') }}" defer></script>@endpush
@endonce
