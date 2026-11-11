<x-layout titulo="Ventas en espera y cotizaciones" activo="pos-vender">
    @include('pos._barra')
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <p class="text-sm text-gray-600">Retome una venta o descargue una cotización guardada con su cuenta.</p>
        <a href="{{ route('pos.vender') }}" class="btn btn-pri"><x-icono name="shopping-cart"/> Volver a vender</a>
    </div>
    @if(session('ok'))<p role="status" class="mb-4 text-sm text-emerald-700">{{ session('ok') }}</p>@endif
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($pendientes as $pendiente)
            @php $datos = $pendiente->contenido; @endphp
            <article class="bg-white rounded-xl border border-gray-200 p-4 min-w-0">
                <p class="text-sm text-gray-500">{{ $pendiente->tipo === 'cotizacion' ? 'Cotización' : 'Venta en espera' }} · {{ $pendiente->updated_at->format('d/m/Y H:i') }}</p>
                <h2 class="text-lg font-semibold break-words text-azul-950">{{ $datos['nombre'] }}</h2>
                <p class="text-sm text-gray-600">{{ count($datos['lineas']) }} líneas · {{ $datos['moneda'] }}</p>
                <p class="text-xl font-bold mt-1">{{ $datos['simbolo'] }} {{ number_format($datos['total'], $datos['decimales']) }}</p>
                <div class="flex flex-wrap gap-2 mt-4">
                    @if($pendiente->venta_id)
                        <span class="btn btn-sec"><x-icono name="check"/> Cobrada</span>
                    @elseif($datos['moneda'] !== $cfg->moneda)
                        <span class="text-sm text-gray-600">Moneda distinta de la actual</span>
                    @else
                        <a class="btn btn-pri" href="{{ route('pos.vender', ['pendiente' => $pendiente->id]) }}"><x-icono name="shopping-cart"/> Retomar</a>
                    @endif
                    @if($pendiente->tipo === 'cotizacion')
                        <a class="btn btn-sec" href="{{ route('pos.pendientes.pdf', $pendiente->id) }}"><x-icono name="file-down"/> PDF</a>
                    @endif
                    @unless($pendiente->venta_id)
                        <form method="POST" action="{{ route('pos.pendientes.descartar', $pendiente->id) }}" onsubmit="return confirm('¿Descartar este pendiente?');">
                            @csrf
                            <button type="submit" class="btn btn-sec"><x-icono name="trash-2"/> Descartar</button>
                        </form>
                    @endunless
                </div>
            </article>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 p-6 text-gray-600 md:col-span-2 xl:col-span-3">Todavía no hay ventas en espera ni cotizaciones. Agréguelas desde el carrito de venta.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $pendientes->links() }}</div>
</x-layout>
