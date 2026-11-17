<?php

// Actualiza solo las dos opciones de voz después de una prueba WAV satisfactoria.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$ruta = $argv[1] ?? '';
$binario = $argv[2] ?? '';
if (! is_file($ruta) || ! is_executable($binario)) {
    fwrite(STDERR, "Falta el .env del proyecto o el ejecutable de Pocket TTS.\n");
    exit(1);
}
$original = file_get_contents($ruta);
$antes = Dotenv\Dotenv::parse($original);
$valores = ['MICV_VIDEO_MOTOR_VOZ' => 'pocket', 'MICV_VIDEO_POCKET' => $binario];
$nuevo = $original;
foreach ($valores as $clave => $valor) {
    $nuevo = preg_replace('/^[ \t]*(?:export[ \t]+)?'.preg_quote($clave, '/').'[ \t]*=.*(?:\r?\n|$)/m', '', $nuevo);
    $seguro = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $valor);
    $nuevo = rtrim($nuevo, "\r\n")."\n".$clave.'="'.$seguro."\"\n";
}
$despues = Dotenv\Dotenv::parse($nuevo);
foreach ($antes as $clave => $valor) {
    if (! array_key_exists($clave, $valores) && ($despues[$clave] ?? null) !== $valor) {
        throw new RuntimeException('Se canceló el cambio para preservar las demás opciones del .env.');
    }
}
$estadisticas = stat($ruta);
$temporal = tempnam(dirname($ruta), '.micv-voz-');
try {
    if (file_put_contents($temporal, $nuevo) === false) {
        throw new RuntimeException('No se pudo escribir la configuración.');
    }
    chmod($temporal, $estadisticas['mode'] & 0777);
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chown($temporal, $estadisticas['uid']);
        chgrp($temporal, $estadisticas['gid']);
    }
    if (! rename($temporal, $ruta)) {
        throw new RuntimeException('No se pudo publicar la configuración.');
    }
} finally {
    if (is_file($temporal)) {
        unlink($temporal);
    }
}
echo "Pocket TTS configurado. Las demás opciones del .env se conservaron.\n";
