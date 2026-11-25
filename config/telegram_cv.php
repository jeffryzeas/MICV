<?php

return [
    'python' => env('TELEGRAM_CV_PYTHON', storage_path('app/telegram-cv-venv/bin/python')),
    'directorio' => storage_path('app/private/telegram-cv'),
];
