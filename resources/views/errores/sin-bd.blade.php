<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Base de datos no disponible</title>
<link rel="stylesheet" href="{{ asset('css/tema.css') }}"></head>
<body class="min-h-screen flex items-center justify-center p-4" style="background:#0B3C74">
<div class="bg-white rounded-2xl p-8 max-w-lg w-full shadow-2xl">
<h1 class="text-xl font-bold text-[#061a33] mb-2">No se pudo conectar a la base de datos</h1>
<p class="text-sm text-gray-600 mb-3">Revise los datos <code>DB_*</code> del archivo <code>.env</code> y luego ejecute:</p>
<pre class="bg-gray-100 rounded-lg p-3 text-sm overflow-auto">php artisan migrate --force</pre>
@if (config('app.debug'))<p class="text-xs text-gray-400 mt-3 break-words">Detalle: {{ $error }}</p>@endif
</div></body></html>
