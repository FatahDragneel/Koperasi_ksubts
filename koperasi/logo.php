<?php
require __DIR__ . '/config.php';
require_login();
$s = setting();
$f = basename((string)($s['logo_file'] ?? ''));
if ($f === '') {
    http_response_code(404);
    exit;
}
$path = __DIR__ . '/uploads/' . $f;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = 'image/jpeg';
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
if ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
} elseif ($ext === 'gif') {
    $mime = 'image/gif';
}
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
