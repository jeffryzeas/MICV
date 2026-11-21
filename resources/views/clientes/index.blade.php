@php $esAdmin = auth()->user()->esAdmin(); @endphp
<x-layout :titulo="$esAdmin ? 'Clientes y usuarios registrados' : 'Lista de clientes'" activo="clientes">

    <div class="gestion gestion-clientes">
    <header class="page-heading">
        <div><h2 class="page-title">{{ $esAdmin ? 'Clientes y usuarios' : 'Clientes' }}</h2><p class="mt-1 text-sm text-gray-500">Fichas personales y currículums registrados.</p></div>
        <div class="gestion-actions"><details class="gestion-export"><summary class="btn btn-sec"><x-icono name="download" class="size-4"/>Exportar</summary><x-exportar :ruta="route('clientes.exportar')"/></details><x-ui.button :href="route('clientes.create')" icon="user-plus">Registrar cliente</x-ui.button></div>
    </header>
    <dl class="gestion-totals"><div><dt>Total</dt><dd>{{ $total }}</dd></div><div><dt>Hombres</dt><dd>{{ $totalHombres }}</dd></div><div><dt>Mujeres</dt><dd>{{ $totalMujeres }}</dd></div></dl>
    <div class="gestion-filters">
        <form method="GET" class="gestion-client-filters">
            <div class="gestion-search">
                <label class="label-campo" for="clientes-q">Buscar</label>
                <input type="search" id="clientes-q" name="q" value="{{ $q }}" placeholder="Documento, nombre, correo o teléfono" class="campo">
            </div>
            <div>
                <label class="label-campo" for="clientes-sexo">Sexo</label>
                <select id="clientes-sexo" name="sexo" class="campo">
                    <option value="">Todos</option>
                    <option value="M" @selected($sexo === 'M')>Masculino</option>
                    <option value="F" @selected($sexo === 'F')>Femenino</option>
                    <option value="O" @selected($sexo === 'O')>Otro</option>
                </select>
            </div>
            <div>
                <label class="label-campo" for="clientes-tipo_documento">Tipo de documento</label>
                <select id="clientes-tipo_documento" name="tipo_documento" class="campo">
                    <option value="">Todos</option>
                    @foreach(\App\Support\Pais::todos() as $pc => $pd)
                        <optgroup label="{{ $pd['nombre'] }}">
                        @foreach($pd['documentos'] as $k => $t)
                            @if($k !== 'pasaporte' || $pc === 'OT')<option value="{{ $k }}" @selected($tipoDoc === $k)>{{ $t }}</option>@endif
                        @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @if($esAdmin)
            <div>
                <label class="label-campo" for="clientes-cyber_id">Negocio</label>
                <select id="clientes-cyber_id" name="cyber_id" class="campo">
                    <option value="">Todos</option>
                    @foreach($cybers as $cy)
                        <option value="{{ $cy->id }}" @selected($cyberId === (string) $cy->id)>{{ $cy->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button type="submit" class="btn btn-pri">
                <x-icono name="search" class="size-4" /> Buscar
            </button>
            @if($q !== '' || $sexo !== '' || $tipoDoc !== '' || $cyberId !== '')
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 hover:text-azul-800 px-3 py-2.5">Limpiar</a>
            @endif
        </form>

    </div>
    <section class="gestion-surface" aria-label="Listado de clientes">
        <div class="gestion-section-heading"><h3>Clientes registrados <span class="gestion-count">{{ $clientes->total() }}</span></h3></div>
        <p class="gestion-scroll-hint">Deslice la tabla para ver todas las columnas y acciones.</p>
        <div class="gestion-table-scroll" role="region" aria-label="Clientes y sus fichas" tabindex="0">
        <table class="gestion-table">
            <thead>
                <tr>
                    <th scope="col">Documento</th><th scope="col">Nombre</th><th scope="col">Edad</th><th scope="col">Municipio</th>
                    @if($esAdmin)<th scope="col">Negocio</th>@endif
                    <th scope="col" class="gestion-actions-heading">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($clientes as $c)
                <tr class="border-b last:border-0">
                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $c->documentoFormateado() }}</td>
                    <td class="py-2.5 pr-3">
                        <div class="flex items-center gap-2">
                            @if($c->tieneFoto())
                                <img src="{{ route('clientes.foto', $c) }}" class="size-8 rounded-full object-cover" alt="">
                            @else
                                <span class="size-8 rounded-full bg-white border border-dashed border-gray-300 text-gray-400 flex items-center justify-center shrink-0" title="Sin foto"><x-icono name="user-round" class="size-4" /></span>
                            @endif
                            <span>{{ $c->nombreCompleto() }}</span>
                            @if($c->user_id)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700" title="Se registró en /registro para hacer su propio CV">Usuario registrado</span>@endif
                            <span class="text-xs" title="{{ $c->datosPais()['nombre'] }}"></span>
                            @unless($c->activo)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700">Mantenimiento</span>@endunless
                        </div>
                    </td>
                    <td class="py-2.5 pr-3">{{ $c->edad() ?? '—' }}</td>
                    <td class="py-2.5 pr-3">{{ $c->municipio_nacimiento ?? '—' }}</td>
                    @if($esAdmin)
                        <td class="py-2.5 pr-3 text-xs">{{ $c->cybers->pluck('nombre')->implode(', ') ?: '—' }}</td>
                    @endif
                    <td class="gestion-row-actions">
                        <a href="{{ route('clientes.show', $c) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-oro-700 hover:text-oro-800">
                            <x-icono name="id-card" class="size-3.5" /> Ver ficha
                        </a>
                        <a href="{{ route('clientes.edit', $c) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-azul-800 hover:text-oro-700 ml-2">
                            <x-icono name="pencil" class="size-3.5" /> Editar / CV
                        </a>
                        <a href="{{ route('clientes.show', $c) }}#generar-cv" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-oro-700 ml-2">
                            <x-icono name="file-text" class="size-3.5" /> CV (PDF)
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $esAdmin ? 6 : 5 }}" class="gestion-empty">Sin resultados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="gestion-pagination">@include('partials.paginacion', ['p' => $clientes])</div>
    </section>
    </div>
</x-layout>
