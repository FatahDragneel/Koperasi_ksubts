<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kelompok_schema();
ensure_tbs_schema();
ensure_logistik_schema();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'ubah') {
    $id = (int)$_POST['id'];
    $sudahIds = array_map(fn($g) => (int)$g['id_kelompok'], kelompok_anggota($id));
    $baruIds = array_values(array_filter(array_map('intval', (array)($_POST['kelompok_id'] ?? []))));
    $luasBaru = array_values((array)($_POST['luas_kel'] ?? []));
    $luasLama = $_POST['luas_lama'] ?? [];
    $luasTot = 0;
    foreach ($sudahIds as $kid) {
        if ($kid > 0) {
            $luasTot += (float)($luasLama[$kid] ?? 0);
            set_luas_lahan_kelompok($id, $kid, (float)($luasLama[$kid] ?? 0));
        }
    }
    foreach ($baruIds as $i => $kid) {
        if ($kid < 1 || in_array($kid, $sudahIds, true)) {
            continue;
        }
        tambah_anggota_ke_kelompok($id, $kid, 'Anggota');
        set_luas_lahan_kelompok($id, $kid, (float)($luasBaru[$i] ?? 0));
        $luasTot += (float)($luasBaru[$i] ?? 0);
        $sudahIds[] = $kid;
    }
    $pdo->prepare("UPDATE anggota SET no_anggota=?,nik=?,nama=?,jenis_kelamin=?,tempat_lahir=?,tanggal_lahir=?,alamat=?,desa=?,kecamatan=?,no_hp=?,pekerjaan=?,plasma=?,punya_tanah=?,luas_tanah=?,stdb=?,no_stdb=?,tanggal_daftar=?,status=? WHERE id=?")
        ->execute([
            trim($_POST['no_anggota'] ?? ''),
            trim($_POST['nik'] ?? ''),
            trim($_POST['nama'] ?? ''),
            $_POST['jenis_kelamin'] ?? 'L',
            trim($_POST['tempat_lahir'] ?? ''),
            $_POST['tanggal_lahir'] ?: null,
            trim($_POST['alamat'] ?? ''),
            trim($_POST['desa'] ?? ''),
            trim($_POST['kecamatan'] ?? ''),
            trim($_POST['no_hp'] ?? ''),
            trim($_POST['pekerjaan'] ?? ''),
            $sudahIds ? 1 : 0,
            1,
            $luasTot ?: null,
            ($_POST['stdb'] ?? 'belum') === 'sudah' ? 'sudah' : 'belum',
            trim($_POST['no_stdb'] ?? ''),
            $_POST['tanggal_daftar'] ?: date('Y-m-d'),
            $_POST['status'] ?? 'aktif',
            $id,
        ]);
    $uname = trim((string)($_POST['username'] ?? ''));
    if ($uname !== '') {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $uname)) {
            flash('err', 'Username 3–50 karakter: huruf, angka, titik, strip, garis bawah.');
            header('Location: anggota_detail.php?id=' . $id);
            exit;
        }
        if (username_sudah_dipakai($uname, $id)) {
            flash('err', 'Username sudah dipakai.');
            header('Location: anggota_detail.php?id=' . $id);
            exit;
        }
        $pdo->prepare('UPDATE anggota SET username=? WHERE id=?')->execute([$uname, $id]);
    }
    if (!empty($_POST['password_baru']) && strlen($_POST['password_baru']) >= 6) {
        $pdo->prepare('UPDATE anggota SET password=? WHERE id=?')->execute([password_hash($_POST['password_baru'], PASSWORD_DEFAULT), $id]);
    }
    update_berkas_anggota($id);
    flash('ok', 'Data anggota disimpan. Kelompok lama tetap, kelompok baru ditambahkan.');
    header('Location: anggota_detail.php?id=' . $id);
    exit;
}

$st = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    flash('err', 'Anggota tidak ditemukan.');
    header('Location: anggota.php');
    exit;
}
$title = 'Detail anggota · ' . $a['nama'];
$akun = !empty($a['username']) ? $a['username'] : null;
$simpan = $pdo->prepare('SELECT COALESCE(SUM(jumlah),0) FROM simpanan WHERE anggota_id=?');
$simpan->execute([$id]);
$totSimpan = $simpan->fetchColumn();
$pin = $pdo->prepare("SELECT COALESCE(SUM(sisa),0) FROM pinjaman WHERE anggota_id=? AND status NOT IN ('ditolak')");
$pin->execute([$id]);
$sisaPinjam = $pin->fetchColumn();

