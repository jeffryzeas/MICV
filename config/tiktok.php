<?php
return [
    'directorio'=>storage_path('app/private/tiktok'),
    'python'=>env('TIKTOK_PYTHON',storage_path('app/tiktok-venv/bin/python')),
    'simultaneos'=>max(1,min(10,(int)env('TIKTOK_SIMULTANEOS',3))),
    'zona'=>env('TIKTOK_ZONA','America/Costa_Rica'),
    'intervalo'=>max(60,(int)env('TIKTOK_INTERVALO',90)),
];
