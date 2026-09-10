<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Laporan';
$pdo = db();
$simpan = $pdo->query("SELECT a.no_anggota, a.nama, COALESCE(SUM(s.jumlah),0) total FROM anggota a LEFT JOIN " . sql_union_simpanan('s') . " ON s.anggota_id=a.id WHERE a.status IN ('aktif','pasif') GROUP BY a.id ORDER BY a.no_anggota")->fetchAll();
$pinjam = $pdo->query("SELECT a.no_anggota, a.nama, p.no_pinjaman, p.jumlah, p.sisa, p.status, p.bagi_hasil_persen, p.tenor, p.total_tagihan FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE a.status IN ('aktif','pasif') ORDER BY p.id DESC")->fetchAll();
$totS = array_sum(array_column($simpan, 'total'));
$totP = 0;
$totSisa = 0;
$totBagi = 0;
foreach ($pinjam as $p) {
    if ($p['status'] === 'ditolak') {
        continue;
    }
    $totP += (float)$p['jumlah'];
    $totSisa += (float)$p['sisa'];
    $totBagi += max(0, (float)$p['total_tagihan'] - (float)$p['jumlah']);
}
$bulanIni = date('Y-m');
$nmBulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$labelBulan = ($nmBulan[(int)date('n')] ?? date('m')) . ' ' . date('Y');
$angsBulan = [];
$totAngsBulan = 0;
$totPokokBulan = 0;
$totBhBulan = 0;
try {
    $stA = $pdo->prepare("SELECT g.*, p.no_pinjaman, a.nama, a.no_anggota
        FROM angsuran g
        JOIN pinjaman p ON p.id=g.pinjaman_id
        JOIN anggota a ON a.id=p.anggota_id
        WHERE DATE_FORMAT(g.tanggal,'%Y-%m')=?
          AND a.status IN ('aktif','pasif')
        ORDER BY g.tanggal DESC, g.id DESC");
    $stA->execute([$bulanIni]);
    $angsBulan = $stA->fetchAll();
    foreach ($angsBulan as $g) {
        $totAngsBulan += (float)$g['jumlah'];
        $totPokokBulan += (float)($g['pokok'] ?? 0);
        $totBhBulan += (float)($g['bagi_hasil'] ?? 0);
    }
} catch (Throwable $e) {
    $angsBulan = [];
}
include __DIR__ . '/includes/app_header.php';
?>
<div class="kpis">
  <div class="kpi"><span>Akumulasi simpanan</span><b><?= rupiah($totS) ?></b></div>
  <div class="kpi"><span>Pokok tersalur (bukan ditolak)</span><b><?= rupiah($totP) ?></b></div>
  <div class="kpi"><span>Outstanding</span><b><?= rupiah($totSisa) ?></b></div>
  <div class="kpi"><span>Angsuran <?= e($labelBulan) ?></span><b><?= rupiah($totAngsBulan) ?></b></div>
  <div class="kpi"><span>Total bagi hasil</span><b><?= rupiah($totBagi) ?></b></div>
</div>
<div class="cards" style="grid-template-columns:1fr 1.2fr;">
  <div class="card">
    <h3>Rekap simpanan per anggota</h3>
    <div class="table-wrap" style="margin-top:12px;">
      <table>
        <thead><tr><th>No</th><th>Nama</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($simpan as $r): ?>
          <tr><td><?= e($r['no_anggota']) ?></td><td><?= e($r['nama']) ?></td><td><?= rupiah($r['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <h3>Posisi pinjaman</h3>
    <p style="font-size:12px;color:var(--muted);margin:8px 0;">Outstanding dan bagi hasil tidak menghitung pinjaman yang ditolak.</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No</th><th>Anggota</th><th>Pokok</th><th>Bagi hasil</th><th>Sisa</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pinjam as $r):
          $bagi = max(0, (float)$r['total_tagihan'] - (float)$r['jumlah']);
        ?>
          <tr>
            <td><?= e($r['no_pinjaman']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= rupiah($r['jumlah']) ?></td>
            <td><?= rupiah($bagi) ?><br><small><?= e($r['bagi_hasil_persen']) ?>% × <?= (int)$r['tenor'] ?> bln</small></td>
            <td><?= $r['status'] === 'ditolak' ? '—' : rupiah($r['sisa']) ?></td>
            <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<p style="margin-top:16px;font-size:13px;color:var(--muted);">Bagi hasil = total tagihan − pokok. Outstanding = sisa pinjaman yang tidak ditolak.</p>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
