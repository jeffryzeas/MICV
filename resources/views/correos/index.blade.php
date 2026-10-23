<x-layout titulo="Correos de clientes" activo="correos">
<div class="max-w-6xl mx-auto space-y-6">

    {{-- Header + botón crear (mismo patrón que clientes / reportes) --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-azul-800 flex items-center gap-2">
                <x-icono name="mail-plus" class="size-7"/>
                Correos de clientes
            </h1>
            <p class="text-gray-500 mt-1 text-sm">
                Registre el correo creado con el cliente y entregue el comprobante con QR.
                Este módulo no crea cuentas en Gmail ni cambia contraseñas de la plataforma.
            </p>
        </div>
        <a href="{{ route('correos.crear') }}"
           class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold px-5 py-3 rounded-xl shadow transition-colors shrink-0">
            <x-icono name="plus" class="size-5"/>
            Registrar correo
        </a>
    </div>

    @if(session('exito'))
        <p role="status" class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-800">{{ session('exito') }}</p>
    @endif
    @if($errors->any())
        <div role="alert" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    {{-- Lista de registros --}}
    <section class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <h2 class="text-lg font-bold text-azul-950 flex items-center gap-2">
                <x-icono name="list" class="size-5"/>
                Registros
            </h2>
            <form method="GET" class="flex gap-2">
                <div class="relative">
                    <x-icono name="search" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400"/>
                    <input type="search" name="q" value="{{ request('q') }}"
                           placeholder="Correo, nombre o documento"
                           class="campo pl-9 w-full sm:w-72">
                </div>
                <button class="btn btn-sec"><x-icono name="search"/> Buscar</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                        <th class="p-3 font-semibold">Cliente</th>
                        <th class="p-3 font-semibold">Correo</th>
                        <th class="p-3 font-semibold">Proveedor</th>
                        <th class="p-3 font-semibold">Fecha</th>
                        <th class="p-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registros as $registro)
                        <tr class="border-b last:border-0 hover:bg-gray-50/80">
                            <td class="p-3">
                                <div class="font-medium text-azul-950">{{ $registro->cliente->nombreCompleto() }}</div>
                                <div class="text-xs text-gray-500">{{ $registro->cliente->numero_documento }}</div>
                            </td>
                            <td class="p-3 font-mono text-sm">{{ $registro->correo }}</td>
                            <td class="p-3">{{ $registro->proveedor }}</td>
                            <td class="p-3 text-gray-600 whitespace-nowrap">
                                {{ $registro->created_at->timezone('America/Costa_Rica')->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-3">
                                <a href="{{ route('correos.ver', $registro) }}"
                                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-azul-800 hover:underline">
                                    <x-icono name="file-text" class="size-4"/> Ver comprobante
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-gray-400">
                                <x-icono name="mail" class="size-10 mx-auto mb-3 opacity-40"/>
                                <p>Aún no hay correos registrados.</p>
                                <a href="{{ route('correos.crear') }}" class="mt-3 inline-flex items-center gap-1.5 text-azul-800 font-semibold hover:underline">
                                    <x-icono name="plus" class="size-4"/> Registrar el primero
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($registros->hasPages())
            <div class="mt-5">{{ $registros->links() }}</div>
        @endif
    </section>
</div>
</x-layout>
