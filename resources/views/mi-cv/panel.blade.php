@php $p = $cliente->datosPais(); @endphp
<x-layout titulo="Mi panel" activo="mi-panel">
    <div class="bg-white rounded-xl shadow p-6 mb-5">
        <div class="flex flex-col sm:flex-row items-center gap-5">
            @if($cliente->tieneFoto())
                <img src="{{ route('clientes.foto', $cliente) }}" class="size-24 rounded-full object-cover border-4 border-oro-500" alt="">
            @else
                <div class="size-24 rounded-full bg-gradient-to-br from-azul-800 to-azul-400 text-oro-300 flex items-center justify-center text-3xl font-bold">{{ $cliente->iniciales() }}</div>
            @endif
            <div class="flex-1 text-center sm:text-left">
                <h1 class="text-2xl font-bold text-azul-950">Hola, {{ $cliente->primer_nombre }} 👋</h1>
                <p class="text-gray-500">{{ $p['bandera'] }} {{ $cliente->nacionalidad ?: $p['nombre'] }} · {{ $cliente->profesion ?: 'Complete su profesión u oficio' }}</p>
                <div class="mt-3">
                    <div class="flex justify-between text-xs font-semibold text-azul-800 mb-1"><span>Su CV está completo al</span><span>{{ $avance }}%</span></div>
                    <div class="h-3 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-oro-500" style="width: {{ $avance }}%"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <a href="{{ route('mi-cv.editar') }}" class="group bg-white rounded-xl shadow p-5 border-l-4 border-oro-500 hover:shadow-lg">
            <div class="flex items-center gap-3"><span class="size-11 rounded-lg bg-azul-800 text-oro-300 flex items-center justify-center group-hover:bg-oro-500 group-hover:text-white"><i data-lucide="user-round-pen" class="size-5"></i></span>
            <div><div class="font-semibold text-azul-950">1. Mis datos</div><div class="text-xs text-gray-500">Documento, foto, estudios, experiencia…</div></div></div>
        </a>
        <a href="#generar-cv" class="group bg-white rounded-xl shadow p-5 border-l-4 border-emerald-500 hover:shadow-lg">
            <div class="flex items-center gap-3"><span class="size-11 rounded-lg bg-azul-800 text-oro-300 flex items-center justify-center group-hover:bg-oro-500 group-hover:text-white"><i data-lucide="file-text" class="size-5"></i></span>
            <div><div class="font-semibold text-azul-950">2. Mi CV en PDF</div><div class="text-xs text-gray-500">4 diseños, el Pro en una hoja</div></div></div>
        </a>
        <a href="{{ route('cartas.index') }}" class="group bg-white rounded-xl shadow p-5 border-l-4 border-blue-400 hover:shadow-lg">
            <div class="flex items-center gap-3"><span class="size-11 rounded-lg bg-azul-800 text-oro-300 flex items-center justify-center group-hover:bg-oro-500 group-hover:text-white"><i data-lucide="mail" class="size-5"></i></span>
            <div><div class="font-semibold text-azul-950">3. Mis cartas</div><div class="text-xs text-gray-500">Solicitud de empleo, renuncia…</div></div></div>
        </a>
    </div>

    @if(!$cliente->numero_documento || $avance < 60)
        <div class="mb-5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex items-center gap-3">
            <i data-lucide="lightbulb" class="size-5 shrink-0"></i>
            <span>Le falta información para un CV completo{{ !$cliente->numero_documento ? ' (por ejemplo, su número de documento)' : '' }}. <a href="{{ route('mi-cv.editar') }}" class="font-semibold underline">Complétela aquí</a>.</span>
        </div>
    @endif

    @include('partials.generador-cv', ['cliente' => $cliente, 'cv' => $cv, 'paletas' => $paletas, 'cyberColores' => null])
</x-layout>
