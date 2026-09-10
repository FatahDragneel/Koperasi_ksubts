<?php
require __DIR__ . '/config.php';
require_login();
$title = 'Berita acara pendirian';
$pdo = db();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus'], true);
$s = setting();
$ba = berita_acara($s);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staff) {
    $act = $_POST['act'] ?? 'simpan';
    $data = [
        'nomor' => trim((string)($_POST['nomor'] ?? '')),
        'tanggal' => trim((string)($_POST['tanggal'] ?? date('Y-m-d'))),
        'waktu_mulai' => trim((string)($_POST['waktu_mulai'] ?? '')),
        'waktu_selesai' => trim((string)($_POST['waktu_selesai'] ?? '')),
        'tempat' => trim((string)($_POST['tempat'] ?? '')),
        'pimpinan' => trim((string)($_POST['pimpinan'] ?? '')),
        'notulis' => trim((string)($_POST['notulis'] ?? '')),
        'pendiri_txt' => trim((string)($_POST['pendiri_txt'] ?? '')),
        'ketua' => trim((string)($_POST['ketua'] ?? '')),
        'sekretaris' => trim((string)($_POST['sekretaris'] ?? '')),
        'bendahara' => trim((string)($_POST['bendahara'] ?? '')),
        'pengawas_ketua' => trim((string)($_POST['pengawas_ketua'] ?? '')),
        'pengawas_anggota' => trim((string)($_POST['pengawas_anggota'] ?? '')),
        'modal_pokok' => trim((string)($_POST['modal_pokok'] ?? '0')),
        'modal_wajib' => trim((string)($_POST['modal_wajib'] ?? '0')),
        'usaha_txt' => trim((string)($_POST['usaha_txt'] ?? '')),
        'kuasa_txt' => trim((string)($_POST['kuasa_txt'] ?? '')),
    ];
    $pdo->prepare('UPDATE pengaturan SET ba_json=? WHERE id=1')
        ->execute([json_encode($data, JSON_UNESCAPED_UNICODE)]);
    if ($act === 'terapkan') {
        $d = json_decode((string)($s['struktur_json'] ?? ''), true);
        if (!is_array($d)) {
            $d = [];
        }
        foreach (['ketua', 'sekretaris', 'bendahara', 'pengawas_ketua', 'pengawas_anggota'] as $k) {
            if ($data[$k] !== '') {
                $d[$k] = $data[$k];
            }
        }
        $dt = DateTime::createFromFormat('Y-m-d', substr($data['tanggal'], 0, 10));
        $pdo->prepare('UPDATE pengaturan SET struktur_json=?, tanggal_berdiri=?, tahun_berdiri=?, ketua=?, simpanan_pokok=?, simpanan_wajib=? WHERE id=1')
            ->execute([
                json_encode($d, JSON_UNESCAPED_UNICODE),
                $dt ? $dt->format('Y-m-d') : substr($data['tanggal'], 0, 10),
                $dt ? (int)$dt->format('Y') : (int)date('Y'),
                $data['ketua'] !== '' ? $data['ketua'] : ($s['ketua'] ?? ''),
                (float)str_replace('.', '', $data['modal_pokok']),
                (float)str_replace('.', '', $data['modal_wajib']),
            ]);
        flash('ok', 'Berita acara disimpan dan diterapkan ke struktur organisasi, tanggal berdiri, ketua, serta besaran simpanan.');
    } else {
        $jml = count(daftar_pendiri_ba($data['pendiri_txt']));
        flash('ok', 'Berita acara disimpan (' . $jml . ' pendiri). Gunakan "Terapkan" untuk memindahkan nama pengurus ke struktur organisasi.');
    }
    header('Location: berita_acara.php');
    exit;
}

$pendiri = daftar_pendiri_ba($ba['pendiri_txt']);
$jmlPendiri = count($pendiri);
$pokok = (float)str_replace('.', '', (string)$ba['modal_pokok']);
$wajib = (float)str_replace('.', '', (string)$ba['modal_wajib']);
$totalModal = $jmlPendiri * ($pokok + $wajib);
$stuk = struktur_koperasi($s);
$namaKop = $s['nama_koperasi'] ?? default_profil_koperasi()['nama_koperasi'];

