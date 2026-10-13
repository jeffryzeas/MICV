<x-publico titulo="¡Listo!" ancho="max-w-sm">
    <div class="text-center">
        <div class="mx-auto size-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center"><x-icono name="check" class="size-9" /></div>
        <h2 class="text-xl font-bold text-azul-950 mt-4">¡Gracias{{ $nombre ? ', ' . $nombre : '' }}!</h2>
        <p class="text-gray-500 mt-1">Recibimos sus datos en <b>{{ $cyber->nombre }}</b>.</p>
        <div class="mt-6 rounded-2xl bg-azul-800 text-white py-6">
            <p class="text-xs uppercase tracking-[.25em] text-white/70 font-semibold">Su turno</p>
            <p class="text-7xl font-black tabular-nums leading-none mt-2">{{ $turno }}</p>
        </div>
        <p class="text-sm text-gray-600 mt-5">Diga este número en el mostrador. Ahí revisamos sus datos, le tomamos la foto si la necesita e imprimimos su CV.</p>
        <p class="text-xs text-gray-400 mt-4">Puede cerrar esta página.</p>
    </div>
</x-publico>
