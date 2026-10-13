@php
    $editando = $cliente->exists;
    $v = fn (string $campo, $def = null) => old($campo, $cliente->{$campo} ?? $def);
    $estudios = old('estudios', $cliente->estudios?->map(fn ($e) => $e->only('nivel','titulo','institucion','anio','detalle'))->all() ?? []);
    $experiencias = old('experiencias', $cliente->experiencias?->map(fn ($e) => [
        'cargo' => $e->cargo, 'empresa' => $e->empresa, 'descripcion' => $e->descripcion,
        'fecha_inicio' => $e->fecha_inicio?->format('Y-m-d'), 'fecha_fin' => $e->fecha_fin?->format('Y-m-d'),
    ])->all() ?? []);
    $habilidades = old('habilidades', $cliente->habilidades?->pluck('habilidad')->all() ?? []);
    $referencias = old('referencias', $cliente->referencias?->map(fn ($r) => $r->only('tratamiento','nombre','telefono','correo'))->all() ?? []);
    if (!$editando && !old()) { $estudios = $estudios ?: [[]]; $experiencias = $experiencias ?: [[]]; $habilidades = $habilidades ?: ['', '']; $referencias = $referencias ?: [[], []]; }
    $niveles = \App\Models\ClienteEstudio::NIVELES;
    $fe = fn ($k) => $errors->has($k) ? 'is-invalid' : '';
@endphp
@extends('layouts.app')
@section('titulo', $editando ? 'Editar: ' . $cliente->nombreCorto() : 'Registrar cliente')
@section('acciones')
    <a href="{{ $editando ? route('clientes.show', $cliente) : route('clientes.index') }}" class="btn btn-sec"><x-icono n="volver"/> Volver</a>
