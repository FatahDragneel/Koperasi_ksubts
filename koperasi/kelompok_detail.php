<?php
require __DIR__ . '/config.php';
require_login();
ensure_kelompok_schema();
$pdo = db();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus']);
$aid = (int)($u['anggota_id'] ?? 0);
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

function tr_anggota_kelompok(array $a, int $id, array $jabatanList): string {
    ob_start(); ?>
        <tr>
          <td><?= e($a['no_anggota'] ?? '') ?></td>
          <td><?= e($a['nama'] ?? '') ?></td>
          <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
          <td>
            <form method="post" class="row" style="gap:6px;" data-ajax="jabatan">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="jabatan">
              <input type="hidden" name="kelompok_id" value="<?= $id ?>">
              <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
              <select name="jabatan_kelompok" onchange="this.form.requestSubmit()">
                <?php foreach ($jabatanList as $j): ?>
                  <option value="<?= e($j) ?>" <?= ($a['jabatan_kelompok'] ?? 'Anggota') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><span class="badge b-<?= e($a['status'] ?? 'aktif') ?>"><?= e($a['status'] ?? 'aktif') ?></span></td>
          <td>
            <form method="post" data-ajax="keluar" data-count="#cntKel" data-select="#calonAnggota" data-opt="mark" onsubmit="return confirm('Keluarkan <?= e($a['nama'] ?? '') ?> dari kelompok?');">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="keluar">
              <input type="hidden" name="kelompok_id" value="<?= $id ?>">
              <input type="hidden" name="anggota_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-danger btn-sm"><i class="fa-solid fa-user-minus"></i> Keluarkan</button>
            </form>
          </td>
        </tr>
    <?php return (string)ob_get_clean();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if (!$staff) {
        if ($aid < 1) {
            flash('err', 'Akun Anda belum terhubung ke data anggota.');
        } elseif ($act === 'gabung') {
            tambah_anggota_ke_kelompok($aid, $id, 'Anggota');
            flash('ok', 'Anda tergabung ke kelompok.');
        } elseif ($act === 'keluar') {
            keluar_anggota_dari_kelompok($aid, $id);
            flash('ok', 'Anda keluar dari kelompok.');
        } else {
            flash('err', 'Akses ditolak.');
        }
        header('Location: kelompok_detail.php?id=' . $id);
        exit;
    }
    if ($act === 'profil') {
        $pdo->prepare('UPDATE kelompok SET luas_tanah=?, lokasi=?, desa=?, kecamatan=?, komoditi=?, jumlah_anggota=? WHERE id=?')->execute([
            $_POST['luas_tanah'] !== '' ? (float)$_POST['luas_tanah'] : 0,
            trim($_POST['lokasi'] ?? ''),
            trim($_POST['desa'] ?? ''),
            trim($_POST['kecamatan'] ?? ''),
            trim($_POST['komoditi'] ?? '') ?: null,
            (int)($_POST['jumlah_anggota'] ?? 0),
            $id,
        ]);
        flash('ok', 'Profil kelompok disimpan.');
    } elseif ($act === 'jabatan' || $act === 'tambah') {
        $aid = (int)$_POST['anggota_id'];
        $jab = $_POST['jabatan_kelompok'] ?? 'Anggota';
        if ($aid < 1) {
            if (is_ajax()) { echo json_encode(['ok' => false, 'msg' => 'Pilih anggota.']); exit; }
            flash('err', 'Pilih anggota.');
        } else {
            set_jabatan_kelompok($aid, $id, $jab);
            if (is_ajax()) { echo json_encode(['ok' => true, 'msg' => 'Jabatan diperbarui.']); exit; }
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
            if (is_ajax()) {
                $na = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
                $na->execute([$aid]);
                $na = $na->fetch() ?: ['id' => $aid];
                $na['jabatan_kelompok'] = in_array($jab, $jabatanList, true) ? $jab : 'Anggota';
                echo json_encode(['ok' => true, 'msg' => 'Anggota ditambahkan ke kelompok ini.', 'id' => $aid, 'html' => tr_anggota_kelompok($na, $id, $jabatanList)]);
                exit;
            }
            flash('ok', 'Anggota ditambahkan ke kelompok ini.');
        }
    } elseif ($act === 'keluar') {
        $aid = (int)$_POST['anggota_id'];
        keluar_anggota_dari_kelompok($aid, $id);
        if (is_ajax()) { echo json_encode(['ok' => true, 'msg' => 'Anggota dikeluarkan dari kelompok ini.', 'id' => $aid]); exit; }
        flash('ok', 'Anggota dikeluarkan dari kelompok ini. Keanggotaan di kelompok lain tetap.');
    }
    header('Location: kelompok_detail.php?id=' . $id);
    exit;
}

