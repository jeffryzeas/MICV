<x-layout titulo="Cartas" activo="cartas">
    <p class="text-gray-500 mb-5">Elija la carta. Cada una tiene 4 modelos de redacción, se adapta al sexo de la persona y a la ley de su país (Nicaragua o Costa Rica) y sale en PDF lista para imprimir.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($tipos as $k => $t)
            <a href="{{ route('cartas.create', $k) }}{{ request('cliente') ? '?cliente=' . request('cliente') : '' }}" class="group bg-white rounded-xl shadow p-5 border-l-4 border-oro-500 hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-3">
                    <span class="size-11 rounded-lg bg-azul-800 text-oro-300 flex items-center justify-center group-hover:bg-oro-500 group-hover:text-white transition-colors">
                        <i data-lucide="{{ \App\Services\CartaGenerator::ICONOS[$k] ?? 'file-text' }}" class="size-5"></i>
                    </span>
                    <div>
                        <div class="font-semibold text-azul-950">{{ $t }}</div>
                        <div class="text-xs text-gray-500">{{ \App\Services\CartaGenerator::DESCRIPCION[$k] ?? '' }}</div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-layout>
