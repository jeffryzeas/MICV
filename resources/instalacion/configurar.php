<?php

use App\Support\InstalacionEntorno;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
try {
    if (($argv[1] ?? '') === '--chrome') {
        echo InstalacionEntorno::leer($raiz)['MICV_CHROME_PATH'] ?? '';
        exit;
    }
    if (($argv[1] ?? '') === '--clave-vacia') {
        echo empty(InstalacionEntorno::leer($raiz)['APP_KEY']) ? 'si' : 'no';
        exit;
    }
    if (($argv[1] ?? '') === '--mysql-local') {
        $e = InstalacionEntorno::leer($raiz);
        echo ($e['DB_CONNECTION'] ?? '') === 'mysql' && empty($e['DB_URL']) && in_array($e['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true) && ($e['DB_PORT'] ?? '3306') === '3306' ? 'si' : 'no';
        exit;
    }
    $op = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
    InstalacionEntorno::configurar($raiz, $op);
    echo "Configuración preparada. APP_KEY y credenciales existentes conservadas.\n";
} catch (Throwable) {
    fwrite(STDERR, "No se pudo preparar .env. Revise dominio, permisos y dependencias.\n");
    exit(1);
}
