<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class VideosPromocionales
{
    private function raiz(): string
    {
        return storage_path('app/private/videos-promocionales');
    }

    public function carpeta(int $usuario, string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return $this->raiz().'/'.$usuario.'/'.$id;
    }

    public function estado(int $usuario, string $id): ?array
    {
        $ruta = $this->carpeta($usuario, $id).'/estado.json';
        if (!is_file($ruta)) {
            return null;
        }

        return json_decode(File::get($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    public function actualizar(int $usuario, string $id, string $estado): array
    {
        $carpeta = $this->carpeta($usuario, $id);
        File::ensureDirectoryExists($carpeta, 0700);
        $anterior = $this->estado($usuario, $id);
        $ahora = now()->getTimestamp();
        $datos = [
            'id' => $id, 'estado' => $estado,
            'creado' => $anterior['creado'] ?? $ahora,
            'actualizado' => $ahora,
        ];
        File::replace($carpeta.'/estado.json', json_encode($datos, JSON_THROW_ON_ERROR), 0600);

        return $datos;
    }

    public function ultimo(int $usuario): ?array
    {
        $ruta = $this->raiz().'/'.$usuario;
        if (!is_dir($ruta)) {
            return null;
        }

        $ultimo = null;
        foreach (File::directories($ruta) as $carpeta) {
            $id = basename($carpeta);
            if (!Str::isUuid($id)) {
                continue;
            }
            $estado = $this->estado($usuario, $id);
            if ($estado && (!$ultimo || $estado['creado'] >= $ultimo['creado'])) {
                $ultimo = $estado;
            }
        }

        return $ultimo;
    }

    public function limpiar(): void
    {
        $raiz = $this->raiz();
        if (!is_dir($raiz)) {
            return;
        }

        $limite = now()->subDays(2)->getTimestamp();
        foreach (File::directories($raiz) as $usuario) {
            foreach (File::directories($usuario) as $carpeta) {
                $ruta = $carpeta.'/estado.json';
                if (is_file($ruta) && filemtime($ruta) < $limite) {
                    File::deleteDirectory($carpeta);
                }
            }
        }
    }
}