$anggotaKel = anggota_di_kelompok($nomor);
$idsKel = array_map(fn($a) => (int)$a['id'], $anggotaKel);
$calon = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status IN ('aktif','pending') ORDER BY nama")->fetchAll();
if (!$staff) {
    $cekIkutKel = $aid > 0 && in_array($id, array_map('intval', array_column(kelompok_anggota($aid), 'id_kelompok')), true);
    if (!$cekIkutKel) {
        flash('err', 'Detail kelompok hanya bisa dilihat setelah Anda bergabung.');
        header('Location: kelompok.php');
        exit;
    }
}

$title = ($k['kode_kelompok'] ?: ('KT-' . $nomor)) . ' · ' . ($k['nama_kelompok'] ?: ('Kelompok ' . $nomor));
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;"><a class="btn btn-ghost btn-sm" href="kelompok.php"><i class="fa-solid fa-arrow-left"></i> Daftar kelompok</a></p>
<?php if ($staff): ?>

<div class="cards" style="grid-template-columns:1fr 1fr;">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="profil">
    <input type="hidden" name="kelompok_id" value="<?= $id ?>">
    <h3><?= e($k['kode_kelompok'] ?: ('KT-'.str_pad((string)$nomor, 2, '0', STR_PAD_LEFT))) ?></h3>
    <p style="font-size:13px;color:var(--muted);"><?= e($k['nama_kelompok'] ?: ('Kelompok Tani '.$nomor)) ?><?= !empty($k['plasma']) ? ' · Plasma: ' . e($k['plasma']) : '' ?></p>
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
    <div class="grid-2">
      <div><label>Komoditi</label><input name="komoditi" value="<?= e($k['komoditi'] ?? '') ?>"></div>
      <div><label>Jumlah anggota</label><input type="number" step="1" min="0" name="jumlah_anggota" value="<?= e($k['jumlah_anggota'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-green" style="margin-top:14px;">Simpan profil kelompok</button>
  </form>
  <form class="card" method="post" data-ajax="masuk" data-tbody="#tbodyKel" data-count="#cntKel" data-select="#calonAnggota" data-opt="mark">
    <?= csrf_field() ?>
    <input type="hidden" name="kelompok_id" value="<?= $id ?>">
    <input type="hidden" name="act" value="masuk">
    <h3>Masukkan anggota</h3>
    <p style="font-size:13px;color:var(--muted);">Satu kelompok bisa diisi lebih dari satu anggota. Pilih Ketua agar nama/HP tampil di daftar.</p>
    <label>Anggota koperasi</label>
    <select name="anggota_id" id="calonAnggota" required>
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
    <button class="btn btn-green" style="margin-top:14px;"><i class="fa-solid fa-plus"></i> Tambah / perbarui</button>
  </form>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Anggota <?= e($k['kode_kelompok'] ?: ('Kelompok ' . $nomor)) ?> (<span id="cntKel"><?= count($anggotaKel) ?></span>)</h3>
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead>
        <tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Jabatan</th><th>Status</th><th></th></tr>
      </thead>
      <tbody id="tbodyKel" data-empty-cols="6" data-empty-text="Belum ada anggota di kelompok ini.">
      <?php foreach ($anggotaKel as $a): ?><?= tr_anggota_kelompok($a, $id, $jabatanList) ?><?php endforeach; ?>
      <?php if (!$anggotaKel): ?>
        <tr class="empty-row"><td colspan="6">Belum ada anggota di kelompok ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<?php
  $ikutKel = $aid > 0 && in_array($id, array_map('intval', array_column(kelompok_anggota($aid), 'id_kelompok')), true);
  $jabSaya = $ikutKel ? jabatan_saya_kelompok($aid, $id) : '';
