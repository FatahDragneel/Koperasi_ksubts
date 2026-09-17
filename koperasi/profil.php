<?php
require __DIR__ . '/config.php';
require_login();
ensure_kelompok_schema();
ensure_tbs_schema();
ensure_logistik_schema();
$title = 'Profil';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'sandi';
    if ($act === 'username' && !$staff && !empty($u['anggota_id'])) {
        $uname = strtolower(trim((string)($_POST['username'] ?? '')));
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $uname)) {
            flash('err', 'Username 3–50 karakter: huruf, angka, titik, _ atau -.');
        } elseif (username_sudah_dipakai($uname, (int)$u['anggota_id'])) {
            flash('err', 'Username sudah dipakai.');
        } else {
            $pdo->prepare('UPDATE anggota SET username=? WHERE id=?')->execute([$uname, (int)$u['anggota_id']]);
            $_SESSION['user']['username'] = $uname;
            flash('ok', 'Username diperbarui. Login berikutnya pakai username baru.');
        }
    } elseif ($act === 'gabung' && !$staff && !empty($u['anggota_id'])) {
        $aid = (int)$u['anggota_id'];
        $kid = (int)($_POST['id_kelompok'] ?? 0);
        $ada = $kid > 0 ? $pdo->query('SELECT COUNT(*) FROM kelompok WHERE id=' . $kid)->fetchColumn() : 0;
        if (!$ada) {
            flash('err', 'Kelompok tidak ditemukan.');
        } else {
            tambah_anggota_ke_kelompok($aid, $kid, 'Anggota');
            set_luas_lahan_kelompok($aid, $kid, (float)($_POST['luas_hektar'] ?? 0));
            flash('ok', 'Anda tergabung ke kelompok.');
        }
    } elseif ($act === 'keluar_kel' && !$staff && !empty($u['anggota_id'])) {
        keluar_anggota_dari_kelompok((int)$u['anggota_id'], (int)($_POST['id_kelompok'] ?? 0));
        flash('ok', 'Anda keluar dari kelompok.');
    } elseif ($act === 'gabung_gap' && !$staff && !empty($u['anggota_id'])) {
        $aid = (int)$u['anggota_id'];
        $gid = (int)($_POST['id_gapoktan'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) FROM gapoktan WHERE id=?');
        $st->execute([$gid]);
        if ($gid < 1 || !$st->fetchColumn()) {
            flash('err', 'Gapoktan tidak ditemukan.');
        } else {
            tambah_anggota_ke_gapoktan($aid, $gid);
            flash('ok', 'Anda tergabung ke gapoktan.');
        }
    } elseif ($act === 'keluar_gap' && !$staff && !empty($u['anggota_id'])) {
        keluar_anggota_dari_gapoktan((int)$u['anggota_id'], (int)($_POST['id_gapoktan'] ?? 0));
        flash('ok', 'Anda keluar dari gapoktan.');
    } elseif ($act === 'gabung_lem' && !$staff && !empty($u['anggota_id'])) {
        $aid = (int)$u['anggota_id'];
        $lid = (int)($_POST['id_koperasi'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) FROM lembaga_koperasi WHERE id=?');
        $st->execute([$lid]);
        if ($lid < 1 || !$st->fetchColumn()) {
            flash('err', 'Koperasi tidak ditemukan.');
        } else {
            tambah_anggota_ke_lembaga($aid, $lid);
            flash('ok', 'Anda tergabung ke koperasi.');
        }
    } elseif ($act === 'keluar_lem' && !$staff && !empty($u['anggota_id'])) {
        keluar_anggota_dari_lembaga((int)$u['anggota_id'], (int)($_POST['id_koperasi'] ?? 0));
        flash('ok', 'Anda keluar dari koperasi.');
    } elseif ($act === 'data' && !$staff && !empty($u['anggota_id'])) {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $jk = ($_POST['jenis_kelamin'] ?? 'L') === 'P' ? 'P' : 'L';
        if ($nama === '') {
            flash('err', 'Nama tidak boleh kosong.');
        } else {
            $pdo->prepare('UPDATE anggota SET nama=?,nik=?,jenis_kelamin=?,tempat_lahir=?,tanggal_lahir=?,alamat=?,desa=?,kecamatan=?,no_hp=?,pekerjaan=? WHERE id=?')->execute([
                $nama,
                trim((string)($_POST['nik'] ?? '')) ?: null,
                $jk,
                trim((string)($_POST['tempat_lahir'] ?? '')) ?: null,
                trim((string)($_POST['tanggal_lahir'] ?? '')) ?: null,
                trim((string)($_POST['alamat'] ?? '')) ?: null,
                trim((string)($_POST['desa'] ?? '')) ?: null,
                trim((string)($_POST['kecamatan'] ?? '')) ?: null,
                trim((string)($_POST['no_hp'] ?? '')) ?: null,
                trim((string)($_POST['pekerjaan'] ?? '')) ?: null,
                (int)$u['anggota_id'],
            ]);
            $_SESSION['user']['nama'] = $nama;
            flash('ok', 'Data diri diperbarui.');
        }
    } elseif (!empty($_POST['password'])) {
        if ($_POST['password'] !== ($_POST['password2'] ?? '')) {
            flash('err', 'Konfirmasi sandi tidak sama.');
        } elseif (strlen($_POST['password']) < 6) {
            flash('err', 'Sandi minimal 6 karakter.');
        } else {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            if ($staff) {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $u['id']]);
            } else {
                $pdo->prepare('UPDATE anggota SET password=? WHERE id=?')->execute([$hash, $u['anggota_id']]);
            }
            flash('ok', 'Kata sandi diperbarui.');
        }
    }
    header('Location: profil.php');
    exit;
}

