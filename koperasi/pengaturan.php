<?php
require __DIR__ . '/config.php';

if (isset($_GET['cetak_ba'])) {
    require_login();
    $s = setting();
    $ba = berita_acara($s);
    $stukBA = struktur_koperasi($s);
    $pendiriBA = daftar_pendiri_ba($ba['pendiri_txt']);
    $jmlBA = count($pendiriBA);
    $pokokBA = (float)($s['simpanan_pokok'] ?? 0);
    $wajibBA = (float)($s['simpanan_wajib'] ?? 0);
    $totalBA = $jmlBA * ($pokokBA + $wajibBA);
    $namaKopBA = $s['nama_koperasi'] ?? default_profil_koperasi()['nama_koperasi'];
    $staffBA = in_array((auth()['role'] ?? ''), ['admin', 'pengurus'], true);
    $hariBA = hari_indo($ba['tanggal']);
    $g = static function (string $k) use ($stukBA): string {
        return trim((string)($stukBA[$k] ?? ''));
    };
    $pwBA = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)($stukBA['pengawas_anggota_txt'] ?? '')) as $lnBA) {
        $lnBA = trim($lnBA);
        if ($lnBA !== '') {
            $pwBA[] = $lnBA;
        }
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara Pendirian — <?= e($namaKopBA) ?></title>
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
<p class="no-print"><button onclick="window.print()">Cetak / Simpan PDF</button> <a href="<?= $staffBA ? 'pengaturan.php' : 'profil_koperasi.php#ba' ?>">Kembali</a></p>
<div class="kop">
  <h1><?= e($namaKopBA) ?></h1>
  <p><?= e($s['alamat'] ?? '') ?></p>
  <p><?= e(trim(($s['telepon'] ?? '') . ' ' . ($s['email'] ?? ''))) ?></p>
</div>
<h2>Berita Acara Rapat Pendirian Koperasi</h2>
<div class="nomor">Nomor: <?= e($ba['nomor'] !== '' ? $ba['nomor'] : '..../..../....') ?></div>

<p class="just">Pada hari <strong><?= e($hariBA !== '' ? $hariBA : '........') ?></strong>, tanggal <strong><?= e(tgl_panjang($ba['tanggal'])) ?></strong>,
pukul <?= e(($ba['waktu_mulai'] !== '' ? $ba['waktu_mulai'] : '....') . ' s.d. ' . ($ba['waktu_selesai'] !== '' ? $ba['waktu_selesai'] : '....')) ?> WIB,
bertempat di <strong><?= e($ba['tempat'] !== '' ? $ba['tempat'] : '........') ?></strong>, telah diselenggarakan <strong>Rapat Pendirian
<?= e($namaKopBA) ?></strong> yang dihadiri oleh <strong><?= $jmlBA ?> (<?= e(terbilang_id($jmlBA)) ?>) orang</strong> pendiri
sebagaimana tercantum dalam daftar hadir berikut:</p>

<table class="daftar">
  <thead><tr><th style="width:36px;">No</th><th>Nama</th><th style="width:170px;">NIK</th><th>Alamat</th><th style="width:90px;">Tanda tangan</th></tr></thead>
  <tbody>
  <?php foreach ($pendiriBA as $iBA => $pBA): ?>
    <tr>
      <td><?= $iBA + 1 ?></td>
      <td><?= e($pBA['nama'] !== '' ? $pBA['nama'] : '—') ?></td>
      <td><?= e($pBA['nik'] !== '' ? $pBA['nik'] : '—') ?></td>
      <td><?= e($pBA['alamat'] !== '' ? $pBA['alamat'] : '—') ?></td>
      <td><?= $iBA + 1 ?>. .........</td>
    </tr>
  <?php endforeach; if (!$pendiriBA): ?>
    <tr><td colspan="5" style="text-align:center;">(Daftar pendiri belum diisi — lengkapi di Pengaturan)</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<p class="just">Rapat dipimpin oleh <strong><?= e($ba['pimpinan'] !== '' ? $ba['pimpinan'] : '........') ?></strong> selaku Pimpinan Rapat dan
<strong><?= e($ba['notulis'] !== '' ? $ba['notulis'] : '........') ?></strong> selaku Notulis. Setelah bermusyawarah untuk mufakat,
rapat <strong>memutuskan</strong> hal-hal sebagai berikut:</p>

<ol class="keputusan">
  <li>Menyetujui pendirian koperasi dengan nama <strong><?= e($namaKopBA) ?></strong>, jenis <strong><?= e($s['jenis_koperasi'] ?? 'Koperasi Produsen') ?></strong>,
    berkedudukan di <?= e($s['alamat'] ?? '........') ?>.</li>
  <li>Menyetujui Rancangan Anggaran Dasar (AD) dan Anggaran Rumah Tangga (ART) koperasi sebagaimana terlampir dan menjadi bagian tidak terpisahkan dari berita acara ini.</li>
  <li>Mengangkat Pengurus dan Pengawas koperasi untuk masa jabatan <strong><?= e($g('masa_jabatan') !== '' ? $g('masa_jabatan') : '........') ?></strong> sebagai berikut:
    <br>Pengurus: Ketua — <strong><?= e($g('ketua') !== '' ? $g('ketua') : '........') ?></strong>;
    Wk. Ketua — <strong><?= e($g('wakil_ketua') !== '' ? $g('wakil_ketua') : '........') ?></strong>;
    Sekretaris — <strong><?= e($g('sekretaris') !== '' ? $g('sekretaris') : '........') ?></strong>;
    Wk. Sekretaris — <strong><?= e($g('wakil_sekretaris') !== '' ? $g('wakil_sekretaris') : '........') ?></strong>;
    Bendahara — <strong><?= e($g('bendahara') !== '' ? $g('bendahara') : '........') ?></strong>.
    <br>Pengawas: Ketua — <strong><?= e($g('pengawas_ketua') !== '' ? $g('pengawas_ketua') : '........') ?></strong><?php if ($pwBA): ?>; Anggota — <strong><?= e(implode('; ', $pwBA)) ?></strong><?php endif; ?>.</li>
  <li>Menetapkan modal koperasi berupa Simpanan Pokok sebesar <strong><?= rupiah($pokokBA) ?></strong> per orang dan Simpanan Wajib
    sebesar <strong><?= rupiah($wajibBA) ?></strong> per orang per bulan. Jumlah modal yang disetor para pendiri pada saat pendirian adalah
    <strong><?= rupiah($totalBA) ?> (<?= e(terbilang_id($totalBA)) ?> rupiah)</strong>.</li>
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
  <div class="kol">Ketua Terpilih<div class="nama"><?= e($g('ketua') !== '' ? $g('ketua') : '( .................... )') ?></div></div>
  <div class="kol">Sekretaris Terpilih<div class="nama"><?= e($g('sekretaris') !== '' ? $g('sekretaris') : '( .................... )') ?></div></div>
  <div class="kol">Bendahara Terpilih<div class="nama"><?= e($g('bendahara') !== '' ? $g('bendahara') : '( .................... )') ?></div></div>
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

require_staff();
$title = 'Pengaturan koperasi';
$pdo = db();
ensure_pengaturan_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'hapus_data') {
    $u = auth();
    if (($u['role'] ?? '') !== 'admin') {
        flash('err', 'Hanya admin yang boleh menghapus semua data.');
        header('Location: pengaturan.php');
        exit;
    }
    $ketik = strtoupper(trim((string)($_POST['konfirmasi'] ?? '')));
    if ($ketik !== 'HAPUS DATA') {
        flash('err', 'Ketik persis HAPUS DATA untuk konfirmasi.');
        header('Location: pengaturan.php');
        exit;
    }
    try {
        $skip = ['users'];
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $n = 0;
        foreach ($tables as $t) {
            $name = (string)$t[0];
            if (in_array(strtolower($name), $skip, true)) {
                continue;
            }
            $pdo->exec('DELETE FROM `' . str_replace('`', '', $name) . '`');
            $n++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $dir = __DIR__ . '/uploads';
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess') {
                    continue;
                }
                $p = $dir . '/' . $f;
                if (is_file($p)) {
                    @unlink($p);
                }
            }
        }
        $pdo->prepare('INSERT INTO pengaturan (id, nama_koperasi, alamat, telepon, email, tahun_berdiri, tanggal_berdiri, ketua, visi, misi, bagi_hasil_persen, simpanan_pokok, simpanan_wajib) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                'Koperasi Produsen Ramah Lingkungan Pasaman Barat',
                '',
                '',
                '',
                date('Y'),
                date('Y-m-d'),
                '',
                '',
                '',
                1,
                150000,
                10000,
            ]);
        $pdo->exec("INSERT INTO jenis_simpanan (kode, nama, keterangan, wajib) VALUES
            ('SPK', 'Simpanan Pokok', 'Dibayar sekali saat menjadi anggota', 1),
            ('SWJ', 'Simpanan Wajib', 'Dibayar setiap bulan oleh anggota aktif', 1),
            ('SSK', 'Simpanan Sukarela', 'Simpanan bebas sesuai kemampuan anggota', 0),
            ('SHR', 'Simpanan Hari Raya', 'Tabungan khusus menjelang hari raya', 0)");
        ensure_kelompok_schema();
        ensure_akuntansi_schema();
        flash('ok', "Data operasional dihapus ($n tabel). Tabel users tetap. Isi ulang identitas koperasi di halaman ini.");
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e2) {
        }
        flash('err', 'Gagal menghapus: ' . $e->getMessage());
    }
    header('Location: pengaturan.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sOld = setting();
    $bh = (float)str_replace(',', '.', $_POST['bagi_hasil_persen'] ?? '1');
    $pokok = (float)$_POST['simpanan_pokok'];
    $wajib = (float)$_POST['simpanan_wajib'];
    $tglBerdiri = trim($_POST['tanggal_berdiri'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $tglBerdiri);
    if (!$dt) {
        flash('err', 'Tanggal berdiri tidak valid.');
        header('Location: pengaturan.php');
        exit;
    }
    $tahun = (int)$dt->format('Y');
    $ketua = trim((string)($_POST['ketua_stuk'] ?? $_POST['ketua'] ?? ''));
    $sekretaris = trim((string)($_POST['sekretaris'] ?? ''));
    $bendahara = trim((string)($_POST['bendahara'] ?? ''));
    $pwKetua = trim((string)($_POST['pengawas_ketua'] ?? ''));
    $wakaKetua = trim((string)($_POST['wakil_ketua'] ?? ''));
    $wakaSek = trim((string)($_POST['wakil_sekretaris'] ?? ''));
    $manajer = trim((string)($_POST['manajer'] ?? ''));
    $masaJab = trim((string)($_POST['masa_jabatan'] ?? ''));
    $pwAnggotaTxt = trim((string)($_POST['pengawas_anggota'] ?? ''));
    $ambilUnit = static function (string $pre): array {
        $out = [];
        foreach ((array)($_POST[$pre . '_nama'] ?? []) as $i => $nm) {
            $nm = trim((string)$nm);
            $or = trim((string)(($_POST[$pre . '_orang'] ?? [])[$i] ?? ''));
            $wr = trim((string)(($_POST[$pre . '_warna'] ?? [])[$i] ?? 'biru'));
            $sub = trim((string)(($_POST[$pre . '_sub'] ?? [])[$i] ?? ''));
            if ($nm === '' && $or === '' && $sub === '') {
                continue;
            }
            $out[] = ['nama' => $nm, 'orang' => $or, 'warna' => $wr, 'sub' => $sub];
        }
        return $out;
    };
    if ($ketua !== '') {
        $_POST['ketua'] = $ketua;
    }
    $struktur = json_encode([
        'pembina' => trim((string)($_POST['pembina'] ?? '')),
        'ketua' => $ketua,
        'sekretaris' => $sekretaris,
        'bendahara' => $bendahara,
        'pengawas_ketua' => $pwKetua,
        'pengawas_anggota' => $pwAnggotaTxt,
        'wakil_ketua' => $wakaKetua,
        'wakil_sekretaris' => $wakaSek,
        'manajer' => $manajer,
        'masa_jabatan' => $masaJab,
        'ktu' => trim((string)($_POST['ktu'] ?? '')),
        'kasir' => trim((string)($_POST['kasir'] ?? '')),
        'unit_kiri' => $ambilUnit('ukiri'),
        'unit_bawah' => $ambilUnit('ubawah'),
        'unit_kanan' => $ambilUnit('ukanan'),
    ], JSON_UNESCAPED_UNICODE);
    $logoFile = $sOld['logo_file'] ?? null;
    $namaLogo = simpan_berkas_anggota('logo_file');
    if ($namaLogo) {
        $logoFile = $namaLogo;
    }
    $baLama = json_decode((string)($sOld['ba_json'] ?? ''), true);
    if (!is_array($baLama)) {
        $baLama = [];
    }
    $baData = array_merge($baLama, [
        'nomor' => trim((string)($_POST['ba_nomor'] ?? '')),
        'tanggal' => trim((string)($_POST['ba_tanggal'] ?? '')),
        'waktu_mulai' => trim((string)($_POST['ba_waktu_mulai'] ?? '')),
        'waktu_selesai' => trim((string)($_POST['ba_waktu_selesai'] ?? '')),
        'tempat' => trim((string)($_POST['ba_tempat'] ?? '')),
        'pimpinan' => trim((string)($_POST['ba_pimpinan'] ?? '')),
        'notulis' => trim((string)($_POST['ba_notulis'] ?? '')),
        'pendiri_txt' => trim((string)($_POST['ba_pendiri_txt'] ?? '')),
        'usaha_txt' => trim((string)($_POST['ba_usaha_txt'] ?? '')),
        'kuasa_txt' => trim((string)($_POST['ba_kuasa_txt'] ?? '')),
    ]);
    // Bersihkan kunci lama yang duplikat (pengurus/modal kini dari struktur & simpanan).
    foreach (['ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'pengawas_ketua', 'pengawas_anggota', 'masa_jabatan', 'modal_pokok', 'modal_wajib'] as $kl) {
        unset($baData[$kl]);
    }
    $pdo->prepare('UPDATE pengaturan SET nama_koperasi=?, alamat=?, telepon=?, email=?, ketua=?, visi=?, misi=?, bagi_hasil_persen=?, simpanan_pokok=?, simpanan_wajib=?, tahun_berdiri=?, tanggal_berdiri=?, no_akta=?, no_badan_hukum=?, nib=?, npwp=?, iusp=?, iup=?, jenis_koperasi=?, sejarah=?, logo_filosofi=?, logo_file=?, nilai_koperasi=?, nik_koperasi=?, rapat_anggota=?, cabang=?, whatsapp=?, sosmed=?, struktur_json=?, motto=?, kegiatan_usaha=?, prestasi=?, karyawan_ket=?, tgl_badan_hukum=?, kbli=?, sertifikasi=?, ba_json=? WHERE id=1')
        ->execute([
            trim($_POST['nama_koperasi']),
            trim($_POST['alamat']),
            trim($_POST['telepon']),
            trim($_POST['email']),
            trim($_POST['ketua']),
            trim($_POST['visi']),
            trim($_POST['misi']),
            $bh,
            $pokok,
            $wajib,
            $tahun,
            $dt->format('Y-m-d'),
            trim($_POST['no_akta'] ?? ''),
            trim($_POST['no_badan_hukum'] ?? ''),
            trim($_POST['nib'] ?? ''),
            trim($_POST['npwp'] ?? ''),
            trim($_POST['iusp'] ?? ''),
            trim($_POST['iup'] ?? ''),
            trim($_POST['jenis_koperasi'] ?? ''),
            trim($_POST['sejarah'] ?? ''),
            trim($_POST['logo_filosofi'] ?? ''),
            $logoFile,
            trim($_POST['nilai_koperasi'] ?? ''),
            trim($_POST['nik_koperasi'] ?? ''),
            trim($_POST['rapat_anggota'] ?? ''),
            trim($_POST['cabang'] ?? ''),
            trim($_POST['whatsapp'] ?? ''),
            trim($_POST['sosmed'] ?? ''),
            $struktur,
            trim($_POST['motto'] ?? ''),
            trim($_POST['kegiatan_usaha'] ?? ''),
            trim($_POST['prestasi'] ?? ''),
            trim($_POST['karyawan_ket'] ?? ''),
            trim($_POST['tgl_badan_hukum'] ?? '') ?: null,
            trim($_POST['kbli'] ?? ''),
            trim($_POST['sertifikasi'] ?? ''),
            json_encode($baData, JSON_UNESCAPED_UNICODE),
        ]);
    $n = sinkron_simpanan_wajib_semua(auth()['id'] ?? null);
    flash('ok', 'Pengaturan disimpan. Tampil di Profil koperasi. Simpanan wajib: ' . $n . ' setoran bulan baru.');
    header('Location: pengaturan.php');
    exit;
}

$s = setting();
$defP = default_profil_koperasi();
$isiP = static function (string $k) use ($s, $defP): string {
    $v = trim((string)($s[$k] ?? ''));
    return $v !== '' ? $v : (string)($defP[$k] ?? '');
};
$tglBerdiri = $s['tanggal_berdiri'] ?? '';
if (!$tglBerdiri && !empty($s['tahun_berdiri'])) {
    $tglBerdiri = $s['tahun_berdiri'] . '-01-01';
}
if (!$tglBerdiri) {
    $tglBerdiri = $defP['tanggal_berdiri'];
}
$stuk = struktur_koperasi($s);
$ba = berita_acara($s);
$jmlPendiriBA = count(daftar_pendiri_ba($ba['pendiri_txt']));
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;"><a class="btn btn-ghost btn-sm" href="profil_koperasi.php">Lihat halaman Profil koperasi</a><a class="btn btn-green btn-sm" href="pengaturan.php?cetak_ba=1" target="_blank">Cetak BA / PDF</a></p>
<form method="post" enctype="multipart/form-data" class="cards" style="grid-template-columns:1fr 1fr;">
  <?= csrf_field() ?>
  <div class="card">
    <h3>Tanggal berdiri &amp; simpan pinjam</h3>
    <label>Tanggal berdiri koperasi</label>
    <input type="date" name="tanggal_berdiri" value="<?= e(substr((string)$tglBerdiri, 0, 10)) ?>" required>
    <p style="font-size:12px;color:var(--muted);margin:6px 0 12px;">Setoran wajib otomatis tiap bulan dihitung mulai bulan pendirian koperasi di atas (September 2026).</p>
    <label>Bagi hasil (% per bulan)</label>
    <input name="bagi_hasil_persen" type="number" step="0.01" min="0" max="100" value="<?= e($s['bagi_hasil_persen'] ?? 1) ?>" required>
    <label>Simpanan pokok (Rp, sekali)</label>
    <input name="simpanan_pokok" type="number" min="0" step="1000" value="<?= (int)($s['simpanan_pokok'] ?? 500000) ?>" required>
    <label>Simpanan wajib (Rp / bulan)</label>
    <input name="simpanan_wajib" type="number" min="0" step="1000" value="<?= (int)($s['simpanan_wajib'] ?? 50000) ?>" required>
  </div>
  <div class="card">
    <h3>Identitas (profil koperasi)</h3>
    <label>Nama resmi koperasi</label>
    <input name="nama_koperasi" value="<?= e($isiP('nama_koperasi')) ?>" required>
    <label>Jenis koperasi</label>
    <input name="jenis_koperasi" value="<?= e($isiP('jenis_koperasi')) ?>" placeholder="KSU / KSP / Produsen / Kopsyah">
    <label>Ketua (singkat)</label>
    <input name="ketua" value="<?= e($s['ketua'] ?: ($stuk['ketua'] ?? '')) ?>">
    <label>Telepon</label>
    <input name="telepon" value="<?= e($s['telepon'] ?? '') ?>">
    <label>WhatsApp resmi</label>
    <input name="whatsapp" value="<?= e($s['whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
    <label>Email</label>
    <input name="email" value="<?= e($s['email'] ?? '') ?>">
    <label>Alamat kantor pusat</label>
    <textarea name="alamat"><?= e($isiP('alamat')) ?></textarea>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Legalitas &amp; badan hukum</h3>
    <div class="grid-2">
      <div><label>No. akta pendirian</label><input name="no_akta" value="<?= e($isiP('no_akta')) ?>"></div>
      <div><label>Tanggal badan hukum</label><input type="date" name="tgl_badan_hukum" value="<?= e(substr((string)($s['tgl_badan_hukum'] ?: $defP['tgl_badan_hukum']), 0, 10)) ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>Nomor Induk Koperasi (NIK)</label><input name="nik_koperasi" value="<?= e($s['nik_koperasi'] ?? '') ?>"></div>
      <div><label>NIB</label><input name="nib" value="<?= e($s['nib'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>NPWP badan</label><input name="npwp" value="<?= e($s['npwp'] ?? '') ?>"></div>
      <div><label>IUSP (izin simpan pinjam)</label><input name="iusp" value="<?= e($s['iusp'] ?? '') ?>"></div>
    </div>
    <label>IUP (opsional)</label>
    <input name="iup" value="<?= e($s['iup'] ?? '') ?>">
    <label>Bidang usaha / KBLI (satu per baris)</label>
    <textarea name="kbli" placeholder="<?= e(kbli_usaha_default()) ?>"><?= e(trim((string)($s['kbli'] ?? '')) !== '' ? (string)$s['kbli'] : kbli_usaha_default()) ?></textarea>
    <label>Sertifikasi (ISPO / organik / SNI pupuk — satu per baris)</label>
    <textarea name="sertifikasi" placeholder="cth. Sertifikat Organik ..."><?= e($s['sertifikasi'] ?? '') ?></textarea>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Sejarah, logo, visi, misi, nilai</h3>
    <label>Latar belakang / sejarah</label>
    <textarea name="sejarah" style="min-height:110px;"><?= e($isiP('sejarah')) ?></textarea>
    <label>Logo koperasi (gambar)</label>
    <?php if (!empty($s['logo_file'])): ?>
      <p style="font-size:13px;color:var(--muted);margin:0 0 8px;">Logo terpasang. Unggah baru untuk ganti.</p>
    <?php endif; ?>
    <input type="file" name="logo_file" accept="image/*">
    <label>Filosofi / arti logo</label>
    <textarea name="logo_filosofi"><?= e($s['logo_filosofi'] ?? '') ?></textarea>
    <label>Visi</label>
    <textarea name="visi"><?= e($isiP('visi')) ?></textarea>
    <label>Misi</label>
    <textarea name="misi" style="min-height:110px;"><?= e($isiP('misi')) ?></textarea>
    <label>Motto</label>
    <textarea name="motto"><?= e($isiP('motto')) ?></textarea>
    <label>Budaya / nilai koperasi</label>
    <textarea name="nilai_koperasi"><?= e($isiP('nilai_koperasi')) ?></textarea>
    <label>Kegiatan usaha (satu per baris)</label>
    <textarea name="kegiatan_usaha" style="min-height:110px;"><?= e($isiP('kegiatan_usaha')) ?></textarea>
    <label>Prestasi (satu per baris)</label>
    <textarea name="prestasi"><?= e($isiP('prestasi')) ?></textarea>
    <label>Karyawan</label>
    <textarea name="karyawan_ket"><?= e($isiP('karyawan_ket')) ?></textarea>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Struktur organisasi (bagan RAT)</h3>
    <label>Penjelasan RAT</label>
    <textarea name="rapat_anggota"><?= e($isiP('rapat_anggota')) ?></textarea>
    <label>Pembina &amp; penasehat (satu per baris)</label>
    <textarea name="pembina"><?= e($stuk['pembina_txt'] ?? '') ?></textarea>
    <div class="grid-2">
      <div><label>Ketua</label><input name="ketua_stuk" value="<?= e($stuk['ketua'] ?? '') ?>"></div>
      <div><label>Sekretaris</label><input name="sekretaris" value="<?= e($stuk['sekretaris'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>Bendahara</label><input name="bendahara" value="<?= e($stuk['bendahara'] ?? '') ?>"></div>
      <div><label>Ketua pengawas</label><input name="pengawas_ketua" value="<?= e($stuk['pengawas_ketua'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>Wk. Ketua</label><input name="wakil_ketua" value="<?= e($stuk['wakil_ketua'] ?? '') ?>"></div>
      <div><label>Wk. Sekretaris</label><input name="wakil_sekretaris" value="<?= e($stuk['wakil_sekretaris'] ?? '') ?>"></div>
    </div>
    <div class="grid-2">
      <div><label>Manajer</label><input name="manajer" value="<?= e($stuk['manajer'] ?? '') ?>"></div>
      <div><label>Masa jabatan</label><input name="masa_jabatan" value="<?= e($stuk['masa_jabatan'] ?? '') ?>" placeholder="cth. 2026-2029"></div>
    </div>
    <label>Anggota pengawas (satu per baris)</label>
    <textarea name="pengawas_anggota"><?= e($stuk['pengawas_anggota_txt'] ?? implode("\n", $stuk['pengawas_anggota'] ?? [])) ?></textarea>
    <div class="grid-2">
      <div><label>KTU</label><input name="ktu" value="<?= e($stuk['ktu'] ?? '') ?>"></div>
      <div><label>Kasir</label><input name="kasir" value="<?= e($stuk['kasir'] ?? '') ?>"></div>
    </div>
    <?php
    $formUnit = static function (string $pre, array $rows, string $judul): void {
        echo '<h4 style="font-size:13px;color:var(--green);margin:14px 0 8px;">' . e($judul) . '</h4>';
        echo '<div id="box-' . e($pre) . '">';
        foreach ($rows as $u) {
            $sub = [];
            foreach ($u['sub'] ?? [] as $srow) {
                $sub[] = trim(($srow['nama'] ?? '') . '|' . ($srow['orang'] ?? ''), '|');
            }
            echo '<div class="grid-2 slot-stuk" style="margin-bottom:8px;border:1px solid #ece6d6;border-radius:12px;padding:10px;">';
            echo '<div><label>Nama unit</label><input name="' . $pre . '_nama[]" value="' . e($u['nama']) . '"></div>';
            echo '<div><label>Penanggung jawab</label><input name="' . $pre . '_orang[]" value="' . e($u['orang']) . '"></div>';
            echo '<div><label>Warna</label><select name="' . $pre . '_warna[]">';
            foreach (['biru' => 'Biru (kantor)', 'hijau' => 'Hijau (usaha)', 'merah' => 'Merah', 'ungu' => 'Ungu'] as $k => $lab) {
                $sel = (($u['warna'] ?? '') === $k) ? ' selected' : '';
                echo '<option value="' . $k . '"' . $sel . '>' . $lab . '</option>';
            }
            echo '</select></div>';
            echo '<div><label>Sub-unit (Nama|Orang per baris)</label><textarea name="' . $pre . '_sub[]">' . e(implode("\n", $sub)) . '</textarea></div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:8px;" onclick="tambahUnit(\'' . $pre . '\')">+ Unit</button>';
    };
    $formUnit('ukiri', $stuk['unit_kiri'] ?? [], 'Unit kantor (kiri)');
    $formUnit('ubawah', $stuk['unit_bawah'] ?? [], 'Unit pendukung (bawah)');
    $formUnit('ukanan', $stuk['unit_kanan'] ?? [], 'Unit usaha (kanan)');
    ?>
  </div>
  <div class="card" id="ba" style="grid-column:1/-1;">
    <h3>Berita acara pendirian — rapat</h3>
    <p style="font-size:12px;color:var(--muted);margin:6px 0 12px;">Nama pengurus, masa jabatan, dan besaran simpanan diambil otomatis dari kartu Struktur &amp; Tanggal berdiri di halaman ini — cukup isi sekali.</p>
    <label>Nomor berita acara</label>
    <input name="ba_nomor" value="<?= e($ba['nomor']) ?>" placeholder="cth. 001/PENDIRIAN/KOP/KPRL-PB/IX/2026">
    <div class="grid-2">
      <div><label>Tanggal rapat</label><input type="date" name="ba_tanggal" value="<?= e(substr($ba['tanggal'], 0, 10)) ?>"></div>
      <div><label>Waktu (mulai — selesai)</label>
        <div style="display:flex;gap:8px;"><input name="ba_waktu_mulai" value="<?= e($ba['waktu_mulai']) ?>" placeholder="09.00"><input name="ba_waktu_selesai" value="<?= e($ba['waktu_selesai']) ?>" placeholder="12.00"></div>
      </div>
    </div>
    <label>Tempat rapat</label>
    <input name="ba_tempat" value="<?= e($ba['tempat']) ?>">
    <div class="grid-2">
      <div><label>Pimpinan rapat</label><input name="ba_pimpinan" value="<?= e($ba['pimpinan']) ?>"></div>
      <div><label>Notulis</label><input name="ba_notulis" value="<?= e($ba['notulis']) ?>"></div>
    </div>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Daftar pendiri (<?= $jmlPendiriBA ?> orang)</h3>
    <?php if ($jmlPendiriBA < 9): ?>
    <div class="alert alert-err">Pendiri baru <?= $jmlPendiriBA ?> orang. Koperasi primer sekurang-kurangnya didirikan oleh <strong>9 orang</strong>.</div>
    <?php endif; ?>
    <p style="font-size:12px;color:var(--muted);margin:6px 0 8px;">Satu baris satu orang, format: <code>Nama|NIK|Alamat</code></p>
    <textarea name="ba_pendiri_txt" style="min-height:180px;" placeholder="Nama Lengkap|1371xxxxxxxxxxxx|Alamat..."><?= e($ba['pendiri_txt']) ?></textarea>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Rencana usaha &amp; kuasa (dokumen BA)</h3>
    <label>Rencana usaha (muncul di dokumen)</label>
    <textarea name="ba_usaha_txt" style="min-height:110px;"><?= e($ba['usaha_txt']) ?></textarea>
    <label>Kuasa pengurusan badan hukum</label>
    <textarea name="ba_kuasa_txt"><?= e($ba['kuasa_txt']) ?></textarea>
  </div>
  <div class="card" style="grid-column:1/-1;">
    <h3>Jangkauan &amp; kontak</h3>
    <label>Jaringan kantor cabang</label>
    <textarea name="cabang" placeholder="Satu baris satu lokasi, atau tulis tidak ada cabang"><?= e($s['cabang'] ?? '') ?></textarea>
    <label>Media sosial resmi</label>
    <textarea name="sosmed" placeholder="Facebook / Instagram / YouTube"><?= e($s['sosmed'] ?? '') ?></textarea>
    <button class="btn btn-green" style="margin-top:16px;">Simpan pengaturan &amp; profil koperasi</button>
  </div>
</form>
<script>
function tambahUnit(pre) {
  const box = document.getElementById('box-' + pre);
  const d = document.createElement('div');
  d.className = 'grid-2 slot-stuk';
  d.style.cssText = 'margin-bottom:8px;border:1px solid #ece6d6;border-radius:12px;padding:10px;';
  d.innerHTML = '<div><label>Nama unit</label><input name="' + pre + '_nama[]"></div>'
    + '<div><label>Penanggung jawab</label><input name="' + pre + '_orang[]"></div>'
    + '<div><label>Warna</label><select name="' + pre + '_warna[]"><option value="biru">Biru (kantor)</option><option value="hijau">Hijau (usaha)</option><option value="merah">Merah</option><option value="ungu">Ungu</option></select></div>'
    + '<div><label>Sub-unit (Nama|Orang per baris)</label><textarea name="' + pre + '_sub[]"></textarea></div>';
  box.appendChild(d);
}
</script>
<?php if ((auth()['role'] ?? '') === 'admin'): ?>
<div class="card" style="margin-top:24px;max-width:520px;">
  <h3>Hapus semua data operasional</h3>
  <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Hanya admin. Tabel users tetap. Ketik <strong>HAPUS DATA</strong>.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="hapus_data">
    <input name="konfirmasi" placeholder="HAPUS DATA" required>
    <button class="btn btn-danger" style="margin-top:12px;" onclick="return confirm('Hapus semua data kecuali akun pengurus?')">Hapus data</button>
  </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
