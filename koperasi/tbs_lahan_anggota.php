<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
$pdo = db();
$aid = (int)($_GET['id'] ?? 0);

$agt = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
$agt->execute([$aid]);
$agt = $agt->fetch();
if (!$agt) {
    flash('err', 'Anggota tidak ditemukan.');
    header('Location: tbs_lahan.php');
    exit;
}

$rows = $pdo->prepare("SELECT l.*, k.kode_kelompok, k.nama_kelompok
  FROM lahan_sawit l
  LEFT JOIN kelompok k ON k.id=l.id_kelompok
  WHERE l.anggota_id=?
  ORDER BY k.nomor, l.id");
$rows->execute([$aid]);
$rows = $rows->fetchAll();

$jml = count($rows);
$luas = 0;
foreach ($rows as $r) {
    $luas += (float)$r['luas_hektar'];
}

$kelompokAll = $pdo->query('SELECT id, kode_kelompok, nama_kelompok, nomor FROM kelompok ORDER BY nomor')->fetchAll();
$anggotaOpt = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();

$title = 'Lahan anggota · ' . $agt['nama'];
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;"><a class="btn btn-ghost btn-sm" href="tbs_lahan.php">← Daftar lahan sawit</a></p>
<div class="kpis">
  <div class="kpi"><span>Anggota</span><b><?= e($agt['nama']) ?></b></div>
  <div class="kpi"><span>No. anggota</span><b><?= e($agt['no_anggota']) ?></b></div>
  <div class="kpi"><span>Jumlah bidang</span><b><?= $jml ?></b></div>
  <div class="kpi"><span>Total luas</span><b><?= number_format($luas, 2, ',', '.') ?> ha</b></div>
</div>
<p style="margin-bottom:14px;color:var(--muted);font-size:14px;">Semua lahan anggota ini (boleh 2–3 bidang per kelompok). Pilih Detail untuk data SHM &amp; umur tanam satu bidang.</p>
<div class="row" style="margin-bottom:12px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="bukaLahan()">+ Lahan anggota ini</button>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Kelompok</th>
        <th>Legalitas</th>
        <th>Luas (ha)</th>
        <th>Pokok</th>
        <th>Tahun tanam</th>
        <th>Umur / kategori</th>
        <th>Lokasi</th>
        <th>SHM atas nama</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $umur = $r['tahun_tanam'] ? ((int)date('Y') - (int)$r['tahun_tanam']) : null;
      $kat = kategori_umur_sawit($r['tahun_tanam'] ? (int)$r['tahun_tanam'] : null);
    ?>
      <tr>
        <td><?= e($r['kode_kelompok'] ?: '—') ?><?php if (!empty($r['nama_kelompok'])): ?><br><small><?= e($r['nama_kelompok']) ?></small><?php endif; ?></td>
        <td><?= e($r['legalitas']) ?></td>
        <td><?= e($r['luas_hektar']) ?></td>
        <td><?= (int)$r['jumlah_pokok'] ?></td>
        <td><?= e($r['tahun_tanam'] ?: '—') ?></td>
        <td><?= $umur !== null ? $umur.' th · '.$kat : $kat ?></td>
        <td><?= e($r['lokasi_desa'] ?: '—') ?></td>
        <td><?= e(($r['atas_nama_shm'] ?? '') ?: '—') ?><?php if (!empty($r['no_shm'])): ?><br><small><?= e($r['no_shm']) ?></small><?php endif; ?></td>
        <td class="actions">
          <a class="btn btn-green btn-sm" href="tbs_lahan_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
          <button type="button" class="btn btn-ghost btn-sm" onclick='ubahLahan(<?= json_encode([
            "id" => (int)$r["id"],
            "anggota_id" => (int)$r["anggota_id"],
            "id_kelompok" => (int)($r["id_kelompok"] ?? 0),
            "legalitas" => $r["legalitas"],
            "luas_hektar" => $r["luas_hektar"],
            "jumlah_pokok" => (int)$r["jumlah_pokok"],
            "tahun_tanam" => $r["tahun_tanam"],
            "lokasi_desa" => $r["lokasi_desa"],
            "atas_nama_shm" => $r["atas_nama_shm"] ?? "",
            "no_shm" => $r["no_shm"] ?? "",
          ], JSON_UNESCAPED_UNICODE) ?>)'>Ubah</button>
          <a class="btn btn-ghost btn-sm" href="tbs_lahan.php?hapus=<?= (int)$r['id'] ?>&kembali=<?= $aid ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus lahan ini?')">Hapus</a>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="9">Belum ada lahan untuk anggota ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<div class="modal-bg" id="mLahan">
  <form class="modal" method="post" action="tbs_lahan.php" id="formLahan">
    <?= csrf_field() ?>
    <input type="hidden" name="kembali" value="<?= $aid ?>">
    <input type="hidden" name="act" id="lahanAct" value="tambah">
    <input type="hidden" name="lahan_id" id="lahanId" value="">
    <h3 id="lahanJudul">Lahan sawit</h3>
    <label>Anggota</label>
    <select name="anggota_id" id="lahanAnggota" required>
      <?php foreach ($anggotaOpt as $a): ?>
        <option value="<?= $a['id'] ?>" <?= (int)$a['id']===$aid ? 'selected' : '' ?>><?= e($a['no_anggota'].' — '.$a['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Kelompok lahan ini</label>
    <select name="id_kelompok" id="lahanKel" required>
      <option value="">— pilih kelompok —</option>
      <?php foreach ($kelompokAll as $kk): ?>
        <option value="<?= (int)$kk['id'] ?>"><?= e(($kk['kode_kelompok'] ?: ('KT-'.str_pad((string)$kk['nomor'],2,'0',STR_PAD_LEFT))).' — '.($kk['nama_kelompok'] ?: '')) ?></option>
      <?php endforeach; ?>
    </select>
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
      <div><label>SHM atas nama</label><input name="atas_nama_shm" id="lahanShmNama"></div>
      <div><label>No. SHM</label><input name="no_shm" id="lahanShmNo"></div>
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
  document.getElementById('lahanAnggota').value = '<?= $aid ?>';
  openModal('mLahan');
}
function ubahLahan(d) {
  document.getElementById('lahanAct').value = 'ubah';
  document.getElementById('lahanId').value = d.id;
  document.getElementById('lahanJudul').textContent = 'Ubah lahan sawit';
  document.getElementById('lahanBtn').textContent = 'Simpan perubahan';
  document.getElementById('lahanAnggota').value = String(d.anggota_id);
  document.getElementById('lahanKel').value = d.id_kelompok ? String(d.id_kelompok) : '';
  document.getElementById('lahanLeg').value = d.legalitas || 'SKT';
  document.getElementById('lahanLuas').value = d.luas_hektar || '';
  document.getElementById('lahanPokok').value = d.jumlah_pokok || 0;
  document.getElementById('lahanTahun').value = d.tahun_tanam || '';
  document.getElementById('lahanLok').value = d.lokasi_desa || '';
  document.getElementById('lahanShmNama').value = d.atas_nama_shm || '';
  document.getElementById('lahanShmNo').value = d.no_shm || '';
  openModal('mLahan');
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
