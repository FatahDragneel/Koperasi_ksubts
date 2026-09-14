<?php
require __DIR__ . '/config.php';
require_staff();
ensure_lembaga_koperasi_schema();
$title = 'Koperasi';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'simpan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE lembaga_koperasi SET kode_koperasi=?, nama_koperasi=?, nama_ketua=?, no_hp_ketua=?, alamat=?, keterangan=? WHERE id=?')->execute([
                trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
            ]);
            flash('ok', 'Koperasi berhasil diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO lembaga_koperasi (kode_koperasi, nama_koperasi, nama_ketua, no_hp_ketua, alamat, keterangan) VALUES (?,?,?,?,?,?)')->execute([
                trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                trim($_POST['hp'] ?? ''), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''),
            ]);
            flash('ok', 'Koperasi baru berhasil ditambahkan.');
        }
            header('Location: lembaga_koperasi.php'); 
exit;
       
    }
}

if (($_GET['act'] ?? '') === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);
    $q = $pdo->prepare('SELECT COUNT(*) FROM gapoktan WHERE id_koperasi=?');
    $q->execute([$id]);
    if ((int)$q->fetchColumn() > 0) {
        flash('err', 'Koperasi tidak bisa dihapus karena masih memiliki gapoktan.');
    } else {
        $pdo->prepare('DELETE FROM lembaga_koperasi WHERE id=?')->execute([$id]);
        flash('ok', 'Koperasi berhasil dihapus.');
    }
             header('Location: lembaga_koperasi.php'); 
exit;
}

$rows = $pdo->query('SELECT l.*, (SELECT COUNT(*) FROM gapoktan g WHERE g.id_koperasi=l.id) jml FROM lembaga_koperasi l ORDER BY l.nama_koperasi')->fetchAll();
$nextId = (int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM lembaga_koperasi')->fetchColumn();
$autoKode = 'KOP-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin:0 0 10px;"><a href="lembaga.php">← Kembali ke Lembaga</a></p>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2 style="margin:0;">Koperasi</h2>
  <button class="btn btn-green" onclick="document.getElementById('mTambah').style.display='flex'">+ Tambah koperasi</button>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Kode</th><th>Nama koperasi</th><th>Ketua</th><th>No. HP</th><th>Gapoktan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6">Belum ada koperasi. Klik “Tambah koperasi” untuk membentuk yang pertama.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['kode_koperasi'] ?? '') ?></td>
        <td><?= e($r['nama_koperasi'] ?? '') ?></td>
        <td><?= e($r['nama_ketua'] ?? '') ?: '—' ?></td>
        <td><?= e($r['no_hp_ketua'] ?? '') ?: '—' ?></td>
        <td><?= (int)$r['jml'] ?> gapoktan</td>
        <td style="white-space:nowrap;">
          <button class="btn btn-ghost btn-sm" onclick='editKop(<?= json_encode(['id' => $r['id'], 'kode' => $r['kode_koperasi'], 'nama' => $r['nama_koperasi'], 'ketua' => $r['nama_ketua'], 'hp' => $r['no_hp_ketua'], 'alamat' => $r['alamat'], 'ket' => $r['keterangan']]) ?>)'>Ubah</button>
          <a class="btn btn-red btn-sm" href="lembaga_koperasi.php?act=hapus&id=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus koperasi <?= e($r['nama_koperasi'] ?? '') ?>?')">Hapus</a>
          <a class="btn btn-green btn-sm" href="lembaga_koperasi_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mTambah" style="justify-content: center; align-items: center;">
  <div class="modal">
    <h3>Tambah Koperasi</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="simpan">
      <input type="hidden" name="id" value="0">
      <label>Kode koperasi<input name="kode" value="<?= e($autoKode) ?>" required></label>
      <label>Nama koperasi<input name="nama" required placeholder="cth: Koperasi Bina Tani"></label>
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
    <h3>Ubah Koperasi</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="simpan">
      <input type="hidden" name="id" id="u_id">
      <label>Kode koperasi<input name="kode" id="u_kode" required></label>
      <label>Nama koperasi<input name="nama" id="u_nama" required></label>
      <label>Nama ketua<input name="ketua" id="u_ketua"></label>
      <label>No. HP ketua<input name="hp" id="u_hp"></label>
      <label>Alamat<textarea name="alamat" id="u_alamat" rows="2"></textarea></label>
      <label>Keterangan<textarea name="ket" id="u_ket" rows="2"></textarea></label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById('mUbah').style.display='none'">Batal</button><button class="btn btn-green" type="submit">Perbarui</button></div>
    </form>
  </div>
</div>
<script>
function editKop(g) {
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
