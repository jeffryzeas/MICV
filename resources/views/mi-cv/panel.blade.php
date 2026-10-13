@php
    $p = $cliente->datosPais();
    $faltan = array_filter($pendientes, fn ($x) => !$x[1]);
    $siguiente = $faltan ? reset($faltan) : null;
    $radio = 34; $circ = 2 * M_PI * $radio; $dash = $circ * $avance / 100;
@endphp
<x-layout titulo="Mi panel" activo="mi-panel">
    {{-- Bienvenida + avance --}}
    <section class="relative overflow-hidden rounded-2xl shadow mb-5 text-white" style="background: linear-gradient(135deg, rgb(var(--jz-azul-800)) 0%, #06264a 100%);">
        <div class="absolute -right-16 -top-16 size-56 rounded-full opacity-10" style="background: rgb(var(--jz-oro-500));"></div>
        <div class="absolute right-24 -bottom-20 size-40 rounded-full opacity-10" style="background: rgb(var(--jz-oro-500));"></div>
        <div class="relative p-5 sm:p-7 flex flex-col sm:flex-row items-center gap-5">
            @if($cliente->tieneFoto())
                <img src="{{ route('clientes.foto', $cliente) }}" class="size-24 rounded-full object-cover ring-4 ring-white/80 shrink-0" alt="Mi foto">
            @else
                <a href="{{ route('mi-cv.editar') }}#sec-foto" title="Agregar foto" class="size-24 shrink-0 rounded-full bg-white/10 border-2 border-dashed border-white/50 text-white/80 hover:bg-white/20 flex flex-col items-center justify-center">
                    <x-icono name="user-round" class="size-10" /><span class="text-[10px] font-semibold uppercase">Sin foto</span>
                </a>
            @endif
            <div class="flex-1 min-w-0 text-center sm:text-left">
                <p class="text-white/70 text-sm">{{ $cliente->nacionalidad ?: $p['nombre'] }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold leading-tight inline-flex items-center gap-2 justify-center sm:justify-start flex-wrap">Hola, {{ $cliente->primer_nombre }}
                    @if(auth()->user()->verificado())<span class="inline-flex items-center gap-1 bg-white/95 text-[#0f5fa8] text-xs font-semibold rounded-full pl-1 pr-2.5 py-0.5 shadow">@include('partials.verificado', ['tam' => 18]) Verificado</span>@endif
                </h1>
                <p class="text-white/80 mt-0.5">{{ $cliente->profesion ?: 'Agregue su profesión u oficio para que su CV destaque' }}</p>
                @if($siguiente)
                    <a href="{{ route('mi-cv.editar') }}#{{ $siguiente[2] }}" class="mt-4 inline-flex items-center gap-2 bg-white text-azul-800 hover:bg-oro-500 hover:text-white font-semibold text-sm px-4 py-2.5 rounded-lg transition-colors">
                        <x-icono name="arrow-right-circle" class="size-4" /> Siguiente paso: {{ mb_strtolower($siguiente[0]) }}
                    </a>
                @else
                    <a href="#generar-cv" class="mt-4 inline-flex items-center gap-2 bg-oro-500 text-white hover:bg-white hover:text-azul-800 font-semibold text-sm px-4 py-2.5 rounded-lg transition-colors">
                        <x-icono name="party-popper" class="size-4" /> ¡Su CV está completo! Descárguelo
                    </a>
                @endif
            </div>
            <div class="relative shrink-0" title="Su CV está completo al {{ $avance }}%">
                <svg width="92" height="92" viewBox="0 0 92 92" class="-rotate-90">
                    <circle cx="46" cy="46" r="{{ $radio }}" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="9"/>
                    <circle cx="46" cy="46" r="{{ $radio }}" fill="none" stroke="rgb(var(--jz-oro-500))" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="{{ round($dash, 1) }} {{ round($circ, 1) }}"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center"><span class="text-xl font-bold">{{ $avance }}%</span><span class="text-[10px] text-white/70 -mt-0.5">completo</span></div>
            </div>
        </div>
    </section>

    {{-- Lo que tiene y lo que falta --}}
    <section class="bg-white rounded-xl shadow p-4 sm:p-5 mb-5">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="font-semibold text-azul-950 flex items-center gap-2"><x-icono name="list-checks" class="size-[18px] text-oro-500" /> Su CV paso a paso</h2>
            <span class="text-xs text-gray-500">{{ count($pendientes) - count($faltan) }} de {{ count($pendientes) }} listos</span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($pendientes as [$texto, $hecho, $ancla])
                <a href="{{ route('mi-cv.editar') }}#{{ $ancla }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold border transition-colors {{ $hecho ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-white border-gray-300 text-gray-600 hover:border-azul-800 hover:text-azul-800' }}">
                    <i data-lucide="{{ $hecho ? 'check-circle-2' : 'circle-dashed' }}" class="size-3.5"></i> {{ $texto }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- Accesos rápidos --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 mb-5">
        @foreach([
            [route('mi-cv.editar'), 'user-round-pen', 'Mis datos', 'Foto, documento, estudios…', 'bg-blue-50 text-azul-800'],
            ['#generar-cv', 'file-text', 'Mi CV en PDF', count(\App\Services\CvDatos::DISENOS) . ' diseños · ES / EN', 'bg-amber-50 text-amber-700'],
            [route('cartas.index'), 'mail', 'Mis cartas', 'Presentación, solicitud…', 'bg-emerald-50 text-emerald-700'],
            [route('tarjetas.mia'), 'contact', 'Mi tarjeta', '20 diseños · QR a WhatsApp', 'bg-violet-50 text-violet-700'],
            [route('mi-cuenta.mostrar'), 'shield-check', 'Mi cuenta', 'Privacidad y datos', 'bg-gray-100 text-gray-700'],
        ] as [$url, $icono, $titulo, $sub, $color])
            <a href="{{ $url }}" class="group bg-white rounded-xl shadow p-4 hover:shadow-lg hover:-translate-y-0.5 transition">
                <span class="size-11 rounded-xl {{ $color }} flex items-center justify-center mb-3 group-hover:scale-105 transition"><i data-lucide="{{ $icono }}" class="size-5"></i></span>
                <div class="font-semibold text-azul-950 leading-tight">{{ $titulo }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ $sub }}</div>
            </a>
        @endforeach
    </div>

    @include('partials.generador-cv', ['cliente' => $cliente, 'cv' => $cv, 'paletas' => $paletas, 'cyberColores' => null])
</x-layout>
