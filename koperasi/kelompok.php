<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kelompok_schema();
$title = 'Kelompok tani';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'simpan') {
        $id = (int)$_POST['id'];
        $tgl = trim($_POST['tanggal_terbentuk'] ?? '');
        $pdo->prepare('UPDATE kelompok SET kode_kelompok=?, nama_kelompok=?, plasma=?, wilayah_dusun=?, blok_hamparan=?, tanggal_terbentuk=?, luas_tanah=?, lokasi=?, desa=?, kecamatan=?, fee_per_kg=? WHERE id=?')->execute([
            trim($_POST['kode_kelompok'] ?? '') ?: null,
            trim($_POST['nama_kelompok'] ?? '') ?: null,
            trim($_POST['plasma'] ?? '') ?: null,
            trim($_POST['wilayah_dusun'] ?? '') ?: null,
            trim($_POST['blok_hamparan'] ?? '') ?: null,
            $tgl !== '' ? $tgl : null,
            (float)($_POST['luas_tanah'] ?? 0),
            trim($_POST['lokasi'] ?? '') ?: null,
            trim($_POST['desa'] ?? '') ?: null,
            trim($_POST['kecamatan'] ?? '') ?: null,
            (float)($_POST['fee_per_kg'] ?? 0),
            $id,
        ]);
        sinkron_ketua_kelompok($id);
        flash('ok', 'Kelompok diperbarui.');
        header('Location: kelompok.php'); exit;
    }
    if ($act === 'tambah') {
        $nomor = nomor_kelompok_baru();
        $kode = trim($_POST['kode_kelompok'] ?? '') ?: ('KT-' . str_pad((string)$nomor, 2, '0', STR_PAD_LEFT));
        $nama = trim($_POST['nama_kelompok'] ?? '') ?: ('Kelompok Tani ' . $nomor);
        $tgl = trim($_POST['tanggal_terbentuk'] ?? '');
        try {
            $pdo->prepare('INSERT INTO kelompok (nomor, kode_kelompok, nama_kelompok, plasma, wilayah_dusun, blok_hamparan, tanggal_terbentuk, luas_tanah, lokasi, desa, kecamatan, fee_per_kg) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                $nomor, $kode, $nama,
                trim($_POST['plasma'] ?? '') ?: null,
                trim($_POST['wilayah_dusun'] ?? '') ?: null,
                trim($_POST['blok_hamparan'] ?? '') ?: null,
                $tgl !== '' ? $tgl : null,
                (float)($_POST['luas_tanah'] ?? 0),
                trim($_POST['lokasi'] ?? '') ?: null,
                trim($_POST['desa'] ?? '') ?: null,
                trim($_POST['kecamatan'] ?? '') ?: null,
                (float)($_POST['fee_per_kg'] ?? 0),
            ]);
            flash('ok', "Kelompok $kode dibentuk.");
        } catch (Throwable $e) {
            flash('err', 'Gagal menambah kelompok. Kode atau nomor mungkin sudah dipakai.');
        }
        header('Location: kelompok.php'); exit;
    }
}

if (isset($_GET['hapus'])) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
        header('Location: kelompok.php'); exit;
    }
    $id = (int)$_GET['hapus'];
    $ref = 0;
    foreach (['anggota_kelompok' => 'id_kelompok', 'lahan_sawit' => 'id_kelompok', 'timbangan_tbs' => 'id_kelompok', 'antrean_truk' => 'id_kelompok'] as $tbl => $kol) {
        try {
            $ref += (int)$pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE `$kol`=" . $id)->fetchColumn();
        } catch (Throwable $e) {
        }
    }
    if ($ref > 0) {
        flash('err', 'Kelompok masih dipakai (anggota/lahan/transaksi). Keluarkan dulu sebelum dihapus.');
    } else {
        $pdo->prepare('DELETE FROM kelompok WHERE id=?')->execute([$id]);
        flash('ok', 'Kelompok dihapus.');
    }
    header('Location: kelompok.php'); exit;
}

