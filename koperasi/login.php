<?php
require __DIR__ . '/config.php';
if (auth()) { header('Location: dashboard.php'); exit; }
$s = setting();
$title = 'Pilih portal masuk · ' . $s['nama_koperasi'];
include __DIR__ . '/includes/public_header.php';
?>
<section style="padding:70px 0 90px;">
  <div class="container">
    <div class="sec-title">
      <span>Portal koperasi</span>
      <h2>Masuk sesuai peran Anda</h2>
    </div>
    <div class="cards" style="grid-template-columns:1fr 1fr;max-width:860px;margin:0 auto;">
      <a class="card" href="login-anggota.php" style="display:block;">
        <div class="icon">☺</div>
        <h3>Login anggota</h3>
        <p>Cek simpanan, ajukan pinjaman, dan lihat status angsuran setelah keanggotaan disetujui.</p>
        <span class="btn btn-green" style="margin-top:16px;">Masuk sebagai anggota</span>
      </a>
      <a class="card" href="login-admin.php" style="display:block;">
        <div class="icon">⚙</div>
        <h3>Login admin / pengurus</h3>
        <p>Verifikasi pendaftaran, setujui pinjaman, catat pembayaran, dan kelola laporan.</p>
        <span class="btn btn-gold" style="margin-top:16px;">Masuk sebagai admin</span>
      </a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
