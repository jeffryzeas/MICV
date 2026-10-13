<x-layout titulo="Países y documentos" activo="paises">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <p class="text-gray-500 text-sm max-w-2xl">Aquí puede crear países nuevos con su escudo y sus documentos. En los que ya vienen con el sistema puede cambiar el escudo, agregar tipos de documento o desactivarlos (dejan de salir en el registro).</p>
        <a href="{{ route('admin.paises.create') }}" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="plus" class="size-4"></i> Nuevo país</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($paises as $cod => $p)
            @continue($cod === 'OT')
            @php $tieneEscudo = !empty($p['escudo']) || !empty($p['escudo_subido']); $activo = $p['activo'] ?? true; @endphp
            <a href="{{ route('admin.paises.edit', $cod) }}" class="group bg-white rounded-xl shadow hover:shadow-lg transition p-4 flex gap-4 items-center {{ $activo ? '' : 'opacity-60' }}">
                <div class="size-16 shrink-0 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden">
                    @if($tieneEscudo)<img src="{{ route('admin.paises.escudo', $cod) }}" class="max-h-14 max-w-14 object-contain" alt="Escudo" loading="lazy">@else<span class="text-3xl">{{ $p['bandera'] }}</span>@endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-azul-950 flex items-center gap-2">{{ $p['bandera'] }} {{ $p['nombre'] }}
                        @if(!empty($p['personalizado']))<span class="text-[10px] font-bold uppercase bg-blue-50 text-azul-800 rounded px-1.5 py-0.5">Creado</span>@endif
                        @unless($activo)<span class="text-[10px] font-bold uppercase bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">Inactivo</span>@endunless
                    </div>
                    <div class="text-xs text-gray-500 truncate">{{ implode(' · ', $p['documentos']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">{{ $p['telefono'] ?: '—' }} · {{ $uso[$cod] ?? 0 }} persona(s){{ $tieneEscudo ? '' : ' · sin escudo' }}</div>
                </div>
                <i data-lucide="chevron-right" class="size-4 text-gray-300 group-hover:text-azul-800"></i>
            </a>
        @endforeach
    </div>
</x-layout>
