<?php
require __DIR__ . '/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$jenis = $_GET['jenis'] ?? '';
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus'], true);

if ($jenis === 'sjb' || $jenis === 'ba') {
    if ($id < 1) {
        http_response_code(400);
        exit;
    }
    $st = db()->prepare('SELECT * FROM pengalihan_hak WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        exit;
    }
    if (!$staff && (int)$u['anggota_id'] !== (int)$row['anggota_lama_id'] && (int)$u['anggota_id'] !== (int)($row['anggota_baru_id'] ?? 0)) {
        http_response_code(403);
        exit;
    }
    $file = $jenis === 'sjb' ? $row['file_sjb'] : $row['file_ba'];
} elseif ($jenis === 'bukti') {
    if ($id < 1) {
        http_response_code(400);
        exit;
    }
    $st = db()->prepare('SELECT file_bukti, anggota_id FROM bukti_bayar_pinjaman WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        exit;
    }
    if (!$staff && (int)$u['anggota_id'] !== (int)$row['anggota_id']) {
        http_response_code(403);
        exit;
    }
    $file = $row['file_bukti'];
} else {
    $map = [
        'foto' => 'foto',
        'ktp' => 'ktp_file',
        'sertifikat' => 'sertifikat_file',
    ];
    if ($id < 1 || !isset($map[$jenis])) {
        http_response_code(400);
        exit;
    }
    if (!$staff && (int)$u['anggota_id'] !== $id) {
        http_response_code(403);
        exit;
    }
    $st = db()->prepare('SELECT `' . $map[$jenis] . '` AS f FROM anggota WHERE id=?');
    $st->execute([$id]);
    $file = $st->fetchColumn();
    if (!$file) {
        http_response_code(404);
        exit;
    }
}

$base = basename((string)$file);
$path = __DIR__ . '/uploads/' . $base;
if ($base === '' || str_contains($base, '..') || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$okMime = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
if (!$okMime) {
    http_response_code(403);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: inline; filename="berkas"');
readfile($path);
exit;
