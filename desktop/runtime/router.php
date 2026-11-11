<?php
// Everything, including static files, passes this loopback gate.
// Never use this router for the public deployment.
$deny = static function (int $code): never {
    http_response_code($code);
    header('Cache-Control: no-store');
    exit('Acceso local no disponible.');
};
$port = getenv('MICV_LOCAL_PORT');
$secret = getenv('MICV_LOCAL_TOKEN');
if (!$port || !$secret || strlen($secret) < 32) $deny(503);
$origin = 'http://127.0.0.1:' . $port;
if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' ||
    ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:' . $port ||
    !hash_equals($secret, $_SERVER['HTTP_X_MICV_LOCAL'] ?? '')) $deny(403);
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== $origin) $deny(403);
$expires = (int) getenv('MICV_LOCAL_VALID_UNTIL');
if ($lease = getenv('MICV_LOCAL_LEASE_FILE')) {
    $signed = @file_get_contents($lease, false, null, 0, 128);
    if (!$signed || !preg_match('/^(\d{1,12}):([a-f0-9]{64})$/D', $signed, $parts) ||
        !hash_equals(hash_hmac('sha256', $parts[1], $secret), $parts[2])) $deny(403);
    $expires = (int) $parts[1];
}
if ($expires <= time()) $deny(403);
define('MICV_ROUTER_VALID_UNTIL', $expires);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.~', $path)) $deny(404);
if ($path === '/_desktop/health') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['status' => 'ready']);
    return;
}
$public = realpath(getenv('MICV_LOCAL_PUBLIC') ?: '');
if (!$public) $deny(503);
$file = realpath($public . $path);
if ($file && is_file($file) && str_starts_with($file, $public . DIRECTORY_SEPARATOR)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['css'=>'text/css','js'=>'text/javascript','png'=>'image/png','jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon',
        'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','map'=>'application/json'];
    if (isset($types[$ext])) {
        header('Content-Type: '.$types[$ext]);
        header('Content-Length: '.filesize($file));
        readfile($file);
        return;
    }
    if ($path !== '/index.php') $deny(404);
}
// Do not expose the public installation wizard from a prepared desktop profile.
if ($path === '/instalar' || str_starts_with($path, '/instalar/')) $deny(403);
require $public . '/index.php';
