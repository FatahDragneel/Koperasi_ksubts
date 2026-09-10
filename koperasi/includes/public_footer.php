<?php $s = setting(); ?>
<footer class="footer">
  <div class="container footer-grid">
    <div>
      <h4><?= e($s['nama_koperasi']) ?></h4>
      <p>Koperasi simpan pinjam berbasis petani. Gotong royong, transparan, dan berpihak pada kesejahteraan anggota.</p>
    </div>
    <div>
      <h4>Kontak</h4>
      <p><?= e($s['alamat']) ?><br><?= e($s['telepon']) ?><br><?= e($s['email']) ?></p>
    </div>
    <div>
      <h4>Tautan</h4>
      <p><a href="login.php">Portal Anggota</a><br><a href="legalitas.php">Legalitas</a><br><a href="index.php#tentang">Profil Koperasi</a></p>
    </div>
  </div>
  <div class="container copy">© <?= date('Y') ?> <?= e($s['nama_koperasi'] ?? 'KSU Bina Tani Sejahtera') ?><?= !empty($s['nib']) ? ' · NIB '.e($s['nib']) : '' ?><?= !empty($s['no_badan_hukum']) ? ' · '.e($s['no_badan_hukum']) : '' ?></div>
</footer>
</body>
</html>
