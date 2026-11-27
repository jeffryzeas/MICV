<?php

use App\Support\InstalacionEntorno;

// Solo prepara la base indicada en .env, sin borrar datos ni cambiar claves de usuarios existentes.
$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
try {
    $e = InstalacionEntorno::leer($raiz);
    $db = $e['DB_DATABASE'] ?? '';
    $usuario = $e['DB_USERNAME'] ?? '';
    $clave = $e['DB_PASSWORD'] ?? '';
    $host = $e['DB_HOST'] ?? '127.0.0.1';
    if (! empty($e['DB_URL']) || ($e['DB_CONNECTION'] ?? '') !== 'mysql' || ! in_array($host, ['127.0.0.1', 'localhost'], true) || ($e['DB_PORT'] ?? '3306') !== '3306') {
        throw new RuntimeException;
    }
    $dsn = 'mysql:host='.$host.';port=3306;dbname='.$db.';charset=utf8mb4';
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    try {
        new PDO($dsn, $usuario, $clave, $opts);
        echo "MySQL local: conexión existente conservada.\n";
        exit;
    } catch (PDOException) {
    }
    if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $db) || ! preg_match('/^[a-zA-Z0-9_]{1,32}$/D', $usuario) || in_array(strtolower($usuario), ['root', 'mysql', 'mariadb'], true) || $clave === '') {
        throw new RuntimeException;
    }
    $pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock', 'root', '', $opts);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$db.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $q = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?');
    $q->execute([$usuario]);
    $nuevo = (int) $q->fetchColumn() === 0;
    if ($nuevo) {
        $cuenta = $pdo->quote($usuario)."@'localhost'";
        $pdo->exec('CREATE USER '.$cuenta.' IDENTIFIED BY '.$pdo->quote($clave));
        $pdo->exec('GRANT ALL PRIVILEGES ON `'.$db.'`.* TO '.$cuenta);
    }
    // Si ya existía, no alterar su contraseña, autenticación o permisos; una configuración incorrecta debe corregirse.
    new PDO($dsn, $usuario, $clave, $opts);
    echo "MySQL local: base disponible.\n";
} catch (Throwable) {
    fwrite(STDERR,"No se pudo preparar MySQL local. Revise DB_DATABASE, DB_USERNAME y DB_PASSWORD, o restaure su base y permisos. No se cambian contraseñas existentes.\n");
    exit(1);
}
