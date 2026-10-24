<?php

namespace App\Console\Commands;

use App\Services\QuitarFondo;
use Illuminate\Console\Command;

/** Descarga el modelo para quitar el fondo de las fotos carné (unos 170 MB, una sola vez). */
class ModeloFondo extends Command
{
    protected $signature = 'micv:modelo-fondo {--forzar : Descargar aunque ya exista} {--perfil=actual : actual, u2net, isnet o birefnet}';

    protected $description = 'Descarga el modelo U²-Net (rembg, Apache-2.0) para quitar el fondo de las fotos carné';

    public function handle(): int
    {
        $perfil=(string)$this->option('perfil');
        if(!in_array($perfil,['actual','u2net','isnet','birefnet'],true)){ $this->error('Perfil no válido.');return self::FAILURE;}
        if($perfil!=='actual') config(['micv.fondo.perfil'=>$perfil]);
        $destino = QuitarFondo::modelo();
        if (QuitarFondo::disponible() && !$this->option('forzar')) {
            $this->info("Ya está instalado: $destino");
            return self::SUCCESS;
        }
        @mkdir(dirname($destino), 0775, true);
        $url = QuitarFondo::url();
        $this->line("Descargando $url");
        $this->line('El tamaño depende del modelo; BiRefNet necesita más memoria. Para BiRefNet instale onnxruntime-node en el servidor.');

        $tmp = $destino . '.parcial';
        $out = fopen($tmp, 'wb');
        $ch = curl_init($url);
        $barra = null;
        curl_setopt_array($ch, [
            CURLOPT_FILE => $out,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($r, $total, $bajado) use (&$barra) {
                if ($total > 0) {
                    $barra ??= $this->output->createProgressBar(100);
                    $barra->setProgress((int) floor($bajado / $total * 100));
                }
                return 0;
            },
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($out);
        $barra?->finish();
        $this->newLine();

        if (!$ok || filesize($tmp) < 1_000_000) {
            @unlink($tmp);
            $this->error('No se pudo descargar: ' . ($error ?: 'archivo incompleto'));
            $this->line("Puede bajarlo a mano y ponerlo en: $destino");
            return self::FAILURE;
        }
        rename($tmp, $destino);
        $this->info("Listo: $destino");
        if($perfil!=='actual')$this->line('Para usarlo de forma permanente, configure MICV_FONDO_PERFIL='.$perfil.' en .env y limpie la caché de configuración.');
        $this->line('Pruebe en el menú "Fotos carné".');
        return self::SUCCESS;
    }
}
