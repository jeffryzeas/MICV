@php $yo = auth()->user(); @endphp
<x-layout :titulo="$yo->esAdmin() ? 'Dashboard' : 'Panel del Cyber'" activo="dashboard">

    <p class="text-gray-500 mb-5">Bienvenido, {{ $yo->name }}@if(!$yo->esAdmin() && $yo->cyber) — {{ $yo->cyber->nombre }}@endif.</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-oro-500">
            <div class="text-xs uppercase font-bold text-azul-800">Clientes</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $totalClientes }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-emerald-500">
            <div class="text-xs uppercase font-bold text-azul-800">Activos</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $clientesActivos }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-400">
            <div class="text-xs uppercase font-bold text-azul-800">Nuevos este mes</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $clientesMes }}</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-400">
            <div class="text-xs uppercase font-bold text-azul-800">CV este mes</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $cvMes }}</div>
        </div>
        @if($yo->esAdmin())
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-indigo-400">
            <div class="text-xs uppercase font-bold text-azul-800">Se registraron solos</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $autoRegistrados }}</div>
        </div>
        @endif
        <div class="bg-white rounded-xl shadow p-4 border-l-4 {{ $cumpleHoy > 0 ? 'border-pink-500' : 'border-gray-300' }}">
            <div class="text-xs uppercase font-bold text-azul-800">Cumplen hoy</div>
            <div class="text-2xl font-bold text-azul-950 mt-1">{{ $cumpleHoy }}</div>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('clientes.create') }}" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
            <i data-lucide="user-plus" class="size-4"></i> Registrar cliente
        </a>
        <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
            <i data-lucide="list" class="size-4"></i> Ver clientes
        </a>
        @if($yo->esAdmin())
            <a href="{{ route('admin.cybers.create') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                <i data-lucide="building-2" class="size-4"></i> Registrar Cyber
            </a>
            <a href="{{ route('admin.usuarios.create') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                <i data-lucide="user-cog" class="size-4"></i> Crear usuario
            </a>
        @endif
    </div>

    @if($yo->esAdmin())
    <div class="bg-white rounded-xl shadow p-6 mt-6">
        <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-4">
            <i data-lucide="building-2" class="size-[18px] text-oro-500"></i> Cybers
        </h2>
        @if($cybers->isEmpty())
            <p class="text-sm text-gray-500">Aún no hay Cybers. <a href="{{ route('admin.cybers.create') }}" class="font-semibold text-azul-800 underline">Registre el primero</a> y luego créele sus usuarios.</p>
        @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b"><th class="py-2 pr-3">Cyber</th><th class="py-2 pr-3">Clientes</th><th class="py-2 pr-3">Usuarios</th><th class="py-2 pr-3">Estado</th><th></th></tr></thead>
            <tbody>
            @foreach($cybers as $cy)
                <tr class="border-b last:border-0">
                    <td class="py-2.5 pr-3 font-medium">{{ $cy->nombre }}</td>
                    <td class="py-2.5 pr-3">{{ $cy->clientes_count }}</td>
                    <td class="py-2.5 pr-3">{{ $cy->usuarios_count }}</td>
                    <td class="py-2.5 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $cy->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $cy->activo ? 'Activo' : 'Mantenimiento' }}</span></td>
                    <td class="py-2.5"><a href="{{ route('clientes.index', ['cyber_id' => $cy->id]) }}" class="text-xs font-semibold text-azul-800 hover:text-oro-700">Ver sus clientes</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6 mt-6">
        <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-4">
            <i data-lucide="users" class="size-[18px] text-oro-500"></i> Últimos clientes registrados
        </h2>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 border-b">
                    <th class="py-2 pr-3">Documento</th><th class="py-2 pr-3">Nombre</th><th class="py-2 pr-3">Edad</th>
                    <th class="py-2 pr-3">Municipio</th><th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($ultimosClientes as $c)
                <tr class="border-b last:border-0">
                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $c->documentoFormateado() }}</td>
                    <td class="py-2.5 pr-3">{{ $c->nombreCompleto() }}</td>
                    <td class="py-2.5 pr-3">{{ $c->edad() ?? '—' }}</td>
                    <td class="py-2.5 pr-3">{{ $c->municipio_nacimiento ?? '—' }}</td>
                    <td class="py-2.5">
                        <a href="{{ route('clientes.show', $c) }}" class="text-xs font-semibold text-azul-800 hover:text-oro-700">Ver ficha / CV</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-gray-400">Sin clientes aún.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</x-layout>