?>
<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3><?= e($k['kode_kelompok'] ?: 'KT') ?></h3>
    <p style="font-size:13px;color:var(--muted);"><?= e($k['nama_kelompok'] ?: 'Kelompok') ?><?= !empty($k['plasma']) ? ' · Plasma: ' . e($k['plasma']) : '' ?></p>
    <p style="margin:10px 0;padding:10px;background:#f4faf5;border-radius:10px;">
      <strong>Ketua</strong><br>
      <?= e($k['nama_ketua'] ?: 'Belum dipilih') ?><br>
      <small><?= e($k['no_hp_ketua'] ?: '—') ?></small>
    </p>
    <div class="grid-2">
      <p><strong>Luas tanah</strong><br><?= ((float)($k['luas_tanah'] ?? 0) > 0) ? e(number_format((float)$k['luas_tanah'], 2, ',', '.')) . ' ha' : '—' ?></p>
      <p><strong>Lokasi</strong><br><?= e($k['lokasi'] ?: '—') ?></p>
      <p><strong>Desa</strong><br><?= e($k['desa'] ?: '—') ?></p>
      <p><strong>Kecamatan</strong><br><?= e($k['kecamatan'] ?: '—') ?></p>
      <p><strong>Wilayah / dusun</strong><br><?= e($k['wilayah_dusun'] ?: '—') ?></p>
      <p><strong>Blok hamparan</strong><br><?= e($k['blok_hamparan'] ?: '—') ?></p>
      <p><strong>Tanggal terbentuk</strong><br><?= !empty($k['tanggal_terbentuk']) ? e(tgl($k['tanggal_terbentuk'])) : '—' ?></p>
      <p><strong>Komoditi</strong><br><?= e($k['komoditi'] ?: '—') ?></p>
      <p><strong>Jumlah anggota</strong><br><?= (int)($k['jumlah_anggota'] ?? 0) > 0 ? (int)$k['jumlah_anggota'] : '—' ?></p>
      <p><strong>Fee per kg</strong><br><?= rupiah($k['fee_per_kg'] ?? 0) ?></p>
    </div>
    <?php if (unit_milik_saya('kelompok', $id, $aid)): ?>
    <p style="font-size:13px;color:var(--muted);">Ini buatan Anda — <a href="kelompok.php">ubah dari daftar kelompok</a>.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>Keanggotaan saya</h3>
    <p style="margin-top:10px;">Status: <?= $ikutKel ? '<span class="badge b-aktif">Tergabung' . ($jabSaya !== '' ? ' · ' . e($jabSaya) : '') . '</span>' : '<span class="badge b-pending">Belum tergabung</span>' ?></p>
    <?php if ($ikutKel): ?>
    <form method="post" onsubmit="return confirm('Keluar dari kelompok ini?')">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="keluar">
      <input type="hidden" name="kelompok_id" value="<?= $id ?>">
      <button class="btn btn-ghost"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar dari kelompok</button>
    </form>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="gabung">
      <input type="hidden" name="kelompok_id" value="<?= $id ?>">
      <button class="btn btn-green"><i class="fa-solid fa-plus"></i> Gabung kelompok ini</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<div class="card" style="margin-top:18px;">
  <h3>Anggota <?= e($k['kode_kelompok'] ?: '') ?> (<?= count($anggotaKel) ?>)</h3>
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead><tr><th>No. Anggota</th><th>Nama</th><th>HP</th><th>Jabatan</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($anggotaKel as $a): ?>
        <tr>
          <td><?= e($a['no_anggota'] ?? '') ?></td>
          <td><?= e($a['nama'] ?? '') ?></td>
          <td><?= e($a['no_hp'] ?? '') ?: '—' ?></td>
          <td><?= e($a['jabatan_kelompok'] ?? 'Anggota') ?></td>
          <td><span class="badge b-<?= e($a['status'] ?? 'aktif') ?>"><?= e($a['status'] ?? 'aktif') ?></span></td>
        </tr>
      <?php endforeach; if (!$anggotaKel): ?>
        <tr><td colspan="5">Belum ada anggota di kelompok ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
