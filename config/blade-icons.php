<?php

return [
    'components' => [
        // Desktop views use x-icono and svg(), so no per-icon Blade aliases are needed.
        'disabled' => strlen((string) getenv('MICV_LOCAL_TOKEN')) >= 32,
        'default' => 'icon',
    ],
];
