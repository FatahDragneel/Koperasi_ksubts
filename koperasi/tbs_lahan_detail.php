<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT l.*, a.nama, a.no_anggota, a.no_hp, a.status AS status_agt,
    k.kode_kelompok, k.nama_kelompok, k.nama_ketua
  FROM lahan_sawit l
  JOIN anggota a ON a.id=l.anggota_id
  LEFT JOIN kelompok k ON k.id=l.id_kelompok
  WHERE l.id=?");
$st->execute([$id]);
$l = $st->fetch();
if (!$l) {
    flash('err', 'Lahan tidak ditemukan.');
    header('Location: tbs_lahan.php');
    exit;
}

$umur = $l['tahun_tanam'] ? ((int)date('Y') - (int)$l['tahun_tanam']) : null;
$kat = kategori_umur_sawit($l['tahun_tanam'] ? (int)$l['tahun_tanam'] : null);

$title = 'Detail lahan · ' . $l['nama'];
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;"><a class="btn btn-ghost btn-sm" href="tbs_lahan_anggota.php?id=<?= (int)$l['anggota_id'] ?>">← Lahan anggota ini</a></p>
<div class="kpis">
  <div class="kpi"><span>Pemilik</span><b><?= e($l['nama']) ?></b></div>
  <div class="kpi"><span>No. anggota</span><b><?= e($l['no_anggota']) ?></b></div>
  <div class="kpi"><span>Luas bidang ini</span><b><?= number_format((float)$l['luas_hektar'], 2, ',', '.') ?> ha</b></div>
  <div class="kpi"><span>Legalitas</span><b><?= e($l['legalitas'] ?: '—') ?></b></div>
</div>
<p style="margin-bottom:14px;color:var(--muted);font-size:14px;">
  HP <?= e($l['no_hp'] ?: '—') ?> · Status <?= e($l['status_agt']) ?><br>
  Kelompok <?= e(($l['kode_kelompok'] ?: '—') . ($l['nama_kelompok'] ? ' — '.$l['nama_kelompok'] : '')) ?>
  <?= !empty($l['nama_ketua']) ? ' · Ketua '.$l['nama_ketua'] : '' ?>
</p>
<div class="card">
  <h3 style="margin-bottom:12px;">Data bidang lahan</h3>
  <div class="table-wrap">
    <table>
      <tbody>
        <tr><th>Lokasi / desa</th><td><?= e($l['lokasi_desa'] ?: '—') ?></td></tr>
        <tr><th>Tahun tanam</th><td><?= e($l['tahun_tanam'] ?: '—') ?></td></tr>
        <tr><th>Umur / kategori</th><td><?= $umur !== null ? $umur.' tahun · '.$kat : $kat ?></td></tr>
        <tr><th>Jumlah pokok</th><td><?= (int)$l['jumlah_pokok'] ?></td></tr>
        <tr><th>SHM atas nama</th><td><?= e(($l['atas_nama_shm'] ?? '') ?: '—') ?></td></tr>
        <tr><th>No. SHM</th><td><?= e(($l['no_shm'] ?? '') ?: '—') ?></td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
