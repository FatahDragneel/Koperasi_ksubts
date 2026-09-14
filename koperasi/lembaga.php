<?php
require __DIR__ . '/config.php';
require_login();
ensure_gapoktan_schema();
ensure_lembaga_koperasi_schema();
ensure_lembaga_anggota_schema();
ensure_kelompok_schema();
$title = 'Lembaga';
$pdo = db();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus']);
$aid = (int)($u['anggota_id'] ?? 0);

$nKop = (int)$pdo->query('SELECT COUNT(*) FROM lembaga_koperasi')->fetchColumn();
$nGap = (int)$pdo->query('SELECT COUNT(*) FROM gapoktan')->fetchColumn();
$nKel = (int)$pdo->query('SELECT COUNT(*) FROM kelompok')->fetchColumn();

if ($staff) {
    $kops = $pdo->query('SELECT l.*, (SELECT COUNT(*) FROM anggota_lembaga al WHERE al.id_koperasi=l.id) jml FROM lembaga_koperasi l ORDER BY l.nama_koperasi')->fetchAll();
    $gaps = $pdo->query('SELECT g.*, (SELECT COUNT(*) FROM anggota_gapoktan ag WHERE ag.id_gapoktan=g.id) jml FROM gapoktan g ORDER BY g.nama_gapoktan')->fetchAll();
    $kels = $pdo->query("SELECT k.*, (SELECT COUNT(*) FROM anggota_kelompok ak JOIN anggota a ON a.id=ak.anggota_id WHERE ak.id_kelompok=k.id AND a.status='aktif') jml FROM kelompok k ORDER BY k.nomor")->fetchAll();
} else {
    $kelSaya = $aid > 0 ? kelompok_anggota($aid) : [];
    $gapSaya = $aid > 0 ? gapoktan_anggota($aid) : [];
    $lemSaya = $aid > 0 ? lembaga_anggota($aid) : [];
}
include __DIR__ . '/includes/app_header.php';
?>
<?php if ($staff): ?>
<div class="kpis" style="margin-bottom:16px;">
  <div class="kpi"><span>Koperasi</span><b><?= (int)$nKop ?></b></div>
  <div class="kpi"><span>Gapoktan</span><b><?= (int)$nGap ?></b></div>
  <div class="kpi"><span>Kelompok tani</span><b><?= (int)$nKel ?></b></div>
</div>
<div class="cards" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:16px;">
  <div class="card">
    <h3><i class="fa-solid fa-building-columns"></i> Koperasi</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Daftar koperasi dalam jaringan lembaga.</p>
    <a class="btn btn-green btn-sm" href="lembaga_koperasi.php"><i class="fa-solid fa-table-list"></i> Kelola koperasi</a>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-people-group"></i> Gapoktan</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Gabungan kelompok tani dan anggotanya.</p>
    <a class="btn btn-green btn-sm" href="gapoktan.php"><i class="fa-solid fa-table-list"></i> Kelola gapoktan</a>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-users"></i> Kelompok tani</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Kelompok tani — satu kelompok bisa diisi lebih dari satu anggota.</p>
    <a class="btn btn-green btn-sm" href="kelompok.php"><i class="fa-solid fa-table-list"></i> Kelola kelompok</a>
  </div>