$anggota = null;
$kel = null;
$kelompokSaya = [];
$lahan = [];
$saprodi = [];
$piutangSap = 0;
$shu = null;
$tahun = (int)date('Y');

if (!$staff && !empty($u['anggota_id'])) {
    $aid = (int)$u['anggota_id'];
    $st = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
    $st->execute([$aid]);
    $anggota = $st->fetch();
    if ($anggota) {
        $kelompokSaya = kelompok_anggota($aid);
        $kel = $kelompokSaya[0] ?? null;
        $gapSaya = gapoktan_anggota($aid);
        $lemSaya = lembaga_anggota($aid);
        $idsGapSaya = array_map('intval', array_column($gapSaya, 'id'));
        $idsLemSaya = array_map('intval', array_column($lemSaya, 'id'));
        $gapTersedia = array_values(array_filter(
            $pdo->query('SELECT id, kode_gapoktan, nama_gapoktan FROM gapoktan ORDER BY nama_gapoktan')->fetchAll(),
            fn($g) => !in_array((int)$g['id'], $idsGapSaya, true)
        ));
        $lemTersedia = array_values(array_filter(
            $pdo->query('SELECT id, kode_koperasi, nama_koperasi FROM lembaga_koperasi ORDER BY nama_koperasi')->fetchAll(),
            fn($l) => !in_array((int)$l['id'], $idsLemSaya, true)
        ));
        try {
            $ls = $pdo->prepare('SELECT l.*, k.kode_kelompok, k.nama_kelompok FROM lahan_sawit l LEFT JOIN kelompok k ON k.id=l.id_kelompok WHERE l.anggota_id=? ORDER BY k.nomor, l.id');
            $ls->execute([$aid]);
            $lahan = $ls->fetchAll();
        } catch (Throwable $e) {}
        try {
            $ss = $pdo->prepare('SELECT * FROM saprodi WHERE anggota_id=? ORDER BY id DESC');
            $ss->execute([$aid]);
            $saprodi = $ss->fetchAll();
            foreach ($saprodi as $sp) {
                $piutangSap += (float)$sp['sisa_piutang'];
            }
        } catch (Throwable $e) {}
        try {
            $hs = $pdo->prepare('SELECT * FROM shu_alokasi WHERE anggota_id=? AND tahun_buku=?');
            $hs->execute([$aid, $tahun]);
            $shu = $hs->fetch() ?: null;
        } catch (Throwable $e) {}
    }
}

include __DIR__ . '/includes/app_header.php';

