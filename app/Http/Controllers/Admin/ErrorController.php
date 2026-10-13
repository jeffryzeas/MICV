<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use Illuminate\Http\Request;

class ErrorController extends Controller
{
    /** No leer más de esto del final del archivo, para no tronar con logs gigantes. */
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * El log de Laravel y sus librerías escriben en inglés (no hay forma de
     * traducir eso, es el framework mismo) — pero para los errores más
     * comunes que le van a salir a un no-programador, agregamos una pista
     * en español arriba del mensaje original.
     */
    private const PISTAS_ESPANOL = [
        'imagick'              => 'Falta instalar/activar la extensión Imagick de PHP en el servidor.',
        'ext-gd'                => 'Falta activar la extensión GD de PHP en el servidor (php.ini).',
        'could not find driver' => 'No se encuentra el driver de la base de datos — revisa la conexión a MySQL.',
        'sqlstate[hy000] [2002]' => 'No se pudo conectar a MySQL — revisa que el servicio de base de datos esté encendido.',
        'sqlstate[42s02]'       => 'Falta una tabla en la base de datos — corre "php artisan migrate".',
        'sqlstate[23000]'       => 'Se intentó guardar un dato duplicado o que rompe una regla de la base de datos.',
        'class not found'       => 'Falta un archivo o un paquete de Composer — corre "composer dump-autoload".',
        'permission denied'     => 'El servidor no tiene permiso para escribir un archivo — revisa permisos de carpeta.',
        'csrf token mismatch'   => 'La sesión expiró o el token de seguridad del formulario venció — es normal si tardó mucho en enviarlo.',
        'route not defined'     => 'Falta una ruta en el sistema — revisa routes/web.php.',
        'browsershot'           => 'Falló Chromium al hacer un PDF — ejecute "php artisan micv:probar-pdf".',
        'could not find chrome' => 'No se encontró Chrome para los PDF — ejecute "npx puppeteer browsers install chrome".',
        'connection could not be established' => 'No se pudo conectar al servidor de correo (Gmail). Revise puerto 587/TLS y que el VPS permita salir por ese puerto.',
        '535'                   => 'Gmail rechazó el usuario/contraseña: use una contraseña de aplicación de 16 letras.',
        'recaptcha'             => 'Mensaje del módulo reCAPTCHA (revise las llaves en Admin → reCAPTCHA).',
        'call to a member function' => 'Se intentó usar algo que llegó vacío (null) donde no debía — probable dato faltante.',
        'no application encryption key' => 'Falta la clave de la aplicación — corre "php artisan key:generate".',
    ];

    private function pistaEspanol(string $mensaje): ?string
    {
        $mensajeMin = strtolower($mensaje);
        foreach (self::PISTAS_ESPANOL as $clave => $pista) {
            if (str_contains($mensajeMin, $clave)) {
                return $pista;
            }
        }
        return null;
    }

    public function index(Request $request)
    {
        $nivel = $request->input('nivel', '');
        $q = trim((string) $request->input('q', ''));

        $entradas = $this->leerEntradas();

        if ($nivel !== '') {
            $entradas = array_values(array_filter($entradas, fn ($e) => $e['nivel'] === $nivel));
        }
        if ($q !== '') {
            $entradas = array_values(array_filter($entradas, fn ($e) => stripos($e['mensaje'], $q) !== false));
        }

        // Más reciente primero, y limitamos a las últimas 300 para no sobrecargar la vista.
        $entradas = array_slice(array_reverse($entradas), 0, 300);

        foreach ($entradas as &$e) {
            $e['pista'] = $this->pistaEspanol($e['mensaje']);
        }
        unset($e);

        return view('admin.errores', [
            'entradas' => $entradas,
            'nivel'    => $nivel,
            'q'        => $q,
            'archivo'  => $this->rutaLog(),
            'tamano'   => is_file($this->rutaLog()) ? round(filesize($this->rutaLog()) / 1024, 1) : 0,
        ]);
    }

    public function limpiar()
    {
        $ruta = $this->rutaLog();
        if (is_file($ruta)) {
            file_put_contents($ruta, '');
        }
        Actividad::registrar('errores_limpiar');

        return back()->with('mensaje', 'Log limpiado correctamente.');
    }

    private function rutaLog(): string
    {
        return storage_path('logs/laravel.log');
    }

    /**
     * Lee el archivo de log (solo los últimos MAX_BYTES para no ahogarse
     * con archivos gigantes) y separa cada entrada por su línea de cabecera
     * "[fecha] canal.NIVEL: mensaje".
     */
    private function leerEntradas(): array
    {
        $ruta = $this->rutaLog();
        if (!is_file($ruta)) {
            return [];
        }

        $tamano = filesize($ruta);
        $handle = fopen($ruta, 'r');
        if (!$handle) {
            return [];
        }

        if ($tamano > self::MAX_BYTES) {
            fseek($handle, -self::MAX_BYTES, SEEK_END);
        }
        $contenido = stream_get_contents($handle);
        fclose($handle);

        $patron = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/m';
        preg_match_all($patron, $contenido, $coincidencias, PREG_OFFSET_CAPTURE);

        $entradas = [];
        $total = count($coincidencias[0]);

        for ($i = 0; $i < $total; $i++) {
            $inicio = $coincidencias[0][$i][1];
            $finBloque = $i + 1 < $total ? $coincidencias[0][$i + 1][1] : strlen($contenido);
            $bloque = substr($contenido, $inicio, $finBloque - $inicio);

            $entradas[] = [
                'fecha'   => $coincidencias[1][$i][0],
                'entorno' => $coincidencias[2][$i][0],
                'nivel'   => strtoupper($coincidencias[3][$i][0]),
                'mensaje' => trim($bloque),
            ];
        }

        return $entradas;
    }
}
