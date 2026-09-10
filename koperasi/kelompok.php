<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kelompok_schema();
$title = 'Kelompok tani';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'simpan') {
    $id = (int)$_POST['id'];
    $pdo->prepare('UPDATE kelompok SET nama_kelompok=?, wilayah_dusun=?, blok_hamparan=?, tanggal_terbentuk=?, luas_tanah=?, lokasi=?, desa=?, kecamatan=?, fee_per_kg=? WHERE id=?')->execute([
        trim($_POST['nama_kelompok'] ?? ''),
        trim($_POST['wilayah_dusun'] ?? ''),
        trim($_POST['blok_hamparan'] ?? ''),
        $_POST['tanggal_terbentuk'] ?: null,
        $_POST['luas_tanah'] !== '' ? (float)$_POST['luas_tanah'] : 0,
        trim($_POST['lokasi'] ?? ''),
        trim($_POST['desa'] ?? ''),
        trim($_POST['kecamatan'] ?? ''),
        (float)($_POST['fee_per_kg'] ?? 0),
        $id,
    ]);
    sinkron_ketua_kelompok($id);
    flash('ok', 'Data kelompok disimpan.');
    header('Location: kelompok.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM kelompok ORDER BY nomor')->fetchAll();
$hitung = [];
try {
    ensure_anggota_kelompok_schema();
    foreach ($pdo->query("SELECT k.nomor, COUNT(*) n FROM anggota_kelompok ak JOIN kelompok k ON k.id=ak.id_kelompok JOIN anggota a ON a.id=ak.anggota_id AND a.status='aktif' GROUP BY k.nomor") as $h) {
        $hitung[(int)$h['nomor']] = (int)$h['n'];
    }
} catch (Throwable $e) {
    foreach ($pdo->query('SELECT id, kelompok_tani, id_kelompok FROM anggota WHERE status=\'aktif\'') as $a) {
        $n = 0;
        if (!empty($a['id_kelompok'])) {
            foreach ($rows as $k) {
                if ((int)$k['id'] === (int)$a['id_kelompok']) {
                    $n = (int)$k['nomor'];
                    break;
                }
            }
        }
        if ($n < 1) {
            $n = nomor_kelompok($a['kelompok_tani'] ?? '');
        }
        if ($n > 0) {
            $hitung[$n] = ($hitung[$n] ?? 0) + 1;
        }
    }
}
foreach ($rows as &$r) {
    $r['jml'] = $hitung[(int)$r['nomor']] ?? 0;
}
unset($r);
[$uk, $ud] = sort_params();
$mapK = [
    'kode' => 'kode_kelompok',
    'nama' => 'nama_kelompok',
    'ketua' => 'nama_ketua',
    'wilayah' => 'wilayah_dusun',
    'luas' => 'luas_tanah',
    'anggota' => 'jml',
];
if (isset($mapK[$uk])) {
    $col = $mapK[$uk];
    usort($rows, function ($a, $b) use ($col, $ud) {
        $va = $a[$col] ?? '';
        $vb = $b[$col] ?? '';
        if (is_numeric($va) && is_numeric($vb)) {
            $cmp = $va <=> $vb;
        } else {
            $cmp = strcasecmp((string)$va, (string)$vb);
        }
        return $ud === 'desc' ? -$cmp : $cmp;
    });
}

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;color:var(--muted);">Master 21 kelompok. Ketua dan HP terisi otomatis setelah jabatan Ketua dipilih di Detail.</p>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?= th_urut('kode', 'Kode') ?>
        <?= th_urut('nama', 'Nama kelompok') ?>
        <?= th_urut('ketua', 'Ketua') ?>
        <?= th_urut('wilayah', 'Wilayah / hamparan') ?>
        <?= th_urut('luas', 'Luas (ha)') ?>
        <?= th_urut('anggota', 'Anggota') ?>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['kode_kelompok'] ?: ('KT-'.str_pad((string)$r['nomor'], 2, '0', STR_PAD_LEFT))) ?></strong></td>
        <td><?= e($r['nama_kelompok'] ?: ('Kelompok '.$r['nomor'])) ?></td>
        <td><?= e($r['nama_ketua'] ?: '—') ?><?= !empty($r['no_hp_ketua']) ? '<br><small>'.e($r['no_hp_ketua']).'</small>' : '' ?></td>
        <td><?= e($r['wilayah_dusun'] ?: ($r['lokasi'] ?: '—')) ?><?= !empty($r['blok_hamparan']) ? ' / '.e($r['blok_hamparan']) : '' ?></td>
        <td><?= e($r['luas_tanah'] ?: '0') ?></td>
        <td><?= (int)$r['jml'] ?></td>
        <td class="actions">
          <a class="btn btn-green btn-sm" href="kelompok_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
          <button class="btn btn-ghost btn-sm" type="button" onclick='editKel(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>Ubah</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mKel">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="simpan">
    <input type="hidden" name="id" id="kelId">
    <h3 id="kelJudul">Ubah kelompok</h3>
    <label>Nama kelompok</label>
    <input name="nama_kelompok" id="kelNama" placeholder="Tani Makmur">
    <p id="kelKetuaInfo" style="font-size:13px;color:var(--muted);margin:8px 0 12px;"></p>
    <div class="grid-2">
      <div><label>Wilayah dusun</label><input name="wilayah_dusun" id="kelDusun"></div>
      <div><label>Blok / hamparan</label><input name="blok_hamparan" id="kelBlok"></div>
    </div>
    <div class="grid-2">
      <div><label>Tanggal terbentuk</label><input type="date" name="tanggal_terbentuk" id="kelTgl"></div>
      <div><label>Luas tanah (ha)</label><input name="luas_tanah" id="kelLuas" type="number" step="0.01" min="0"></div>
    </div>
    <label>Fee kas kelompok (Rp / kg TBS)</label>
    <input name="fee_per_kg" id="kelFee" type="number" step="1" min="0" placeholder="10">
    <label>Lokasi / catatan</label>
    <input name="lokasi" id="kelLok">
    <div class="grid-2">
      <div><label>Desa</label><input name="desa" id="kelDesa"></div>
      <div><label>Kecamatan</label><input name="kecamatan" id="kelKec"></div>
    </div>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mKel')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<script>
function editKel(r) {
  document.getElementById('kelId').value = r.id;
  document.getElementById('kelJudul').textContent = 'Ubah ' + (r.kode_kelompok || ('KT-' + String(r.nomor).padStart(2,'0')));
  document.getElementById('kelNama').value = r.nama_kelompok || '';
  const info = document.getElementById('kelKetuaInfo');
  info.innerHTML = r.nama_ketua
    ? ('<strong>Ketua (otomatis dari jabatan)</strong><br>' + r.nama_ketua + (r.no_hp_ketua ? ' · ' + r.no_hp_ketua : '') + '<br><small>Ubah di Detail kelompok, bukan di sini.</small>')
    : '<strong>Ketua belum dipilih.</strong><br><small>Atur jabatan Ketua di halaman Detail.</small>';
  document.getElementById('kelDusun').value = r.wilayah_dusun || '';
  document.getElementById('kelBlok').value = r.blok_hamparan || '';
  document.getElementById('kelTgl').value = r.tanggal_terbentuk || '';
  document.getElementById('kelLuas').value = r.luas_tanah || '';
  document.getElementById('kelFee').value = r.fee_per_kg || '';
  document.getElementById('kelLok').value = r.lokasi || '';
  document.getElementById('kelDesa').value = r.desa || '';
  document.getElementById('kelKec').value = r.kecamatan || '';
  openModal('mKel');
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
