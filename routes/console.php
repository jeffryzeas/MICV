<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza diaria (auditoría de más de 12 meses, códigos vencidos, registros sin verificar).
// En el VPS agregue al cron:  * * * * * cd /var/www/mi-cv && php artisan schedule:run >> /dev/null 2>&1
Artisan::command('micv:limpiar', function () {
    $r = \App\Support\Limpieza::ejecutar();
    $this->info('Borrados → auditoría: ' . $r['auditoria'] . ' · códigos: ' . $r['codigos'] . ' · registros sin verificar: ' . $r['pendientes']);
})->purpose('Borra datos vencidos según la política de privacidad');

\Illuminate\Support\Facades\Schedule::command('micv:limpiar')->dailyAt('03:15');
