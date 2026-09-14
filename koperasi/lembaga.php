<?php
require __DIR__ . '/config.php';
require_staff();
ensure_gapoktan_schema();
ensure_lembaga_koperasi_schema();
ensure_kelompok_schema();
$title = 'Lembaga';
$pdo = db();

$nKop = (int)$pdo->query('SELECT COUNT(*) FROM lembaga_koperasi')->fetchColumn();
$nGap = (int)$pdo->query('SELECT COUNT(*) FROM gapoktan')->fetchColumn();
$nKel = (int)$pdo->query('SELECT COUNT(*) FROM kelompok')->fetchColumn();

$kops = $pdo->query('SELECT * FROM lembaga_koperasi ORDER BY nama_koperasi')->fetchAll();
$gaps = $pdo->query('SELECT * FROM gapoktan ORDER BY nama_gapoktan')->fetchAll();
$kels = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM anggota_kelompok ak JOIN anggota a ON a.id=ak.anggota_id WHERE ak.id_kelompok=k.id AND a.status=\'aktif\') jml FROM kelompok k ORDER BY k.nomor')->fetchAll();
$gapByKop = [];
$gapLepas = [];
foreach ($gaps as $g) {
    if (!empty($g['id_koperasi'])) {
        $gapByKop[(int)$g['id_koperasi']][] = $g;
    } else {
        $gapLepas[] = $g;
    }
}
$kelByGap = [];
$kelLepas = [];
foreach ($kels as $k) {
    if (!empty($k['id_gapoktan'])) {
        $kelByGap[(int)$k['id_gapoktan']][] = $k;
    } else {
        $kelLepas[] = $k;
    }
}
include __DIR__ . '/includes/app_header.php';
?>
<div class="kpis" style="margin-bottom:16px;">
  <div class="kpi"><span>Koperasi</span><b><?= (int)$nKop ?></b></div>
  <div class="kpi"><span>Gapoktan</span><b><?= (int)$nGap ?></b></div>
  <div class="kpi"><span>Kelompok tani</span><b><?= (int)$nKel ?></b></div>
</div>
<div class="cards" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:16px;">
  <div class="card">
    <h3>Koperasi</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Daftar koperasi dalam jaringan lembaga.</p>
    <a class="btn btn-green btn-sm" href="lembaga_koperasi.php">Kelola koperasi</a>
  </div>
  <div class="card">
    <h3>Gapoktan</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Gabungan kelompok tani di bawah koperasi.</p>
    <a class="btn btn-green btn-sm" href="gapoktan.php">Kelola gapoktan</a>
  </div>
  <div class="card">
    <h3>Kelompok tani</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Kelompok tani anggota gapoktan (1 kelompok = 1 anggota).</p>
    <a class="btn btn-green btn-sm" href="kelompok.php">Kelola kelompok</a>
  </div>
</div>
<div class="card">
  <h3>Struktur lembaga</h3>
  <div style="margin-top:12px;font-size:14px;line-height:2;">
  <?php foreach ($kops as $kp): ?>
    <p style="margin:6px 0;">🏛 <strong><?= e($kp['nama_koperasi'] ?: 'Koperasi') ?></strong>
      <a class="btn btn-ghost btn-sm" href="lembaga_koperasi_detail.php?id=<?= (int)$kp['id'] ?>">Detail</a></p>
    <?php foreach ($gapByKop[(int)$kp['id']] ?? [] as $g): ?>
      <p style="margin:2px 0 2px 28px;">▣ <?= e($g['nama_gapoktan'] ?: 'Gapoktan') ?>
        <a class="btn btn-ghost btn-sm" href="gapoktan_detail.php?id=<?= (int)$g['id'] ?>">Detail</a></p>
      <?php foreach ($kelByGap[(int)$g['id']] ?? [] as $k): ?>
        <p style="margin:2px 0 2px 56px;font-size:13px;color:var(--muted);">▫ <?= e(($k['kode_kelompok'] ?: 'KT') . ' — ' . ($k['nama_kelompok'] ?: 'Kelompok')) ?> (<?= (int)$k['jml'] ?> anggota)
          <a href="kelompok_detail.php?id=<?= (int)$k['id'] ?>">Detail</a></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <?php foreach ($gapLepas as $g): ?>
    <p style="margin:6px 0;">▣ <?= e($g['nama_gapoktan'] ?: 'Gapoktan') ?> <small>(belum masuk koperasi)</small>
      <a class="btn btn-ghost btn-sm" href="gapoktan_detail.php?id=<?= (int)$g['id'] ?>">Detail</a></p>
    <?php foreach ($kelByGap[(int)$g['id']] ?? [] as $k): ?>
      <p style="margin:2px 0 2px 28px;font-size:13px;color:var(--muted);">▫ <?= e(($k['kode_kelompok'] ?: 'KT') . ' — ' . ($k['nama_kelompok'] ?: 'Kelompok')) ?> (<?= (int)$k['jml'] ?> anggota)
        <a href="kelompok_detail.php?id=<?= (int)$k['id'] ?>">Detail</a></p>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <?php foreach ($kelLepas as $k): ?>
    <p style="margin:2px 0;font-size:13px;color:var(--muted);">▫ <?= e(($k['kode_kelompok'] ?: 'KT') . ' — ' . ($k['nama_kelompok'] ?: 'Kelompok')) ?> <small>(belum masuk gapoktan)</small>
      <a href="kelompok_detail.php?id=<?= (int)$k['id'] ?>">Detail</a></p>
  <?php endforeach; ?>
  <?php if (!$kops && !$gaps && !$kels): ?>
    <p style="color:var(--muted);">Belum ada data lembaga.</p>
  <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
