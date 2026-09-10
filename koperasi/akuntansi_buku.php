<?php
require __DIR__ . '/config.php';
require_staff();
ensure_akuntansi_schema();
$kode = preg_replace('/\D/', '', $_GET['kode'] ?? '1111');
$st = db()->prepare('SELECT * FROM coa_akun WHERE kode=?');
$st->execute([$kode]);
$akun = $st->fetch();
if (!$akun) {
    flash('err', 'Akun tidak ada.');
    header('Location: akuntansi.php');
    exit;
}
$title = 'Buku besar ' . $akun['kode'];
$rows = db()->prepare("SELECT j.tanggal, j.no_bukti, j.keterangan, d.posisi, d.nominal
    FROM jurnal_detail d JOIN jurnal j ON j.id=d.jurnal_id
    WHERE d.kode_akun=? ORDER BY j.tanggal, j.id");
$rows->execute([$kode]);
$rows = $rows->fetchAll();
$saldo = 0;
include __DIR__ . '/includes/app_header.php';
?>
<p><a class="btn btn-ghost btn-sm" href="akuntansi.php">← Neraca saldo</a></p>
<p style="margin:10px 0;"><?= e($akun['kode'].' — '.$akun['nama']) ?> · saldo normal <?= e($akun['saldo_normal']) ?></p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Tanggal</th><th>Bukti</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      if ($akun['saldo_normal'] === 'debit') {
          $saldo += $r['posisi'] === 'debit' ? (float)$r['nominal'] : -(float)$r['nominal'];
      } else {
          $saldo += $r['posisi'] === 'kredit' ? (float)$r['nominal'] : -(float)$r['nominal'];
      }
    ?>
      <tr>
        <td><?= tgl($r['tanggal']) ?></td>
        <td><?= e($r['no_bukti']) ?></td>
        <td><?= e($r['keterangan']) ?></td>
        <td><?= $r['posisi']==='debit' ? rupiah($r['nominal']) : '—' ?></td>
        <td><?= $r['posisi']==='kredit' ? rupiah($r['nominal']) : '—' ?></td>
        <td><?= rupiah($saldo) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6">Belum ada mutasi.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
