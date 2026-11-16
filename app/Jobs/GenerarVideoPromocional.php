<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\VideoPromocional;
use App\Services\VideosPromocionales;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GenerarVideoPromocional implements ShouldQueue
{
    use Queueable;

    public int $timeout = 570;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $usuario, public string $id)
    {
        $this->onConnection(VideoPromocional::COLA);
        $this->onQueue(VideoPromocional::COLA);
    }

    public function handle(VideosPromocionales $videos, VideoPromocional $motor): void
    {
        if (! $videos->estado($this->usuario, $this->id)) {
            return;
        }
        if (! User::whereKey($this->usuario)->where('rol', 'admin')->where('activo', true)->exists()) {
            throw new RuntimeException('La cuenta solicitante ya no tiene acceso al generador de video.');
        }

        $videos->actualizar($this->usuario, $this->id, 'procesando');
        $carpeta = $videos->carpeta($this->usuario, $this->id);
        $temporal = $carpeta.'/video-temporal.mp4';
        $motor->generar($temporal);
        if (! File::move($temporal, $carpeta.'/video.mp4')) {
            throw new RuntimeException('No se pudo publicar el archivo MP4.');
        }
        $videos->actualizar($this->usuario, $this->id, 'listo');
    }

    public function failed(?\Throwable $error): void
    {
        $videos = app(VideosPromocionales::class);
        if (! $videos->estado($this->usuario, $this->id)) {
            return;
        }
        $carpeta = $videos->carpeta($this->usuario, $this->id);
        File::delete($carpeta.'/video-temporal.mp4');
        $videos->actualizar($this->usuario, $this->id, 'error');
        File::deleteDirectory($carpeta.'/render');
    }
}
