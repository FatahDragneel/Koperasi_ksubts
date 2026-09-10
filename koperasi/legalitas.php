<?php
require __DIR__ . '/config.php';
$s = setting();
$title = 'Legalitas · ' . ($s['nama_koperasi'] ?? 'Koperasi');
$baris = [
    ['Akta pendirian', $s['no_akta'] ?? '', 'Dokumen notaris berisi Anggaran Dasar koperasi.'],
    ['SK / nomor badan hukum', $s['no_badan_hukum'] ?? '', 'Pengesahan sebagai badan hukum koperasi.'],
    ['NIB', $s['nib'] ?? '', 'Nomor Induk Berusaha melalui OSS.'],
    ['NPWP badan', $s['npwp'] ?? '', 'Nomor pajak atas nama koperasi.'],
    ['Izin unit simpan pinjam (IUSP)', $s['iusp'] ?? '', 'Hanya diisi jika koperasi punya izin USP/KSP resmi.'],
    ['Izin usaha perkebunan (IUP)', $s['iup'] ?? '', 'Hanya diisi jika koperasi mengelola kebun sebagai pelaku usaha perkebunan.'],
];
include __DIR__ . '/includes/public_header.php';
?>
<section>
  <div class="container" style="padding-top:28px;padding-bottom:40px;">
    <div class="sec-title" style="text-align:left;margin-bottom:18px;">
      <span>Transparansi</span>
      <h2>Legalitas koperasi</h2>
    </div>
    <p style="color:var(--muted);max-width:720px;margin-bottom:20px;">
      <?= e($s['nama_koperasi'] ?? 'Koperasi') ?> menampilkan identitas resmi yang diisi pengurus.
      Menampilkan halaman ini <strong>bukan kewajiban undang-undang</strong>; yang wajib adalah
      <em>memiliki</em> dokumen tersebut untuk beroperasi. Scan akta/izin tidak diunggah ke situs
      (cegah penyalahgunaan). Nomor diisi di menu Pengaturan.
    </p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Dokumen</th><th>Nomor / keterangan</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach ($baris as [$nama, $nomor, $ket]): ?>
          <tr>
            <td><strong><?= e($nama) ?></strong></td>
            <td><?= $nomor !== '' ? e($nomor) : '<span style="color:var(--muted)">Belum diisi pengurus</span>' ?></td>
            <td style="font-size:13px;color:var(--muted);"><?= e($ket) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:18px;font-size:14px;color:var(--muted);">
      Alamat kantor: <?= e($s['alamat'] ?: '—') ?> · Telp <?= e($s['telepon'] ?: '—') ?> · <?= e($s['email'] ?: '') ?>
    </p>
    <p style="margin-top:8px;"><a class="btn btn-ghost btn-sm" href="index.php#tentang">← Tentang koperasi</a></p>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
