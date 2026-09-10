<?php
require __DIR__ . '/config.php';
require_staff();
ensure_akuntansi_schema();
$title = 'Jurnal umum';
$pdo = db();
$u = auth();
$akun = $pdo->query('SELECT kode, nama FROM coa_akun ORDER BY kode')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baris = [];
    foreach ($_POST['kode'] ?? [] as $i => $kode) {
        $nom = (float)str_replace('.', '', $_POST['nominal'][$i] ?? 0);
        $pos = ($_POST['posisi'][$i] ?? '') === 'kredit' ? 'kredit' : 'debit';
        if ($kode && $nom > 0) {
            $baris[] = ['kode' => $kode, 'posisi' => $pos, 'nominal' => $nom];
        }
    }
    $jid = posting_jurnal($_POST['tanggal'] ?: date('Y-m-d'), trim($_POST['keterangan'] ?? 'Jurnal manual'), $baris, 'manual', null, $u['id']);
    if ($jid) {
        flash('ok', 'Jurnal tersimpan.');
    } else {
        flash('err', 'Jurnal ditolak. Debit harus sama dengan kredit dan lebih dari 0.');
    }
    header('Location: akuntansi_jurnal.php');
    exit;
}

$list = $pdo->query('SELECT * FROM jurnal ORDER BY id DESC LIMIT 80')->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p><a class="btn btn-ghost btn-sm" href="akuntansi.php">← Akuntansi</a>
<button class="btn btn-green" type="button" onclick="openModal('mJur')">+ Jurnal manual</button></p>

<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>No. bukti</th><th>Tanggal</th><th>Keterangan</th><th>Sumber</th><th>Debit</th><th>Kredit</th></tr></thead>
    <tbody>
    <?php foreach ($list as $j):
      $d = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM jurnal_detail WHERE jurnal_id=? AND posisi='debit'");
      $d->execute([$j['id']]);
      $deb = $d->fetchColumn();
      $c = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM jurnal_detail WHERE jurnal_id=? AND posisi='kredit'");
      $c->execute([$j['id']]);
      $kre = $c->fetchColumn();
    ?>
      <tr>
        <td><?= e($j['no_bukti']) ?></td>
        <td><?= tgl($j['tanggal']) ?></td>
        <td><?= e($j['keterangan']) ?></td>
        <td><?= e($j['sumber'] ?: 'manual') ?></td>
        <td><?= rupiah($deb) ?></td>
        <td><?= rupiah($kre) ?></td>
      </tr>
    <?php endforeach; if (!$list): ?>
      <tr><td colspan="6">Belum ada jurnal. Transaksi baru akan otomatis masuk.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mJur">
  <form class="modal" method="post" style="width:min(640px,100%);">
    <?= csrf_field() ?>
    <h3>Jurnal manual</h3>
    <p style="font-size:13px;color:var(--muted);">Minimal 2 baris. Total debit = total kredit.</p>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Keterangan</label><input name="keterangan" required></div>
    </div>
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="grid-2" style="margin-top:8px;">
      <div>
        <label>Akun</label>
        <select name="kode[]">
          <option value="">—</option>
          <?php foreach ($akun as $a): ?>
            <option value="<?= e($a['kode']) ?>"><?= e($a['kode'].' '.$a['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Posisi / nominal</label>
        <div class="row">
          <select name="posisi[]" style="max-width:120px;"><option value="debit">Debit</option><option value="kredit">Kredit</option></select>
          <input name="nominal[]" placeholder="0">
        </div>
      </div>
    </div>
    <?php endfor; ?>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mJur')">Batal</button>
      <button class="btn btn-green">Posting</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
