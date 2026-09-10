<?php
require __DIR__ . '/config.php';
try {
    ensure_anggota_schema();
    ensure_kelompok_schema();
    $s = setting();
} catch (Throwable $e) {
    exit('Gagal memuat database: ' . htmlspecialchars($e->getMessage()));
}
if (auth()) {
    header('Location: dashboard.php');
    exit;
}

function simpan_berkas(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'] ?? 'jpg', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = 'jpg';
    }
    $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    return $name;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'Foto terlalu besar. Perkecil, lalu kirim lagi.';
    } else {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $nik = trim((string)($_POST['nik'] ?? ''));
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $kelIds = array_values(array_filter(array_map('intval', (array)($_POST['kelompok_id'] ?? []))));
        $luasArr = array_values((array)($_POST['luas_kel'] ?? []));
        $luasTot = 0;
        foreach ($luasArr as $i => $v) {
            if (!empty($kelIds[$i])) {
                $luasTot += (float)$v;
            }
        }
        if ($nama === '' || $nik === '' || $user === '' || strlen($pass) < 6) {
            $error = 'Isi nama, NIK, username, dan sandi (min. 6 karakter).';
        } elseif (!$kelIds) {
            $error = 'Pilih minimal satu kelompok.';
        } elseif (username_sudah_dipakai($user)) {
            $error = 'Username sudah dipakai.';
        } else {
            try {
                $n = 1 + (int)db()->query('SELECT COUNT(*) FROM anggota')->fetchColumn();
                $no = 'AGT-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
                $tglLahir = trim((string)($_POST['tanggal_lahir'] ?? '')) ?: null;
                $insAgt = [
                    'no_anggota' => $no,
                    'nik' => $nik,
                    'nama' => $nama,
                    'jenis_kelamin' => $_POST['jenis_kelamin'] ?? 'L',
                    'tempat_lahir' => trim((string)($_POST['tempat_lahir'] ?? '')),
                    'tanggal_lahir' => $tglLahir,
                    'alamat' => trim((string)($_POST['alamat'] ?? '')),
                    'desa' => trim((string)($_POST['desa'] ?? '')),
                    'kecamatan' => trim((string)($_POST['kecamatan'] ?? '')),
                    'no_hp' => trim((string)($_POST['no_hp'] ?? '')),
                    'pekerjaan' => trim((string)($_POST['pekerjaan'] ?? 'Petani')),
                    'kelompok_tani' => (string)$kelIds[0],
                    'plasma' => 1,
                    'punya_tanah' => 1,
                    'luas_tanah' => $luasTot ?: null,
                    'stdb' => (($_POST['stdb'] ?? '') === 'sudah') ? 'sudah' : 'belum',
                    'no_stdb' => trim((string)($_POST['no_stdb'] ?? '')),
                    'tanggal_daftar' => date('Y-m-d'),
                    'status' => 'pending',
                    'foto' => simpan_berkas('foto'),
                    'ktp_file' => simpan_berkas('ktp_file'),
                    'sertifikat_file' => simpan_berkas('sertifikat_file'),
                    'jabatan_kelompok' => 'Anggota',
                    'username' => $user,
                    'password' => password_hash($pass, PASSWORD_DEFAULT),
                ];
                $adaKol = [];
                try {
                    $adaKol = array_column(db()->query('SHOW COLUMNS FROM anggota')->fetchAll(), 'Field');
                } catch (Throwable $e) {
                }
                $fields = [];
                $vals = [];
                foreach ($insAgt as $f => $v) {
                    if (!$adaKol || in_array($f, $adaKol, true)) {
                        $fields[] = $f;
                        $vals[] = $v;
                    }
                }
                $ph = implode(',', array_fill(0, count($fields), '?'));
                db()->prepare('INSERT INTO anggota (' . implode(',', $fields) . ') VALUES (' . $ph . ')')->execute($vals);
                $aid = (int)db()->lastInsertId();
                foreach ($kelIds as $i => $kid) {
                    tambah_anggota_ke_kelompok($aid, $kid, 'Anggota');
                    set_luas_lahan_kelompok($aid, $kid, (float)($luasArr[$i] ?? 0));
                }
                flash('ok', "Pendaftaran $no terkirim. Menunggu persetujuan admin.");
                header('Location: index.php');
                exit;
            } catch (Throwable $ex) {
                $error = $ex->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar · <?= e($s['nama_koperasi'] ?? 'Koperasi') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="reg-wrap">
  <form class="reg-card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="reg-head">
      <div class="logo">BT</div>
      <div>
        <h1>Daftar anggota</h1>
        <p>Lengkapi data. Pilih jumlah kelompok, lalu isi kelompok mana dan luasnya.</p>
      </div>
    </div>
    <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>

    <div class="form-sec">
      <h4>Identitas</h4>
      <div class="grid-2">
        <div><label>Nama lengkap</label><input name="nama" required></div>
        <div><label>NIK</label><input name="nik" required maxlength="16"></div>
      </div>
      <div class="grid-2">
        <div>
          <label>Jenis kelamin</label>
          <select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
        </div>
        <div><label>No. HP</label><input name="no_hp"></div>
      </div>
      <div class="grid-2">
        <div><label>Tempat lahir</label><input name="tempat_lahir"></div>
        <div><label>Tanggal lahir</label><input type="date" name="tanggal_lahir"></div>
      </div>
      <label>Alamat</label><textarea name="alamat"></textarea>
      <div class="grid-2">
        <div><label>Desa</label><input name="desa"></div>
        <div><label>Kecamatan</label><input name="kecamatan"></div>
      </div>
      <label>Pekerjaan</label><input name="pekerjaan" value="Petani">
    </div>

    <div class="form-sec">
      <h4>Kelompok &amp; lahan</h4>
      <?= html_slot_kelompok(5) ?>
      <div class="grid-2" style="margin-top:12px;">
        <div>
          <label>STDB</label>
          <select name="stdb"><option value="belum">Belum</option><option value="sudah">Sudah</option></select>
        </div>
        <div><label>Nomor STDB</label><input name="no_stdb"></div>
      </div>
    </div>

    <div class="form-sec">
      <h4>Berkas (opsional)</h4>
      <div class="grid-2">
        <div><label>Foto KTP</label><input type="file" name="ktp_file" accept="image/*"></div>
        <div><label>Sertifikat tanah</label><input type="file" name="sertifikat_file" accept="image/*"></div>
      </div>
      <label>Foto diri</label><input type="file" name="foto" accept="image/*">
    </div>

    <div class="form-sec">
      <h4>Akun masuk</h4>
      <div class="grid-2">
        <div><label>Username</label><input name="username" required></div>
        <div><label>Kata sandi</label><input type="password" name="password" required minlength="6"></div>
      </div>
    </div>

    <button class="btn btn-green" style="width:100%;" type="submit">Kirim pendaftaran</button>
    <p style="margin-top:14px;font-size:13px;text-align:center;color:var(--muted);">
      <a href="login-anggota.php" style="color:var(--green);font-weight:700;">Login anggota</a>
      · <a href="index.php" style="color:var(--green);font-weight:700;">Beranda</a>
    </p>
  </form>
</div>
<script>
function tampilSlotKel() {
  const n = parseInt(document.getElementById('jmlKel').value, 10) || 1;
  document.querySelectorAll('.slot-kel').forEach(function (el) {
    const i = parseInt(el.dataset.n, 10);
    const on = i <= n;
    el.style.display = on ? '' : 'none';
    el.querySelectorAll('select,input').forEach(function (inp) { inp.disabled = !on; });
  });
}
</script>
</body>
</html>