$map = ['kode' => 'kode_kelompok', 'nama' => 'nama_kelompok', 'ketua' => 'nama_ketua', 'plasma' => 'plasma', 'luas' => 'luas_tanah', 'anggota' => 'jml'];
$sort = sort_params($map, 'kode');
$rows = $pdo->query("SELECT k.*, (SELECT COUNT(*) FROM anggota_kelompok ak JOIN anggota a ON a.id=ak.anggota_id WHERE ak.id_kelompok=k.id AND a.status='aktif') jml FROM kelompok k ORDER BY k.nomor " . ($sort['dir'] === 'DESC' ? 'DESC' : 'ASC'))->fetchAll();
if ($sort['key'] !== 'kode') {
    $col = $map[$sort['key']];
    usort($rows, function ($a, $b) use ($col, $sort) {
        $va = $a[$col] ?? '';
        $vb = $b[$col] ?? '';
        $cmp = (is_numeric($va) && is_numeric($vb)) ? ((float)$va <=> (float)$vb) : strcasecmp((string)$va, (string)$vb);
        return $sort['dir'] === 'DESC' ? -$cmp : $cmp;
    });
}
$jml = count($rows);
$rowsJson = [];
foreach ($rows as $r) {
    $rowsJson[(int)$r['id']] = [
        'id' => (int)$r['id'], 'kode' => $r['kode_kelompok'], 'nama' => $r['nama_kelompok'],
        'plasma' => $r['plasma'] ?? '', 'ketua' => $r['nama_ketua'], 'hp' => $r['no_hp_ketua'],
        'wilayah' => $r['wilayah_dusun'], 'blok' => $r['blok_hamparan'], 'tgl' => $r['tanggal_terbentuk'],
        'luas' => $r['luas_tanah'], 'lokasi' => $r['lokasi'], 'desa' => $r['desa'],
        'kecamatan' => $r['kecamatan'], 'fee' => $r['fee_per_kg'],
    ];
}
include __DIR__ . '/includes/app_header.php';
?>
<div class="row" style="margin-bottom:14px;align-items:center;">
  <p style="color:var(--muted);margin:0;"><?= (int)$jml ?> kelompok. Ketua dan HP terisi otomatis setelah jabatan Ketua dipilih di Detail.</p>
  <span style="flex:1;"></span>
  <a class="btn btn-green" href="#formTambah">+ Tambah kelompok</a>
