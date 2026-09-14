<?php
require __DIR__ . '/config.php';
require_staff();
ensure_lembaga_koperasi_schema();
ensure_gapoktan_schema();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['koperasi_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM lembaga_koperasi WHERE id=?');
$st->execute([$id]);
$l = $st->fetch();
if (!$l) {
    flash('err', 'Koperasi tidak ditemukan.');
    header('Location: lembaga_koperasi.php');
    exit;
}
$title = 'Detail Koperasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'profil') {
        $pdo->prepare('UPDATE lembaga_koperasi SET kode_koperasi=?, nama_koperasi=?, nama_ketua=?, no_hp_ketua=?, alamat=?, keterangan=? WHERE id=?')->execute([
            trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
            trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
        ]);
        flash('ok', 'Profil koperasi disimpan.');
    } elseif ($act === 'masuk') {
        $gid = (int)($_POST['gapoktan_id'] ?? 0);
        if ($gid < 1) {
            flash('err', 'Pilih gapoktan.');
        } else {
            $pdo->prepare('UPDATE gapoktan SET id_koperasi=? WHERE id=?')->execute([$id, $gid]);
            flash('ok', 'Gapoktan dimasukkan ke koperasi ini.');
        }
    } elseif ($act === 'keluar') {
        $gid = (int)($_POST['gapoktan_id'] ?? 0);
        $pdo->prepare('UPDATE gapoktan SET id_koperasi=NULL WHERE id=? AND id_koperasi=?')->execute([$gid, $id]);
        flash('ok', 'Gapoktan dikeluarkan dari koperasi ini.');
    }
    header('Location: lembaga_koperasi_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM lembaga_koperasi WHERE id=?');
$st->execute([$id]);
$l = $st->fetch();
$anggotas = $pdo->prepare('SELECT g.*, (SELECT COUNT(*) FROM kelompok k WHERE k.id_gapoktan=g.id) jml FROM gapoktan g WHERE g.id_koperasi=? ORDER BY g.nama_gapoktan');
$anggotas->execute([$id]);
$anggotas = $anggotas->fetchAll();
$bebas = $pdo->query('SELECT id, kode_gapoktan, nama_gapoktan FROM gapoktan WHERE id_koperasi IS NULL ORDER BY nama_gapoktan')->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin:0 0 10px;"><a href="lembaga_koperasi.php">← Kembali ke Koperasi</a></p>
<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>Profil Koperasi</h3>
    <form method="post" style="margin-top:10px;">
      <?= csrf_field() ?>
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="profil">
      <label>Kode koperasi<input name="kode" value="<?= e($l['kode_koperasi'] ?? '') ?>" required></label>
      <label>Nama koperasi<input name="nama" value="<?= e($l['nama_koperasi'] ?? '') ?>" required></label>
      <label>Nama ketua<input name="ketua" value="<?= e($l['nama_ketua'] ?? '') ?>"></label>
      <label>No. HP ketua<input name="hp" value="<?= e($l['no_hp_ketua'] ?? '') ?>"></label>
      <label>Alamat<textarea name="alamat" rows="2"><?= e($l['alamat'] ?? '') ?></textarea></label>
      <label>Keterangan<textarea name="ket" rows="2"><?= e($l['keterangan'] ?? '') ?></textarea></label>
      <button class="btn btn-green" type="submit">Simpan profil</button>
    </form>
  </div>
  <div class="card">
    <h3>Gapoktan anggota (<?= count($anggotas) ?>)</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;">
      <?= csrf_field() ?>
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="gapoktan_id" style="flex:1;">
        <option value="0">— pilih gapoktan —</option>
        <?php foreach ($bebas as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= e(($b['kode_gapoktan'] ?: 'GAP') . ' — ' . ($b['nama_gapoktan'] ?: 'Gapoktan')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit">+ Masukkan</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Kelompok</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (!$anggotas): ?>
          <tr><td colspan="4">Belum ada gapoktan di koperasi ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($anggotas as $r): ?>
          <tr>
            <td><?= e($r['kode_gapoktan'] ?? '') ?></td>
            <td><a href="gapoktan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['nama_gapoktan'] ?? '') ?></a></td>
            <td><?= (int)$r['jml'] ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Keluarkan gapoktan ini dari koperasi?')">
                <?= csrf_field() ?>
                <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
                <input type="hidden" name="act" value="keluar">
                <input type="hidden" name="gapoktan_id" value="<?= (int)$r['id'] ?>">
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
