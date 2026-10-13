@php
    $cfgMarca = \App\Models\Configuracion::actualSegura();
    $yo = auth()->user();
    $act = fn (string $patron) => request()->routeIs($patron) ? 'activo' : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ $cfgMarca?->nombre_negocio ?? 'Mi CV' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @if ($cfgMarca)
    <style>:root{--pri:{{ $cfgMarca->color_primario }};--acento:{{ $cfgMarca->color_secundario }}}</style>
    @endif
</head>
<body>
<div class="app">
    <aside class="side">
        <div class="marca">
            @if ($cfgMarca && \App\Support\Archivos::existe($cfgMarca->logo))
                <img src="{{ route('marca.logo') }}" alt="">
            @else
                <div class="logo-ini">CV</div>
            @endif
            <div><b>{{ $cfgMarca?->nombre_negocio ?? 'Mi CV' }}</b><span class="small" style="opacity:.7">Sistema de currículums</span></div>
        </div>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ $act('dashboard') }}"><x-icono n="inicio"/> Inicio</a>
            <div class="grupo">Clientes</div>
            <a href="{{ route('clientes.create') }}" class="{{ $act('clientes.create') }}"><x-icono n="nuevo"/> Registrar cliente</a>
            <a href="{{ route('clientes.index') }}" class="{{ request()->routeIs('clientes.index','clientes.show','clientes.edit') ? 'activo' : '' }}"><x-icono n="clientes"/> Clientes y CV</a>
            @if ($yo->esAdmin())
                <div class="grupo">Administración</div>
                <a href="{{ route('admin.usuarios.index') }}" class="{{ $act('admin.usuarios.*') }}"><x-icono n="usuarios"/> Usuarios</a>
                <a href="{{ route('admin.configuracion.edit') }}" class="{{ $act('admin.configuracion.*') }}"><x-icono n="config"/> Configuración y CV</a>
                <a href="{{ route('admin.actividad') }}" class="{{ $act('admin.actividad') }}"><x-icono n="actividad"/> Actividad</a>
            @endif
            <div class="grupo">Cuenta</div>
            <a href="{{ route('perfil.edit') }}" class="{{ $act('perfil.*') }}"><x-icono n="perfil"/> Mi perfil</a>
        </nav>
        <div class="yo">
            <div class="av">{{ $yo->iniciales() }}</div>
            <div class="info"><b>{{ $yo->name }}</b><span class="small" style="opacity:.7">{{ $yo->rolLegible() }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button type="submit" title="Cerrar sesión"><x-icono n="salir" width="18" height="18"/></button>
            </form>
        </div>
    </aside>

    <main class="main">
        <div class="top">
            <div style="display:flex;align-items:center;gap:10px">
                <button type="button" class="btn btn-sec btn-sm burger" data-menu><x-icono n="menu"/></button>
                <h1>@yield('titulo', 'Panel')</h1>
            </div>
            <div class="acciones">@yield('acciones')</div>
        </div>
        @include('partials.alertas')
        @yield('contenido')
    </main>
</div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
</body>
</html>
