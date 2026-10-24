<?php

/*
| Configuración del motor de PDF (Chromium + Node vía Browsershot).
| Todo se ajusta desde el .env — ver README.
*/
return [
    'pdf' => [
        // Ruta al ejecutable de Chromium/Chrome. Vacío = el que trae Puppeteer.
        'chrome_path'  => env('MICV_CHROME_PATH', ''),
        // Rutas a node y npm (vacío = se buscan en el PATH).
        'node_binary'  => env('MICV_NODE_BINARY', ''),
        'npm_binary'   => env('MICV_NPM_BINARY', ''),
        // Carpeta node_modules donde está puppeteer (por defecto la del proyecto).
        'node_modules' => env('MICV_NODE_MODULES', base_path('node_modules')),
        // En VPS corriendo como root o en contenedores hace falta --no-sandbox.
        'no_sandbox'   => (bool) env('MICV_CHROME_NO_SANDBOX', true),
        'timeout'      => (int) env('MICV_PDF_TIMEOUT', 60),
    ],

    // Quitar el fondo de las fotos carné (modelo U²-Net, se descarga con: php artisan micv:modelo-fondo).
    'fondo' => [
        'perfil' => env('MICV_FONDO_PERFIL', 'actual'),
        'modelo'  => env('MICV_MODELO_FONDO', storage_path('app/modelos/u2net_human_seg.onnx')),
        'url'     => env('MICV_MODELO_FONDO_URL', 'https://github.com/danielgatis/rembg/releases/download/v0.0.0/u2net_human_seg.onnx'),
        'timeout' => (int) env('MICV_FONDO_TIMEOUT', 120),
    ],
];
