<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;

/**
 * Convierte el HTML del CV en PDF usando Chromium (Browsershot + Puppeteer).
 * El resultado es idéntico a la vista previa del navegador.
 */
class CvPdf
{
    /** @param bool $esperarAjuste  true en los CV Pro: espera a que el script los ajuste a una hoja. */
    public static function desdeHtml(string $html, bool $esperarAjuste = false): string
    {
        $cfg = config('micv.pdf');

        $b = Browsershot::html($html)
            ->format('Letter')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->emulateMedia('print')
            ->timeout(max(10, (int) $cfg['timeout']));

        // Respeta el tamaño y márgenes definidos con @page en la plantilla.
        $b->setOption('preferCSSPageSize', true);

        if (filled($cfg['chrome_path'])) {
            $b->setChromePath($cfg['chrome_path']);
        }
        $node = $cfg['node_binary'] ?: self::buscar(['/usr/bin/node', '/usr/local/bin/node']);
        $npm = $cfg['npm_binary'] ?: self::buscar(['/usr/bin/npm', '/usr/local/bin/npm']);
        if (filled($node)) {
            $b->setNodeBinary($node);
        }
        if (filled($npm)) {
            $b->setNpmBinary($npm);
        }
        if (filled($cfg['node_modules']) && is_dir($cfg['node_modules'])) {
            $b->setNodeModulePath($cfg['node_modules']);
        }
        if ($cfg['no_sandbox']) {
            $b->noSandbox();
        }

        // En Linux (VPS) Chromium necesita un HOME con permisos de escritura
        // (www-data no siempre lo tiene). En Windows NO se toca el entorno:
        // Chrome necesita las variables propias de Windows para arrancar.
        $home = storage_path('app/chromium');
        if (!is_dir($home)) {
            @mkdir($home, 0775, true);
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            $b->setEnvironmentOptions([
                'HOME'            => $home,
                'XDG_CONFIG_HOME' => $home,
                'XDG_CACHE_HOME'  => $home,
                'PATH'            => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ]);
            if (!getenv('HOME')) {
                self::entorno('HOME', $home);
            }
        }

        // Node/Puppeteer: dónde quedó el Chrome descargado por "npm install" (.puppeteerrc.cjs).
        self::entorno('PUPPETEER_CACHE_DIR', base_path('.cache' . DIRECTORY_SEPARATOR . 'puppeteer'));

        $b->addChromiumArguments(['disable-dev-shm-usage', 'font-render-hinting=none']);

        if ($esperarAjuste) {
            $b->waitForFunction('window.cvListo === true', null, 15000);
        }

        return $b->pdf();
    }

    /** En Linux busca node/npm en las rutas típicas (PHP-FPM a veces no trae PATH). */
    protected static function buscar(array $rutas): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }
        foreach ($rutas as $r) {
            if (@is_file($r) && @is_executable($r)) {
                return $r;
            }
        }
        return null;
    }

    protected static function entorno(string $k, string $v): void
    {
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}
