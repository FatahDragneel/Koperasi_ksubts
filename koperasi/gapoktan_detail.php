<?php
require __DIR__ . '/config.php';
require_login();
ensure_gapoktan_schema();
ensure_lembaga_anggota_schema();

function tr_anggota_gapoktan(array $a, int $id): string {
    ob_start(); ?>
          <tr>
            <td><?= e($a['no_anggota'] ?? '') ?></td>
            <td><a href="anggota_detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['nama'] ?? '') ?></a></td>
            <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
            <td>
              <form method="post" style="display:inline;" data-ajax="keluar" data-count="#cntGap" data-select="#calonGap" onsubmit="return confirm('Keluarkan anggota ini dari gapoktan?')">
                <?= csrf_field() ?>
                <input type="hidden" name="gapoktan_id" value="<?= $id ?>">
                <input type="hidden" name="act" value="keluar">
                <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid fa-user-minus"></i> Keluarkan</button>
              </form>
            </td>
          </tr>
    <?php return (string)ob_get_clean();
}

$pdo = db();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus']);
$aid = (int)($u['anggota_id'] ?? 0);
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
    if (!$staff) {
        if ($aid < 1) {
            flash('err', 'Akun Anda belum terhubung ke data anggota.');
        } elseif ($act === 'gabung') {
            tambah_anggota_ke_gapoktan($aid, $id);
            flash('ok', 'Anda tergabung ke gapoktan.');
        } elseif ($act === 'keluar') {
            keluar_anggota_dari_gapoktan($aid, $id);
            flash('ok', 'Anda keluar dari gapoktan.');
        } else {
            flash('err', 'Akses ditolak.');
        }
        header('Location: gapoktan_detail.php?id=' . $id);
        exit;
    }
    if ($act === 'profil') {
        $pdo->prepare('UPDATE gapoktan SET kode_gapoktan=?, nama_gapoktan=?, nama_ketua=?, no_hp_ketua=?, komoditi=?, luas_lahan=?, jumlah_anggota=?, alamat=?, keterangan=? WHERE id=?')->execute([
            trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
            trim($_POST['hp'] ?? ''), trim($_POST['komoditi'] ?? ''), (float)($_POST['luas_lahan'] ?? 0), (int)($_POST['jumlah_anggota'] ?? 0), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
        ]);
        flash('ok', 'Profil gapoktan disimpan.');
    } elseif ($act === 'masuk') {
        $aid2 = (int)($_POST['anggota_id'] ?? 0);
        if ($aid2 < 1) {
            if (is_ajax()) { echo json_encode(['ok' => false, 'msg' => 'Pilih anggota.']); exit; }
            flash('err', 'Pilih anggota.');
        } else {
            tambah_anggota_ke_gapoktan($aid2, $id);
            if (is_ajax()) {
                $na = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
                $na->execute([$aid2]);
                $na = $na->fetch() ?: ['id' => $aid2];
                echo json_encode(['ok' => true, 'msg' => 'Anggota ditambahkan ke gapoktan ini.', 'id' => $aid2, 'html' => tr_anggota_gapoktan($na, $id)]);
                exit;
            }
            flash('ok', 'Anggota ditambahkan ke gapoktan ini.');
        }
    } elseif ($act === 'keluar') {
        $aid2 = (int)($_POST['anggota_id'] ?? 0);
        if (is_ajax()) {
            $na = $pdo->prepare('SELECT no_anggota, nama FROM anggota WHERE id=?');
            $na->execute([$aid2]);
            $na = $na->fetch() ?: [];
            keluar_anggota_dari_gapoktan($aid2, $id);
            echo json_encode(['ok' => true, 'msg' => 'Anggota dikeluarkan dari gapoktan ini.', 'id' => $aid2,
                'opt' => ['value' => $aid2, 'text' => (($na['no_anggota'] ?? '') ?: '-') . ' — ' . (($na['nama'] ?? '') ?: 'Anggota')]]);
            exit;
        }
        keluar_anggota_dari_gapoktan($aid2, $id);
        flash('ok', 'Anggota dikeluarkan dari gapoktan ini.');
    }
    header('Location: gapoktan_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM gapoktan WHERE id=?');
