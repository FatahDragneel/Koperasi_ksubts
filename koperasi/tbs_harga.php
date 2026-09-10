<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
$title = 'Harga TBS harian';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tgl = $_POST['tanggal_berlaku'] ?: date('Y-m-d');
    $ins = $pdo->prepare('INSERT INTO harga_tbs (tanggal_berlaku,kategori,harga_beli,harga_jual_pks) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE harga_beli=VALUES(harga_beli), harga_jual_pks=VALUES(harga_jual_pks)');
    foreach (['muda', 'dewasa', 'super'] as $kat) {
        $ins->execute([
            $tgl,
            $kat,
            (float)($_POST['beli_'.$kat] ?? 0),
            (float)($_POST['jual_'.$kat] ?? 0),
        ]);
    }
    flash('ok', 'Harga TBS ' . $tgl . ' disimpan.');
    header('Location: tbs_harga.php');
    exit;
}

$hari = $pdo->query('SELECT * FROM harga_tbs ORDER BY tanggal_berlaku DESC, kategori LIMIT 90')->fetchAll();
$byTgl = [];
foreach ($hari as $h) {
    $byTgl[$h['tanggal_berlaku']][$h['kategori']] = $h;
}
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;color:var(--muted);">Harga beli petani dan jual ke PKS per kg, menurut umur tanaman: muda (≤5 th), dewasa (6–10 th), super (&gt;10 th).</p>
<form method="post" class="card" style="max-width:720px;margin-bottom:20px;">
  <?= csrf_field() ?>
  <h3>Set harga</h3>
  <label>Tanggal berlaku</label>
  <input type="date" name="tanggal_berlaku" value="<?= date('Y-m-d') ?>" required>
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead><tr><th>Kategori</th><th>Harga beli petani / kg</th><th>Harga jual PKS / kg</th></tr></thead>
      <tbody>
        <?php foreach (['muda'=>'Muda (3–5 th)','dewasa'=>'Dewasa (6–10 th)','super'=>'Super (>10 th)'] as $k=>$lab): ?>
        <tr>
          <td><?= e($lab) ?></td>
          <td><input name="beli_<?= $k ?>" type="number" step="1" min="0" required></td>
          <td><input name="jual_<?= $k ?>" type="number" step="1" min="0" required></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button class="btn btn-green" style="margin-top:14px;">Simpan harga</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Tanggal</th><th>Muda beli / PKS</th><th>Dewasa beli / PKS</th><th>Super beli / PKS</th></tr></thead>
    <tbody>
    <?php foreach ($byTgl as $tgl => $kats): ?>
      <tr>
        <td><?= tgl($tgl) ?></td>
        <?php foreach (['muda','dewasa','super'] as $k): $x = $kats[$k] ?? null; ?>
        <td><?= $x ? rupiah($x['harga_beli']).' / '.rupiah($x['harga_jual_pks']) : '—' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; if (!$byTgl): ?>
      <tr><td colspan="4">Belum ada harga. Isi dulu sebelum menimbang.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
