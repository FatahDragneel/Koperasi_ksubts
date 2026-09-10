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
        } elseif (!tambah_anggota_ke_kelompok($aid, $kid, 'Anggota')) {
            flash('err', 'Kelompok ini sudah diisi anggota lain. Pilih kelompok lain.');
        } else {
            set_luas_lahan_kelompok($aid, $kid, (float)($_POST['luas_hektar'] ?? 0));
            flash('ok', 'Anda tergabung ke kelompok.');
        }
    } elseif ($act === 'keluar_kel' && !$staff && !empty($u['anggota_id'])) {
        keluar_anggota_dari_kelompok((int)$u['anggota_id'], (int)($_POST['id_kelompok'] ?? 0));
        flash('ok', 'Anda keluar dari kelompok.');
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
  <div class="kpi"><span>SHU <?= $tahun ?></span><b><?= rupiah($shu['total_shu'] ?? 0) ?></b></div>
</div>

<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>Data saya</h3>
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
  <div class="card">
    <h3>Kelompok saya</h3>
    <?php if (!empty($kelompokSaya)): ?>
      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead><tr><th>Kode</th><th>Nama</th><th>Jabatan saya</th><th>Ketua</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($kelompokSaya as $gk): ?>
            <tr>
              <td><strong><?= e($gk['kode_kelompok']) ?></strong></td>
              <td><?= e($gk['nama_kelompok']) ?></td>
              <td><?= e($gk['jabatan'] ?: 'Anggota') ?></td>
              <td><?= e($gk['nama_ketua'] ?: '—') ?><?= !empty($gk['no_hp_ketua']) ? '<br><small>'.e($gk['no_hp_ketua']).'</small>' : '' ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Keluar dari <?= e($gk['kode_kelompok']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="act" value="keluar_kel">
                  <input type="hidden" name="id_kelompok" value="<?= (int)$gk['id_kelompok'] ?>">
                  <button class="btn btn-danger btn-sm">Keluar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="margin-top:12px;color:var(--muted);">Anda belum terhubung ke kelompok tani.</p>
    <?php endif; ?>
    <?php $opsiGabung = options_kelompok_id([], array_unique(array_merge(array_map('intval', array_column($kelompokSaya, 'id_kelompok')), ids_kelompok_terisi($aid)))); ?>
    <?php if ($opsiGabung !== ''): ?>
    <form method="post" style="margin-top:12px;border-top:1px solid #ece6d6;padding-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="gabung">
      <h4 style="font-size:13px;margin-bottom:8px;">Gabung kelompok</h4>
      <label>Kelompok</label>
      <select name="id_kelompok" required><option value="">Pilih kelompok</option><?= $opsiGabung ?></select>
      <label>Luas lahan saya di kelompok ini (ha)</label>
      <input name="luas_hektar" type="number" step="0.01" min="0" value="0">
      <button class="btn btn-green btn-sm" style="margin-top:10px;">Gabung</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="cards" style="grid-template-columns:1fr 1fr;margin-top:16px;">
  <div class="card">
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
<?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
