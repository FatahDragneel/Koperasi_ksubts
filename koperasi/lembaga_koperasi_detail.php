<?php
require __DIR__ . '/config.php';
require_login();
ensure_lembaga_koperasi_schema();
ensure_lembaga_anggota_schema();

function tr_anggota_lembaga(array $a, int $id): string {
    ob_start(); ?>
          <tr>
            <td><?= e($a['no_anggota'] ?? '') ?></td>
            <td><a href="anggota_detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['nama'] ?? '') ?></a></td>
            <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
            <td>
              <form method="post" style="display:inline;" data-ajax="keluar" data-count="#cntLem" data-select="#calonLem" onsubmit="return confirm('Keluarkan anggota ini dari koperasi?')">
                <?= csrf_field() ?>
                <input type="hidden" name="koperasi_id" value="<?= $id ?>">
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
    if (!$staff) {
        if ($aid < 1) {
            flash('err', 'Akun Anda belum terhubung ke data anggota.');
        } elseif ($act === 'gabung') {
            tambah_anggota_ke_lembaga($aid, $id);
            flash('ok', 'Anda tergabung ke koperasi.');
        } elseif ($act === 'keluar') {
            keluar_anggota_dari_lembaga($aid, $id);
            flash('ok', 'Anda keluar dari koperasi.');
        } else {
            flash('err', 'Akses ditolak.');
        }
        header('Location: lembaga_koperasi_detail.php?id=' . $id);
        exit;
    }
    if ($act === 'profil') {
        $pdo->prepare('UPDATE lembaga_koperasi SET kode_koperasi=?, nama_koperasi=?, nama_ketua=?, no_hp_ketua=?, komoditi=?, luas_lahan=?, jumlah_anggota=?, alamat=?, keterangan=? WHERE id=?')->execute([
            trim($_POST['kode'] ?? ''), trim($_POST['nama'] ?? ''), trim($_POST['ketua'] ?? ''),
            trim($_POST['hp'] ?? ''), trim($_POST['komoditi'] ?? ''), (float)($_POST['luas_lahan'] ?? 0), (int)($_POST['jumlah_anggota'] ?? 0), trim($_POST['alamat'] ?? ''), trim($_POST['ket'] ?? ''), $id,
        ]);
        flash('ok', 'Profil koperasi disimpan.');
    } elseif ($act === 'masuk') {
        $aid2 = (int)($_POST['anggota_id'] ?? 0);
        if ($aid2 < 1) {
            if (is_ajax()) { echo json_encode(['ok' => false, 'msg' => 'Pilih anggota.']); exit; }
            flash('err', 'Pilih anggota.');
        } else {
            tambah_anggota_ke_lembaga($aid2, $id);
            if (is_ajax()) {
                $na = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
                $na->execute([$aid2]);
                $na = $na->fetch() ?: ['id' => $aid2];
                echo json_encode(['ok' => true, 'msg' => 'Anggota ditambahkan ke koperasi ini.', 'id' => $aid2, 'html' => tr_anggota_lembaga($na, $id)]);
                exit;
            }
            flash('ok', 'Anggota ditambahkan ke koperasi ini.');
        }
    } elseif ($act === 'keluar') {
        $aid2 = (int)($_POST['anggota_id'] ?? 0);
        if (is_ajax()) {
            $na = $pdo->prepare('SELECT no_anggota, nama FROM anggota WHERE id=?');
            $na->execute([$aid2]);
            $na = $na->fetch() ?: [];
            keluar_anggota_dari_lembaga($aid2, $id);
            echo json_encode(['ok' => true, 'msg' => 'Anggota dikeluarkan dari koperasi ini.', 'id' => $aid2,
                'opt' => ['value' => $aid2, 'text' => (($na['no_anggota'] ?? '') ?: '-') . ' — ' . (($na['nama'] ?? '') ?: 'Anggota')]]);
            exit;
        }
        keluar_anggota_dari_lembaga($aid2, $id);
        flash('ok', 'Anggota dikeluarkan dari koperasi ini.');
    }
    header('Location: lembaga_koperasi_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM lembaga_koperasi WHERE id=?');
$st->execute([$id]);
$l = $st->fetch();
if ($staff) {
    $anggotaLem = anggota_di_lembaga($id);
    $calon = $pdo->prepare("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') AND id NOT IN (SELECT anggota_id FROM anggota_lembaga WHERE id_koperasi=?) ORDER BY nama");
    $calon->execute([$id]);
    $calon = $calon->fetchAll();
} else {
    $ikut = $aid > 0 && in_array($id, array_map('intval', array_column(lembaga_anggota($aid), 'id')), true);
    $anggotaLem = anggota_di_lembaga($id);
    if (!$ikut) {
        flash('err', 'Detail koperasi hanya bisa dilihat setelah Anda bergabung.');
        header('Location: lembaga_koperasi.php');
        exit;
    }
}
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;"><a class="btn btn-ghost btn-sm" href="lembaga_koperasi.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Koperasi</a></p>
<?php if ($staff): ?>
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
      <label>Komoditi<input name="komoditi" value="<?= e($l['komoditi'] ?? '') ?>"></label>
      <label>Luas lahan koperasi<input type="number" step="0.01" min="0" name="luas_lahan" value="<?= e($l['luas_lahan'] ?? '') ?>"></label>
      <label>Jumlah anggota<input type="number" step="1" min="0" name="jumlah_anggota" value="<?= e($l['jumlah_anggota'] ?? '') ?>"></label>
      <label>Alamat<textarea name="alamat" rows="2"><?= e($l['alamat'] ?? '') ?></textarea></label>
      <label>Keterangan<textarea name="ket" rows="2"><?= e($l['keterangan'] ?? '') ?></textarea></label>
      <button class="btn btn-green" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan profil</button>
    </form>
  </div>
  <div class="card">
    <h3>Tambah anggota</h3>
    <form method="post" style="display:flex;gap:8px;margin:10px 0;" data-ajax="masuk" data-tbody="#tbodyLem" data-count="#cntLem" data-select="#calonLem">
      <?= csrf_field() ?>
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <input type="hidden" name="act" value="masuk">
      <select name="anggota_id" id="calonLem" style="flex:1;" required>
        <option value="">— pilih anggota —</option>
        <?php foreach ($calon as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e(($c['no_anggota'] ?: '-') . ' — ' . ($c['nama'] ?: 'Anggota')) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-green btn-sm" type="submit"><i class="fa-solid fa-plus"></i> Tambah</button>
    </form>
    <h3 style="margin-top:14px;">Anggota (<span id="cntLem"><?= count($anggotaLem) ?></span>)</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Aksi</th></tr></thead>
        <tbody id="tbodyLem" data-empty-cols="4" data-empty-text="Belum ada anggota di koperasi ini.">
        <?php foreach ($anggotaLem as $a): ?><?= tr_anggota_lembaga($a, $id) ?><?php endforeach; ?>
        <?php if (!$anggotaLem): ?>
          <tr class="empty-row"><td colspan="4">Belum ada anggota di koperasi ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>Profil Koperasi</h3>
    <div class="grid-2" style="margin-top:12px;">
      <p><strong>Kode</strong><br><?= e($l['kode_koperasi'] ?: '—') ?></p>
      <p><strong>Nama</strong><br><?= e($l['nama_koperasi'] ?: '—') ?></p>
      <p><strong>Ketua</strong><br><?= e($l['nama_ketua'] ?: 'Belum dipilih') ?></p>
      <p><strong>No. HP ketua</strong><br><?= e($l['no_hp_ketua'] ?: '—') ?></p>
      <p><strong>Komoditi</strong><br><?= e($l['komoditi'] ?: '—') ?></p>
      <p><strong>Luas lahan koperasi</strong><br><?= !empty($l['luas_lahan']) ? e($l['luas_lahan']) . ' ha' : '—' ?></p>
      <p><strong>Jumlah anggota</strong><br><?= (int)($l['jumlah_anggota'] ?? 0) > 0 ? (int)$l['jumlah_anggota'] : '—' ?></p>
      <p><strong>Alamat</strong><br><?= e($l['alamat'] ?: '—') ?></p>
    </div>
    <p style="margin-top:10px;"><strong>Keterangan</strong><br><?= e($l['keterangan'] ?: '—') ?></p>
    <?php if (unit_milik_saya('lembaga_koperasi', $id, $aid)): ?>
    <p style="font-size:13px;color:var(--muted);">Ini buatan Anda — <a href="lembaga_koperasi.php">ubah dari daftar koperasi</a>.</p>
    <?php endif; ?>
    <p style="margin-top:10px;">Status: <?= $ikut ? '<span class="badge b-aktif">Tergabung</span>' : '<span class="badge b-pending">Belum tergabung</span>' ?></p>
    <?php if ($ikut): ?>
    <form method="post" onsubmit="return confirm('Keluar dari koperasi ini?')">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="keluar">
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <button class="btn btn-ghost"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar dari koperasi</button>
    </form>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="gabung">
      <input type="hidden" name="koperasi_id" value="<?= (int)$id ?>">
      <button class="btn btn-green"><i class="fa-solid fa-plus"></i> Gabung koperasi ini</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>Anggota (<?= count($anggotaLem) ?>)</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th></tr></thead>
        <tbody>
        <?php foreach ($anggotaLem as $a): ?>
          <tr>
            <td><?= e($a['no_anggota'] ?? '') ?></td>
            <td><?= e($a['nama'] ?? '') ?></td>
            <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
          </tr>
        <?php endforeach; if (!$anggotaLem): ?>
          <tr><td colspan="3">Belum ada anggota di koperasi ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
