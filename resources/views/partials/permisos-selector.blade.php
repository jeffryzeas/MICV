@php $permisosActuales = old('permisos', $usuario->permisos ?? \App\Support\Permisos::perfil($usuario->encargado_id ? 'cajero' : 'encargado')); $esPersonal = $esPersonal ?? false; @endphp
<fieldset class="border border-gray-200 rounded-xl p-4 mt-4" data-permisos-selector>
    <legend class="text-sm font-bold">Permisos por módulo y acción</legend>
    <input type="hidden" name="permisos_enviados" value="1">
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['cajero'=>'Cajero', 'operador'=>'Operador de documentos', 'encargado'=>'Todos los permitidos'] as $perfil => $nombre)
            <button type="button" data-perfil="{{ $perfil }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $nombre }}</button>
        @endforeach
        <button type="button" data-perfil="ninguno" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">Ninguno</button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach(\App\Support\Permisos::LISTA as $clave=>$etiqueta)
            @if(!$esPersonal || ($clave !== 'personal' && (auth()->user()->esAdmin() || auth()->user()->tienePermiso($clave))))<label class="flex gap-2 items-start text-sm"><input type="checkbox" name="permisos[]" value="{{ $clave }}" @checked(in_array($clave,$permisosActuales,true))>{{ $etiqueta }}</label>@endif
        @endforeach
    </div>
    <p class="text-xs text-gray-500 mt-3">Al habilitar una acción se habilita también la lectura necesaria para usarla. Los cajeros no pueden administrar personal.</p>
    <script type="application/json" data-presets>{!! json_encode(['cajero'=>\App\Support\Permisos::perfil('cajero'),'operador'=>\App\Support\Permisos::perfil('operador'),'encargado'=>\App\Support\Permisos::perfil('encargado'),'ninguno'=>[]], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
</fieldset>
@once @push('scripts')<script>document.querySelectorAll('[data-permisos-selector]').forEach(box=>{const presets=JSON.parse(box.querySelector('[data-presets]').textContent);box.querySelectorAll('[data-perfil]').forEach(b=>b.addEventListener('click',()=>box.querySelectorAll('input[type=checkbox]').forEach(i=>i.checked=presets[b.dataset.perfil].includes(i.value))));});</script>@endpush @endonce
