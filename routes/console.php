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
    $this->info('Borrados → auditoría: ' . $r['auditoria'] . ' · códigos: ' . $r['codigos'] . ' · registros sin verificar: ' . $r['pendientes'] . ' · autoservicio: ' . $r['autoservicio']);
})->purpose('Borra datos vencidos según la política de privacidad');

\Illuminate\Support\Facades\Schedule::command('micv:limpiar')->dailyAt('03:15');

Artisan::command('micv:video-promocional {--salida=Mi-CV-Tutorial.mp4}', function () {
    $salida = (string) $this->option('salida');
    if (!str_ends_with(strtolower($salida), '.mp4')) {
        $this->error('La salida debe terminar en .mp4.');
        return 1;
    }
    $salida = str_starts_with($salida, DIRECTORY_SEPARATOR) ? $salida : base_path($salida);
    if (file_exists($salida)) {
        $this->error('El archivo ya existe. Elija otro nombre.');
        return 1;
    }
    $carpeta = dirname($salida).'/.micv-video-'.\Illuminate\Support\Str::uuid();
    \Illuminate\Support\Facades\File::ensureDirectoryExists($carpeta, 0700);
    try {
        app(\App\Services\VideoPromocional::class)->generar($carpeta.'/video.mp4');
        if (!\Illuminate\Support\Facades\File::move($carpeta.'/video.mp4', $salida)) {
            $this->error('No se pudo guardar el archivo.');
            return 1;
        }
        $this->info($salida);
    } finally {
        \Illuminate\Support\Facades\File::deleteDirectory($carpeta);
    }
    return 0;
})->purpose('Genera el tutorial de Mi CV con la interfaz actual y una cuenta ficticia');

\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\VideosPromocionales::class)->limpiar())
    ->name('videos-promocionales-limpiar')->dailyAt('03:30');
