@php
    $yo = auth()->user();
    $h = (int) now()->format('G');
    $saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
    $nombreCorto = \Illuminate\Support\Str::of($yo->name)->explode(' ')->first();
    $maxSerie = max(1, max($serieClientes), max($serieCv));
    $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5';
@endphp
<x-layout :titulo="$yo->esAdmin() ? 'Dashboard' : 'Panel del Cyber'" activo="dashboard">

    {{-- Bienvenida + búsqueda rápida --}}
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-azul-950 via-azul-800 to-[#1d5fa8] text-white p-5 sm:p-7 shadow-lg">
        <div class="absolute -right-16 -top-20 size-64 rounded-full bg-white/5"></div>
        <div class="absolute right-24 -bottom-24 size-56 rounded-full bg-oro-500/15"></div>
        <div class="relative flex flex-col lg:flex-row lg:items-end gap-5 justify-between">
            <div>
                <p class="text-white/70 text-sm">{{ ucfirst(now()->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mt-1">{{ $saludo }}, {{ \Illuminate\Support\Str::title(mb_strtolower($nombreCorto)) }} 👋</h2>
                <p class="text-white/80 mt-1 text-sm sm:text-base">
                    @if($yo->esAdmin()) Así va toda la plataforma este mes.
                    @elseif($yo->cyber) <b class="text-amber-200">{{ $yo->cyber->nombre }}</b> · ¿A quién atendemos hoy?
                    @endif
                </p>
            </div>
            <form action="{{ route('clientes.index') }}" method="GET" class="w-full lg:w-[420px]">
                <label for="buscaRapida" class="sr-only">Buscar cliente</label>
                <div class="flex items-center bg-white rounded-xl p-1.5 shadow-md">
                    <i data-lucide="search" class="size-5 text-gray-400 ml-2.5 shrink-0"></i>
                    <input id="buscaRapida" name="q" type="search" placeholder="Buscar por nombre o documento…" class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 text-gray-800 text-sm px-2.5 py-2">
                    <button class="bg-oro-500 hover:bg-oro-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Buscar</button>
                </div>
            </form>
        </div>
    </section>

    {{-- Acciones rápidas --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
        @foreach(array_filter([
            [route('clientes.create'), 'user-plus', 'Registrar cliente', 'Ficha nueva y su CV', 'from-azul-800 to-[#1d5fa8]', true],
            [route('clientes.index'), 'users', 'Mis clientes', $totalClientes . ' en total', 'from-emerald-500 to-teal-600', false],
            [route('cartas.index'), 'mail', 'Cartas', 'Recomendación, trabajo…', 'from-violet-500 to-purple-600', false],
            $yo->esAdmin() ? [route('admin.cybers.create'), 'building-2', 'Registrar Cyber', 'Y créele sus usuarios', 'from-oro-500 to-amber-600', false]
                           : [route('marca.edit'), 'palette', 'Mi marca', 'Logo y colores en los CV', 'from-oro-500 to-amber-600', false],
        ]) as [$url, $ic, $tx, $sub, $grad, $principal])
            <a href="{{ $url }}" class="group {{ $card }} p-4 flex items-center gap-3 hover:-translate-y-0.5 hover:shadow-md transition">
                <span class="size-11 shrink-0 rounded-xl bg-gradient-to-br {{ $grad }} text-white flex items-center justify-center shadow-sm"><i data-lucide="{{ $ic }}" class="size-5"></i></span>
                <span class="min-w-0"><span class="block font-semibold text-azul-950 text-sm leading-tight">{{ $tx }}</span><span class="block text-xs text-gray-500 truncate">{{ $sub }}</span></span>
                <i data-lucide="chevron-right" class="size-4 text-gray-300 ml-auto group-hover:text-azul-800 hidden sm:block"></i>
            </a>
        @endforeach
    </section>

    {{-- Números del mes --}}
    <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mt-5">
        @foreach(array_filter([
            ['users', 'Clientes', $totalClientes, $clientesActivos . ' activos', 'text-azul-800 bg-blue-50'],
            ['user-plus', 'Nuevos este mes', $clientesMes, 'registrados', 'text-emerald-700 bg-emerald-50'],
            ['file-text', 'CV este mes', $cvMes, 'descargados', 'text-violet-700 bg-violet-50'],
            ['mail', 'Cartas este mes', $cartasMes, 'generadas', 'text-sky-700 bg-sky-50'],
            ['contact', 'Tarjetas este mes', $tarjetasMes, 'exportadas', 'text-oro-700 bg-amber-50'],
            $yo->esAdmin() ? ['user-check', 'Se registraron solos', $autoRegistrados, 'con su cuenta', 'text-indigo-700 bg-indigo-50']
                           : ['send', 'Enviados por correo', $correosMes, 'CV y tarjetas', 'text-pink-700 bg-pink-50'],
        ]) as [$ic, $tx, $n, $sub, $col])
            <div class="{{ $card }} p-4">
                <div class="flex items-center justify-between"><span class="text-[11px] uppercase font-bold tracking-wide text-gray-500">{{ $tx }}</span><span class="size-8 rounded-lg {{ $col }} flex items-center justify-center"><i data-lucide="{{ $ic }}" class="size-4"></i></span></div>
                <div class="text-3xl font-bold text-azul-950 mt-2 tabular-nums">{{ number_format($n) }}</div>
                <div class="text-xs text-gray-400">{{ $sub }}</div>
            </div>
        @endforeach
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mt-5">
        {{-- Últimos clientes --}}
        <section class="{{ $card }} p-4 sm:p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2"><i data-lucide="users" class="size-[18px] text-oro-500"></i> Últimos clientes</h2>
                <a href="{{ route('clientes.index') }}" class="text-xs font-semibold text-azul-800 hover:text-oro-700">Ver todos →</a>
            </div>
            <ul class="divide-y divide-gray-100">
            @forelse($ultimosClientes as $c)
                @php $pc = $c->porcentajeCompleto(); @endphp
                <li class="py-3 flex items-center gap-3">
                    <a href="{{ route('clientes.show', $c) }}" class="shrink-0">
                        @if($c->tieneFoto())
                            <img src="{{ route('clientes.foto', $c) }}" alt="" loading="lazy" class="size-11 rounded-full object-cover ring-2 ring-white shadow">
                        @else
                            <span class="size-11 rounded-full bg-gradient-to-br from-azul-800 to-[#1d5fa8] text-white font-semibold text-sm flex items-center justify-center">{{ $c->iniciales() }}</span>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('clientes.show', $c) }}" class="font-medium text-azul-950 hover:text-azul-800 truncate block">{{ $c->nombreCompleto() }}</a>
                        <div class="text-xs text-gray-500 flex flex-wrap gap-x-3"><span class="font-mono">{{ $c->documentoFormateado() }}</span>@if($c->municipio_nacimiento)<span>{{ $c->municipio_nacimiento }}</span>@endif<span>{{ $c->created_at?->locale('es')->diffForHumans() }}</span></div>
                        <div class="mt-1.5 flex items-center gap-2 max-w-[220px]"><span class="h-1.5 flex-1 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full rounded-full {{ $pc >= 80 ? 'bg-emerald-500' : ($pc >= 50 ? 'bg-oro-500' : 'bg-red-400') }}" style="width: {{ $pc }}%"></span></span><span class="text-[11px] text-gray-500 tabular-nums">{{ $pc }}%</span></div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <a href="{{ route('cv.pdf', $c) }}" title="Descargar CV" class="size-9 rounded-lg hover:bg-gray-100 text-azul-800 flex items-center justify-center"><i data-lucide="file-down" class="size-[18px]"></i></a>
                        <a href="{{ route('tarjetas.editar', $c) }}" title="Tarjeta de presentación" class="size-9 rounded-lg hover:bg-gray-100 flex items-center justify-center {{ in_array($c->id, $conTarjeta) ? 'text-oro-700' : 'text-azul-800' }}"><i data-lucide="contact" class="size-[18px]"></i></a>
                        <a href="{{ route('clientes.edit', $c) }}" title="Editar ficha" class="hidden sm:flex size-9 rounded-lg hover:bg-gray-100 text-azul-800 items-center justify-center"><i data-lucide="pencil" class="size-[18px]"></i></a>
                    </div>
                </li>
            @empty
                <li class="py-10 text-center">
                    <i data-lucide="user-plus" class="size-10 text-gray-300 mx-auto"></i>
                    <p class="text-gray-500 mt-2">Todavía no hay clientes.</p>
                    <a href="{{ route('clientes.create') }}" class="inline-block mt-3 bg-azul-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Registrar el primero</a>
                </li>
            @endforelse
            </ul>
        </section>

        <div class="space-y-5">
            {{-- Gráfica de 6 meses --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-1"><i data-lucide="chart-column" class="size-[18px] text-oro-500"></i> Últimos 6 meses</h2>
                <div class="flex gap-4 text-[11px] text-gray-500 mb-3"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-azul-800"></span>Clientes nuevos</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-oro-500"></span>CV descargados</span></div>
                <div class="flex items-end gap-2 h-36" role="img" aria-label="Clientes nuevos y CV descargados por mes">
                    @foreach($meses as $i => $m)
                        <div class="flex-1 flex flex-col items-center gap-1 h-full">
                            <div class="flex-1 w-full flex items-end justify-center gap-0.5">
                                <span title="{{ $serieClientes[$i] }} clientes" class="w-1/3 max-w-3 rounded-t bg-azul-800" style="height: {{ max(3, round($serieClientes[$i] / $maxSerie * 100)) }}%"></span>
                                <span title="{{ $serieCv[$i] }} CV" class="w-1/3 max-w-3 rounded-t bg-oro-500" style="height: {{ max(3, round($serieCv[$i] / $maxSerie * 100)) }}%"></span>
                            </div>
                            <span class="text-[10px] text-gray-500">{{ rtrim($m, '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Cumpleaños --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-2"><i data-lucide="cake" class="size-[18px] text-pink-500"></i> Cumplen años hoy</h2>
                @forelse($cumpleaneros as $c)
                    <a href="{{ route('clientes.show', $c) }}" class="flex items-center justify-between py-1.5 text-sm hover:text-azul-800"><span class="truncate">{{ $c->nombreCompleto() }}</span><span class="text-xs text-pink-600 font-semibold shrink-0 ml-2">🎂 {{ $c->edad() }}</span></a>
                @empty
                    <p class="text-sm text-gray-400">Nadie hoy.</p>
                @endforelse
            </section>

            {{-- Fichas por completar --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-2"><i data-lucide="list-todo" class="size-[18px] text-red-400"></i> Fichas por completar</h2>
                @forelse($porCompletar as $c)
                    <a href="{{ route('clientes.edit', $c) }}" class="flex items-center gap-2 py-1.5 text-sm hover:text-azul-800">
                        <span class="truncate flex-1">{{ $c->nombreCompleto() }}</span>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-600 tabular-nums">{{ $c->porcentajeCompleto() }}%</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400 flex items-center gap-1.5"><i data-lucide="party-popper" class="size-4 text-emerald-500"></i> Todas las fichas recientes están completas.</p>
                @endforelse
            </section>
        </div>
    </div>

    @if($yo->esAdmin())
    <section class="{{ $card }} p-4 sm:p-5 mt-5">
        <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-4"><i data-lucide="building-2" class="size-[18px] text-oro-500"></i> Cybers</h2>
        @if($cybers->isEmpty())
            <p class="text-sm text-gray-500">Aún no hay Cybers. <a href="{{ route('admin.cybers.create') }}" class="font-semibold text-azul-800 underline">Registre el primero</a> y luego créele sus usuarios.</p>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($cybers as $cy)
                <a href="{{ route('clientes.index', ['cyber_id' => $cy->id]) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 hover:border-azul-800 p-3 transition">
                    @if($cy->logoUrl())<img src="{{ $cy->logoUrl() }}" alt="" class="size-10 rounded-lg object-contain bg-gray-50">@else<span class="size-10 rounded-lg bg-azul-800/10 text-azul-800 font-bold flex items-center justify-center">{{ mb_substr($cy->nombre, 0, 1) }}</span>@endif
                    <span class="min-w-0 flex-1"><span class="block font-medium text-azul-950 truncate">{{ $cy->nombre }}</span><span class="text-xs text-gray-500">{{ $cy->clientes_count }} clientes · {{ $cy->usuarios_count }} usuarios</span></span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $cy->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $cy->activo ? 'Activo' : 'Pausado' }}</span>
                </a>
            @endforeach
        </div>
        @endif
    </section>
    @endif
</x-layout>
