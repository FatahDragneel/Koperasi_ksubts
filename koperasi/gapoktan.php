<?php
require __DIR__ . '/config.php';
require_staff();
ensure_gapoktan_schema();
ensure_lembaga_anggota_schema();
$title = 'Gapoktan';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'simpan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE gapoktan SET kode_gapoktan=?, nama_gapoktan=?, nama_ketua=?, no_hp_ketua=?, alamat=?, keterangan=? WHERE id=?')->execute([
                trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
            ]);
            flash('ok', 'Gapoktan berhasil diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO gapoktan (kode_gapoktan, nama_gapoktan, nama_ketua, no_hp_ketua, alamat, keterangan) VALUES (?,?,?,?,?,?)')->execute([
                trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''),
            ]);
            flash('ok', 'Gapoktan baru berhasil ditambahkan.');
        }
           header('Location: gapoktan.php'); 
exit;
    }
}

if (($_GET['act'] ?? '') === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);
    $q = $pdo->prepare('SELECT COUNT(*) FROM anggota_gapoktan WHERE id_gapoktan=?');
    $q->execute([$id]);
    if ((int)$q->fetchColumn() > 0) {
        flash('err', 'Gapoktan tidak bisa dihapus karena masih memiliki anggota.');
    } else {
        $pdo->prepare('DELETE FROM gapoktan WHERE id=?')->execute([$id]);
        flash('ok', 'Gapoktan berhasil dihapus.');
    }
    header('Location: gapoktan.php'); 
exit;
}

$rows = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM anggota_gapoktan ag WHERE ag.id_gapoktan=g.id) jml FROM gapoktan g ORDER BY g.nama_gapoktan")->fetchAll();
$nextId = (int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM gapoktan')->fetchColumn();
$autoKode = 'GAP-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin:0 0 10px;"><a href="lembaga.php">← Kembali ke Lembaga</a></p>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2 style="margin:0;">Gapoktan</h2>
  <button class="btn btn-green" onclick="document.getElementById('mTambah').style.display='flex'">+ Tambah gapoktan</button>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Kode</th><th>Nama gapoktan</th><th>Ketua</th><th>Anggota</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5">Belum ada gapoktan. Klik “Tambah gapoktan” untuk membentuk yang pertama.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['kode_gapoktan'] ?? '') ?></td>
        <td><?= e($r['nama_gapoktan'] ?? '') ?></td>
        <td><?= e($r['nama_ketua'] ?? '') ?: '—' ?></td>
        <td><?= (int)$r['jml'] ?> anggota</td>
        <td style="white-space:nowrap;">
          <button class="btn btn-ghost btn-sm" onclick='editGap(<?= json_encode(['id' => $r['id'], 'kode' => $r['kode_gapoktan'], 'nama' => $r['nama_gapoktan'], 'ketua' => $r['nama_ketua'], 'hp' => $r['no_hp_ketua'], 'alamat' => $r['alamat'], 'ket' => $r['keterangan']]) ?>)'>Ubah</button>
          <a class="btn btn-red btn-sm" href="gapoktan.php?act=hapus&id=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus gapoktan <?= e($r['nama_gapoktan'] ?? '') ?>?')">Hapus</a>
          <a class="btn btn-green btn-sm" href="gapoktan_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mTambah" style="justify-content: center; align-items: center;">
  <div class="modal">
    <h3>Tambah Gapoktan</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="simpan">
      <input type="hidden" name="id" value="0">
      <label>Kode gapoktan<input name="kode" value="<?= e($autoKode) ?>" required></label>
      <label>Nama gapoktan<input name="nama" required placeholder="cth: Gapoktan Mekar Jaya"></label>
      <label>Nama ketua<input name="ketua"></label>
      <label>No. HP ketua<input name="hp"></label>
      <label>Alamat<textarea name="alamat" rows="2"></textarea></label>
      <label>Keterangan<textarea name="ket" rows="2"></textarea></label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById('mTambah').style.display='none'">Batal</button><button class="btn btn-green" type="submit">Simpan</button></div>
    </form>
  </div>
</div>
<div class="modal-bg" id="mUbah" style="display:none;">
  <div class="modal">
    <h3>Ubah Gapoktan</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="simpan">
      <input type="hidden" name="id" id="u_id">
      <label>Kode gapoktan<input name="kode" id="u_kode" required></label>
      <label>Nama gapoktan<input name="nama" id="u_nama" required></label>
      <label>Nama ketua<input name="ketua" id="u_ketua"></label>
      <label>No. HP ketua<input name="hp" id="u_hp"></label>
      <label>Alamat<textarea name="alamat" id="u_alamat" rows="2"></textarea></label>
      <label>Keterangan<textarea name="ket" id="u_ket" rows="2"></textarea></label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById('mUbah').style.display='none'">Batal</button><button class="btn btn-green" type="submit">Perbarui</button></div>
    </form>
  </div>
</div>
<script>
function editGap(g) {
  document.getElementById('u_id').value = g.id;
  document.getElementById('u_kode').value = g.kode || '';
  document.getElementById('u_nama').value = g.nama || '';
  document.getElementById('u_ketua').value = g.ketua || '';
  document.getElementById('u_hp').value = g.hp || '';
  document.getElementById('u_alamat').value = g.alamat || '';
  document.getElementById('u_ket').value = g.ket || '';
  document.getElementById('mUbah').style.display = 'flex';
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