if (isset($_GET['cetak'])) {
    $hari = hari_indo($ba['tanggal']);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara Pendirian — <?= e($namaKop) ?></title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; color: #111; margin: 32px; line-height: 1.7; }
  .kop { text-align: center; border-bottom: 3px double #111; padding-bottom: 12px; margin-bottom: 20px; }
  .kop h1 { font-size: 20px; margin: 0; text-transform: uppercase; }
  .kop p { margin: 2px 0; font-size: 13px; }
  h2 { text-align: center; font-size: 17px; text-transform: uppercase; margin: 18px 0 4px; text-decoration: underline; }
  .nomor { text-align: center; font-size: 14px; margin-bottom: 18px; }
  p.just { text-align: justify; font-size: 14.5px; }
  table.daftar { width: 100%; border-collapse: collapse; font-size: 13.5px; margin: 12px 0 18px; }
  table.daftar th, table.daftar td { border: 1px solid #111; padding: 6px 8px; text-align: left; vertical-align: top; }
  ol.keputusan { font-size: 14.5px; text-align: justify; }
  ol.keputusan li { margin-bottom: 8px; }
  .ttd { display: flex; justify-content: space-between; gap: 24px; margin-top: 28px; font-size: 14px; }
  .ttd .kol { flex: 1; text-align: center; }
  .ttd .nama { margin-top: 72px; font-weight: bold; text-decoration: underline; }
  .catatan { font-size: 12px; color: #444; border-top: 1px solid #999; margin-top: 28px; padding-top: 8px; }
  @media print { .no-print { display: none; } body { margin: 12mm; } }
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">Cetak / Simpan PDF</button> <a href="berita_acara.php">Kembali</a></p>
<div class="kop">
  <h1><?= e($namaKop) ?></h1>
  <p><?= e($s['alamat'] ?? '') ?></p>
  <p><?= e(trim(($s['telepon'] ?? '') . ' ' . ($s['email'] ?? ''))) ?></p>
</div>
<h2>Berita Acara Rapat Pendirian Koperasi</h2>
<div class="nomor">Nomor: <?= e($ba['nomor'] !== '' ? $ba['nomor'] : '..../..../....') ?></div>

<p class="just">Pada hari <strong><?= e($hari !== '' ? $hari : '........') ?></strong>, tanggal <strong><?= e(tgl_panjang($ba['tanggal'])) ?></strong>,
pukul <?= e(($ba['waktu_mulai'] !== '' ? $ba['waktu_mulai'] : '....') . ' s.d. ' . ($ba['waktu_selesai'] !== '' ? $ba['waktu_selesai'] : '....')) ?> WIB,
bertempat di <strong><?= e($ba['tempat'] !== '' ? $ba['tempat'] : '........') ?></strong>, telah diselenggarakan <strong>Rapat Pendirian
<?= e($namaKop) ?></strong> yang dihadiri oleh <strong><?= $jmlPendiri ?> (<?= e(terbilang_id($jmlPendiri)) ?>) orang</strong> pendiri
sebagaimana tercantum dalam daftar hadir berikut:</p>

<table class="daftar">
  <thead><tr><th style="width:36px;">No</th><th>Nama</th><th style="width:170px;">NIK</th><th>Alamat</th><th style="width:90px;">Tanda tangan</th></tr></thead>
  <tbody>
  <?php foreach ($pendiri as $i => $p): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= e($p['nama'] !== '' ? $p['nama'] : '—') ?></td>
      <td><?= e($p['nik'] !== '' ? $p['nik'] : '—') ?></td>
      <td><?= e($p['alamat'] !== '' ? $p['alamat'] : '—') ?></td>
      <td><?= $i + 1 ?>. .........</td>
    </tr>
  <?php endforeach; if (!$pendiri): ?>
    <tr><td colspan="5" style="text-align:center;">(Daftar pendiri belum diisi — lengkapi di halaman Berita Acara)</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<p class="just">Rapat dipimpin oleh <strong><?= e($ba['pimpinan'] !== '' ? $ba['pimpinan'] : '........') ?></strong> selaku Pimpinan Rapat dan
<strong><?= e($ba['notulis'] !== '' ? $ba['notulis'] : '........') ?></strong> selaku Notulis. Setelah bermusyawarah untuk mufakat,
rapat <strong>memutuskan</strong> hal-hal sebagai berikut:</p>

<ol class="keputusan">
  <li>Menyetujui pendirian koperasi dengan nama <strong><?= e($namaKop) ?></strong>, jenis <strong><?= e($s['jenis_koperasi'] ?? 'Koperasi Produsen') ?></strong>,
    berkedudukan di <?= e($s['alamat'] ?? '........') ?>.</li>
  <li>Menyetujui Rancangan Anggaran Dasar (AD) dan Anggaran Rumah Tangga (ART) koperasi sebagaimana terlampir dan menjadi bagian tidak terpisahkan dari berita acara ini.</li>
  <li>Mengangkat Pengurus dan Pengawas koperasi untuk masa jabatan pertama sebagai berikut:
    <br>Pengurus: Ketua — <strong><?= e($ba['ketua'] !== '' ? $ba['ketua'] : '........') ?></strong>;
    Sekretaris — <strong><?= e($ba['sekretaris'] !== '' ? $ba['sekretaris'] : '........') ?></strong>;
    Bendahara — <strong><?= e($ba['bendahara'] !== '' ? $ba['bendahara'] : '........') ?></strong>.
    <br>Pengawas: Ketua — <strong><?= e($ba['pengawas_ketua'] !== '' ? $ba['pengawas_ketua'] : '........') ?></strong><?php
    $pwA = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$ba['pengawas_anggota']) as $ln) { $ln = trim($ln); if ($ln !== '') { $pwA[] = $ln; } }
    ?><?php if ($pwA): ?>; Anggota — <strong><?= e(implode('; ', $pwA)) ?></strong><?php endif; ?>.</li>
  <li>Menetapkan modal koperasi berupa Simpanan Pokok sebesar <strong><?= rupiah($pokok) ?></strong> per orang dan Simpanan Wajib
    sebesar <strong><?= rupiah($wajib) ?></strong> per orang per bulan. Jumlah modal yang disetor para pendiri pada saat pendirian adalah
    <strong><?= rupiah($totalModal) ?> (<?= e(terbilang_id($totalModal)) ?> rupiah)</strong>.</li>
  <li>Menetapkan rencana usaha koperasi sebagai berikut:
    <br><?= nl2br(e($ba['usaha_txt'] !== '' ? $ba['usaha_txt'] : '........')) ?></li>
  <li><?= nl2br(e($ba['kuasa_txt'] !== '' ? $ba['kuasa_txt'] : '........')) ?></li>
</ol>

<p class="just">Demikian berita acara ini dibuat dengan sebenarnya, untuk dipergunakan sebagaimana mestinya.</p>

<p style="font-size:14px;"><?= e($ba['tempat'] !== '' ? $ba['tempat'] : '........') ?>, <?= e(tgl_panjang($ba['tanggal'])) ?></p>
<div class="ttd">
  <div class="kol">Pimpinan Rapat<div class="nama"><?= e($ba['pimpinan'] !== '' ? $ba['pimpinan'] : '( .................... )') ?></div></div>
  <div class="kol">Notulis<div class="nama"><?= e($ba['notulis'] !== '' ? $ba['notulis'] : '( .................... )') ?></div></div>
</div>
<div class="ttd">
  <div class="kol">Ketua Terpilih<div class="nama"><?= e($ba['ketua'] !== '' ? $ba['ketua'] : '( .................... )') ?></div></div>
  <div class="kol">Sekretaris Terpilih<div class="nama"><?= e($ba['sekretaris'] !== '' ? $ba['sekretaris'] : '( .................... )') ?></div></div>
  <div class="kol">Bendahara Terpilih<div class="nama"><?= e($ba['bendahara'] !== '' ? $ba['bendahara'] : '( .................... )') ?></div></div>
</div>

<div class="catatan">
  Catatan: lengkapi dokumen dengan (1) fotokopi KTP para pendiri, (2) Rancangan AD/ART yang ditandatangani, (3) surat bukti setoran modal,
  dan (4) berita acara di atas materai sesuai ketentuan. Konsultasikan ke Dinas Koperasi &amp; UKM setempat sebelum pengesahan badan hukum.
</div>
</body>
</html>
    <?php
    exit;
}

include __DIR__ . '/includes/app_header.php';
?>
<?php if ($staff): ?>
<p style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;">
  <a class="btn btn-green btn-sm" href="berita_acara.php?cetak=1" target="_blank">Cetak / PDF</a>
  <a class="btn btn-ghost btn-sm" href="pengaturan.php">Pengaturan</a>
  <a class="btn btn-ghost btn-sm" href="profil_koperasi.php">Lihat struktur organisasi</a>
</p>
<?php if ($jmlPendiri < 9): ?>
<div class="alert alert-err">Pendiri baru <?= $jmlPendiri ?> orang. Koperasi primer sekurang-kurangnya didirikan oleh <strong>9 orang</strong> — tambahkan di daftar pendiri.</div>
<?php endif; ?>
<form method="post" class="cards" style="grid-template-columns:1fr 1fr;">
  <?= csrf_field() ?>
  <div class="card">
    <h3>Rapat pendirian</h3>
    <label>Nomor berita acara</label>
    <input name="nomor" value="<?= e($ba['nomor']) ?>" placeholder="cth. 01/BA-KOP/IX/2026">
    <div class="grid-2">
      <div><label>Tanggal rapat</label><input type="date" name="tanggal" value="<?= e(substr($ba['tanggal'], 0, 10)) ?>"></div>
      <div><label>Waktu (mulai — selesai)</label>
        <div style="display:flex;gap:8px;"><input name="waktu_mulai" value="<?= e($ba['waktu_mulai']) ?>" placeholder="09.00"><input name="waktu_selesai" value="<?= e($ba['waktu_selesai']) ?>" placeholder="12.00"></div>
      </div>
    </div>
    <label>Tempat rapat</label>
    <input name="tempat" value="<?= e($ba['tempat']) ?>" placeholder="cth. Aula Kantor ...">
    <div class="grid-2">
      <div><label>Pimpinan rapat</label><input name="pimpinan" value="<?= e($ba['pimpinan']) ?>"></div>
      <div><label>Notulis</label><input name="notulis" value="<?= e($ba['notulis']) ?>"></div>
    </div>
    <label>Modal — simpanan pokok per orang (Rp)</label>
    <input name="modal_pokok" value="<?= e($ba['modal_pokok']) ?>">
    <label>Modal — simpanan wajib per orang / bulan (Rp)</label>
    <input name="modal_wajib" value="<?= e($ba['modal_wajib']) ?>">
    <p style="font-size:13px;color:var(--muted);margin-top:8px;">Total modal disetor <?= $jmlPendiri ?> pendiri: <strong><?= rupiah($totalModal) ?></strong>.</p>
  </div>
  <div class="card">
    <h3>Daftar pendiri (<?= $jmlPendiri ?> orang)</h3>
    <p style="font-size:12px;color:var(--muted);margin:6px 0 8px;">Satu baris satu orang, format: <code>Nama|NIK|Alamat</code></p>
    <textarea name="pendiri_txt" style="min-height:260px;" placeholder="Nama Lengkap|1371xxxxxxxxxxxx|Alamat..."><?= e($ba['pendiri_txt']) ?></textarea>
  </div>
  <div class="card">
    <h3>Pengurus &amp; pengawas terpilih</h3>
    <div class="grid-2">
      <div><label>Ketua</label><input name="ketua" value="<?= e($ba['ketua']) ?>"></div>
      <div><label>Sekretaris</label><input name="sekretaris" value="<?= e($ba['sekretaris']) ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>Bendahara</label><input name="bendahara" value="<?= e($ba['bendahara']) ?>"></div>
      <div><label>Ketua pengawas</label><input name="pengawas_ketua" value="<?= e($ba['pengawas_ketua']) ?>"></div>
    </div>
    <label>Anggota pengawas (satu per baris)</label>
    <textarea name="pengawas_anggota"><?= e($ba['pengawas_anggota']) ?></textarea>
  </div>
  <div class="card">
    <h3>Rencana usaha &amp; kuasa</h3>
    <label>Rencana usaha (muncul di dokumen)</label>
    <textarea name="usaha_txt" style="min-height:130px;"><?= e($ba['usaha_txt']) ?></textarea>
    <label>Kuasa pengurusan badan hukum</label>
    <textarea name="kuasa_txt"><?= e($ba['kuasa_txt']) ?></textarea>
    <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
      <button class="btn btn-ghost" name="act" value="simpan">Simpan saja</button>
      <button class="btn btn-green" name="act" value="terapkan" onclick="return confirm('Terapkan nama pengurus, tanggal berdiri, dan simpanan ke Pengaturan & Struktur?')">Simpan &amp; terapkan ke struktur</button>
    </div>
  </div>
</form>
<?php else: ?>
<div class="card">
  <h3>Berita acara pendirian</h3>
  <div class="grid-2" style="margin-top:12px;">
    <p><strong>Nomor</strong><br><?= e($ba['nomor'] !== '' ? $ba['nomor'] : '—') ?></p>
    <p><strong>Tanggal</strong><br><?= e(tgl_panjang($ba['tanggal'])) ?></p>
    <p><strong>Tempat</strong><br><?= e($ba['tempat'] !== '' ? $ba['tempat'] : '—') ?></p>
    <p><strong>Jumlah pendiri</strong><br><?= $jmlPendiri ?> orang</p>
  </div>
  <p style="margin-top:12px;"><strong>Pengurus terpilih</strong><br>
    Ketua: <?= e($ba['ketua'] !== '' ? $ba['ketua'] : '—') ?> ·
    Sekretaris: <?= e($ba['sekretaris'] !== '' ? $ba['sekretaris'] : '—') ?> ·
    Bendahara: <?= e($ba['bendahara'] !== '' ? $ba['bendahara'] : '—') ?></p>
  <p style="margin-top:12px;"><a class="btn btn-green btn-sm" href="berita_acara.php?cetak=1" target="_blank">Cetak / PDF</a></p>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
