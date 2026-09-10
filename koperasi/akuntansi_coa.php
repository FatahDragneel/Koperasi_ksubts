<?php
require __DIR__ . '/config.php';
require_staff();
ensure_akuntansi_schema();
$title = 'Bagan akun (COA)';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = preg_replace('/\D/', '', $_POST['kode'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $kat = $_POST['kategori'] ?? 'Aset';
    $sn = ($_POST['saldo_normal'] ?? '') === 'kredit' ? 'kredit' : 'debit';
    if (strlen($kode) < 4 || $nama === '') {
        flash('err', 'Kode min. 4 digit dan nama wajib.');
    } else {
        $pdo->prepare('INSERT INTO coa_akun (kode,nama,kategori,saldo_normal) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nama=VALUES(nama), kategori=VALUES(kategori), saldo_normal=VALUES(saldo_normal)')
            ->execute([$kode, $nama, $kat, $sn]);
        flash('ok', 'Akun disimpan.');
    }
    header('Location: akuntansi_coa.php');
    exit;
}
$rows = $pdo->query('SELECT * FROM coa_akun ORDER BY kode')->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p><a class="btn btn-ghost btn-sm" href="akuntansi.php">← Akuntansi</a></p>
<form method="post" class="card" style="max-width:560px;margin:16px 0;">
  <?= csrf_field() ?>
  <h3>Tambah / ubah akun</h3>
  <div class="grid-2">
    <div><label>Kode</label><input name="kode" required placeholder="1113"></div>
    <div><label>Nama</label><input name="nama" required></div>
  </div>
  <div class="grid-2">
    <div>
      <label>Kategori</label>
      <select name="kategori">
        <?php foreach (['Aset','Kewajiban','Ekuitas','Pendapatan','Biaya'] as $k): ?>
          <option><?= $k ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Saldo normal</label>
      <select name="saldo_normal"><option value="debit">Debit</option><option value="kredit">Kredit</option></select>
    </div>
  </div>
  <button class="btn btn-green" style="margin-top:12px;">Simpan akun</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Saldo normal</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['kode']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['kategori']) ?></td>
        <td><?= e($r['saldo_normal']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
