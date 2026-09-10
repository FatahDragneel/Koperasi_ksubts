<?php
require_login();
if (!function_exists('th_urut')) {
    function sort_params(): array {
        $k = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['urut'] ?? ''));
        $d = strtolower((string)($_GET['arah'] ?? 'asc'));
        if ($d !== 'desc') {
            $d = 'asc';
        }
        return [$k, $d];
    }
    function sql_urut(array $map, string $defaultSql): string {
        [$k, $d] = sort_params();
        if ($k === '' || !isset($map[$k])) {
            return $defaultSql;
        }
        return $map[$k] . ' ' . strtoupper($d);
    }
    function th_urut(string $key, string $label): string {
        [$k, $d] = sort_params();
        $next = ($k === $key && $d === 'asc') ? 'desc' : 'asc';
        $q = $_GET;
        $q['urut'] = $key;
        $q['arah'] = $next;
        $sorted = ($k === $key) ? ($d === 'desc' ? 'sorted-desc' : 'sorted-asc') : '';
        return '<th class="' . $sorted . '"><a class="th-sort" href="?' . e(http_build_query($q)) . '">' . e($label) . '</a></th>';
    }
}
$s = setting();
$u = auth();
$path = basename($_SERVER['PHP_SELF']);
$__kata = preg_split('/\s+/', trim((string)($s['nama_koperasi'] ?? 'Koperasi')));
$__inisial = strtoupper(substr($__kata[0] ?? 'K', 0, 1) . substr(end($__kata) ?: 'P', 0, 1));
function nav_active($file) {
    return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Dashboard') ?> · <?= e($s['nama_koperasi'] ?? 'Koperasi') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=24">
</head>
<body>
<input type="checkbox" id="navCek" class="nav-cek" autocomplete="off">
<label class="nav-backdrop" for="navCek"></label>
<div class="app">
  <aside class="sidebar" id="appSide">
    <div class="side-brand">
      <label class="nav-close" for="navCek">✕</label>
      <div class="logo"><?= e($__inisial) ?></div>
      <div>
        <small style="color:#e8c547;font-size:10px;letter-spacing:.12em;text-transform:uppercase;">Portal Koperasi</small>
        <strong style="display:block;font-size:13px;color:#fff;"><?= e($s['nama_koperasi'] ?? 'Koperasi') ?></strong>
      </div>
    </div>
    <nav class="side-nav">
      <a class="<?= nav_active('dashboard.php') ?>" href="dashboard.php">⌂ Dashboard</a>
      <a class="<?= nav_active('profil_koperasi.php') ?>" href="profil_koperasi.php">🏛 Profil koperasi</a>
      <?php if (in_array($u['role'], ['admin','pengurus'])): ?>
        <a class="<?= nav_active('anggota.php') ?>" href="anggota.php">☺ Anggota</a>
        <a class="<?= nav_active('pengalihan.php') ?>" href="pengalihan.php">⇄ Pengalihan hak</a>
        <a class="<?= nav_active('kelompok.php') ?>" href="kelompok.php">▣ Kelompok</a>
        <a class="<?= nav_active('pupuk.php') ?>" href="pupuk.php">🌱 Pupuk organik</a>
        <a class="<?= nav_active('verifikasi.php') ?>" href="verifikasi.php">☑ Verifikasi anggota</a>
        <a class="<?= nav_active('verifikasi_bayar.php') ?>" href="verifikasi_bayar.php">☑ Verifikasi bayar</a>
        <a class="<?= nav_active('simpanan.php') ?>" href="simpanan.php">⛁ Simpanan</a>
        <a class="<?= nav_active('penarikan.php') ?>" href="penarikan.php">↩ Penarikan simpanan</a>
        <a class="<?= nav_active('pinjaman.php') ?>" href="pinjaman.php">⇄ Pinjaman</a>
        <a class="<?= nav_active('angsuran.php') ?>" href="angsuran.php">↻ Angsuran</a>
        <a class="<?= nav_active('pengumuman.php') ?>" href="pengumuman.php">✉ Pengumuman</a>
        <a class="<?= nav_active('laporan.php') ?>" href="laporan.php">▣ Laporan</a>
        <a class="<?= nav_active('pengaturan.php') ?>" href="pengaturan.php">⚙ Pengaturan</a>
      <?php else: ?>
        <a class="<?= nav_active('simpanan.php') ?>" href="simpanan.php">⛁ Simpanan Saya</a>
        <a class="<?= nav_active('penarikan.php') ?>" href="penarikan.php">↩ Penarikan simpanan</a>
        <a class="<?= nav_active('pinjaman.php') ?>" href="pinjaman.php">⇄ Pinjaman Saya</a>
        <a class="<?= nav_active('pinjaman_bayar.php') ?>" href="pinjaman_bayar.php">↻ Bayar angsuran</a>
        <a class="<?= nav_active('pupuk.php') ?>" href="pupuk.php">🌱 Pupuk organik</a>
        <a class="<?= nav_active('profil_koperasi.php') ?>" href="profil_koperasi.php#ba">📜 Berita acara</a>
        <a class="<?= nav_active('profil.php') ?>" href="profil.php">◉ Data &amp; usaha saya</a>
      <?php endif; ?>
      <?php if (in_array($u['role'], ['admin','pengurus'])): ?>
      <a class="<?= nav_active('profil.php') ?>" href="profil.php">◉ Profil</a>
      <?php endif; ?>
      <a href="logout.php">↪ Keluar</a>
    </nav>
    <div style="margin-top:auto;padding:12px;background:rgba(255,255,255,.06);border-radius:12px;font-size:12px;">
      <div style="opacity:.7;">Masuk sebagai</div>
      <strong style="color:#fff;"><?= e($u['nama']) ?></strong>
      <div style="color:#e8c547;text-transform:capitalize;"><?= e($u['role']) ?></div>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <label class="nav-toggle" for="navCek">☰ Menu</label>
        <div>
          <h2 style="letter-spacing:-.02em;"><?= e($title ?? 'Dashboard') ?></h2>
          <div style="color:#5c6b60;font-size:13px;"><?= e($s['nama_koperasi']) ?></div>
        </div>
      </div>
      <a class="btn btn-ghost hide-sm" href="index.php" target="_blank">Lihat Situs Umum</a>
    </div>
    <?php if ($m = flash('ok')): ?><div class="alert alert-ok"><?= e($m) ?></div><?php endif; ?>
    <?php if ($m = flash('err')): ?><div class="alert alert-err"><?= e($m) ?></div><?php endif; ?>
