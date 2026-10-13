@props(['titulo' => 'Mi CV', 'ancho' => 'max-w-sm'])
@php
    $config = \App\Models\Configuracion::actualSegura();
    $nombre = $config?->nombre_negocio ?? 'Mi CV';
    $logo = ($config && \App\Support\Archivos::existe($config->logo)) ? route('marca.logo') : null;
    $pri = $config?->color_primario ?? '#0B3C74';
    $sec = $config?->color_secundario ?? '#C9971F';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<title>{{ $titulo }} · {{ $nombre }}</title>
<meta name="theme-color" content="{{ $pri }}">
@include('partials.head-app')
<link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ @filemtime(public_path('css/tema.css')) }}">
<script src="{{ asset('vendor/lucide.min.js') }}"></script>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4" style="background-color:{{ $pri }};">
<div class="w-full {{ $ancho }}">
    <div class="flex flex-col items-center mb-7">
        @if($logo)
            <img src="{{ $logo }}" class="size-20 rounded-2xl object-contain bg-white p-1.5 mb-4" style="box-shadow:0 6px 18px rgba(0,0,0,.25);" alt="">
        @else
            <div class="size-20 rounded-2xl flex items-center justify-center font-bold text-3xl mb-4"
                 style="background-color:{{ $sec }}; color:{{ $pri }}; box-shadow:0 6px 18px rgba(0,0,0,.25);">
                {{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}
            </div>
        @endif
        <h1 class="text-white text-2xl font-bold tracking-tight text-center">{{ $nombre }}</h1>
        @if($config?->eslogan)<p class="text-white/70 text-sm text-center mt-1">{{ $config->eslogan }}</p>@endif
        <div class="w-12 h-1 rounded-full mt-3" style="background-color:{{ $sec }};"></div>
    </div>

    <div class="bg-white rounded-2xl p-8" style="box-shadow:0 10px 30px rgba(0,0,0,.28);">
        @if (session('aviso'))
            <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 text-sm">{{ session('aviso') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2">
                <i data-lucide="alert-circle" class="size-4 mt-0.5 shrink-0"></i>
                <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            </div>
        @endif
        @if (session('mensaje'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('mensaje') }}</div>
        @endif
        {{ $slot }}
    </div>

    @include('partials.pie-legal', ['oscuro' => true])
</div>
<script>lucide.createIcons();</script>
</body>
</html>