$kelompokSaya = kelompok_anggota($id);
$luasPerKel = [];
try {
    $lsu = $pdo->prepare('SELECT id_kelompok, SUM(luas_hektar) luas FROM lahan_sawit WHERE anggota_id=? GROUP BY id_kelompok');
    $lsu->execute([$id]);
    foreach ($lsu as $x) {
        $luasPerKel[(int)$x['id_kelompok']] = $x['luas'];
    }
} catch (Throwable $e) {}
$kel = $kelompokSaya[0] ?? null;
if (!$kel && !empty($a['id_kelompok'])) {
    $ks = $pdo->prepare('SELECT * FROM kelompok WHERE id=?');
    $ks->execute([$a['id_kelompok']]);
    $kel = $ks->fetch() ?: null;
}
if (!$kel) {
    $n = nomor_kelompok($a['kelompok_tani'] ?? '');
    if ($n) {
        $ks = $pdo->prepare('SELECT * FROM kelompok WHERE nomor=?');
        $ks->execute([$n]);
        $kel = $ks->fetch() ?: null;
    }
}

$lahan = [];
try {
    $ls = $pdo->prepare('SELECT * FROM lahan_sawit WHERE anggota_id=? ORDER BY id');
    $ls->execute([$id]);
    $lahan = $ls->fetchAll();
} catch (Throwable $e) {}

$saprodi = [];
$piutangSap = 0;
try {
    $ss = $pdo->prepare('SELECT * FROM saprodi WHERE anggota_id=? ORDER BY id DESC');
    $ss->execute([$id]);
    $saprodi = $ss->fetchAll();
    foreach ($saprodi as $sp) {
        $piutangSap += (float)$sp['sisa_piutang'];
    }
} catch (Throwable $e) {}

$tahun = (int)date('Y');
$shu = null;
try {
    $hs = $pdo->prepare('SELECT * FROM shu_alokasi WHERE anggota_id=? AND tahun_buku=?');
    $hs->execute([$id, $tahun]);
    $shu = $hs->fetch() ?: null;
} catch (Throwable $e) {}

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;"><a class="btn btn-ghost btn-sm" href="anggota.php">← Data anggota</a></p>
<div class="row" style="justify-content:space-between;margin-bottom:12px;">
  <span class="badge b-<?= e($a['status']) ?>"><?= e($a['status']) ?></span>
  <div class="actions">
    <button class="btn btn-green" type="button" onclick="openModal('mUbah')">Ubah data anggota</button>
    <?php if ($a['status']==='aktif'): ?>
      <a class="btn btn-danger" href="anggota.php?nonaktif=<?= (int)$a['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Nonaktifkan anggota ini?')">Nonaktifkan</a>
    <?php elseif ($a['status']==='nonaktif'): ?>
      <a class="btn btn-green" href="anggota.php?aktifkan=<?= (int)$a['id'] ?>&_csrf=<?= e(csrf_token()) ?>">Aktifkan</a>
    <?php endif; ?>
  </div>
