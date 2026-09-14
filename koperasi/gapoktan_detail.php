<?php
require __DIR__ . '/config.php';
require_staff();
ensure_gapoktan_schema();
ensure_lembaga_anggota_schema();
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
        $pdo->prepare('UPDATE gapoktan SET kode_gapoktan=?, nama_gapoktan=?, nama_ketua=?, no_hp_ketua=?, alamat=?, keterangan=? WHERE id=?')->execute([
            trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
            trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
        ]);
        flash('ok', 'Profil gapoktan disimpan.');
    } elseif ($act === 'masuk') {
        $aid = (int)($_POST['anggota_id'] ?? 0);
        if ($aid < 1) {
            flash('err', 'Pilih anggota.');
        } else {
            tambah_anggota_ke_gapoktan($aid, $id);
            flash('ok', 'Anggota ditambahkan ke gapoktan ini.');
        }
    } elseif ($act === 'keluar') {
        $aid = (int)($_POST['anggota_id'] ?? 0);
        keluar_anggota_dari_gapoktan($aid, $id);
        flash('ok', 'Anggota dikeluarkan dari gapoktan ini.');
    }
    header('Location: gapoktan_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM gapoktan WHERE id=?');
$st->execute([$id]);
$g = $st->fetch();
$anggotaGap = anggota_di_gapoktan($id);
$calon = $pdo->prepare("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') AND id NOT IN (SELECT anggota_id FROM anggota_gapoktan WHERE id_gapoktan=?) ORDER BY nama");
$calon->execute([$id]);
$calon = $calon->fetchAll();
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
      <label>Keterangan<textarea name="ket" rows="2"><?= e($g['keterangan'] ?? '') ?></textarea></label>
      <button class="btn btn-green" type="submit">Simpan profil</button>
    </form>
  </div>
  <div class="card">
    <h3>Tambah anggota</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;">
      <?= csrf_field() ?>
      <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="anggota_id" style="flex:1;" required>
        <option value="">— pilih anggota —</option>
        <?php foreach ($calon as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e(($c['no_anggota'] ?: '-') . ' — ' . ($c['nama'] ?: 'Anggota')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit">+ Tambah</button>
    </form>
    <h3 style="margin-top:14px;">Anggota (<?= count($anggotaGap) ?>)</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (!$anggotaGap): ?>
          <tr><td colspan="4">Belum ada anggota di gapoktan ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($anggotaGap as $a): ?>
          <tr>
            <td><?= e($a['no_anggota'] ?? '') ?></td>
            <td><a href="anggota_detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['nama'] ?? '') ?></a></td>
            <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Keluarkan anggota ini dari gapoktan?')">
                <?= csrf_field() ?>
                <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
                <input type="hidden" name="act" value="keluar">
                <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
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
