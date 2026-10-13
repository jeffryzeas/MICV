<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $titulo }} · {{ $cfg->nombre_negocio }}</title>
<link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ @filemtime(public_path('css/tema.css')) }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<style>
.legal h1{font-size:1.6rem;font-weight:800;color:#061a33;margin:0 0 .5rem}
.legal h2{font-size:1.1rem;font-weight:700;color:{{ $cfg->color_primario }};margin:1.6rem 0 .5rem}
.legal p,.legal li{color:#374151;line-height:1.7;font-size:.95rem}
.legal ol,.legal ul{padding-left:1.4rem;margin:.4rem 0} .legal ol{list-style:decimal} .legal ul{list-style:disc}
.legal a{color:{{ $cfg->color_primario }};text-decoration:underline}
.legal table{width:100%;border-collapse:collapse;font-size:.9rem;margin:.8rem 0}.legal th,.legal td{border:1px solid #e5e7eb;padding:8px;text-align:left}.legal th{background:#f9fafb}
.legal strong{color:#111827}
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <a href="{{ url('/') }}" class="font-bold text-[#061a33] flex items-center gap-2">
            <span class="size-8 rounded-lg flex items-center justify-center text-sm font-bold" style="background:{{ $cfg->color_secundario }};color:{{ $cfg->color_primario }}">{{ mb_strtoupper(mb_substr($cfg->nombre_negocio, 0, 1)) }}</span>
            {{ $cfg->nombre_negocio }}
        </a>
        <nav class="flex flex-wrap gap-3 text-xs font-semibold">
            @foreach(\App\Support\Legal::DOCUMENTOS as $k => [$t])
                <a href="{{ route('legal.ver', $k) }}" class="{{ $k === $doc ? 'text-[#0B3C74] underline' : 'text-gray-500 hover:text-[#0B3C74]' }}">{{ \Illuminate\Support\Str::before($t, ' (') }}</a>
            @endforeach
        </nav>
    </div>
</header>
<main class="max-w-3xl mx-auto px-4 py-8">
    <article class="legal bg-white rounded-xl shadow p-6 sm:p-10">{!! $html !!}</article>
    <p class="text-xs text-gray-400 mt-4 text-center">Versión {{ $cfg->legal_version }} · <a href="{{ route('eliminar.mostrar') }}" class="underline">Solicitar eliminación de cuenta</a></p>
</main>
</body>
</html>
