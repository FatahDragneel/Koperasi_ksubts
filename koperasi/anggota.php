<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Data Anggota';
$pdo = db();

function anggota_fields(): array {
    return [
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
        trim($_POST['kelompok_tani'] ?? ''),
        isset($_POST['plasma']) ? 1 : 0,
        isset($_POST['punya_tanah']) ? 1 : 0,
        $_POST['luas_tanah'] !== '' ? (float)$_POST['luas_tanah'] : null,
        ($_POST['stdb'] ?? 'belum') === 'sudah' ? 'sudah' : 'belum',
        trim($_POST['no_stdb'] ?? ''),
        $_POST['tanggal_daftar'] ?: date('Y-m-d'),
        $_POST['status'] ?? 'aktif',
    ];
}

if (isset($_GET['setujui'])) {
    $id = (int)$_GET['setujui'];
    $pdo->prepare("UPDATE anggota SET status='aktif' WHERE id=?")->execute([$id]);
    catat_simpanan_awal_anggota($id, auth()['id'] ?? null);
    flash('ok', 'Pendaftaran disetujui. Simpanan pokok dan wajib tertunggak dicatat.');
    header('Location: anggota.php'); exit;
}

if (isset($_GET['nonaktif']) || isset($_GET['aktifkan'])) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
        header('Location: anggota.php');
        exit;
    }
    if (isset($_GET['nonaktif'])) {
        $id = (int)$_GET['nonaktif'];
        $pdo->prepare("UPDATE anggota SET status='nonaktif' WHERE id=?")->execute([$id]);
        flash('ok', 'Anggota dinonaktifkan. Akun tidak bisa masuk sampai diaktifkan lagi.');
    } else {
        $id = (int)$_GET['aktifkan'];
        $pdo->prepare("UPDATE anggota SET status='aktif' WHERE id=?")->execute([$id]);
        flash('ok', 'Anggota diaktifkan kembali.');
    }
    header('Location: anggota.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $data = anggota_fields();
    if ($id) {
        $data[] = $id;
        $pdo->prepare("UPDATE anggota SET no_anggota=?,nik=?,nama=?,jenis_kelamin=?,tempat_lahir=?,tanggal_lahir=?,alamat=?,desa=?,kecamatan=?,no_hp=?,pekerjaan=?,kelompok_tani=?,plasma=?,punya_tanah=?,luas_tanah=?,stdb=?,no_stdb=?,tanggal_daftar=?,status=? WHERE id=?")->execute($data);
        $nom = nomor_kelompok($_POST['kelompok_tani'] ?? '');
        if ($nom) {
            $kid = $pdo->prepare('SELECT id FROM kelompok WHERE nomor=?');
            $kid->execute([$nom]);
            $kid = $kid->fetchColumn();
            if ($kid) {
                $pdo->prepare('UPDATE anggota SET id_kelompok=? WHERE id=?')->execute([(int)$kid, $id]);
            }
        }
        flash('ok', 'Data anggota diperbarui.');
    } else {
        $nama = trim($_POST['nama'] ?? '');
        $no = trim($_POST['no_anggota'] ?? '');
        $kelIds = array_values(array_filter(array_map('intval', (array)($_POST['kelompok_id'] ?? []))));
        $luasArr = array_values((array)($_POST['luas_kel'] ?? []));
        $luasTot = 0;
        foreach ($luasArr as $i => $v) {
            if (!empty($kelIds[$i])) {
                $luasTot += (float)$v;
            }
        }
        if ($nama === '' || $no === '') {
            flash('err', 'Isi nomor dan nama anggota.');
            header('Location: anggota.php');
            exit;
        }
        $pdo->prepare("INSERT INTO anggota (no_anggota,nik,nama,jenis_kelamin,tempat_lahir,tanggal_lahir,alamat,desa,kecamatan,no_hp,pekerjaan,kelompok_tani,plasma,punya_tanah,luas_tanah,stdb,no_stdb,tanggal_daftar,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,1,?,?,?,?,?)")
            ->execute([
                $no, trim($_POST['nik'] ?? ''), $nama, $_POST['jenis_kelamin'] ?? 'L',
                trim($_POST['tempat_lahir'] ?? ''), $_POST['tanggal_lahir'] ?: null,
                trim($_POST['alamat'] ?? ''), trim($_POST['desa'] ?? ''), trim($_POST['kecamatan'] ?? ''),
                trim($_POST['no_hp'] ?? ''), trim($_POST['pekerjaan'] ?? 'Petani'),
                (string)($kelIds[0] ?? ''), $luasTot ?: null,
                (($_POST['stdb'] ?? '') === 'sudah') ? 'sudah' : 'belum',
                trim($_POST['no_stdb'] ?? ''),
                $_POST['tanggal_daftar'] ?: date('Y-m-d'),
                $_POST['status'] ?? 'aktif',
            ]);
        $aid = (int)$pdo->lastInsertId();
        $uname = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', $nama)[0])) ?: 'anggota';
        $uname .= $aid;
        if (username_sudah_dipakai($uname)) {
            $uname .= $aid;
        }
        $pdo->prepare('UPDATE anggota SET username=?, password=? WHERE id=?')
            ->execute([$uname, password_hash('anggota123', PASSWORD_DEFAULT), $aid]);
        update_berkas_anggota($aid);
        foreach ($kelIds as $i => $kid) {
            tambah_anggota_ke_kelompok($aid, $kid, 'Anggota');
            set_luas_lahan_kelompok($aid, $kid, (float)($luasArr[$i] ?? 0));
        }
        if (($_POST['status'] ?? 'aktif') === 'aktif') {
            catat_simpanan_awal_anggota($aid, auth()['id'] ?? null);
        }
        flash('ok', "Anggota ditambah. Username: $uname / sandi: anggota123");
    }
    header('Location: anggota.php'); exit;
}

