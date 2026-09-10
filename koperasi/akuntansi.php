<?php
require __DIR__ . '/config.php';
require_staff();
ensure_akuntansi_schema();
$title = 'Akuntansi';
$pdo = db();

$neraca = $pdo->query("
  SELECT c.kode, c.nama, c.kategori, c.saldo_normal,
    COALESCE(SUM(CASE WHEN d.posisi='debit' THEN d.nominal ELSE 0 END),0) debit,
    COALESCE(SUM(CASE WHEN d.posisi='kredit' THEN d.nominal ELSE 0 END),0) kredit
  FROM coa_akun c
  LEFT JOIN jurnal_detail d ON d.kode_akun=c.kode
  GROUP BY c.kode
  ORDER BY c.kode
")->fetchAll();

$totD = 0;
$totK = 0;
foreach ($neraca as &$r) {
    $saldo = $r['saldo_normal'] === 'debit' ? (float)$r['debit'] - (float)$r['kredit'] : (float)$r['kredit'] - (float)$r['debit'];
    $r['saldo'] = $saldo;
    $totD += (float)$r['debit'];
    $totK += (float)$r['kredit'];
}
unset($r);

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;">
  <a class="btn btn-green" href="akuntansi_jurnal.php">Jurnal umum</a>
  <a class="btn btn-ghost" href="akuntansi_coa.php">Bagan akun (COA)</a>
</p>
<div class="kpis">
  <div class="kpi"><span>Total debit</span><b><?= rupiah($totD) ?></b></div>
  <div class="kpi"><span>Total kredit</span><b><?= rupiah($totK) ?></b></div>
  <div class="kpi"><span>Selisih</span><b><?= rupiah($totD - $totK) ?></b></div>
  <div class="kpi"><span>Status</span><b><?= abs($totD - $totK) < 1 ? 'Seimbang' : 'Tidak balance' ?></b></div>
</div>
<p style="font-size:13px;color:var(--muted);margin-bottom:12px;">Neraca saldo. Transaksi simpanan, pencairan pinjaman, angsuran, kas, dan TBS otomatis dijurnal (berpasangan).</p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Kode</th><th>Akun</th><th>Kategori</th><th>Debit</th><th>Kredit</th><th>Saldo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($neraca as $r): ?>
      <tr>
        <td><?= e($r['kode']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['kategori']) ?></td>
        <td><?= rupiah($r['debit']) ?></td>
        <td><?= rupiah($r['kredit']) ?></td>
        <td><?= rupiah($r['saldo']) ?></td>
        <td><a class="btn btn-ghost btn-sm" href="akuntansi_buku.php?kode=<?= e($r['kode']) ?>">Buku besar</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
