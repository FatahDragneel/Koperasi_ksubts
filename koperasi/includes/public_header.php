<?php $s = setting(); $page = $page ?? '';
$__kata = preg_split('/\s+/', trim((string)($s['nama_koperasi'] ?? 'Koperasi')));
$__inisial = strtoupper(substr($__kata[0] ?? 'K', 0, 1) . substr(end($__kata) ?: 'P', 0, 1));
$__jenis = trim((string)($s['jenis_koperasi'] ?? '')) !== '' ? $s['jenis_koperasi'] : 'Koperasi Produsen';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? $s['nama_koperasi']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=10">
</head>
<body>
<nav class="nav">
  <div class="nav-inner">
    <a class="brand" href="index.php">
      <div class="logo"><?= e($__inisial) ?></div>
      <div>
        <small><?= e($__jenis) ?></small>
        <strong><?= e($s['nama_koperasi'] ?? 'Koperasi') ?></strong>
      </div>
    </a>
    <input type="checkbox" id="navCek" class="nav-cek" autocomplete="off">
    <label class="nav-toggle" for="navCek">☰ Menu</label>
    <div class="nav-links" id="navLinks">
      <label class="nav-close" for="navCek">✕ Tutup</label>
      <a href="index.php#beranda">Beranda</a>
      <a href="index.php#tentang">Tentang</a>
      <a href="legalitas.php">Legalitas</a>
      <a href="index.php#layanan">Layanan</a>
      <a href="index.php#berita">Berita</a>
      <a href="index.php#kontak">Kontak</a>
      <a href="login-admin.php">Admin</a>
      <a href="login-anggota.php">Masuk Anggota</a>
    </div>
    <label class="nav-backdrop" for="navCek"></label>
  </div>
</nav>
