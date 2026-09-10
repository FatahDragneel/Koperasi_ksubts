<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kas_schema();
ensure_logistik_schema();
$title = 'SHU — Sisa Hasil Usaha';
$pdo = db();

$tahun = (int)($_GET['tahun'] ?? date('Y'));
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

$pinjam = $pdo->prepare("SELECT p.*, a.nama, a.no_anggota FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE YEAR(p.tanggal)=? AND p.status NOT IN ('ditolak','pengajuan')");
$pinjam->execute([$tahun]);
$pinjam = $pinjam->fetchAll();

$jasaPinjam = 0;
$jasaPerAnggota = [];
foreach ($pinjam as $p) {
    $jasa = max(0, (float)$p['total_tagihan'] - (float)$p['jumlah']);
    $jasaPinjam += $jasa;
    $aid = (int)$p['anggota_id'];
    $jasaPerAnggota[$aid] = ($jasaPerAnggota[$aid] ?? 0) + $jasa;
}

$simpan = $pdo->prepare("SELECT s.anggota_id, a.nama, a.no_anggota, COALESCE(SUM(s.jumlah),0) total FROM simpanan s JOIN anggota a ON a.id=s.anggota_id WHERE YEAR(s.tanggal)<=? AND a.status='aktif' GROUP BY s.anggota_id");
$simpan->execute([$tahun]);
$simpan = $simpan->fetchAll();
$totSimpan = 0;
$simpanMap = [];
foreach ($simpan as $s) {
    $totSimpan += (float)$s['total'];
    $simpanMap[(int)$s['anggota_id']] = $s;
}

$stBiaya = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE arah='keluar' AND YEAR(tanggal)=?");
$stBiaya->execute([$tahun]);
$biaya = (float)$stBiaya->fetchColumn();
$pendapatanLain = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE arah='masuk' AND YEAR(tanggal)=?");
$pendapatanLain->execute([$tahun]);
$pendapatanLain = (float)$pendapatanLain->fetchColumn();

$pendapatan = $jasaPinjam + $pendapatanLain;
$shu = $pendapatan - $biaya;
$cadangan = max(0, $shu * 0.40);
$dibagi = max(0, $shu * 0.60);