if ($staff):
?>
<div class="card" style="max-width:520px;">
  <h3>Akun pengurus</h3>
  <p style="margin:10px 0;"><strong><?= e($u['nama']) ?></strong><br>Username: <?= e($u['username']) ?><br>Peran: <?= e($u['role']) ?></p>
  <form method="post">
    <?= csrf_field() ?>
    <label>Kata sandi baru</label>
    <input type="password" name="password" required>
    <label>Ulangi sandi</label>
    <input type="password" name="password2" required>
    <button class="btn btn-green" style="margin-top:14px;">Ubah sandi</button>
  </form>
</div>
<?php else: ?>
<?php if (!$anggota): ?>
  <div class="card"><p>Data keanggotaan belum terhubung ke akun ini.</p></div>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><span>Kelompok</span><b><?= count($kelompokSaya ?? []) ?></b></div>
  <div class="kpi"><span>Lahan</span><b><?= count($lahan) ?> bidang</b></div>
  <div class="kpi"><span>Piutang saprodi</span><b><?= rupiah($piutangSap) ?></b></div>
  <?php if (FITUR_SHU): ?><div class="kpi"><span>SHU <?= $tahun ?></span><b><?= rupiah($shu['total_shu'] ?? 0) ?></b></div><?php endif; ?>
</div>

