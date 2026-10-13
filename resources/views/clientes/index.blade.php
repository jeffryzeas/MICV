@php $esAdmin = auth()->user()->esAdmin(); @endphp
<x-layout :titulo="$esAdmin ? 'Clientes y usuarios registrados' : 'Lista de clientes'" activo="clientes">

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-oro-500">
            <div class="text-xs uppercase font-bold text-azul-800">Total</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $total }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-400">
            <div class="text-xs uppercase font-bold text-azul-800">Hombres</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $totalHombres }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-pink-400">
            <div class="text-xs uppercase font-bold text-azul-800">Mujeres</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $totalMujeres }}</div>
        </div>
        <a href="{{ route('clientes.create') }}" class="bg-azul-800 hover:bg-oro-500 transition-colors rounded-xl shadow p-4 text-white flex items-center gap-3">
            <i data-lucide="user-plus" class="size-6"></i><span class="font-semibold text-sm">Registrar cliente</span>
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end mb-5">
            <div class="flex-1 min-w-[200px] relative">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Buscar</label>
                <i data-lucide="search" class="absolute left-3 top-[34px] size-4 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}" placeholder="Documento, nombre, correo o teléfono" class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5">
            </div>
            <div class="min-w-[150px]">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Sexo</label>
                <select name="sexo" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos</option>
                    <option value="M" @selected($sexo === 'M')>Masculino</option>
                    <option value="F" @selected($sexo === 'F')>Femenino</option>
                    <option value="O" @selected($sexo === 'O')>Otro</option>
                </select>
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Tipo de documento</label>
                <select name="tipo_documento" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Cliente::TIPOS_DOCUMENTO as $k => $t)
                        <option value="{{ $k }}" @selected($tipoDoc === $k)>{{ \Illuminate\Support\Str::before($t, ' (') }}</option>
                    @endforeach
                </select>
            </div>
            @if($esAdmin)
            <div class="min-w-[180px]">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Cyber</label>
                <select name="cyber_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos</option>
                    @foreach($cybers as $cy)
                        <option value="{{ $cy->id }}" @selected($cyberId === (string) $cy->id)>{{ $cy->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button type="submit" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                <i data-lucide="search" class="size-4"></i> Buscar
            </button>
            @if($q !== '' || $sexo !== '' || $tipoDoc !== '' || $cyberId !== '')
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 hover:text-azul-800 px-3 py-2.5">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 border-b">
                    <th class="py-2 pr-3">Documento</th><th class="py-2 pr-3">Nombre</th><th class="py-2 pr-3">Edad</th><th class="py-2 pr-3">Municipio</th>
                    @if($esAdmin)<th class="py-2 pr-3">Cyber</th>@endif
                    <th class="py-2"></th>
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
                                <span class="size-8 rounded-full bg-azul-800 text-oro-300 text-xs font-bold flex items-center justify-center">{{ $c->iniciales() }}</span>
                            @endif
                            <span>{{ $c->nombreCompleto() }}</span>
                            @if($c->user_id)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700" title="Se registró en /registro para hacer su propio CV">Usuario registrado</span>@endif
                            <span class="text-xs" title="{{ $c->datosPais()['nombre'] }}">{{ $c->datosPais()['bandera'] }}</span>
                            @unless($c->activo)<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700">Mantenimiento</span>@endunless
                        </div>
                    </td>
                    <td class="py-2.5 pr-3">{{ $c->edad() ?? '—' }}</td>
                    <td class="py-2.5 pr-3">{{ $c->municipio_nacimiento ?? '—' }}</td>
                    @if($esAdmin)
                        <td class="py-2.5 pr-3 text-xs">{{ $c->cybers->pluck('nombre')->implode(', ') ?: '—' }}</td>
                    @endif
                    <td class="py-2.5 whitespace-nowrap">
                        <a href="{{ route('clientes.show', $c) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-oro-700 hover:text-oro-800">
                            <i data-lucide="id-card" class="size-3.5"></i> Ver ficha
                        </a>
                        <a href="{{ route('clientes.edit', $c) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-azul-800 hover:text-oro-700 ml-2">
                            <i data-lucide="pencil" class="size-3.5"></i> Editar / CV
                        </a>
                        <a href="{{ route('clientes.show', $c) }}#generar-cv" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-oro-700 ml-2">
                            <i data-lucide="file-text" class="size-3.5"></i> CV (PDF)
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-400">Sin resultados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.paginacion', ['p' => $clientes])
    </div>
</x-layout>