$tbsMap = [];
$totKg = 0;
$tbs = $pdo->prepare("SELECT anggota_id, id_kelompok, COALESCE(SUM(berat_netto),0) kg FROM timbangan_tbs WHERE YEAR(tanggal)=? GROUP BY anggota_id, id_kelompok");
$tbs->execute([$tahun]);
foreach ($tbs as $t) {
    $kg = (float)$t['kg'];
    $totKg += $kg;
    if (!empty($t['anggota_id'])) {
        $aid = (int)$t['anggota_id'];
        $tbsMap[$aid] = ($tbsMap[$aid] ?? 0) + $kg;
        continue;
    }
    $ids = ids_anggota_kelompok((int)$t['id_kelompok']);
    if (!$ids) {
        continue;
    }
    $bagi = $kg / count($ids);
    foreach ($ids as $aid) {
        $tbsMap[$aid] = ($tbsMap[$aid] ?? 0) + $bagi;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'simpan_shu') {
    $ins = $pdo->prepare('INSERT INTO shu_alokasi (tahun_buku,anggota_id,jasa_modal,jasa_usaha,total_shu) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE jasa_modal=VALUES(jasa_modal), jasa_usaha=VALUES(jasa_usaha), total_shu=VALUES(total_shu)');
    $n = 0;
    foreach (array_unique(array_merge(array_keys($simpanMap), array_keys($tbsMap))) as $aid) {
        $sp = (float)($simpanMap[$aid]['total'] ?? 0);
        $kg = (float)($tbsMap[$aid] ?? 0);
        $jm = $totSimpan > 0 ? $dibagi * 0.5 * ($sp / $totSimpan) : 0;
        $ju = $totKg > 0 ? $dibagi * 0.5 * ($kg / $totKg) : 0;
        $ins->execute([$tahun, $aid, $jm, $ju, $jm + $ju]);
        $n++;
    }
    flash('ok', "Alokasi SHU $tahun disimpan untuk $n anggota.");
    header('Location: shu.php?tahun=' . $tahun);
    exit;
}

$ids = [];
foreach ($simpanMap as $id => $_) { $ids[$id] = true; }
foreach ($jasaPerAnggota as $id => $_) { $ids[$id] = true; }
foreach ($tbsMap as $id => $_) { $ids[$id] = true; }
$rows = [];
foreach (array_keys($ids) as $aid) {
    $nm = $simpanMap[$aid]['nama'] ?? '';
    $no = $simpanMap[$aid]['no_anggota'] ?? '';
    if ($nm === '') {
        $q = $pdo->prepare('SELECT nama, no_anggota FROM anggota WHERE id=?');
        $q->execute([$aid]);
        $r = $q->fetch();
        $nm = $r['nama'] ?? ('#'.$aid);
        $no = $r['no_anggota'] ?? '';
    }
    $sp = (float)($simpanMap[$aid]['total'] ?? 0);
    $kg = (float)($tbsMap[$aid] ?? 0);
    $jp = (float)($jasaPerAnggota[$aid] ?? 0);
    $jm = $totSimpan > 0 ? $dibagi * 0.5 * ($sp / $totSimpan) : 0;
    $ju = $totKg > 0 ? $dibagi * 0.5 * ($kg / $totKg) : 0;
    $bagian = $jm + $ju;
    $rows[] = compact('aid', 'no', 'nm', 'sp', 'kg', 'jp', 'jm', 'ju', 'bagian');
}
usort($rows, fn($a, $b) => $b['bagian'] <=> $a['bagian']);

include __DIR__ . '/includes/app_header.php';
?>
<form method="get" class="row" style="margin-bottom:16px;">
  <label style="margin:0;">Tahun</label>
  <input type="number" name="tahun" value="<?= $tahun ?>" style="max-width:120px;">
  <button class="btn btn-ghost" type="submit">Tampilkan</button>
</form>

<div class="kpis">
  <div class="kpi"><span>Pendapatan bagi hasil pinjaman</span><b><?= rupiah($jasaPinjam) ?></b></div>
  <div class="kpi"><span>Pendapatan kas lain</span><b><?= rupiah($pendapatanLain) ?></b></div>
  <div class="kpi"><span>Biaya (kas keluar)</span><b><?= rupiah($biaya) ?></b></div>
  <div class="kpi"><span>SHU <?= $tahun ?></span><b><?= rupiah($shu) ?></b></div>
</div>
<p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
  SHU = (bagi hasil pinjaman + kas masuk lain) − pengeluaran kas tahun ini.
  Bagi hasil = total tagihan − pokok, pinjaman ditolak/pengajuan tidak dihitung.
  Pembagian 60% anggota: 50% jasa modal (simpanan), 50% jasa usaha (tonase TBS tahun ini).
</p>

<div class="cards" style="grid-template-columns:1fr 1fr;margin-bottom:18px;">
  <div class="card">
    <h3>Alokasi</h3>
    <p style="margin-top:10px;"><strong>Cadangan 40%</strong><br><?= rupiah($cadangan) ?></p>
    <p><strong>Dibagi anggota 60%</strong><br><?= rupiah($dibagi) ?></p>
  </div>
  <div class="card">
    <h3>Dasar bagi</h3>
    <p style="margin-top:10px;"><strong>Total simpanan (s/d <?= $tahun ?>)</strong><br><?= rupiah($totSimpan) ?></p>
    <p><strong>Total jasa pinjaman tahun ini</strong><br><?= rupiah($jasaPinjam) ?></p>
    <p><strong>Total TBS netto tahun ini</strong><br><?= number_format($totKg, 0, ',', '.') ?> kg</p>
    <form method="post" style="margin-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="simpan_shu">
      <button class="btn btn-green">Simpan alokasi SHU <?= $tahun ?></button>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>No. Anggota</th>
        <th>Nama</th>
        <th>Simpanan</th>
        <th>TBS (kg)</th>
        <th>Jasa modal</th>
        <th>Jasa usaha</th>
        <th>Total SHU</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['no']) ?></td>
        <td><?= e($r['nm']) ?></td>
        <td><?= rupiah($r['sp']) ?></td>
        <td><?= number_format($r['kg'], 0, ',', '.') ?></td>
        <td><?= rupiah($r['jm']) ?></td>
        <td><?= rupiah($r['ju']) ?></td>
        <td><strong><?= rupiah($r['bagian']) ?></strong></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="7">Belum ada data untuk tahun ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
