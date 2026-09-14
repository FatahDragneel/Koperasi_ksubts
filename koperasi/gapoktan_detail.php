<?php
require __DIR__ . '/config.php';
require_staff();
ensure_gapoktan_schema();
ensure_kelompok_schema();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['gapoktan_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM gapoktan WHERE id=?');
$st->execute([$id]);
$g = $st->fetch();
if (!$g) {
    flash('err', 'Gapoktan tidak ditemukan.');
    header('Location: gapoktan.php');
    exit;
}
$title = 'Detail Gapoktan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'profil') {
        $pdo->prepare('UPDATE gapoktan SET kode_gapoktan=?, nama_gapoktan=?, nama_ketua=?, no_hp_ketua=?, alamat=?, keterangan=?, id_koperasi=? WHERE id=?')->execute([
            trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
            trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''),
            (int)($_POST['id_koperasi'] ?? 0) ?: null, $id,
        ]);
        flash('ok', 'Profil gapoktan disimpan.');
    } elseif ($act === 'masuk') {
        $kid = (int)($_POST['kelompok_id'] ?? 0);
        if ($kid < 1) {
            flash('err', 'Pilih kelompok tani.');
        } else {
            $pdo->prepare('UPDATE kelompok SET id_gapoktan=? WHERE id=?')->execute([$id, $kid]);
            flash('ok', 'Kelompok tani dimasukkan ke gapoktan ini.');
        }
    } elseif ($act === 'keluar') {
        $kid = (int)($_POST['kelompok_id'] ?? 0);
        $pdo->prepare('UPDATE kelompok SET id_gapoktan=NULL WHERE id=? AND id_gapoktan=?')->execute([$kid, $id]);
        flash('ok', 'Kelompok tani dikeluarkan dari gapoktan ini.');
    }
    header('Location: gapoktan_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM gapoktan WHERE id=?');
$st->execute([$id]);
$g = $st->fetch();
$anggotas = $pdo->prepare("SELECT k.*, (SELECT COUNT(*) FROM anggota_kelompok ak JOIN anggota a ON a.id=ak.anggota_id WHERE ak.id_kelompok=k.id AND a.status='aktif') jml FROM kelompok k WHERE k.id_gapoktan=? ORDER BY k.nomor");
$anggotas->execute([$id]);
$anggotas = $anggotas->fetchAll();
$bebas = $pdo->query('SELECT id, kode_kelompok, nama_kelompok FROM kelompok WHERE id_gapoktan IS NULL ORDER BY nomor')->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin:0 0 10px;"><a href="gapoktan.php">← Kembali ke Gapoktan</a></p>
<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>Profil Gapoktan</h3>
    <form method="post" style="margin-top:10px;">
      <?= csrf_field() ?>
      <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="profil">
      <label>Kode gapoktan<input name="kode" value="<?= e($g['kode_gapoktan'] ?? '') ?>" required></label>
      <label>Nama gapoktan<input name="nama" value="<?= e($g['nama_gapoktan'] ?? '') ?>" required></label>
      <label>Nama ketua<input name="ketua" value="<?= e($g['nama_ketua'] ?? '') ?>"></label>
      <label>No. HP ketua<input name="hp" value="<?= e($g['no_hp_ketua'] ?? '') ?>"></label>
      <label>Alamat<textarea name="alamat" rows="2"><?= e($g['alamat'] ?? '') ?></textarea></label>
      <label>Koperasi induk<select name="id_koperasi"><option value="0">— belum masuk koperasi —</option><?= options_lembaga_koperasi((int)($g['id_koperasi'] ?? 0)) ?></select></label>
      <label>Keterangan<textarea name="ket" rows="2"><?= e($g['keterangan'] ?? '') ?></textarea></label>
      <button class="btn btn-green" type="submit">Simpan profil</button>
    </form>
  </div>
  <div class="card">
    <h3>Kelompok tani anggota (<?= count($anggotas) ?>)</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;">
      <?= csrf_field() ?>
      <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="kelompok_id" style="flex:1;">
        <option value="0">— pilih kelompok tani —</option>
        <?php foreach ($bebas as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= e(($b['kode_kelompok'] ?: 'KT') . ' — ' . ($b['nama_kelompok'] ?: 'Kelompok')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit">+ Masukkan</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Anggota</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (!$anggotas): ?>
          <tr><td colspan="4">Belum ada kelompok tani di gapoktan ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($anggotas as $k): ?>
          <tr>
            <td><?= e($k['kode_kelompok'] ?? '') ?></td>
            <td><a href="kelompok_detail.php?id=<?= (int)$k['id'] ?>"><?= e($k['nama_kelompok'] ?? '') ?></a></td>
            <td><?= (int)$k['jml'] ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Keluarkan kelompok ini dari gapoktan?')">
                <?= csrf_field() ?>
                <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
                <input type="hidden" name="act" value="keluar">
                <input type="hidden" name="kelompok_id" value="<?= (int)$k['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Keluarkan</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
