{{-- Páginas de error: no dependen de la base de datos (funcionan aunque falle). --}}
@php
    try { $cfgE = \App\Models\Configuracion::actualSegura(); } catch (\Throwable $e) { $cfgE = null; }
    $marcaE = $cfgE?->nombre_negocio ?: config('app.name', 'Mi CV');
    $pri = $cfgE?->color_primario ?: '#0B3C74';
    $sec = $cfgE?->color_secundario ?: '#C9971F';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $titulo }} · {{ $marcaE }}</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:{{ $pri }};font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937}
.c{background:#fff;border-radius:18px;max-width:440px;width:100%;padding:34px 28px;text-align:center;box-shadow:0 12px 34px rgba(0,0,0,.28)}
.ico{font-size:46px;line-height:1;margin-bottom:10px}.cod{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.12em;color:{{ $sec }};background:#fbf6ea;border-radius:20px;padding:4px 12px;margin-bottom:12px}
h1{font-size:22px;margin:0 0 8px;color:#0f172a}p{margin:0 0 18px;color:#4b5563;line-height:1.55;font-size:15px}
a.b,button.b{display:inline-block;background:{{ $pri }};color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:10px;border:0;cursor:pointer}
a.b:hover,button.b:hover{background:{{ $sec }}}.l{display:block;margin-top:14px;color:#6b7280;font-size:13px}
.m{color:rgba(255,255,255,.8);text-align:center;margin-top:16px;font-size:13px}
</style>
</head>
<body>
<div>
<div class="c">
    <div class="ico">{{ $icono }}</div>
    <div class="cod">ERROR {{ $codigo }}</div>
    <h1>{{ $titulo }}</h1>
    <p>{!! $mensaje !!}</p>
    <a class="b" href="{{ url('/') }}">Ir al inicio</a>
    <a class="l" href="javascript:history.back()">← Volver atrás</a>
</div>
<div class="m">{{ $marcaE }}</div>
</div>
</body>
</html>
