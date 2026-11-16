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
        if (! is_file($ruta)) {
            return null;
        }

        $datos = json_decode(File::get($ruta), true, flags: JSON_THROW_ON_ERROR);
        // Compatibilidad con generaciones anteriores a la incorporación del progreso.
        $datos += [
            'porcentaje' => $datos['estado'] === 'listo' ? 100 : ($datos['estado'] === 'procesando' ? 1 : 0),
            'etapa' => match ($datos['estado']) {
                'listo' => 'completado', 'procesando' => 'preparacion', 'error' => 'error', default => 'cola',
            },
            'mensaje' => match ($datos['estado']) {
                'listo' => 'Su video está listo para reproducir y descargar.',
                'procesando' => 'Preparando la generación del video.',
                'error' => 'No se pudo completar el video. Puede volver a intentarlo.',
                default => 'Esperando al trabajador de video.',
            },
            'iniciado' => null, 'escena' => 0, 'escenas' => 10,
        ];
        $progreso = dirname($ruta).'/progreso.json';
        if ($datos['estado'] === 'procesando' && is_file($progreso)) {
            // El motor publica un archivo completo con rename; nunca se lee JSON a medias.
            $avance = json_decode((string) @file_get_contents($progreso), true);
            if (is_array($avance)) {
                foreach (['etapa', 'mensaje', 'escena', 'escenas', 'motor_voz'] as $campo) {
                    if (isset($avance[$campo])) {
                        $datos[$campo] = $avance[$campo];
                    }
                }
                $datos['porcentaje'] = min(99, max(1, (int) ($avance['porcentaje'] ?? 1)));
                $datos['actualizado'] = max($datos['actualizado'], (int) ($avance['actualizado'] ?? 0));
            }
        }

        return $datos;
    }

    public function actualizar(int $usuario, string $id, string $estado): array
    {
        $carpeta = $this->carpeta($usuario, $id);
        File::ensureDirectoryExists($carpeta, 0700);
        $anterior = $this->estado($usuario, $id);
        $ahora = now()->getTimestamp();
        $etapas = [
            'pendiente' => ['cola', 0, 'Esperando al trabajador de video.'],
            'procesando' => ['preparacion', 1, 'Preparando las pantallas de la demostración.'],
            'listo' => ['completado', 100, 'Su video está listo para reproducir y descargar.'],
            'error' => ['error', $anterior['porcentaje'] ?? 0, 'No se pudo completar el video. Puede volver a intentarlo.'],
        ];
        [$etapa, $porcentaje, $mensaje] = $etapas[$estado];
        $datos = [
            'id' => $id, 'estado' => $estado,
            'creado' => $anterior['creado'] ?? $ahora,
            'actualizado' => $ahora,
            'iniciado' => $anterior['iniciado'] ?? ($estado === 'procesando' ? $ahora : null),
            'etapa' => $etapa, 'porcentaje' => $porcentaje, 'mensaje' => $mensaje,
            'escena' => $anterior['escena'] ?? 0,
            'escenas' => $anterior['escenas'] ?? 10,
            'motor_voz' => $anterior['motor_voz'] ?? null,
        ];
        File::replace($carpeta.'/estado.json', json_encode($datos, JSON_THROW_ON_ERROR), 0600);

        return $datos;
    }

    public function ultimo(int $usuario): ?array
    {
        $ruta = $this->raiz().'/'.$usuario;
        if (! is_dir($ruta)) {
            return null;
        }

        $ultimo = null;
        foreach (File::directories($ruta) as $carpeta) {
            $id = basename($carpeta);
            if (! Str::isUuid($id)) {
                continue;
            }
            $estado = $this->estado($usuario, $id);
            if ($estado && (! $ultimo || $estado['creado'] >= $ultimo['creado'])) {
                $ultimo = $estado;
            }
        }

        return $ultimo;
    }

    public function limpiar(): void
    {
        $raiz = $this->raiz();
        if (! is_dir($raiz)) {
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
