<?php
require __DIR__ . '/config.php';
require_staff();
ensure_lembaga_koperasi_schema();
ensure_lembaga_anggota_schema();
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
        $aid = (int)($_POST['anggota_id'] ?? 0);
        if ($aid < 1) {
            flash('err', 'Pilih anggota.');
        } else {
            tambah_anggota_ke_lembaga($aid, $id);
            flash('ok', 'Anggota ditambahkan ke koperasi ini.');
        }
    } elseif ($act === 'keluar') {
        $aid = (int)($_POST['anggota_id'] ?? 0);
        keluar_anggota_dari_lembaga($aid, $id);
        flash('ok', 'Anggota dikeluarkan dari koperasi ini.');
    }
    header('Location: lembaga_koperasi_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM lembaga_koperasi WHERE id=?');
$st->execute([$id]);
$l = $st->fetch();
$anggotaLem = anggota_di_lembaga($id);
$calon = $pdo->prepare("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') AND id NOT IN (SELECT anggota_id FROM anggota_lembaga WHERE id_koperasi=?) ORDER BY nama");
$calon->execute([$id]);
$calon = $calon->fetchAll();
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
    <h3>Tambah anggota</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;">
      <?= csrf_field() ?>
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="anggota_id" style="flex:1;" required>
        <option value="">— pilih anggota —</option>
        <?php foreach ($calon as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e(($c['no_anggota'] ?: '-') . ' — ' . ($c['nama'] ?: 'Anggota')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit">+ Tambah</button>
    </form>
    <h3 style="margin-top:14px;">Anggota (<?= count($anggotaLem) ?>)</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (!$anggotaLem): ?>
          <tr><td colspan="4">Belum ada anggota di koperasi ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($anggotaLem as $a): ?>
          <tr>
            <td><?= e($a['no_anggota'] ?? '') ?></td>
            <td><a href="anggota_detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['nama'] ?? '') ?></a></td>
            <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Keluarkan anggota ini dari koperasi?')">
                <?= csrf_field() ?>
                <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
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
