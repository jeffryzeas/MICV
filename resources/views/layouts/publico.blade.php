@php $cfgMarca = \App\Models\Configuracion::actualSegura(); @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · {{ $cfgMarca?->nombre_negocio ?? 'Mi CV' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @if ($cfgMarca)
    <style>:root{--pri:{{ $cfgMarca->color_primario }};--acento:{{ $cfgMarca->color_secundario }}}
    .login-fondo{background:linear-gradient(160deg,{{ $cfgMarca->color_primario }},#06172d)}</style>
    @endif
</head>
<body>
<div class="login-fondo">
    <div class="login-caja @yield('ancho')">
        <div class="cab">
            @if ($cfgMarca && \App\Support\Archivos::existe($cfgMarca->logo))
                <img src="{{ route('marca.logo') }}" alt=""><br>
            @else
                <div class="logo-ini">CV</div>
            @endif
            <h1 style="margin-top:8px">@yield('encabezado')</h1>
            <div class="muted small">@yield('bajada')</div>
        </div>
        @include('partials.alertas')
        @yield('contenido')
    </div>
</div>
</body>
</html>
