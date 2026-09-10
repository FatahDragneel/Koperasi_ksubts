<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
ensure_logistik_schema();
$title = 'Surat jalan PKS';
$pdo = db();
$u = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'buat';
    if ($act === 'buat') {
        $no = 'SJ-' . date('Ymd') . '-' . str_pad((string)(1 + (int)$pdo->query('SELECT COUNT(*) FROM surat_jalan_pks')->fetchColumn()), 3, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO surat_jalan_pks (no_sj,tanggal,no_plat,nama_sopir,pabrik_tujuan,estimasi_tonase,status,created_by) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$no, $_POST['tanggal'] ?: date('Y-m-d'), trim($_POST['no_plat']), trim($_POST['nama_sopir']), trim($_POST['pabrik_tujuan']), (float)$_POST['estimasi_tonase'], 'berangkat', $u['id']]);
        $sj = (int)$pdo->lastInsertId();
        foreach ($_POST['timbang'] ?? [] as $tid) {
            $pdo->prepare('UPDATE timbangan_tbs SET surat_jalan_id=? WHERE id=? AND surat_jalan_id IS NULL')->execute([$sj, (int)$tid]);
        }
        flash('ok', "Surat jalan $no dibuat.");
    }
    header('Location: tbs_sj.php');
    exit;
}

$open = $pdo->query("SELECT t.*, k.kode_kelompok, k.nama_kelompok FROM timbangan_tbs t LEFT JOIN kelompok k ON k.id=t.id_kelompok WHERE t.surat_jalan_id IS NULL ORDER BY t.id DESC LIMIT 80")->fetchAll();
$list = $pdo->query('SELECT s.*, (SELECT COALESCE(SUM(berat_netto),0) FROM timbangan_tbs t WHERE t.surat_jalan_id=s.id) kg FROM surat_jalan_pks s ORDER BY s.id DESC')->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p><a class="btn btn-ghost btn-sm" href="tbs_timbang.php">← Timbangan</a> <a class="btn btn-ghost btn-sm" href="tbs_invoice.php">Invoice PKS</a></p>
<div class="cards" style="grid-template-columns:1fr 1fr;margin-top:14px;">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="buat">
    <h3>Buat surat jalan</h3>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>No. plat</label><input name="no_plat" required></div>
    </div>
    <div class="grid-2">
      <div><label>Sopir</label><input name="nama_sopir" required></div>
      <div><label>PKS tujuan</label><input name="pabrik_tujuan" required placeholder="Nama pabrik"></div>
    </div>
    <label>Estimasi tonase (ton)</label>
    <input name="estimasi_tonase" type="number" step="0.01" min="0" value="0">
    <label>Muat timbangan belum berangkat</label>
    <div style="max-height:180px;overflow:auto;border:1px solid #efe8d8;border-radius:10px;padding:8px;">
      <?php foreach ($open as $o): ?>
        <label class="check" style="margin:4px 0;"><input type="checkbox" name="timbang[]" value="<?= (int)$o['id'] ?>" style="width:auto;">
          <span>#<?= (int)$o['id'] ?> <?= e($o['kode_kelompok'] ?: 'Kelompok') ?> · <?= number_format((float)$o['berat_netto'],0,',','.') ?> kg</span>
        </label>
      <?php endforeach; if (!$open): ?><p>Semua timbangan sudah masuk SJ.</p><?php endif; ?>
    </div>
    <button class="btn btn-green" style="margin-top:12px;">Simpan SJ</button>
  </form>
  <div class="card">
    <h3>Daftar surat jalan</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>No</th><th>Truk / sopir</th><th>PKS</th><th>Netto koperasi</th></tr></thead>
        <tbody>
        <?php foreach ($list as $s): ?>
          <tr>
            <td><?= e($s['no_sj']) ?><br><small><?= tgl($s['tanggal']) ?></small></td>
            <td><?= e($s['no_plat']) ?><br><small><?= e($s['nama_sopir']) ?></small></td>
            <td><?= e($s['pabrik_tujuan']) ?></td>
            <td><?= number_format((float)$s['kg'], 0, ',', '.') ?> kg</td>
          </tr>
        <?php endforeach; if (!$list): ?><tr><td colspan="4">Belum ada SJ.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
