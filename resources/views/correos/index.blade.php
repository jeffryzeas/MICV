<x-layout titulo="Correos de clientes" activo="correos">
    <div class="gestion">
        <header class="page-heading">
            <div>
                <h2 class="page-title">Correos de clientes</h2>
                <p class="mt-1 text-sm text-gray-500">Cuentas registradas y comprobantes de entrega.</p>
            </div>
            <x-ui.button :href="route('correos.create')" icon="plus">Registrar correo</x-ui.button>
        </header>
        @if(session('exito'))<p role="status" class="gestion-notice gestion-notice-success">{{ session('exito') }}</p>@endif

        <section class="gestion-surface" aria-labelledby="correos-resultados">
            <div class="gestion-list-toolbar">
                <h3 id="correos-resultados">Registros <span class="gestion-count">{{ $registros->total() }}</span></h3>
                <form method="GET" action="{{ route('correos.index') }}" class="gestion-search-form" role="search">
                    <label for="correos-buscar" class="sr-only">Buscar correo, primer nombre o documento</label>
                    <input id="correos-buscar" type="search" class="campo" name="q" value="{{ request('q') }}" placeholder="Correo, primer nombre o documento">
                    <x-ui.button type="submit" variant="secondary" icon="search">Buscar</x-ui.button>
                    @if(request()->filled('q'))<a class="gestion-link" href="{{ route('correos.index') }}">Limpiar</a>@endif
                </form>
            </div>
            <p class="gestion-scroll-hint">Deslice la tabla para ver todas las columnas y acciones.</p>
        <div class="gestion-table-scroll" role="region" aria-label="Correos registrados" tabindex="0">
                <table class="gestion-table">
                    <thead><tr><th scope="col">Cliente</th><th scope="col">Correo</th><th scope="col">Proveedor</th><th scope="col">Registro</th><th scope="col" class="gestion-actions-heading">Comprobante</th></tr></thead>
                    <tbody>
                        @forelse($registros as $registro)
                            <tr>
                                <td class="font-semibold">{{ $registro->cliente->nombreCompleto() }}</td>
                                <td>{{ $registro->correo }}</td>
                                <td>{{ $registro->proveedor }}</td>
                                <td><time datetime="{{ $registro->created_at->toIso8601String() }}">{{ $registro->created_at->timezone('America/Costa_Rica')->format('d/m/Y H:i') }}</time></td>
                                <td class="gestion-row-actions"><a class="gestion-link" href="{{ route('correos.ver', $registro) }}" aria-label="Ver comprobante de {{ $registro->correo }}">Ver comprobante <x-icono name="chevron-right" class="size-4"/></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="gestion-empty">
                                <p class="font-semibold">{{ request()->filled('q') ? 'No se encontraron correos con esa búsqueda.' : 'Aún no hay correos registrados.' }}</p>
                                <a class="gestion-link mt-2" href="{{ request()->filled('q') ? route('correos.index') : route('correos.create') }}">{{ request()->filled('q') ? 'Ver todos los registros' : 'Registrar el primer correo' }}</a>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($registros->hasPages())<div class="gestion-pagination">{{ $registros->links() }}</div>@endif
        </section>
    </div>
</x-layout>
