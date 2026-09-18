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
function nav_active_lembaga() {
    static $f = ['lembaga.php','kelompok.php','kelompok_detail.php','gapoktan.php','gapoktan_detail.php','lembaga_koperasi.php','lembaga_koperasi_detail.php'];
    return in_array(basename($_SERVER['PHP_SELF'] ?? ''), $f, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Dashboard') ?> · <?= e($s['nama_koperasi'] ?? 'Koperasi') ?></title>
  <meta name="theme-color" content="#0f4224">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=26">
  <link rel="stylesheet" href="assets/theme.css?v=2">
</head>
<body>
<input type="checkbox" id="navCek" class="nav-cek" autocomplete="off">
<label class="nav-backdrop" for="navCek"></label>
<div class="app">
  <aside class="sidebar" id="appSide">
    <div class="side-brand">
      <label class="nav-close" for="navCek"><i class="fa-solid fa-xmark"></i></label>
      <?php if (!empty($s['logo_file'])): ?>
      <img src="logo.php" alt="Logo koperasi" style="width:44px;height:44px;border-radius:12px;object-fit:cover;background:#fff;flex:0 0 auto;">
      <?php else: ?>
      <div class="logo"><?= e($__inisial) ?></div>
      <?php endif; ?>
      <div>
        <small style="color:#e8c547;font-size:10px;letter-spacing:.12em;text-transform:uppercase;">Portal Koperasi</small>
        <strong style="display:block;font-size:13px;color:#fff;"><?= e($s['nama_koperasi'] ?? 'Koperasi') ?></strong>
      </div>
    </div>
    <nav class="side-nav">
      <a class="<?= nav_active('dashboard.php') ?>" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <?php if (in_array($u['role'], ['admin','pengurus'])): ?>
        <div class="nav-sect">Koperasi</div>
        <a class="<?= nav_active('profil_koperasi.php') ?>" href="profil_koperasi.php"><i class="fa-solid fa-building-columns"></i> Profil koperasi</a>
        <a class="<?= nav_active('pengumuman.php') ?>" href="pengumuman.php"><i class="fa-solid fa-bullhorn"></i> Pengumuman</a>
        <a class="<?= nav_active('laporan.php') ?>" href="laporan.php"><i class="fa-solid fa-chart-line"></i> Laporan</a>
        <div class="nav-sect">Anggota &amp; lembaga</div>
        <a class="<?= nav_active('anggota.php') ?>" href="anggota.php"><i class="fa-solid fa-users"></i> Anggota</a>
        <a class="<?= nav_active('verifikasi.php') ?>" href="verifikasi.php"><i class="fa-solid fa-user-check"></i> Verifikasi anggota</a>
        <?php if (FITUR_PENGALIHAN): ?>
        <a class="<?= nav_active('pengalihan.php') ?>" href="pengalihan.php"><i class="fa-solid fa-right-left"></i> Pengalihan hak</a>
        <?php endif; ?>
        <a class="<?= nav_active_lembaga() ?>" href="lembaga.php"><i class="fa-solid fa-sitemap"></i> Lembaga</a>
        <div class="nav-sect">Keuangan</div>
        <a class="<?= nav_active('simpanan.php') ?>" href="simpanan.php"><i class="fa-solid fa-piggy-bank"></i> Simpanan</a>
        <a class="<?= nav_active('penarikan.php') ?>" href="penarikan.php"><i class="fa-solid fa-hand-holding-dollar"></i> Penarikan simpanan</a>
        <?php if (FITUR_PINJAMAN): ?>
        <a class="<?= nav_active('verifikasi_bayar.php') ?>" href="verifikasi_bayar.php"><i class="fa-solid fa-money-check-dollar"></i> Verifikasi bayar</a>
        <a class="<?= nav_active('pinjaman.php') ?>" href="pinjaman.php"><i class="fa-solid fa-file-invoice-dollar"></i> Pinjaman</a>
        <a class="<?= nav_active('angsuran.php') ?>" href="angsuran.php"><i class="fa-solid fa-money-bill-wave"></i> Angsuran</a>
        <?php endif; ?>
        <div class="nav-sect">Usaha</div>
        <a class="<?= nav_active('pupuk.php') ?>" href="pupuk.php"><i class="fa-solid fa-seedling"></i> Pupuk organik</a>
        <div class="nav-sect">Sistem</div>
        <a class="<?= nav_active('pengaturan.php') ?>" href="pengaturan.php"><i class="fa-solid fa-gear"></i> Pengaturan</a>
        <a class="<?= nav_active('profil.php') ?>" href="profil.php"><i class="fa-solid fa-circle-user"></i> Profil user</a>
      <?php else: ?>
        <div class="nav-sect">Data saya</div>
        <a class="<?= nav_active('profil.php') ?>" href="profil.php"><i class="fa-solid fa-circle-user"></i> Data &amp; usaha saya</a>
        <a class="<?= nav_active('simpanan.php') ?>" href="simpanan.php"><i class="fa-solid fa-piggy-bank"></i> Simpanan Saya</a>
        <a class="<?= nav_active('penarikan.php') ?>" href="penarikan.php"><i class="fa-solid fa-hand-holding-dollar"></i> Penarikan simpanan</a>
        <?php if (FITUR_PINJAMAN): ?>
        <a class="<?= nav_active('pinjaman.php') ?>" href="pinjaman.php"><i class="fa-solid fa-file-invoice-dollar"></i> Pinjaman Saya</a>
        <a class="<?= nav_active('pinjaman_bayar.php') ?>" href="pinjaman_bayar.php"><i class="fa-solid fa-credit-card"></i> Bayar angsuran</a>
        <?php endif; ?>
        <div class="nav-sect">Lembaga &amp; usaha</div>
        <a class="<?= nav_active_lembaga() ?>" href="lembaga.php"><i class="fa-solid fa-sitemap"></i> Lembaga</a>
        <a class="<?= nav_active('pupuk.php') ?>" href="pupuk.php"><i class="fa-solid fa-seedling"></i> Pupuk organik</a>
        <div class="nav-sect">Informasi</div>
        <a class="<?= nav_active('profil_koperasi.php') ?>" href="profil_koperasi.php"><i class="fa-solid fa-building-columns"></i> Profil koperasi</a>
      <?php endif; ?>
      <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </nav>
    <div style="margin-top:auto;padding:12px;background:rgba(255,255,255,.06);border-radius:12px;font-size:12px;">
      <div style="opacity:.7;">Masuk sebagai</div>
      <strong style="color:#fff;"><i class="fa-solid fa-circle-user"></i> <?= e($u['nama']) ?></strong>
      <div style="color:#e8c547;text-transform:capitalize;"><?= e($u['role']) ?></div>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <label class="nav-toggle" for="navCek"><i class="fa-solid fa-bars"></i> Menu</label>
        <div>
          <h2 style="letter-spacing:-.02em;"><?= e($title ?? 'Dashboard') ?></h2>
          <div style="color:#5c6b60;font-size:13px;"><?= e($s['nama_koperasi']) ?></div>
        </div>
      </div>
      <a class="btn btn-ghost hide-sm" href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Situs Umum</a>
    </div>
    <?php if ($m = flash('ok')): ?><div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i><span><?= e($m) ?></span></div><?php endif; ?>
    <?php if ($m = flash('err')): ?><div class="alert alert-err"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e($m) ?></span></div><?php endif; ?>
