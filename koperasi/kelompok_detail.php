<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kelompok_schema();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['kelompok_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM kelompok WHERE id=?');
$st->execute([$id]);
$k = $st->fetch();
if (!$k) {
    flash('err', 'Kelompok tidak ditemukan.');
    header('Location: kelompok.php');
    exit;
}
$nomor = (int)$k['nomor'];
$jabatanList = jabatan_kelompok_opsi();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'profil') {
        $pdo->prepare('UPDATE kelompok SET luas_tanah=?, lokasi=?, desa=?, kecamatan=? WHERE id=?')->execute([
            $_POST['luas_tanah'] !== '' ? (float)$_POST['luas_tanah'] : 0,
            trim($_POST['lokasi'] ?? ''),
            trim($_POST['desa'] ?? ''),
            trim($_POST['kecamatan'] ?? ''),
            $id,
        ]);
        flash('ok', 'Profil kelompok disimpan.');
    } elseif ($act === 'jabatan' || $act === 'tambah') {
        $aid = (int)$_POST['anggota_id'];
        $jab = $_POST['jabatan_kelompok'] ?? 'Anggota';
        if ($aid < 1) {
            flash('err', 'Pilih anggota.');
        } else {
            set_jabatan_kelompok($aid, $id, $jab);
            flash('ok', $jab === 'Ketua'
                ? 'Ketua kelompok disimpan. Nama dan HP tampil di daftar kelompok.'
                : 'Jabatan diperbarui.');
        }
    } elseif ($act === 'masuk') {
        $aid = (int)$_POST['anggota_id'];
        $jab = $_POST['jabatan_kelompok'] ?? 'Anggota';
        if ($aid < 1) {
            flash('err', 'Pilih anggota.');
        } else {
            tambah_anggota_ke_kelompok($aid, $id, $jab);
            flash('ok', 'Anggota ditambahkan ke kelompok ini (boleh juga di kelompok lain).');
        }
    } elseif ($act === 'keluar') {
        $aid = (int)$_POST['anggota_id'];
        keluar_anggota_dari_kelompok($aid, $id);
        flash('ok', 'Anggota dikeluarkan dari kelompok ini. Keanggotaan di kelompok lain tetap.');
    }
    header('Location: kelompok_detail.php?id=' . $id);
    exit;
}

$anggotaKel = anggota_di_kelompok($nomor);
$idsKel = array_map(fn($a) => (int)$a['id'], $anggotaKel);
$calon = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') ORDER BY nama")->fetchAll();

$title = 'Kelompok ' . $nomor;
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;"><a class="btn btn-ghost btn-sm" href="kelompok.php">← Daftar kelompok</a></p>

<div class="cards" style="grid-template-columns:1fr 1fr;">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="profil">
    <input type="hidden" name="kelompok_id" value="<?= $id ?>">
    <h3><?= e($k['kode_kelompok'] ?: ('KT-'.str_pad((string)$nomor, 2, '0', STR_PAD_LEFT))) ?></h3>
    <p style="font-size:13px;color:var(--muted);"><?= e($k['nama_kelompok'] ?: ('Kelompok Tani '.$nomor)) ?></p>
    <p style="margin:10px 0;padding:10px;background:#f4faf5;border-radius:10px;">
      <strong>Ketua</strong><br>
      <?= e($k['nama_ketua'] ?: 'Belum dipilih') ?><br>
      <small><?= e($k['no_hp_ketua'] ?: 'HP mengikuti data anggota yang dijabat Ketua') ?></small>
    </p>
    <label>Luas tanah kelompok (ha)</label>
    <input name="luas_tanah" type="number" step="0.01" min="0" value="<?= e($k['luas_tanah']) ?>">
    <label>Lokasi</label>
    <input name="lokasi" value="<?= e($k['lokasi']) ?>" placeholder="Dusun / blok kebun">
    <div class="grid-2">
      <div><label>Desa</label><input name="desa" value="<?= e($k['desa']) ?>"></div>
      <div><label>Kecamatan</label><input name="kecamatan" value="<?= e($k['kecamatan']) ?>"></div>
    </div>
    <button class="btn btn-green" style="margin-top:14px;">Simpan profil kelompok</button>
  </form>
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="kelompok_id" value="<?= $id ?>">
    <input type="hidden" name="act" value="masuk">
    <h3>Masukkan anggota</h3>
    <p style="font-size:13px;color:var(--muted);">Satu orang boleh di beberapa kelompok. Pilih Ketua agar nama/HP tampil di daftar.</p>
    <label>Anggota koperasi</label>
    <select name="anggota_id" required>
      <option value="">Pilih anggota</option>
      <?php foreach ($calon as $c): ?>
        <option value="<?= (int)$c['id'] ?>">
          <?= e($c['no_anggota'].' — '.$c['nama']) ?>
          <?= in_array((int)$c['id'], $idsKel, true) ? ' (sudah di kelompok ini)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label>Jabatan</label>
    <select name="jabatan_kelompok" required>
      <?php foreach ($jabatanList as $j): ?>
        <option value="<?= e($j) ?>"><?= e($j) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-green" style="margin-top:14px;">Tambah / perbarui</button>
  </form>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Anggota Kelompok <?= $nomor ?></h3>
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead>
        <tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Jabatan</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($anggotaKel as $a): ?>
        <tr>
          <td><?= e($a['no_anggota']) ?></td>
          <td><?= e($a['nama']) ?></td>
          <td><?= e($a['no_hp'] ?: '—') ?></td>
          <td>
            <form method="post" class="row" style="gap:6px;">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="jabatan">
              <input type="hidden" name="kelompok_id" value="<?= $id ?>">
              <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
              <select name="jabatan_kelompok" onchange="this.form.submit()">
                <?php foreach ($jabatanList as $j): ?>
                  <option value="<?= e($j) ?>" <?= ($a['jabatan_kelompok'] ?? 'Anggota') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><span class="badge b-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
          <td>
            <form method="post" onsubmit="return confirm('Keluarkan <?= e($a['nama']) ?> dari kelompok?');">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="keluar">
              <input type="hidden" name="kelompok_id" value="<?= $id ?>">
              <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-danger btn-sm">Keluarkan</button>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$anggotaKel): ?>
        <tr><td colspan="6">Belum ada anggota di kelompok ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