</div>
<div class="cards" style="grid-template-columns:1fr 1fr 1fr;">
  <div class="card">
    <h3><i class="fa-solid fa-building-columns lembaga-ic"></i>Koperasi (<?= (int)$nKop ?>)</h3>
    <div style="margin-top:10px;font-size:14px;line-height:2;">
    <?php if (!$kops): ?><p style="color:var(--muted);">Belum ada koperasi.</p><?php endif; ?>
    <?php foreach ($kops as $kp): ?>
      <p style="margin:2px 0;"><i class="fa-solid fa-building-columns lembaga-ic"></i><?= e($kp['nama_koperasi'] ?: 'Koperasi') ?> (<?= (int)$kp['jml'] ?> anggota)
        <a class="btn btn-ghost btn-sm" href="lembaga_koperasi_detail.php?id=<?= (int)$kp['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-people-group lembaga-ic"></i>Gapoktan (<?= (int)$nGap ?>)</h3>
    <div style="margin-top:10px;font-size:14px;line-height:2;">
    <?php if (!$gaps): ?><p style="color:var(--muted);">Belum ada gapoktan.</p><?php endif; ?>
    <?php foreach ($gaps as $g): ?>
      <p style="margin:2px 0;"><i class="fa-solid fa-people-group lembaga-ic"></i><?= e($g['nama_gapoktan'] ?: 'Gapoktan') ?> (<?= (int)$g['jml'] ?> anggota)
        <a class="btn btn-ghost btn-sm" href="gapoktan_detail.php?id=<?= (int)$g['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-users lembaga-ic"></i>Kelompok tani (<?= (int)$nKel ?>)</h3>
    <div style="margin-top:10px;font-size:14px;line-height:2;">
    <?php if (!$kels): ?><p style="color:var(--muted);">Belum ada kelompok.</p><?php endif; ?>
    <?php foreach ($kels as $k): ?>
      <p style="margin:2px 0;"><i class="fa-solid fa-users lembaga-ic"></i><?= e(($k['kode_kelompok'] ?: 'KT') . ' — ' . ($k['nama_kelompok'] ?: 'Kelompok')) ?> (<?= (int)$k['jml'] ?> anggota)
        <a class="btn btn-ghost btn-sm" href="kelompok_detail.php?id=<?= (int)$k['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="kpis" style="margin-bottom:16px;">
  <div class="kpi"><span><i class="fa-solid fa-users kpi-ic"></i>Kelompok saya</span><b><?= count($kelSaya) ?></b></div>
  <div class="kpi"><span><i class="fa-solid fa-people-group kpi-ic"></i>Gapoktan saya</span><b><?= count($gapSaya) ?></b></div>
  <div class="kpi"><span><i class="fa-solid fa-building-columns kpi-ic"></i>Koperasi saya</span><b><?= count($lemSaya) ?></b></div>
</div>
<div class="cards" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:16px;">
  <div class="card">
    <h3><i class="fa-solid fa-building-columns"></i> Koperasi</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Lihat daftar koperasi dan gabung sendiri.</p>
    <a class="btn btn-green btn-sm" href="lembaga_koperasi.php"><i class="fa-solid fa-table-list"></i> Lihat &amp; gabung</a>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-people-group"></i> Gapoktan</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Lihat daftar gapoktan dan gabung sendiri.</p>
    <a class="btn btn-green btn-sm" href="gapoktan.php"><i class="fa-solid fa-table-list"></i> Lihat &amp; gabung</a>
  </div>
  <div class="card">
    <h3><i class="fa-solid fa-users"></i> Kelompok tani</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Lihat daftar kelompok dan gabung sendiri.</p>
    <a class="btn btn-green btn-sm" href="kelompok.php"><i class="fa-solid fa-table-list"></i> Lihat &amp; gabung</a>
  </div>
</div>
<div class="card">
  <h3>Keanggotaan saya</h3>
  <div class="cards" style="grid-template-columns:1fr 1fr 1fr;margin-top:10px;">
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-building-columns lembaga-ic"></i>Koperasi</h4>
      <?php if (!$lemSaya): ?><p style="font-size:13px;color:var(--muted);">Belum tergabung.</p><?php endif; ?>
      <?php foreach ($lemSaya as $l): ?>
        <p style="margin:4px 0;font-size:14px;"><?= e(($l['kode_koperasi'] ?: 'KOP') . ' — ' . ($l['nama_koperasi'] ?: 'Koperasi')) ?>
          <a class="btn btn-ghost btn-sm" href="lembaga_koperasi_detail.php?id=<?= (int)$l['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
      <?php endforeach; ?>
    </div>
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-people-group lembaga-ic"></i>Gapoktan</h4>
      <?php if (!$gapSaya): ?><p style="font-size:13px;color:var(--muted);">Belum tergabung.</p><?php endif; ?>
      <?php foreach ($gapSaya as $g): ?>
        <p style="margin:4px 0;font-size:14px;"><?= e(($g['kode_gapoktan'] ?: 'GAP') . ' — ' . ($g['nama_gapoktan'] ?: 'Gapoktan')) ?>
          <a class="btn btn-ghost btn-sm" href="gapoktan_detail.php?id=<?= (int)$g['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
      <?php endforeach; ?>
    </div>
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-users lembaga-ic"></i>Kelompok tani</h4>
      <?php if (!$kelSaya): ?><p style="font-size:13px;color:var(--muted);">Belum tergabung.</p><?php endif; ?>
      <?php foreach ($kelSaya as $k): ?>
        <p style="margin:4px 0;font-size:14px;"><?= e(($k['kode_kelompok'] ?: 'KT') . ' — ' . ($k['nama_kelompok'] ?: 'Kelompok')) ?>
          <a class="btn btn-ghost btn-sm" href="kelompok_detail.php?id=<?= (int)$k['id_kelompok'] ?>"><i class="fa-solid fa-eye"></i> Detail</a></p>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
