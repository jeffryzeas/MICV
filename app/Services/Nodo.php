<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Corre los scripts de Node del proyecto (quitar fondo, CV en Word).
 * Usa el mismo Node y la misma carpeta node_modules que los PDF.
 */
class Nodo
{
    public static function binario(): string
    {
        $cfg = config('micv.pdf.node_binary');
        if (filled($cfg)) {
            return $cfg;
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            foreach (['/usr/bin/node', '/usr/local/bin/node', '/opt/node22/bin/node'] as $r) {
                if (@is_file($r) && @is_executable($r)) {
                    return $r;
                }
            }
        }
        return 'node';
    }

    /**
     * @param string $script ruta absoluta del .cjs
     * @return string salida estándar
     * @throws \RuntimeException con el detalle del error
     */
    public static function correr(string $script, array $args = [], int $timeout = 60, ?string $entrada = null): string
    {
        $p = new Process(array_merge([self::binario(), $script], $args), base_path(), [
            'NODE_PATH' => config('micv.pdf.node_modules', base_path('node_modules')),
            'HOME'      => storage_path('app'),
        ], $entrada, $timeout);
        try {
            $p->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException(trim($p->getErrorOutput() ?: $p->getOutput()) ?: $e->getMessage());
        }
        return $p->getOutput();
    }
}
