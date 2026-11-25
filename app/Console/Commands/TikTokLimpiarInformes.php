<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\TikTokRetencion;
class TikTokLimpiarInformes extends Command {
 protected $signature='micv:tiktok-limpiar-informes';protected $description='Elimina únicamente los archivos de informes TikTok caducados';
 public function handle(TikTokRetencion $r): int {$n=$r->limpiar();$this->info($n.' informes temporales limpiados. Las capturas se conservan.');return 0;}
}
