<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CvPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ComprobarServidor extends Command
{
    protected $signature = 'micv:comprobar-servidor {--pdf} {--admin}';

    protected $description = 'Comprueba la base de datos, permisos y motor de PDF sin enviar mensajes';

    public function handle(): int
    {
        if ($this->option('admin')) {
            return User::where('rol', 'admin')->where('activo', true)->exists() ? 0 : 1;
        }
        $ok = true;
        try {
            DB::connection()->getPdo();
            $this->info('Base de datos: disponible.');
        } catch (\Throwable) {
            $this->error('Base de datos: revise la conexión de .env.');
            $ok = false;
        }
        foreach ([storage_path(), storage_path('app/private'), base_path('bootstrap/cache')] as $p) {
            if (! is_writable($p)) {
                $this->error('Directorio sin escritura: '.$p);
                $ok = false;
            }
        }
        foreach (['curl', 'mbstring', 'dom', 'zip', 'fileinfo', 'openssl', 'pdo'] as $e) {
            if (! extension_loaded($e)) {
                $this->error('Falta extensión PHP: '.$e);
                $ok = false;
            }
        }
        if ($this->option('pdf')) {
            try {
                $b = CvPdf::desdeHtml('<!doctype html><html><body><p>Comprobación de Mi CV</p></body></html>');
                if (! str_starts_with($b, '%PDF-')) {
                    throw new \RuntimeException;
                }$this->info('Chromium: PDF generado en memoria.');
            } catch (\Throwable) {
                $this->error('Chromium: no se pudo generar el PDF. Revise Node, Chrome y sus permisos.');
                $ok = false;
            }
        }

        return $ok ? 0 : 1;
    }
}