</div>
<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>Identitas</h3>
    <div class="grid-2" style="margin-top:12px;">
      <p><strong>No. Anggota</strong><br><?= e($a['no_anggota']) ?></p>
      <p><strong>Nama</strong><br><?= e($a['nama']) ?></p>
      <p><strong>NIK</strong><br><?= e($a['nik'] ?: '—') ?></p>
      <p><strong>Jenis kelamin</strong><br><?= ($a['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki' ?></p>
      <p><strong>Tempat / tgl lahir</strong><br><?= e($a['tempat_lahir'] ?: '—') ?>, <?= tgl($a['tanggal_lahir']) ?></p>
      <p><strong>No. HP</strong><br><?= e($a['no_hp'] ?: '—') ?></p>
      <p><strong>Pekerjaan</strong><br><?= e($a['pekerjaan'] ?: '—') ?></p>
      <p><strong>Tanggal daftar</strong><br><?= tgl($a['tanggal_daftar']) ?></p>
    </div>
    <p style="margin-top:10px;"><strong>Alamat</strong><br><?= e($a['alamat'] ?: '—') ?></p>
    <p><strong>Desa / Kecamatan</strong><br><?= e($a['desa'] ?: '—') ?>, <?= e($a['kecamatan'] ?: '—') ?></p>
  </div>
  <div class="card">
    <h3>Kelompok</h3>
    <?php if ($kelompokSaya): ?>
      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead><tr><th>Kode</th><th>Jabatan</th><th>Ketua</th><th>Luas lahan</th></tr></thead>
          <tbody>
          <?php foreach ($kelompokSaya as $gk): ?>
            <tr>
              <td><strong><?= e($gk['kode_kelompok']) ?></strong></td>
              <td><?= e($gk['jabatan'] ?: 'Anggota') ?></td>
              <td><?= e($gk['nama_ketua'] ?: '—') ?></td>
              <td><?= isset($luasPerKel[(int)$gk['id_kelompok']]) ? e($luasPerKel[(int)$gk['id_kelompok']]).' ha' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="margin-top:10px;">Belum masuk kelompok.</p>
    <?php endif; ?>
    <p style="margin-top:10px;"><strong>STDB</strong> <?= ($a['stdb'] ?? '') === 'sudah' ? 'Sudah' : 'Belum' ?><?= !empty($a['no_stdb']) ? ' · '.e($a['no_stdb']) : '' ?></p>
    <p style="margin-top:10px;"><strong>Username login</strong> <?= e($akun ?: '—') ?><br>
       <strong>Total simpanan</strong> <?= rupiah($totSimpan) ?> · <strong>Sisa pinjaman</strong> <?= rupiah($sisaPinjam) ?></p>
  </div>
</div>

<div class="cards" style="grid-template-columns:1fr 1fr;margin-top:16px;">
  <div class="card">
    <h3>Lahan sawit (data sendiri)</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>Lokasi</th><th>Luas</th><th>Tahun tanam</th><th>Pokok</th><th>SHM atas nama</th></tr></thead>
        <tbody>
        <?php foreach ($lahan as $l): ?>
          <tr>
            <td><?= e($l['lokasi_desa'] ?: '—') ?></td>
            <td><?= e($l['luas_hektar']) ?> ha</td>
            <td><?= e($l['tahun_tanam'] ?: '—') ?></td>
            <td><?= e($l['jumlah_pokok'] ?: '—') ?></td>
            <td><?= e(($l['atas_nama_shm'] ?? '') ?: '—') ?><?php if (!empty($l['no_shm'])): ?><br><small><?= e($l['no_shm']) ?></small><?php endif; ?></td>
          </tr>
        <?php endforeach; if (!$lahan): ?>
          <tr><td colspan="5">Belum ada. Tambah / ubah di menu <a href="tbs_lahan.php">Lahan sawit</a>.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <h3>SHU <?= $tahun ?> (data sendiri)</h3>
    <?php if ($shu): ?>
      <div class="grid-2" style="margin-top:12px;">
        <p><strong>Jasa modal</strong><br><?= rupiah($shu['jasa_modal']) ?></p>
        <p><strong>Jasa usaha</strong><br><?= rupiah($shu['jasa_usaha']) ?></p>
        <p><strong>Total SHU</strong><br><?= rupiah($shu['total_shu']) ?></p>
      </div>
    <?php else: ?>
      <p style="margin-top:12px;color:var(--muted);">Belum dialokasi. Hitung di menu <a href="shu.php">SHU</a>.</p>
    <?php endif; ?>
    <p style="margin-top:12px;"><strong>Piutang saprodi</strong> <?= rupiah($piutangSap) ?></p>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Saprodi (data sendiri)</h3>
  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <thead><tr><th>Tanggal</th><th>Barang</th><th>Qty</th><th>Total</th><th>Sisa piutang</th><th>Status</th></tr></thead>
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
        <tr><td colspan="6">Belum ada. Catat di menu <a href="saprodi.php">Saprodi</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Berkas</h3>
  <div class="docs">
    <?php foreach ([['foto','Foto diri','foto'],['ktp_file','Foto KTP','ktp'],['sertifikat_file','Sertifikat tanah','sertifikat']] as [$f,$cap,$jenis]):
      $src = !empty($a[$f]) ? berkas_url((int)$a['id'], $jenis) : ''; ?>
    <?php if ($src): ?>
    <figure class="zoom" data-src="<?= e($src) ?>" data-cap="<?= e($cap) ?>">
      <img src="<?= e($src) ?>" alt="<?= e($cap) ?>">
      <figcaption><?= e($cap) ?> · klik perbesar</figcaption>
    </figure>
    <?php else: ?>
    <figure style="cursor:default;">
      <div style="height:180px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;padding:12px;text-align:center;">Belum ada <?= e(strtolower($cap)) ?></div>
      <figcaption><?= e($cap) ?></figcaption>
    </figure>
    <?php endif; endforeach; ?>
  </div>
</div>
<?php
$arsipAlih = [];
try {
    ensure_pengalihan_schema();
    $ah = $pdo->prepare('SELECT * FROM pengalihan_hak WHERE anggota_lama_id=? OR anggota_baru_id=? ORDER BY id DESC');
    $ah->execute([$id, $id]);
    $arsipAlih = $ah->fetchAll();
} catch (Throwable $e) {}
?>
<?php if ($arsipAlih): ?>
<div class="card" style="margin-top:16px;">
  <h3>Arsip pengalihan hak</h3>
  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <thead><tr><th>No</th><th>Tanggal</th><th>Peran</th><th>Status</th><th>Berkas</th></tr></thead>
      <tbody>
      <?php foreach ($arsipAlih as $ar): ?>
        <tr>
          <td><?= e($ar['no_pengalihan']) ?></td>
          <td><?= tgl($ar['tanggal']) ?></td>
          <td><?= (int)$ar['anggota_lama_id'] === $id ? 'Anggota lama (keluar)' : 'Anggota baru (penerima)' ?></td>
          <td><?= e($ar['status']) ?></td>
          <td>
            <?php if ($ar['file_sjb']): ?><a href="berkas.php?id=<?= (int)$ar['id'] ?>&jenis=sjb" target="_blank">SJB</a><?php endif; ?>
            <?php if ($ar['file_ba']): ?> · <a href="berkas.php?id=<?= (int)$ar['id'] ?>&jenis=ba" target="_blank">BA</a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php $idsSudah = array_map(fn($g) => (int)$g['id_kelompok'], $kelompokSaya); ?>
<div class="modal-bg" id="mUbah">
  <form class="modal" method="post" style="max-width:720px;max-height:90vh;overflow:auto;">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="ubah">
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <h3>Ubah data anggota</h3>

    <div class="form-sec">
      <h4>Identitas</h4>
      <div class="grid-2">
        <div><label>No. Anggota</label><input name="no_anggota" value="<?= e($a['no_anggota']) ?>" required></div>
        <div><label>NIK</label><input name="nik" value="<?= e($a['nik']) ?>" maxlength="16"></div>
      </div>
      <label>Nama lengkap</label><input name="nama" value="<?= e($a['nama']) ?>" required>
      <div class="grid-2">
        <div>
          <label>Jenis kelamin</label>
          <select name="jenis_kelamin">
            <option value="L" <?= ($a['jenis_kelamin']??'')==='L'?'selected':'' ?>>Laki-laki</option>
            <option value="P" <?= ($a['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option>
          </select>
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <?php foreach (['aktif','pending','nonaktif'] as $stt): ?>
              <option value="<?= $stt ?>" <?= ($a['status']??'')===$stt?'selected':'' ?>><?= $stt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div><label>Tempat lahir</label><input name="tempat_lahir" value="<?= e($a['tempat_lahir']) ?>"></div>
        <div><label>Tanggal lahir</label><input type="date" name="tanggal_lahir" value="<?= e($a['tanggal_lahir']) ?>"></div>
      </div>
      <label>Alamat</label><textarea name="alamat"><?= e($a['alamat']) ?></textarea>
      <div class="grid-2">
        <div><label>Desa</label><input name="desa" value="<?= e($a['desa']) ?>"></div>
        <div><label>Kecamatan</label><input name="kecamatan" value="<?= e($a['kecamatan']) ?>"></div>
      </div>
      <div class="grid-2">
        <div><label>No. HP</label><input name="no_hp" value="<?= e($a['no_hp']) ?>"></div>
        <div><label>Pekerjaan</label><input name="pekerjaan" value="<?= e($a['pekerjaan']) ?>"></div>
      </div>
      <label>Tanggal daftar</label><input type="date" name="tanggal_daftar" value="<?= e($a['tanggal_daftar']) ?>">
    </div>

    <div class="form-sec">
      <h4>Kelompok yang sudah dimiliki</h4>
      <?php if ($kelompokSaya): ?>
        <div class="table-wrap" style="margin-bottom:10px;">
          <table>
            <thead><tr><th>Kelompok</th><th>Luas (ha)</th></tr></thead>
            <tbody>
            <?php foreach ($kelompokSaya as $gk): $kid=(int)$gk['id_kelompok']; ?>
              <tr>
                <td><strong><?= e($gk['kode_kelompok']) ?></strong> — <?= e($gk['nama_kelompok'] ?: '') ?></td>
                <td><input name="luas_lama[<?= $kid ?>]" type="number" step="0.01" min="0" value="<?= e((string)($luasPerKel[$kid] ?? '')) ?>" style="max-width:120px;margin:0;"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p style="font-size:12px;color:var(--muted);margin:0 0 10px;">Kelompok di atas tetap. Pilihan di bawah hanya kelompok yang belum dimiliki.</p>
      <?php else: ?>
        <p style="font-size:13px;color:var(--muted);">Belum masuk kelompok. Tambah di bawah.</p>
      <?php endif; ?>
      <?= html_slot_kelompok(5, $idsSudah, 'Tambah berapa kelompok lagi?', true) ?>
      <div class="grid-2" style="margin-top:12px;">
        <div>
          <label>STDB</label>
          <select name="stdb">
            <option value="belum" <?= ($a['stdb']??'')!=='sudah'?'selected':'' ?>>Belum</option>
            <option value="sudah" <?= ($a['stdb']??'')==='sudah'?'selected':'' ?>>Sudah</option>
          </select>
        </div>
        <div><label>Nomor STDB</label><input name="no_stdb" value="<?= e($a['no_stdb']) ?>"></div>
      </div>
    </div>

    <div class="form-sec">
      <h4>Berkas (gambar)</h4>
      <p style="font-size:12px;color:var(--muted);margin:0 0 8px;">Kosongkan jika tidak diganti. File yang ada tetap dipakai.</p>
      <div class="docs" style="margin-bottom:10px;">
        <?php foreach ([['foto','Foto diri','foto'],['ktp_file','Foto KTP','ktp'],['sertifikat_file','Sertifikat','sertifikat']] as [$f,$cap,$jenis]):
          $src = !empty($a[$f]) ? berkas_url((int)$a['id'], $jenis) : ''; ?>
        <figure <?= $src ? 'class="zoom" data-src="'.e($src).'" data-cap="'.e($cap).'"' : 'style="cursor:default;"' ?>>
          <?php if ($src): ?>
            <img src="<?= e($src) ?>" alt="<?= e($cap) ?>">
          <?php else: ?>
            <div style="height:120px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px;">Belum ada</div>
          <?php endif; ?>
          <figcaption><?= e($cap) ?></figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <div class="grid-2">
        <div><label>Ganti foto diri</label><input type="file" name="foto" accept="image/*"></div>
        <div><label>Ganti foto KTP</label><input type="file" name="ktp_file" accept="image/*"></div>
      </div>
      <label>Ganti sertifikat tanah</label><input type="file" name="sertifikat_file" accept="image/*">
    </div>

    <div class="form-sec">
      <h4>Akun masuk</h4>
      <label>Username</label>
      <input name="username" value="<?= e($a['username'] ?? '') ?>" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9._-]+" placeholder="untuk login anggota">
      <label>Sandi baru (kosongkan jika tidak diubah)</label>
      <input type="password" name="password_baru" minlength="6" placeholder="Minimal 6 karakter">
    </div>

    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mUbah')">Batal</button>
      <button class="btn btn-green">Simpan perubahan</button>
    </div>
  </form>
</div>
<script>
function tampilSlotKel() {
  const el = document.getElementById('jmlKel');
  const n = el ? (parseInt(el.value, 10) || 0) : 0;
  document.querySelectorAll('.slot-kel').forEach(function (box) {
    const i = parseInt(box.dataset.n, 10);
    const on = i <= n;
    box.style.display = on ? '' : 'none';
    box.querySelectorAll('select,input').forEach(function (inp) { inp.disabled = !on; });
  });
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
