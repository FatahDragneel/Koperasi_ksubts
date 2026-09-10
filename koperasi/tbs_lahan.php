<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
$title = 'Lahan sawit anggota';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'tambah';
    $aid = (int)$_POST['anggota_id'];
    $idk = (int)($_POST['id_kelompok'] ?? 0) ?: null;
    $atasNama = trim((string)($_POST['atas_nama_shm'] ?? ''));
    $noShm = trim((string)($_POST['no_shm'] ?? ''));
    $data = [
        $aid,
        $idk,
        $_POST['legalitas'] ?? 'SKT',
        (float)$_POST['luas_hektar'],
        (int)$_POST['jumlah_pokok'],
        (int)$_POST['tahun_tanam'] ?: null,
        trim($_POST['lokasi_desa'] ?? ''),
        $atasNama,
        $noShm,
    ];
    if ($act === 'ubah') {
        $lid = (int)$_POST['lahan_id'];
        $pdo->prepare('UPDATE lahan_sawit SET anggota_id=?,id_kelompok=?,legalitas=?,luas_hektar=?,jumlah_pokok=?,tahun_tanam=?,lokasi_desa=?,atas_nama_shm=?,no_shm=? WHERE id=?')
            ->execute([...$data, $lid]);
        flash('ok', 'Lahan diperbarui.');
    } else {
        $pdo->prepare('INSERT INTO lahan_sawit (anggota_id,id_kelompok,legalitas,luas_hektar,jumlah_pokok,tahun_tanam,lokasi_desa,atas_nama_shm,no_shm) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute($data);
        flash('ok', 'Lahan dicatat.');
    }
    $kembali = (int)($_POST['kembali'] ?? 0);
    header('Location: ' . ($kembali ? ('tbs_lahan_anggota.php?id=' . $kembali) : 'tbs_lahan.php'));
    exit;
}
if (isset($_GET['hapus'])) {
    if (hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        $pdo->prepare('DELETE FROM lahan_sawit WHERE id=?')->execute([(int)$_GET['hapus']]);
        flash('ok', 'Lahan dihapus.');
    }
    $kembali = (int)($_GET['kembali'] ?? 0);
    header('Location: ' . ($kembali ? ('tbs_lahan_anggota.php?id=' . $kembali) : 'tbs_lahan.php'));
    exit;
}

$anggota = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();
$kelompokAll = $pdo->query('SELECT id, kode_kelompok, nama_kelompok, nomor FROM kelompok ORDER BY nomor')->fetchAll();
$ordL = sql_urut([
    'nama' => 'a.nama',
    'kelompok' => 'kelompok',
    'bidang' => 'jml',
    'luas' => 'luas',
    'pokok' => 'pokok',
], 'a.nama ASC');
$rows = $pdo->query("SELECT a.id AS anggota_id, a.nama, a.no_anggota,
    COUNT(l.id) AS jml,
    COALESCE(SUM(l.luas_hektar),0) AS luas,
    COALESCE(SUM(l.jumlah_pokok),0) AS pokok,
    GROUP_CONCAT(DISTINCT k.kode_kelompok ORDER BY k.nomor SEPARATOR ', ') AS kelompok
    FROM anggota a
    INNER JOIN lahan_sawit l ON l.anggota_id = a.id
    LEFT JOIN kelompok k ON k.id = l.id_kelompok
    GROUP BY a.id, a.nama, a.no_anggota
    ORDER BY $ordL")->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;color:var(--muted);font-size:14px;">Ringkasan umum: satu nama satu baris. Semua bidang anggota ada di halaman Detail.</p>
<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="bukaLahan()">+ Lahan</button>
</div>
<div class="table-wrap">
  <table>
    <thead><tr>
      <?= th_urut('nama','Anggota') ?>
      <?= th_urut('kelompok','Kelompok') ?>
      <?= th_urut('bidang','Bidang') ?>
      <?= th_urut('luas','Total luas (ha)') ?>
      <?= th_urut('pokok','Total pokok') ?>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nama']) ?><br><small><?= e($r['no_anggota']) ?></small></td>
        <td><?= e($r['kelompok'] ?: '—') ?></td>
        <td><?= (int)$r['jml'] ?></td>
        <td><?= number_format((float)$r['luas'], 2, ',', '.') ?></td>
        <td><?= (int)$r['pokok'] ?></td>
        <td class="actions">
          <a class="btn btn-green btn-sm" href="tbs_lahan_anggota.php?id=<?= (int)$r['anggota_id'] ?>">Semua lahan</a>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6">Belum ada lahan. Tambah sebelum timbang (agar harga mengikuti umur tanam).</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<div class="modal-bg" id="mLahan">
  <form class="modal" method="post" id="formLahan">
    <?= csrf_field() ?>
    <input type="hidden" name="act" id="lahanAct" value="tambah">
    <input type="hidden" name="lahan_id" id="lahanId" value="">
    <h3 id="lahanJudul">Lahan sawit</h3>
    <label>Anggota</label>
    <select name="anggota_id" id="lahanAnggota" required>
      <?php foreach ($anggota as $a): ?>
        <option value="<?= $a['id'] ?>"><?= e($a['no_anggota'].' — '.$a['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Kelompok lahan ini</label>
    <select name="id_kelompok" id="lahanKel" required>
      <option value="">— pilih kelompok —</option>
      <?php foreach ($kelompokAll as $kk): ?>
        <option value="<?= (int)$kk['id'] ?>"><?= e(($kk['kode_kelompok'] ?: ('KT-'.str_pad((string)$kk['nomor'],2,'0',STR_PAD_LEFT))).' — '.($kk['nama_kelompok'] ?: '')) ?></option>
      <?php endforeach; ?>
    </select>
    <p style="font-size:12px;color:var(--muted);">Satu anggota boleh beberapa bidang. Daftar ini hanya ringkasan; rincian di tombol Semua lahan.</p>
    <div class="grid-2">
      <div><label>Legalitas</label>
        <select name="legalitas" id="lahanLeg">
          <option>SHM</option><option>SKT</option><option>Lainnya</option>
        </select>
      </div>
      <div><label>Luas (ha)</label><input name="luas_hektar" id="lahanLuas" type="number" step="0.01" min="0" required></div>
    </div>
    <div class="grid-2">
      <div><label>Jumlah pokok</label><input name="jumlah_pokok" id="lahanPokok" type="number" min="0" value="0"></div>
      <div><label>Tahun tanam</label><input name="tahun_tanam" id="lahanTahun" type="number" min="1980" max="<?= date('Y') ?>" value="2015"></div>
    </div>
    <label>Lokasi / desa</label>
    <input name="lokasi_desa" id="lahanLok">
    <div class="grid-2">
      <div><label>SHM atas nama</label><input name="atas_nama_shm" id="lahanShmNama" placeholder="Nama di sertifikat"></div>
      <div><label>No. SHM (opsional)</label><input name="no_shm" id="lahanShmNo"></div>
    </div>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mLahan')">Batal</button>
      <button class="btn btn-green" id="lahanBtn">Simpan</button>
    </div>
  </form>
</div>
<script>
function bukaLahan() {
  document.getElementById('lahanAct').value = 'tambah';
  document.getElementById('lahanId').value = '';
  document.getElementById('lahanJudul').textContent = 'Tambah lahan sawit';
  document.getElementById('lahanBtn').textContent = 'Simpan';
  document.getElementById('formLahan').reset();
  openModal('mLahan');
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
