<?php
require __DIR__ . '/config.php';
require_staff();
ensure_logistik_schema();
$title = 'Invoice PKS';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sjid = (int)$_POST['surat_jalan_id'];
    $nettoPks = (float)$_POST['berat_netto_pks'];
    $kop = (float)$pdo->prepare('SELECT COALESCE(SUM(berat_netto),0) FROM timbangan_tbs WHERE surat_jalan_id=?')->execute([$sjid]) ? 0 : 0;
    $st = $pdo->prepare('SELECT COALESCE(SUM(berat_netto),0) FROM timbangan_tbs WHERE surat_jalan_id=?');
    $st->execute([$sjid]);
    $kop = (float)$st->fetchColumn();
    $susut = $kop - $nettoPks;
    $total = (float)$_POST['total_transfer'];
    $pdo->prepare('INSERT INTO invoice_pks (surat_jalan_id,no_nota_pks,berat_netto_pks,total_transfer,susut_kg,tanggal_cair) VALUES (?,?,?,?,?,?)')
        ->execute([$sjid, trim($_POST['no_nota_pks']), $nettoPks, $total, $susut, $_POST['tanggal_cair'] ?: date('Y-m-d')]);
    $iid = (int)$pdo->lastInsertId();
    posting_jurnal($_POST['tanggal_cair'] ?: date('Y-m-d'), 'Invoice PKS SJ#'.$sjid, [
        ['kode' => '1112', 'posisi' => 'debit', 'nominal' => $total],
        ['kode' => '4113', 'posisi' => 'kredit', 'nominal' => $total],
    ], 'invoice_pks', $iid, auth()['id'] ?? null);
    flash('ok', 'Invoice dicatat. Susut ' . number_format($susut, 1, ',', '.') . ' kg.');
    header('Location: tbs_invoice.php');
    exit;
}

$sj = $pdo->query("SELECT s.*, (SELECT COALESCE(SUM(berat_netto),0) FROM timbangan_tbs t WHERE t.surat_jalan_id=s.id) kg,
    (SELECT COUNT(*) FROM invoice_pks i WHERE i.surat_jalan_id=s.id) inv
    FROM surat_jalan_pks s ORDER BY s.id DESC")->fetchAll();
$inv = $pdo->query("SELECT i.*, s.no_sj, s.pabrik_tujuan FROM invoice_pks i JOIN surat_jalan_pks s ON s.id=i.surat_jalan_id ORDER BY i.id DESC")->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p><a class="btn btn-ghost btn-sm" href="tbs_sj.php">← Surat jalan</a></p>
<div class="cards" style="grid-template-columns:1fr 1fr;margin-top:14px;">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <h3>Input nota PKS</h3>
    <label>Surat jalan</label>
    <select name="surat_jalan_id" required>
      <?php foreach ($sj as $s): if ((int)$s['inv']>0) continue; ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['no_sj'].' · koperasi '.number_format((float)$s['kg'],0,',','.').' kg · '.$s['pabrik_tujuan']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>No. nota timbangan PKS</label>
    <input name="no_nota_pks" required>
    <div class="grid-2">
      <div><label>Netto PKS (kg)</label><input name="berat_netto_pks" type="number" step="0.01" required></div>
      <div><label>Uang transfer PKS</label><input name="total_transfer" required></div>
    </div>
    <label>Tanggal cair</label>
    <input type="date" name="tanggal_cair" value="<?= date('Y-m-d') ?>">
    <button class="btn btn-green" style="margin-top:12px;">Simpan invoice</button>
  </form>
  <div class="card">
    <h3>Invoice tercatat</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>SJ</th><th>Nota PKS</th><th>Netto PKS</th><th>Susut</th><th>Transfer</th></tr></thead>
        <tbody>
        <?php foreach ($inv as $i): ?>
          <tr>
            <td><?= e($i['no_sj']) ?></td>
            <td><?= e($i['no_nota_pks']) ?></td>
            <td><?= number_format((float)$i['berat_netto_pks'],0,',','.') ?></td>
            <td><?= number_format((float)$i['susut_kg'],1,',','.') ?> kg</td>
            <td><?= rupiah($i['total_transfer']) ?></td>
          </tr>
        <?php endforeach; if (!$inv): ?><tr><td colspan="5">Belum ada invoice.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
