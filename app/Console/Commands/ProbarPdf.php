<?php

namespace App\Console\Commands;

use App\Services\CvPdf;
use Illuminate\Console\Command;

/**
 * php artisan micv:probar-pdf
 * Verifica en el VPS que Node + Puppeteer + Chromium generan PDF.
 */
class ProbarPdf extends Command
{
    protected $signature = 'micv:probar-pdf';

    protected $description = 'Genera un PDF de prueba para confirmar que Chromium funciona';

    public function handle(): int
    {
        $cfg = config('micv.pdf');
        $this->line('Node:         ' . ($cfg['node_binary'] ?: trim((string) @shell_exec('command -v node')) ?: 'NO ENCONTRADO'));
        $this->line('Chromium:     ' . ($cfg['chrome_path'] ?: '(el que trae Puppeteer)'));
        $this->line('node_modules: ' . $cfg['node_modules'] . (is_dir($cfg['node_modules'] . '/puppeteer') ? '  ✔ puppeteer' : '  ✘ falta puppeteer (npm install)'));

        try {
            $pdf = CvPdf::desdeHtml('<!doctype html><html><body style="font-family:sans-serif;padding:40px"><h1>Mi CV — prueba de PDF ✔</h1><p>Áéíóú ñ — ' . now() . '</p></body></html>');
        } catch (\Throwable $e) {
            $this->error('FALLÓ la generación del PDF:');
            $this->line($e->getMessage());
            return self::FAILURE;
        }

        $ruta = storage_path('app/prueba-micv.pdf');
        file_put_contents($ruta, $pdf);
        $this->info('PDF generado correctamente (' . number_format(strlen($pdf)) . " bytes): $ruta");

        return self::SUCCESS;
    }
}
