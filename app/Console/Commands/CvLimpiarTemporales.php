<?php

namespace App\Console\Commands;

use App\Services\CvEnvioRetencion;
use Illuminate\Console\Command;

class CvLimpiarTemporales extends Command
{
    protected $signature = 'micv:cv-limpiar-temporales';

    protected $description = 'Retira los PDF temporales de envíos finalizados y borradores caducados';

    public function handle(CvEnvioRetencion $retencion): int
    {
        $this->info('PDF temporales retirados: '.$retencion->limpiar());

        return 0;
    }
}
