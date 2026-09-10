<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Pengumuman';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("INSERT INTO pengumuman (judul,isi,tanggal,publik) VALUES (?,?,?,?)")
        ->execute([trim($_POST['judul']), trim($_POST['isi']), $_POST['tanggal'], isset($_POST['publik']) ? 1 : 0]);
    flash('ok', 'Pengumuman diterbitkan.');
    header('Location: pengumuman.php'); exit;
}
if (isset($_GET['hapus'])) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
        header('Location: pengumuman.php');
        exit;
    }
    $pdo->prepare("DELETE FROM pengumuman WHERE id=?")->execute([(int)$_GET['hapus']]);
    flash('ok', 'Pengumuman dihapus.');
    header('Location: pengumuman.php'); exit;
}
$rows = $pdo->query("SELECT * FROM pengumuman ORDER BY tanggal DESC")->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="openModal('mNews')">+ Pengumuman</button>
</div>
<div class="cards">
<?php foreach ($rows as $r): ?>
  <div class="card">
    <div style="font-size:12px;color:var(--green);font-weight:700;"><?= tgl($r['tanggal']) ?> · <?= $r['publik']?'Publik':'Internal' ?></div>
    <h3 style="margin:8px 0;"><?= e($r['judul']) ?></h3>
    <p><?= nl2br(e($r['isi'])) ?></p>
    <a class="btn btn-ghost btn-sm" style="margin-top:10px;" href="?hapus=<?= $r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus?')">Hapus</a>
  </div>
<?php endforeach; ?>
</div>
<div class="modal-bg" id="mNews">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <h3>Pengumuman baru</h3>
    <label>Judul</label><input name="judul" required>
    <label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
    <label>Isi</label><textarea name="isi" required></textarea>
    <label><input type="checkbox" name="publik" checked style="width:auto;"> Tampilkan di situs umum</label>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mNews')">Batal</button>
      <button class="btn btn-green">Terbit</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