</div>
<div class="card" id="formTambah" style="margin-bottom:16px;">
  <h3>Tambah kelompok</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="tambah">
    <div class="grid-2">
      <div><label>Kode (opsional)</label><input name="kode_kelompok" placeholder="mis. KT-04"></div>
      <div><label>Nama kelompok</label><input name="nama_kelompok" placeholder="mis. Kelompok Tani Plasma A"></div>
    </div>
    <div class="grid-2">
      <div><label>Plasma (opsional)</label><input name="plasma" placeholder="mis. Plasma A"></div>
      <div><label>Tanggal terbentuk</label><input type="date" name="tanggal_terbentuk"></div>
    </div>
    <div class="grid-2">
      <div><label>Wilayah / dusun</label><input name="wilayah_dusun"></div>
      <div><label>Blok hamparan</label><input name="blok_hamparan"></div>
    </div>
    <div class="grid-2">
      <div><label>Lokasi</label><input name="lokasi" placeholder="Dusun / blok kebun"></div>
      <div><label>Luas tanah kelompok (ha)</label><input type="number" step="0.01" min="0" name="luas_tanah"></div>
    </div>
    <div class="grid-2">
      <div><label>Desa</label><input name="desa"></div>
      <div><label>Kecamatan</label><input name="kecamatan"></div>
    </div>
    <label>Fee per kg (Rp)</label><input type="number" step="1" min="0" name="fee_per_kg">
    <button class="btn btn-green" style="margin-top:14px;">Simpan kelompok</button>
  </form>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th><?= th_urut('kode', $sort, 'Kode') ?></th>
        <th><?= th_urut('nama', $sort, 'Nama kelompok') ?></th>
        <th><?= th_urut('ketua', $sort, 'Ketua') ?></th>
        <th><?= th_urut('plasma', $sort, 'Plasma') ?></th>
        <th>Wilayah / hamparan</th>
        <th><?= th_urut('luas', $sort, 'Luas (ha)') ?></th>
        <th><?= th_urut('anggota', $sort, 'Anggota') ?></th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['kode_kelompok']) ?></strong></td>
        <td><?= e($r['nama_kelompok']) ?></td>
        <td><?= e($r['nama_ketua'] ?: '—') ?><br><small><?= e($r['no_hp_ketua'] ?: '') ?></small></td>
        <td><?= e($r['plasma'] ?? '') ?: '—' ?></td>
        <td><small><?= e(trim(($r['wilayah_dusun'] ?? '') . ' ' . ($r['blok_hamparan'] ?? ''))) ?: '—' ?></small></td>
        <td><?= e(rp($r['luas_tanah'])) ?></td>
        <td><span class="badge b-aktif"><?= (int)$r['jml'] ?></span></td>
        <td>
          <div class="row" style="gap:6px;">
            <a class="btn btn-ghost btn-sm" href="kelompok_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
            <button class="btn btn-gold btn-sm" type="button" onclick='editKel(<?= json_encode($rowsJson[(int)$r['id']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ubah</button>
            <a class="btn btn-danger btn-sm" href="kelompok.php?hapus=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus <?= e($r['kode_kelompok']) ?>? Hanya bisa jika belum dipakai.');">Hapus</a>
          </div>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="8">Belum ada kelompok. Klik “Tambah kelompok” untuk membentuk yang pertama.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="mKel">
  <div class="modal">
    <h3 id="kelJudul">Ubah kelompok</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="act" id="kelAct" value="simpan">
      <input type="hidden" name="id" id="kelId">
      <div class="grid-2">
        <div><label>Kode</label><input name="kode_kelompok" id="kelKode" placeholder="mis. KT-01"></div>
        <div><label>Nama kelompok</label><input name="nama_kelompok" id="kelNama" placeholder="mis. Kelompok Tani Plasma A"></div>
      </div>
      <p id="kelKetuaInfo" style="font-size:12px;color:var(--muted);"></p>
      <div class="grid-2">
        <div><label>Plasma (opsional)</label><input name="plasma" id="kelPlasma" placeholder="mis. Plasma A"></div>
        <div><label>Tanggal terbentuk</label><input type="date" name="tanggal_terbentuk" id="kelTgl"></div>
      </div>
      <div class="grid-2">
        <div><label>Wilayah / dusun</label><input name="wilayah_dusun" id="kelWilayah"></div>
        <div><label>Blok hamparan</label><input name="blok_hamparan" id="kelBlok"></div>
      </div>
      <div class="grid-2">
        <div><label>Lokasi</label><input name="lokasi" id="kelLokasi" placeholder="Dusun / blok kebun"></div>
        <div><label>Luas tanah kelompok (ha)</label><input type="number" step="0.01" min="0" name="luas_tanah" id="kelLuas"></div>
      </div>
      <div class="grid-2">
        <div><label>Desa</label><input name="desa" id="kelDesa"></div>
        <div><label>Kecamatan</label><input name="kecamatan" id="kelKec"></div>
      </div>
      <label>Fee per kg (Rp)</label><input type="number" step="1" min="0" name="fee_per_kg" id="kelFee">
      <div class="row" style="margin-top:16px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeModal('mKel')">Batal</button>
        <button class="btn btn-green">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
const KEL = <?= json_encode($rowsJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function isiKel(r) {
  document.getElementById('kelId').value = r.id || '';
  document.getElementById('kelKode').value = r.kode || '';
  document.getElementById('kelNama').value = r.nama || '';
  document.getElementById('kelPlasma').value = r.plasma || '';
  document.getElementById('kelWilayah').value = r.wilayah || '';
  document.getElementById('kelBlok').value = r.blok || '';
  document.getElementById('kelTgl').value = r.tgl || '';
  document.getElementById('kelLuas').value = r.luas || '';
  document.getElementById('kelLokasi').value = r.lokasi || '';
  document.getElementById('kelDesa').value = r.desa || '';
  document.getElementById('kelKec').value = r.kecamatan || '';
  document.getElementById('kelFee').value = r.fee || '';
}
function editKel(r) {
  document.getElementById('kelAct').value = 'simpan';
  document.getElementById('kelJudul').textContent = 'Ubah ' + (r.kode || '');
  isiKel(r);
  document.getElementById('kelKetuaInfo').textContent = 'Ketua: ' + (r.ketua || 'belum dipilih') + (r.hp ? ' (' + r.hp + ')' : '') + ' — diubah lewat jabatan di Detail.';
  openModal('mKel');
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
