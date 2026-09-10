<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT t.*, k.kode_kelompok, k.nama_kelompok, k.nama_ketua, k.no_hp_ketua
    FROM timbangan_tbs t
    LEFT JOIN kelompok k ON k.id=t.id_kelompok
    WHERE t.id=?");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    exit('Nota tidak ditemukan');
}
$s = setting();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Nota TBS <?= (int)$r['id'] ?></title>
<style>
  body { font-family: Georgia, serif; margin: 24px; color: #1a241c; }
  h1 { font-size: 18px; margin: 0; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  td, th { border-bottom: 1px solid #ddd; padding: 6px 0; text-align: left; }
  .kop { border-bottom: 3px solid #1b6b3a; padding-bottom: 8px; margin-bottom: 12px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">Cetak</button> <a href="tbs_timbang.php">Kembali</a></p>
<div class="kop">
  <h1><?= e($s['nama_koperasi']) ?></h1>
  <small>Nota timbangan TBS kelompok #<?= (int)$r['id'] ?> · <?= tgl($r['tanggal']) ?></small>
</div>
<table>
  <tr><th>Kelompok</th><td><?= e(($r['kode_kelompok'] ?: '').' '.$r['nama_kelompok']) ?></td></tr>
  <tr><th>Ketua</th><td><?= e($r['nama_ketua'] ?: '—') ?> · <?= e($r['no_hp_ketua'] ?: '') ?></td></tr>
  <tr><th>No. polisi</th><td><?= e($r['no_polisi'] ?: '—') ?></td></tr>
  <tr><th>Masuk / keluar</th><td><?= $r['berat_masuk'] ?> / <?= $r['berat_keluar'] ?> kg</td></tr>
  <tr><th>Bruto</th><td><?= $r['berat_bruto'] ?> kg</td></tr>
  <tr><th>Potongan grading</th><td><?= $r['persen_potongan'] ?>%</td></tr>
  <tr><th>Netto</th><td><?= $r['berat_netto'] ?> kg (<?= e($r['kategori_umur']) ?>)</td></tr>
  <tr><th>Harga beli / kg</th><td><?= rupiah($r['harga_per_kg']) ?></td></tr>
  <tr><th>Bruto uang</th><td><?= rupiah($r['total_bruto_uang']) ?></td></tr>
  <tr><th>Fee kelompok</th><td><?= rupiah($r['fee_kelompok']) ?> (<?= rupiah($r['fee_kelompok_per_kg']) ?>/kg)</td></tr>
  <tr><th>Potong saprodi</th><td><?= rupiah($r['potong_saprodi'] ?? 0) ?></td></tr>
  <tr><th>Potong angsuran</th><td><?= rupiah($r['potong_angsuran']) ?></td></tr>
  <tr><th>Bersih kelompok</th><td><strong><?= rupiah($r['bersih_petani']) ?></strong></td></tr>
</table>
<p style="margin-top:32px;">Ketua kelompok ________________ &nbsp;&nbsp; Petugas ________________</p>
</body>
</html>
