<?php
require __DIR__ . '/config.php';
$_SESSION = [];
session_destroy();
setcookie('kbts_auth', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
setcookie('kbts_auth_local', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
header('Location: login.php');
exit;