$st->execute([$id]);
$g = $st->fetch();
if ($staff) {
    $anggotaGap = anggota_di_gapoktan($id);
    $calon = $pdo->prepare("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') AND id NOT IN (SELECT anggota_id FROM anggota_gapoktan WHERE id_gapoktan=?) ORDER BY nama");
    $calon->execute([$id]);
    $calon = $calon->fetchAll();
} else {
    $ikut = $aid > 0 && in_array($id, array_map('intval', array_column(gapoktan_anggota($aid), 'id')), true);
}
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;"><a class="btn btn-ghost btn-sm" href="gapoktan.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Gapoktan</a></p>
<?php if ($staff): ?>
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
      <label>Komoditi<input name="komoditi" value="<?= e($g['komoditi'] ?? '') ?>"></label>
      <label>Luas lahan koperasi<input type="number" step="0.01" min="0" name="luas_lahan" value="<?= e($g['luas_lahan'] ?? '') ?>"></label>
      <label>Jumlah anggota<input type="number" step="1" min="0" name="jumlah_anggota" value="<?= e($g['jumlah_anggota'] ?? '') ?>"></label>
      <label>Alamat<textarea name="alamat" rows="2"><?= e($g['alamat'] ?? '') ?></textarea></label>
      <label>Keterangan<textarea name="ket" rows="2"><?= e($g['keterangan'] ?? '') ?></textarea></label>
      <button class="btn btn-green" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan profil</button>
    </form>
  </div>
  <div class="card">
    <h3>Tambah anggota</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;" data-ajax="masuk" data-tbody="#tbodyGap" data-count="#cntGap" data-select="#calonGap">
      <?= csrf_field() ?>
      <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="anggota_id" id="calonGap" style="flex:1;" required>
        <option value="">— pilih anggota —</option>
        <?php foreach ($calon as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e(($c['no_anggota'] ?: '-') . ' — ' . ($c['nama'] ?: 'Anggota')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit"><i class="fa-solid fa-plus"></i> Tambah</button>
    </form>
    <h3 style="margin-top:14px;">Anggota (<span id="cntGap"><?= count($anggotaGap) ?></span>)</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Aksi</th></tr></thead>
        <tbody id="tbodyGap" data-empty-cols="4" data-empty-text="Belum ada anggota di gapoktan ini.">
        <?php foreach ($anggotaGap as $a): ?><?= tr_anggota_gapoktan($a, $id) ?><?php endforeach; ?>
        <?php if (!$anggotaGap): ?>
          <tr class="empty-row"><td colspan="4">Belum ada anggota di gapoktan ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card" style="max-width:640px;">
  <h3><?= e(($g['kode_gapoktan'] ?: 'GAP') . ' · ' . ($g['nama_gapoktan'] ?: 'Gapoktan')) ?></h3>
  <p style="font-size:14px;margin-top:10px;">Ketua: <strong><?= e($g['nama_ketua'] ?: 'Belum dipilih') ?></strong><?= !empty($g['komoditi']) ? '<br>Komoditi: ' . e($g['komoditi']) : '' ?><?= ((float)($g['luas_lahan'] ?? 0) > 0) ? '<br>Luas lahan koperasi: ' . e(number_format((float)$g['luas_lahan'], 2, ',', '.')) . ' ha' : '' ?><?= ((int)($g['jumlah_anggota'] ?? 0) > 0) ? '<br>Jumlah anggota: ' . (int)$g['jumlah_anggota'] : '' ?><?= !empty($g['alamat']) ? '<br>Alamat: ' . e($g['alamat']) : '' ?><?= !empty($g['keterangan']) ? '<br>Keterangan: ' . e($g['keterangan']) : '' ?></p>
  <?php if ($ikut): ?>
  <p style="font-size:14px;">No. HP ketua: <strong><?= e($g['no_hp_ketua'] ?: '—') ?></strong></p>
  <?php endif; ?>
  <?php if (unit_milik_saya('gapoktan', $id, $aid)): ?>
  <p style="font-size:13px;color:var(--muted);">Ini buatan Anda — <a href="gapoktan.php">ubah dari daftar gapoktan</a>.</p>
  <?php endif; ?>
  <p style="margin-top:10px;">Status: <?= $ikut ? '<span class="badge b-aktif">Tergabung</span>' : '<span class="badge b-pending">Belum tergabung</span>' ?></p>
  <?php if ($ikut): ?>
  <form method="post" onsubmit="return confirm('Keluar dari gapoktan ini?')">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="keluar">
    <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
    <button class="btn btn-ghost"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar dari gapoktan</button>
  </form>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="gabung">
    <input type="hidden" name="gapoktan_id" value="<?= (int)$id ?>">
    <button class="btn btn-green"><i class="fa-solid fa-plus"></i> Gabung gapoktan ini</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
