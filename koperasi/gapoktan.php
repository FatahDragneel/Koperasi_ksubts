<?php
require __DIR__ . '/config.php';
require_login();
ensure_gapoktan_schema();
ensure_lembaga_anggota_schema();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus']);
$aid = (int)($u['anggota_id'] ?? 0);
$title = 'Gapoktan';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if (!$staff) {
        if ($act === 'simpan') {
            $sid = (int)($_POST['id'] ?? 0);
            if ($sid > 0) {
                if (!unit_milik_saya('gapoktan', $sid, $aid)) {
                    flash('err', 'Hanya gapoktan buatan sendiri yang bisa diubah.');
                } else {
                    $pdo->prepare('UPDATE gapoktan SET kode_gapoktan=?, nama_gapoktan=?, nama_ketua=?, alamat=?, keterangan=? WHERE id=?')->execute([
                        trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                        trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $sid,
                    ]);
                    flash('ok', 'Gapoktan berhasil diperbarui.');
                }
            } else {
                $pdo->prepare('INSERT INTO gapoktan (kode_gapoktan, nama_gapoktan, nama_ketua, alamat, keterangan, dibuat_oleh) VALUES (?,?,?,?,?,?)')->execute([
                    trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
                    trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $aid > 0 ? $aid : null,
                ]);
                flash('ok', 'Gapoktan baru berhasil ditambahkan.');
            }
            header('Location: gapoktan.php'); exit;
        }
        $gid = (int)($_POST['id'] ?? 0);
        if ($gid < 1 || $aid < 1) {
            flash('err', 'Permintaan tidak valid.');
        } elseif ($act === 'gabung') {
            tambah_anggota_ke_gapoktan($aid, $gid);
            flash('ok', 'Anda tergabung ke gapoktan.');
        } elseif ($act === 'keluar') {
            keluar_anggota_dari_gapoktan($aid, $gid);
            flash('ok', 'Anda keluar dari gapoktan.');
        } else {
            flash('err', 'Akses ditolak.');
        }
        header('Location: gapoktan.php'); exit;
    }
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
    if (!$staff) { flash('err', 'Akses ditolak.'); header('Location: gapoktan.php'); exit; }
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
$milikSaya = $aid > 0 ? array_map('intval', array_column(gapoktan_anggota($aid), 'id')) : [];
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin:0 0 10px;"><a href="lembaga.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Lembaga</a></p>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2 style="margin:0;">Gapoktan</h2>
  <button class="btn btn-green" onclick="document.getElementById('mTambah').style.display='flex'"><i class="fa-solid fa-plus"></i> Tambah gapoktan</button>
</div>
<div class="table-wrap">
  <table>
    <?php if ($staff): ?><thead><tr><th>Kode</th><th>Nama gapoktan</th><th>Ketua</th><th>Anggota</th><th>Aksi</th></tr></thead><?php else: ?><thead><tr><th>Kode</th><th>Nama gapoktan</th><th>Ketua</th><th>Status saya</th><th>Aksi</th></tr></thead><?php endif; ?>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5">Belum ada gapoktan. Klik “Tambah gapoktan” untuk membentuk yang pertama.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['kode_gapoktan'] ?? '') ?></td>
        <td><?= e($r['nama_gapoktan'] ?? '') ?></td>
        <td><?= e($r['nama_ketua'] ?? '') ?: '—' ?></td>
        <?php if ($staff): ?><td><?= (int)$r['jml'] ?> anggota</td><?php else: ?><td><?= in_array((int)$r['id'], $milikSaya, true) ? '<span class="badge b-aktif">Tergabung</span>' : '<span class="badge b-pending">Belum</span>' ?></td><?php endif; ?>
        <td style="white-space:nowrap;">
          <?php $punya = $staff || unit_milik_saya('gapoktan', (int)$r['id'], $aid); ?>
          <?php if ($punya): ?>
          <button class="btn btn-ghost btn-sm" onclick='editGap(<?= json_encode(['id' => $r['id'], 'kode' => $r['kode_gapoktan'], 'nama' => $r['nama_gapoktan'], 'ketua' => $r['nama_ketua'], 'hp' => $staff ? $r['no_hp_ketua'] : '', 'alamat' => $r['alamat'], 'ket' => $r['keterangan']]) ?>)'><i class="fa-solid fa-pen"></i> Ubah</button>
          <?php if ($staff): ?>
          <a class="btn btn-red btn-sm" href="gapoktan.php?act=hapus&id=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus gapoktan <?= e($r['nama_gapoktan'] ?? '') ?>?')"><i class="fa-solid fa-trash"></i> Hapus</a>
          <?php endif; ?>
          <?php endif; ?>
          <?php if (!$staff): ?>
          <?php if (in_array((int)$r['id'], $milikSaya, true)): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Keluar dari gapoktan ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="keluar">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</button>
          </form>
          <?php else: ?>
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="gabung">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-green btn-sm" type="submit"><i class="fa-solid fa-plus"></i> Gabung</button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
          <a class="btn btn-green btn-sm" href="gapoktan_detail.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-eye"></i> Detail</a>
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
      <?php if ($staff): ?><label>No. HP ketua<input name="hp"></label><?php endif; ?>
      <label>Alamat<textarea name="alamat" rows="2"></textarea></label>
      <label>Keterangan<textarea name="ket" rows="2"></textarea></label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById('mTambah').style.display='none'"><i class="fa-solid fa-xmark"></i> Batal</button><button class="btn btn-green" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button></div>
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
      <?php if ($staff): ?><label>No. HP ketua<input name="hp" id="u_hp"></label><?php endif; ?>
      <label>Alamat<textarea name="alamat" id="u_alamat" rows="2"></textarea></label>
      <label>Keterangan<textarea name="ket" id="u_ket" rows="2"></textarea></label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById('mUbah').style.display='none'"><i class="fa-solid fa-xmark"></i> Batal</button><button class="btn btn-green" type="submit"><i class="fa-solid fa-floppy-disk"></i> Perbarui</button></div>
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
