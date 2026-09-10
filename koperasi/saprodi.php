<?php
require __DIR__ . '/config.php';
require_staff();
ensure_logistik_schema();
$title = 'Saprodi';
$pdo = db();
$u = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (float)$_POST['kuantitas'];
    $hs = (float)str_replace('.', '', $_POST['harga_satuan'] ?? '0');
    $tot = $qty * $hs;
    $status = ($_POST['status_bayar'] ?? '') === 'tunai' ? 'lunas' : 'piutang';
    $pdo->prepare('INSERT INTO saprodi (tanggal,anggota_id,item_barang,kuantitas,harga_satuan,total_harga,status_bayar,sisa_piutang,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([
            $_POST['tanggal'] ?: date('Y-m-d'),
            (int)$_POST['anggota_id'],
            trim($_POST['item_barang']),
            $qty, $hs, $tot, $status, $status === 'lunas' ? 0 : $tot, $u['id'],
        ]);
    $sid = (int)$pdo->lastInsertId();
    if ($status === 'lunas') {
        posting_jurnal(date('Y-m-d'), 'Saprodi tunai', [
            ['kode' => '1111', 'posisi' => 'debit', 'nominal' => $tot],
            ['kode' => '4112', 'posisi' => 'kredit', 'nominal' => $tot],
        ], 'saprodi', $sid, $u['id']);
    } else {
        posting_jurnal(date('Y-m-d'), 'Piutang saprodi', [
            ['kode' => '1212', 'posisi' => 'debit', 'nominal' => $tot],
            ['kode' => '4112', 'posisi' => 'kredit', 'nominal' => $tot],
        ], 'saprodi', $sid, $u['id']);
    }
    flash('ok', 'Nota saprodi tercatat.');
    header('Location: saprodi.php');
    exit;
}

$anggota = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();
$rows = $pdo->query("SELECT s.*, a.nama, a.no_anggota FROM saprodi s JOIN anggota a ON a.id=s.anggota_id ORDER BY s.id DESC LIMIT 150")->fetchAll();
$piutang = (float)$pdo->query("SELECT COALESCE(SUM(sisa_piutang),0) FROM saprodi")->fetchColumn();
include __DIR__ . '/includes/app_header.php';
?>
<div class="kpis"><div class="kpi"><span>Piutang saprodi</span><b><?= rupiah($piutang) ?></b></div></div>
<div class="row" style="margin-bottom:14px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="openModal('mSap')">+ Nota saprodi</button>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Tanggal</th><th>Anggota</th><th>Barang</th><th>Qty</th><th>Total</th><th>Sisa piutang</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= tgl($r['tanggal']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['item_barang']) ?></td>
        <td><?= e($r['kuantitas']) ?></td>
        <td><?= rupiah($r['total_harga']) ?></td>
        <td><?= rupiah($r['sisa_piutang']) ?></td>
        <td><span class="badge <?= $r['status_bayar']==='lunas'?'b-lunas':'b-pengajuan' ?>"><?= e($r['status_bayar']) ?></span></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7">Belum ada nota.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="modal-bg" id="mSap">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <h3>Penjualan saprodi</h3>
    <label>Anggota</label>
    <select name="anggota_id" required>
      <?php foreach ($anggota as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['no_anggota'].' — '.$a['nama']) ?></option><?php endforeach; ?>
    </select>
    <label>Item (pupuk, herbisida, dodos…)</label>
    <input name="item_barang" required>
    <div class="grid-2">
      <div><label>Kuantitas</label><input name="kuantitas" type="number" step="0.01" value="1" required></div>
      <div><label>Harga satuan</label><input name="harga_satuan" required></div>
    </div>
    <label>Pembayaran</label>
    <select name="status_bayar"><option value="piutang">Piutang (potong panen)</option><option value="tunai">Lunas tunai</option></select>
    <label>Tanggal</label>
    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
    <div class="row" style="margin-top:14px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mSap')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