<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <h3>Data saya</h3>
      <button class="btn btn-gold btn-sm" type="button" onclick="openModal('mData')"><i class="fa-solid fa-pen"></i> Ubah</button>
    </div>
    <div class="grid-2" style="margin-top:12px;">
      <p><strong>No. Anggota</strong><br><?= e($anggota['no_anggota']) ?></p>
      <p><strong>Nama</strong><br><?= e($anggota['nama']) ?></p>
      <p><strong>NIK</strong><br><?= e($anggota['nik'] ?: '—') ?></p>
      <p><strong>HP</strong><br><?= e($anggota['no_hp'] ?: '—') ?></p>
      <p><strong>Desa / Kecamatan</strong><br><?= e($anggota['desa'] ?: '—') ?>, <?= e($anggota['kecamatan'] ?: '—') ?></p>
      <p><strong>Pekerjaan</strong><br><?= e($anggota['pekerjaan'] ?: '—') ?></p>
      <p><strong>STDB</strong><br><?= ($anggota['stdb']??'')==='sudah' ? 'Sudah' : 'Belum' ?><?= $anggota['no_stdb'] ? ' · '.e($anggota['no_stdb']) : '' ?></p>
      <p><strong>Status</strong><br><?= e($anggota['status']) ?></p>
    </div>
    <p style="margin-top:10px;"><strong>Alamat</strong><br><?= e($anggota['alamat'] ?: '—') ?></p>
    <p><strong>Username</strong> <?= e($anggota['username'] ?: '—') ?></p>
  </div>
  <?php if (FITUR_SHU): ?>
  <div class="card">
    <h3>SHU saya <?= $tahun ?></h3>
    <?php if ($shu): ?>
      <div class="grid-2" style="margin-top:12px;">
        <p><strong>Jasa modal</strong><br><?= rupiah($shu['jasa_modal']) ?></p>
        <p><strong>Jasa usaha</strong><br><?= rupiah($shu['jasa_usaha']) ?></p>
        <p><strong>Total SHU</strong><br><?= rupiah($shu['total_shu']) ?></p>
      </div>
    <?php else: ?>
      <p style="margin-top:12px;color:var(--muted);">SHU <?= $tahun ?> belum dialokasikan pengurus.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Lembaga saya</h3>
  <div class="cards" style="grid-template-columns:1fr 1fr 1fr;margin-top:12px;">
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-building-columns lembaga-ic"></i>Koperasi saya</h4>
      <?php if (!$lemSaya): ?>
        <p style="font-size:13px;color:var(--muted);">Belum tergabung.</p>
      <?php endif; ?>
      <?php foreach ($lemSaya as $l): ?>
        <form method="post" style="display:flex;gap:6px;align-items:center;margin:4px 0;" onsubmit="return confirm('Keluar dari <?= e($l['kode_koperasi']) ?>?');">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="keluar_lem">
          <input type="hidden" name="id_koperasi" value="<?= (int)$l['id'] ?>">
          <span style="flex:1;font-size:13px;"><?= e(($l['kode_koperasi'] ?: 'KOP') . ' — ' . ($l['nama_koperasi'] ?: 'Koperasi')) ?></span>
          <button class="btn btn-ghost btn-sm">Keluar</button>
        </form>
      <?php endforeach; ?>
      <?php if ($lemTersedia): ?>
      <form method="post" style="margin-top:10px;border-top:1px solid #ece6d6;padding-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="gabung_lem">
        <label style="font-size:12px;">Gabung koperasi</label>
        <select name="id_koperasi" required><option value="">Pilih koperasi</option><?php foreach ($lemTersedia as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e(($l['kode_koperasi'] ?: 'KOP') . ' — ' . ($l['nama_koperasi'] ?: 'Koperasi')) ?></option><?php endforeach; ?></select>
        <button class="btn btn-green btn-sm" style="margin-top:8px;">Gabung</button>
      </form>
      <?php endif; ?>
    </div>
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-people-group lembaga-ic"></i>Gapoktan saya</h4>
      <?php if (!$gapSaya): ?>
        <p style="font-size:13px;color:var(--muted);">Belum tergabung.</p>
      <?php endif; ?>
      <?php foreach ($gapSaya as $g): ?>
        <form method="post" style="display:flex;gap:6px;align-items:center;margin:4px 0;" onsubmit="return confirm('Keluar dari <?= e($g['kode_gapoktan']) ?>?');">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="keluar_gap">
          <input type="hidden" name="id_gapoktan" value="<?= (int)$g['id'] ?>">
          <span style="flex:1;font-size:13px;"><?= e(($g['kode_gapoktan'] ?: 'GAP') . ' — ' . ($g['nama_gapoktan'] ?: 'Gapoktan')) ?></span>
          <button class="btn btn-ghost btn-sm">Keluar</button>
        </form>
      <?php endforeach; ?>
      <?php if ($gapTersedia): ?>
      <form method="post" style="margin-top:10px;border-top:1px solid #ece6d6;padding-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="gabung_gap">
        <label style="font-size:12px;">Gabung gapoktan</label>
        <select name="id_gapoktan" required><option value="">Pilih gapoktan</option><?php foreach ($gapTersedia as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e(($g['kode_gapoktan'] ?: 'GAP') . ' — ' . ($g['nama_gapoktan'] ?: 'Gapoktan')) ?></option><?php endforeach; ?></select>
        <button class="btn btn-green btn-sm" style="margin-top:8px;">Gabung</button>
      </form>
      <?php endif; ?>
    </div>
    <div>
      <h4 style="font-size:13px;margin-bottom:8px;"><i class="fa-solid fa-users lembaga-ic"></i>Kelompok saya</h4>
      <?php if (empty($kelompokSaya)): ?>
        <p style="font-size:13px;color:var(--muted);">Belum tergabung.</p>
      <?php endif; ?>
      <?php foreach ($kelompokSaya as $gk): ?>
        <form method="post" style="display:flex;gap:6px;align-items:center;margin:4px 0;" onsubmit="return confirm('Keluar dari <?= e($gk['kode_kelompok']) ?>?');">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="keluar_kel">
          <input type="hidden" name="id_kelompok" value="<?= (int)$gk['id_kelompok'] ?>">
          <span style="flex:1;font-size:13px;"><?= e(($gk['kode_kelompok'] ?: 'KT') . ' — ' . ($gk['nama_kelompok'] ?: 'Kelompok')) ?><br><small style="color:var(--muted);">Jabatan: <?= e($gk['jabatan'] ?: 'Anggota') ?> · Ketua: <?= e($gk['nama_ketua'] ?: '—') ?></small></span>
          <button class="btn btn-ghost btn-sm">Keluar</button>
        </form>
      <?php endforeach; ?>
      <?php $opsiGabung = options_kelompok_id([], array_map('intval', array_column($kelompokSaya, 'id_kelompok'))); ?>
      <?php if ($opsiGabung !== ''): ?>
      <form method="post" style="margin-top:10px;border-top:1px solid #ece6d6;padding-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="gabung">
        <label style="font-size:12px;">Gabung kelompok</label>
        <select name="id_kelompok" required><option value="">Pilih kelompok</option><?= $opsiGabung ?></select>
        <label style="font-size:12px;">Luas lahan saya di kelompok ini (ha)</label>
        <input name="luas_hektar" type="number" step="0.01" min="0" value="0">
        <button class="btn btn-green btn-sm" style="margin-top:8px;">Gabung</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Lahan sawit saya</h3>
  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <thead><tr><th>Kelompok</th><th>Lokasi</th><th>Luas</th><th>Tahun tanam</th><th>Pokok</th></tr></thead>
      <tbody>
      <?php foreach ($lahan as $l): ?>
        <tr>
          <td><?= e($l['kode_kelompok'] ?: '—') ?></td>
          <td><?= e($l['lokasi_desa'] ?: '—') ?></td>
          <td><?= e($l['luas_hektar']) ?> ha</td>
          <td><?= e($l['tahun_tanam'] ?: '—') ?></td>
          <td><?= e($l['jumlah_pokok'] ?: '—') ?></td>
        </tr>
      <?php endforeach; if (!$lahan): ?>
        <tr><td colspan="5">Belum ada data lahan. Gabung kelompok di atas sambil mengisi luas kebun.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Saprodi / piutang saya</h3>
  <p style="margin:8px 0 10px;">Sisa piutang: <strong><?= rupiah($piutangSap) ?></strong></p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tanggal</th><th>Barang</th><th>Qty</th><th>Total</th><th>Sisa</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($saprodi as $sp): ?>
        <tr>
          <td><?= tgl($sp['tanggal']) ?></td>
          <td><?= e($sp['item_barang']) ?></td>
          <td><?= e($sp['kuantitas']) ?></td>
          <td><?= rupiah($sp['total_harga']) ?></td>
          <td><?= rupiah($sp['sisa_piutang']) ?></td>
          <td><span class="badge <?= $sp['status_bayar']==='lunas'?'b-lunas':'b-pengajuan' ?>"><?= e($sp['status_bayar']) ?></span></td>
        </tr>
      <?php endforeach; if (!$saprodi): ?>
        <tr><td colspan="6">Tidak ada nota saprodi atas nama Anda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px;max-width:420px;">
  <h3>Ubah sandi</h3>
  <form method="post">
    <?= csrf_field() ?>
    <label>Kata sandi baru</label>
    <input type="password" name="password" required minlength="6">
    <label>Ulangi sandi</label>
    <input type="password" name="password2" required minlength="6">
    <button class="btn btn-green" style="margin-top:14px;">Simpan sandi</button>
  </form>
</div>
<div class="modal-bg" id="mData">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="data">
    <h3>Ubah data saya</h3>
    <p style="font-size:13px;color:var(--muted);margin:0 0 12px;">Nomor anggota, status, dan STDB hanya bisa diubah pengurus.</p>
    <label>Nama lengkap<input name="nama" value="<?= e($anggota['nama'] ?? '') ?>" required></label>
    <div class="grid-2">
      <div><label>NIK</label><input name="nik" value="<?= e($anggota['nik'] ?? '') ?>"></div>
      <div><label>Jenis kelamin</label><select name="jenis_kelamin">
        <option value="L" <?= ($anggota['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
        <option value="P" <?= ($anggota['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
      </select></div>
    </div>
    <div class="grid-2">
      <div><label>Tempat lahir</label><input name="tempat_lahir" value="<?= e($anggota['tempat_lahir'] ?? '') ?>"></div>
      <div><label>Tanggal lahir</label><input type="date" name="tanggal_lahir" value="<?= e($anggota['tanggal_lahir'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>No. HP</label><input name="no_hp" value="<?= e($anggota['no_hp'] ?? '') ?>"></div>
      <div><label>Pekerjaan</label><input name="pekerjaan" value="<?= e($anggota['pekerjaan'] ?? '') ?>"></div>
    </div>
    <label>Alamat<textarea name="alamat" rows="2"><?= e($anggota['alamat'] ?? '') ?></textarea></label>
    <div class="grid-2">
      <div><label>Desa</label><input name="desa" value="<?= e($anggota['desa'] ?? '') ?>"></div>
      <div><label>Kecamatan</label><input name="kecamatan" value="<?= e($anggota['kecamatan'] ?? '') ?>"></div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mData')"><i class="fa-solid fa-xmark"></i> Batal</button>
      <button class="btn btn-green" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