@endsection
@section('contenido')
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('clientes.update', $cliente) : route('clientes.store') }}">
    @csrf
    @if ($editando) @method('PUT') @endif

    {{-- DOCUMENTO --}}
    <div class="card">
        <h2>1. Documento de identidad</h2>
        <div class="grid g3">
            <div>
                <label for="tipo_documento">Tipo de documento</label>
                <select id="tipo_documento" name="tipo_documento">
                    @foreach (\App\Models\Cliente::TIPOS_DOCUMENTO as $k => $t)
                        <option value="{{ $k }}" @selected($v('tipo_documento') === $k)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="numero_documento">Número</label>
                <input type="text" id="numero_documento" name="numero_documento" value="{{ $v('numero_documento') }}" class="{{ $fe('numero_documento') }}"
                       data-url="{{ route('documento.consultar') }}" data-ignorar="{{ $cliente->id }}" autocomplete="off" placeholder="Ej. 001-010190-0001A">
                <div id="estado-documento" class="campo-estado"></div>
            </div>
            <div><label>INSS / Seguro social</label><input type="text" name="inss_nss" value="{{ $v('inss_nss') }}"></div>
        </div>
        <div class="ayuda">Con cédula de Nicaragua se llenan solos la fecha y el lugar de nacimiento.</div>
    </div>

    {{-- DATOS PERSONALES --}}
    <div class="card">
        <h2>2. Datos personales</h2>
        <div class="grid g4">
            <div><label>Primer nombre *</label><input type="text" name="primer_nombre" value="{{ $v('primer_nombre') }}" class="{{ $fe('primer_nombre') }}" required></div>
            <div><label>Segundo nombre</label><input type="text" name="segundo_nombre" value="{{ $v('segundo_nombre') }}"></div>
            <div><label>Primer apellido *</label><input type="text" name="primer_apellido" value="{{ $v('primer_apellido') }}" class="{{ $fe('primer_apellido') }}" required></div>
            <div><label>Segundo apellido</label><input type="text" name="segundo_apellido" value="{{ $v('segundo_apellido') }}"></div>

            <div><label>Fecha de nacimiento</label><input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $cliente->fecha_nacimiento?->format('Y-m-d')) }}" class="{{ $fe('fecha_nacimiento') }}"></div>
            <div><label>Municipio de nacimiento</label><input type="text" id="municipio_nacimiento" name="municipio_nacimiento" value="{{ $v('municipio_nacimiento') }}"></div>
            <div><label>Departamento / Provincia</label><input type="text" id="departamento_nacimiento" name="departamento_nacimiento" value="{{ $v('departamento_nacimiento') }}"></div>
            <div><label>Nacionalidad</label><input type="text" id="nacionalidad" name="nacionalidad" value="{{ $v('nacionalidad') }}"></div>

            <div><label>Sexo</label>
                <select name="sexo"><option value="">—</option>
                    @foreach (['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'] as $k => $t)<option value="{{ $k }}" @selected($v('sexo') === $k)>{{ $t }}</option>@endforeach
                </select></div>
            <div><label>Estado civil</label>
                <select name="estado_civil"><option value="">—</option>
                    @foreach (\App\Models\Cliente::ESTADOS_CIVILES as $t)<option @selected($v('estado_civil') === $t)>{{ $t }}</option>@endforeach
                </select></div>
            <div><label>Número de hijos</label><input type="number" min="0" max="30" name="numero_hijos" value="{{ $v('numero_hijos') }}"></div>
            <div><label>Profesión u oficio</label><input type="text" name="profesion" value="{{ $v('profesion') }}" placeholder="Ej. Técnico en contabilidad"></div>

            <div class="spanall"><label>Dirección actual</label><input type="text" name="direccion" value="{{ $v('direccion') }}"></div>
        </div>
    </div>

    {{-- CONTACTO Y FOTO --}}
    <div class="grid g2">
        <div class="card">
            <h2>3. Contacto</h2>
            <div class="grid g3">
                <div><label>Código país</label><input type="text" name="telefono_codigo_pais" value="{{ $v('telefono_codigo_pais') }}" placeholder="+505"></div>
                <div><label>Celular</label><input type="text" name="telefono" value="{{ $v('telefono') }}"></div>
                <div><label>Compañía</label><input type="text" name="telefono_red" value="{{ $v('telefono_red') }}" placeholder="Claro, Tigo, Kölbi…"></div>
                <div></div>
                <div><label>Otro celular</label><input type="text" name="telefono_secundario" value="{{ $v('telefono_secundario') }}"></div>
                <div><label>Compañía</label><input type="text" name="telefono_secundario_red" value="{{ $v('telefono_secundario_red') }}"></div>
                <div class="spanall"><label>Correo electrónico</label><input type="email" name="correo" value="{{ $v('correo') }}" class="{{ $fe('correo') }}"></div>
            </div>
        </div>
        <div class="card">
            <h2>4. Foto (tamaño carné)</h2>
            <div class="ficha-cab">
                <div id="foto-previa">
                    @if ($editando && $cliente->tieneFoto())
                        <img src="{{ route('clientes.foto', $cliente) }}" class="foto" alt="">
                    @else
                        <div class="foto">{{ $editando ? $cliente->iniciales() : '?' }}</div>
                    @endif
                </div>
                <div style="flex:1">
                    <label for="foto">Subir foto</label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <div class="ayuda">JPG, PNG o WEBP, máx. 8 MB. Se ajusta sola.</div>
                    @if ($editando && $cliente->tieneFoto())
                        <label class="check" style="margin-top:10px"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- PERFIL --}}
    <div class="card">
        <h2>5. Perfil profesional</h2>
        <p class="sub">Un párrafo corto sobre la persona. Sale destacado en el diseño Pro.</p>
        <textarea name="perfil_profesional" maxlength="1500" placeholder="Ej. Persona responsable y puntual, con experiencia en atención al cliente y manejo de caja…">{{ $v('perfil_profesional') }}</textarea>
    </div>

    {{-- ESTUDIOS --}}
    <div class="card" data-repetidor="estudios">
        <h2>6. Información académica</h2>
        <div data-lista>
            @foreach ($estudios as $i => $e)
                @include('clientes._fila-estudio', ['i' => $i, 'e' => $e])
            @endforeach
        </div>
        <button type="button" class="btn btn-sec btn-sm" data-agregar><x-icono n="mas"/> Agregar estudio</button>
        <template id="tpl-estudios">@include('clientes._fila-estudio', ['i' => '__I__', 'e' => []])</template>
    </div>

    {{-- EXPERIENCIA --}}
    <div class="card" data-repetidor="experiencias">
        <h2>7. Experiencia laboral</h2>
        <div data-lista>
            @foreach ($experiencias as $i => $e)
                @include('clientes._fila-experiencia', ['i' => $i, 'e' => $e])
            @endforeach
        </div>
        <button type="button" class="btn btn-sec btn-sm" data-agregar><x-icono n="mas"/> Agregar experiencia</button>
        <template id="tpl-experiencias">@include('clientes._fila-experiencia', ['i' => '__I__', 'e' => []])</template>
    </div>

    <div class="grid g2">
        {{-- HABILIDADES --}}
        <div class="card" data-repetidor="habilidades">
            <h2>8. Habilidades</h2>
            <div data-lista>
                @foreach ($habilidades as $h)
                    <div class="fila-hab" data-fila><input type="text" name="habilidades[]" value="{{ $h }}" maxlength="150" placeholder="Ej. Atención al cliente"><button type="button" class="btn btn-peligro btn-sm" data-quitar><x-icono n="x"/></button></div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sec btn-sm" data-agregar><x-icono n="mas"/> Agregar habilidad</button>
            <template id="tpl-habilidades"><div class="fila-hab" data-fila><input type="text" name="habilidades[]" maxlength="150" placeholder="Ej. Manejo de Excel"><button type="button" class="btn btn-peligro btn-sm" data-quitar><x-icono n="x"/></button></div></template>
        </div>

        {{-- FAMILIA --}}
        <div class="card">
            <h2>9. Datos familiares</h2>
            <div class="grid">
                <div><label>Nombre del padre</label><input type="text" name="nombre_padre" value="{{ $v('nombre_padre') }}"></div>
                <div><label>Nombre de la madre</label><input type="text" name="nombre_madre" value="{{ $v('nombre_madre') }}"></div>
            </div>
        </div>
    </div>

    {{-- REFERENCIAS --}}
    <div class="card" data-repetidor="referencias">
        <h2>10. Referencias personales</h2>
        <div data-lista>
            @foreach ($referencias as $i => $r)
                @include('clientes._fila-referencia', ['i' => $i, 'r' => $r])
            @endforeach
        </div>
        <button type="button" class="btn btn-sec btn-sm" data-agregar><x-icono n="mas"/> Agregar referencia</button>
        <template id="tpl-referencias">@include('clientes._fila-referencia', ['i' => '__I__', 'r' => []])</template>
    </div>

    <div class="card">
        <h2>Notas internas</h2>
        <textarea name="notas" placeholder="No salen en el CV.">{{ $v('notas') }}</textarea>
        <label class="check" style="margin-top:10px"><input type="checkbox" name="activo" value="1" @checked(old('activo', $cliente->activo ?? true))> Cliente activo</label>
    </div>

    <div style="display:flex;gap:10px;position:sticky;bottom:0;background:var(--fondo);padding:12px 0">
        <button class="btn btn-pri" style="padding:12px 22px"><x-icono n="guardar"/> {{ $editando ? 'Guardar cambios' : 'Registrar cliente' }}</button>
        <a href="{{ $editando ? route('clientes.show', $cliente) : route('clientes.index') }}" class="btn btn-sec">Cancelar</a>
    </div>
</form>
@endsection