if (isset($_GET['hapus'])) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
        header('Location: anggota.php');
        exit;
    }
    $id = (int)$_GET['hapus'];
    $utang = $pdo->prepare("SELECT COUNT(*) FROM pinjaman WHERE anggota_id=? AND status IN ('berjalan','disetujui') AND sisa>0");
    $utang->execute([$id]);
    if ((int)$utang->fetchColumn() > 0) {
        flash('err', 'Anggota tidak bisa dihapus karena masih ada utang pinjaman yang belum lunas. Lunasi dulu di menu Angsuran.');
        header('Location: anggota.php');
        exit;
    }
    $pdo->beginTransaction();
    try {
        $ids = $pdo->prepare('SELECT id, pengajuan_id, pencairan_id FROM pinjaman WHERE anggota_id=?');
        $ids->execute([$id]);
        $rowsP = $ids->fetchAll();
        $pids = array_column($rowsP, 'id');
        $cids = array_filter(array_column($rowsP, 'pencairan_id'));
        $gids = array_filter(array_column($rowsP, 'pengajuan_id'));
        if ($pids) {
            $in = implode(',', array_map('intval', $pids));
            $pdo->exec("DELETE FROM angsuran WHERE pinjaman_id IN ($in)");
        }
        if ($cids) {
            $pdo->exec('DELETE FROM pencairan_pinjaman WHERE id IN (' . implode(',', array_map('intval', $cids)) . ')');
        }
        $pdo->prepare('DELETE FROM pinjaman WHERE anggota_id=?')->execute([$id]);
        if ($gids) {
            $pdo->exec('DELETE FROM pengajuan_pinjaman WHERE id IN (' . implode(',', array_map('intval', $gids)) . ')');
        }
        $pdo->prepare('DELETE FROM pengajuan_pinjaman WHERE anggota_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM simpanan WHERE anggota_id=?')->execute([$id]);
        try {
            $pdo->prepare('DELETE FROM simpanan_sukarela WHERE anggota_id=?')->execute([$id]);
        } catch (Throwable $e) {
        }
        $pdo->prepare('DELETE FROM users WHERE anggota_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM anggota WHERE id=?')->execute([$id]);
        $pdo->commit();
        flash('ok', 'Anggota dan riwayatnya dihapus.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('err', 'Gagal menghapus: ' . $e->getMessage());
    }
    header('Location: anggota.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$tahun = (int)date('Y');
$sql = "SELECT a.*,
    COALESCE(k.kode_kelompok, k2.kode_kelompok) AS kode_kelompok,
    COALESCE(k.nama_kelompok, k2.nama_kelompok) AS nama_kelompok,
    COALESCE(k.nama_ketua, k2.nama_ketua) AS nama_ketua,
    (SELECT COALESCE(SUM(sisa_piutang),0) FROM saprodi s WHERE s.anggota_id=a.id) AS piutang_saprodi,
    (SELECT COUNT(*) FROM lahan_sawit l WHERE l.anggota_id=a.id) AS jml_lahan,
    (SELECT COALESCE(SUM(luas_hektar),0) FROM lahan_sawit l WHERE l.anggota_id=a.id) AS luas_kebun,
    (SELECT GROUP_CONCAT(DISTINCT tahun_tanam ORDER BY tahun_tanam SEPARATOR ', ') FROM lahan_sawit l WHERE l.anggota_id=a.id AND l.tahun_tanam IS NOT NULL) AS tahun_tanam,
    (SELECT COALESCE(SUM(total_shu),0) FROM shu_alokasi h WHERE h.anggota_id=a.id AND h.tahun_buku=$tahun) AS shu_tahun,
    (SELECT GROUP_CONCAT(DISTINCT k3.kode_kelompok ORDER BY k3.nomor SEPARATOR ', ') FROM anggota_kelompok ak3 JOIN kelompok k3 ON k3.id=ak3.id_kelompok WHERE ak3.anggota_id=a.id) AS semua_kelompok
  FROM anggota a
  LEFT JOIN kelompok k ON k.id=a.id_kelompok
  LEFT JOIN kelompok k2 ON a.id_kelompok IS NULL AND k2.nomor=CAST(a.kelompok_tani AS UNSIGNED)";
if ($q) {
    $sql .= " WHERE a.nama LIKE ? OR a.no_anggota LIKE ? OR a.desa LIKE ? OR a.kelompok_tani LIKE ? OR k.kode_kelompok LIKE ?";
    $sql .= ' ORDER BY ' . sql_urut([
        'no' => 'a.no_anggota',
        'nama' => 'a.nama',
        'kelompok' => 'kode_kelompok',
        'lahan' => 'luas_kebun',
        'piutang' => 'piutang_saprodi',
        'shu' => 'shu_tahun',
        'status' => 'a.status',
    ], "FIELD(a.status,'pending','aktif','pasif','nonaktif'), a.no_anggota");
    $st = $pdo->prepare($sql);
    $like = "%$q%";
    $st->execute([$like, $like, $like, $like, $like]);
    $rows = $st->fetchAll();
} else {
    $sql .= ' ORDER BY ' . sql_urut([
        'no' => 'a.no_anggota',
        'nama' => 'a.nama',
        'kelompok' => 'kode_kelompok',
        'lahan' => 'luas_kebun',
        'piutang' => 'piutang_saprodi',
        'shu' => 'shu_tahun',
        'status' => 'a.status',
    ], "FIELD(a.status,'pending','aktif','pasif','nonaktif'), a.no_anggota");
    $rows = $pdo->query($sql)->fetchAll();
}
include __DIR__ . '/includes/app_header.php';
?>
<div class="toolbar-stack">
  <form method="get" class="row" style="width:100%;">
    <input name="q" value="<?= e($q) ?>" placeholder="Cari nama / nomor / desa / poktan" style="flex:1;min-width:0;max-width:none;">
    <button class="btn btn-ghost" type="submit">Cari</button>
  </form>
  <div class="row">
    <a class="btn btn-ghost" href="cetak.php?jenis=anggota" target="_blank">Cetak tabel</a>
    <a class="btn btn-ghost" href="verifikasi.php">Tinjau berkas</a>
    <button class="btn btn-green" type="button" onclick="openModal('mAnggota')">+ Anggota baru</button>
  </div>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?= th_urut('no', 'No. Anggota') ?><?= th_urut('nama', 'Nama') ?><?= th_urut('kelompok', 'Kelompok / ketua') ?><?= th_urut('lahan', 'Lahan') ?><?= th_urut('piutang', 'Piutang saprodi') ?><?= th_urut('shu', 'SHU ' . date('Y')) ?><?= th_urut('status', 'Status') ?><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['no_anggota']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td>
          <?php if (!empty($r['kode_kelompok'])): ?>
            <strong><?= e($r['kode_kelompok']) ?></strong>
            <?= !empty($r['nama_kelompok']) ? '<br><small>'.e($r['nama_kelompok']).'</small>' : '' ?>
            <?= !empty($r['nama_ketua']) ? '<br><small>Ketua: '.e($r['nama_ketua']).'</small>' : '' ?>
          <?php else: ?>
            <?= !empty($r['plasma']) ? label_kelompok($r['kelompok_tani']) : '—' ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int)($r['jml_lahan'] ?? 0) > 0): ?>
            <?= (int)$r['jml_lahan'] ?> bidang · <?= number_format((float)$r['luas_kebun'], 2, ',', '.') ?> ha
            <?= !empty($r['tahun_tanam']) ? '<br><small>Tanam '.e($r['tahun_tanam']).'</small>' : '' ?>
          <?php else: ?>
            <?= !empty($r['luas_tanah']) ? e($r['luas_tanah']).' ha' : '—' ?>
          <?php endif; ?>
        </td>
        <td><?= rupiah($r['piutang_saprodi'] ?? 0) ?></td>
        <td><?= rupiah($r['shu_tahun'] ?? 0) ?></td>
        <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td class="actions" style="white-space:nowrap;">
          <a class="btn btn-green btn-sm" href="anggota_detail.php?id=<?= $r['id'] ?>">Detail</a>
          <?php if ($r['status']==='pending'): ?>
            <a class="btn btn-ghost btn-sm" href="verifikasi.php?id=<?= $r['id'] ?>">Berkas</a>
          <?php endif; ?>
          <?php if ($r['status']==='aktif'): ?>
            <a class="btn btn-danger btn-sm" href="?nonaktif=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Nonaktifkan <?= e($r['nama']) ?>? Tidak bisa login sampai diaktifkan lagi.')">Nonaktifkan</a>
          <?php elseif ($r['status']==='nonaktif'): ?>
            <a class="btn btn-green btn-sm" href="?aktifkan=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Aktifkan kembali <?= e($r['nama']) ?>?')">Aktifkan</a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="?hapus=<?= $r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus anggota ini? Jika masih ada utang pinjaman, penghapusan akan ditolak.')">Hapus</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mAnggota">
  <form class="modal" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h3>Anggota baru</h3>
    <p style="font-size:13px;color:var(--muted);margin:0 0 12px;">Username otomatis · sandi <strong>anggota123</strong></p>
    <div class="grid-2">
      <div><label>No. Anggota</label><input name="no_anggota" required placeholder="AGT-0006"></div>
      <div><label>NIK</label><input name="nik"></div>
    </div>
    <label>Nama lengkap</label><input name="nama" required>
    <div class="grid-2">
      <div><label>Jenis kelamin</label><select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
      <div><label>Status</label><select name="status"><option value="aktif">Aktif</option><option value="pending">Pending</option></select></div>
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
    <div class="grid-2">
      <div><label>No. HP</label><input name="no_hp"></div>
      <div><label>Pekerjaan</label><input name="pekerjaan" value="Petani"></div>
    </div>
    <?= html_slot_kelompok(5) ?>
    <div class="grid-2">
      <div><label>STDB</label><select name="stdb"><option value="belum">Belum</option><option value="sudah">Sudah</option></select></div>
      <div><label>Nomor STDB</label><input name="no_stdb"></div>
    </div>
    <label>Tanggal daftar</label><input type="date" name="tanggal_daftar" value="<?= date('Y-m-d') ?>">
    <div class="form-sec" style="margin-top:12px;">
      <h4>Berkas (gambar)</h4>
      <div class="grid-2">
        <div><label>Foto diri</label><input type="file" name="foto" accept="image/*"></div>
        <div><label>Foto KTP</label><input type="file" name="ktp_file" accept="image/*"></div>
      </div>
      <label>Sertifikat tanah</label><input type="file" name="sertifikat_file" accept="image/*">
    </div>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mAnggota')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<script>
function tampilSlotKel() {
  const el = document.getElementById('jmlKel');
  if (!el) return;
  const n = parseInt(el.value, 10) || 1;
  document.querySelectorAll('.slot-kel').forEach(function (box) {
    const i = parseInt(box.dataset.n, 10);
    const on = i <= n;
    box.style.display = on ? '' : 'none';
    box.querySelectorAll('select,input').forEach(function (inp) { inp.disabled = !on; });
  });
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
