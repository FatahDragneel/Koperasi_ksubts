<?php
require __DIR__ . '/config.php';
require_login();
$s = setting();
$stuk = struktur_koperasi($s);
$p = static function (string $k) use ($s): string {
    return profil_isi($s, $k);
};
$title = 'Profil koperasi';
include __DIR__ . '/includes/app_header.php';
$staff = in_array(($u = auth())['role'] ?? '', ['admin', 'pengurus'], true);
$ketua = (string)($stuk['ketua'] ?? $s['ketua'] ?? '');
$usaha = [];
foreach (preg_split('/\r\n|\r|\n/', $p('kegiatan_usaha')) as $ln) {
    $ln = trim($ln);
    if ($ln !== '') {
        $usaha[] = $ln;
    }
}
$prestasi = [];
foreach (preg_split('/\r\n|\r|\n/', $p('prestasi')) as $ln) {
    $ln = trim($ln);
    if ($ln !== '') {
        $prestasi[] = $ln;
    }
}
$jmlAgt = 0;
try {
    $jmlAgt = (int)db()->query("SELECT COUNT(*) FROM anggota WHERE status IN ('aktif','pasif')")->fetchColumn();
} catch (Throwable $e) {
}
$ba = berita_acara($s);
$jmlPendiriBA = count(daftar_pendiri_ba($ba['pendiri_txt']));
?>
<?php if ($staff): ?>
<p style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;"><a class="btn btn-green btn-sm" href="pengaturan.php">Ubah semua di Pengaturan</a><a class="btn btn-ghost btn-sm" href="pengaturan.php?cetak_ba=1" target="_blank">Cetak BA / PDF</a></p>
<?php endif; ?>
<div class="card" style="margin-bottom:16px;">
  <h3>Informasi dasar &amp; identitas</h3>
  <div class="grid-2" style="margin-top:12px;">
    <p><strong>Nama resmi</strong><br><?= e($p('nama_koperasi')) ?></p>
    <p><strong>Jenis</strong><br><?= e($p('jenis_koperasi')) ?></p>
    <p><strong>Tanggal berdiri</strong><br><?= e(tgl($p('tanggal_berdiri'))) ?></p>
    <p><strong>Ketua</strong><br><?= e($ketua ?: '—') ?></p>
  </div>
  <?php if (!empty($s['logo_file'])): ?>
    <p style="margin-top:12px;"><strong>Logo koperasi</strong></p>
    <img src="logo.php" alt="Logo koperasi" style="max-width:180px;max-height:180px;border-radius:16px;border:1px solid #ece6d6;margin:8px 0;">
  <?php endif; ?>
  <?php if (!empty($s['logo_filosofi'])): ?>
    <p style="margin-top:8px;"><strong>Arti / filosofi logo</strong><br><?= nl2br(e($s['logo_filosofi'])) ?></p>
  <?php endif; ?>
  <p style="margin-top:12px;"><strong>Latar belakang</strong><br><?= nl2br(e($p('sejarah'))) ?></p>
</div>

<div class="card" id="ba" style="margin-bottom:16px;">
  <h3>Berita acara pendirian</h3>
  <div class="grid-2" style="margin-top:12px;">
    <p><strong>Nomor</strong><br><?= e($ba['nomor'] !== '' ? $ba['nomor'] : '—') ?></p>
    <p><strong>Tanggal rapat</strong><br><?= e(tgl_panjang($ba['tanggal'])) ?></p>
    <p><strong>Tempat</strong><br><?= e($ba['tempat'] !== '' ? $ba['tempat'] : '—') ?></p>
    <p><strong>Jumlah pendiri</strong><br><?= $jmlPendiriBA ?> orang</p>
  </div>
  <p style="margin-top:12px;"><a class="btn btn-green btn-sm" href="pengaturan.php?cetak_ba=1" target="_blank">Cetak / PDF</a></p>
</div>

<div class="cards" style="grid-template-columns:1fr 1fr;margin-bottom:16px;">
  <div class="card">
    <h3>Visi</h3>
    <p class="misi" style="margin-top:10px;"><?= e($p('visi')) ?></p>
  </div>
  <div class="card">
    <h3>Misi</h3>
    <p class="misi" style="margin-top:10px;"><?= e($p('misi')) ?></p>
  </div>
</div>
<div class="card" style="margin-bottom:16px;">
  <h3>Motto</h3>
  <p class="misi" style="margin-top:10px;"><?= e($p('motto')) ?></p>
  <p style="margin-top:12px;"><strong>Budaya / nilai</strong><br><?= e($p('nilai_koperasi')) ?></p>
</div>

<div class="card" style="margin-bottom:16px;">
  <h3>Kegiatan usaha koperasi</h3>
  <ol style="margin:12px 0 0 20px;">
    <?php foreach ($usaha as $i => $u): ?>
      <li style="margin-bottom:6px;"><?= e($u) ?></li>
    <?php endforeach; ?>
  </ol>
</div>

<div class="kpis">
  <div class="kpi"><span>Jumlah anggota sekarang</span><b><?= $jmlAgt ?></b></div>
  <div class="kpi"><span>Karyawan</span><b><?= nl2br(e($p('karyawan_ket'))) ?></b></div>
</div>

<div class="card" style="margin-bottom:16px;">
  <h3>Prestasi</h3>
  <ol style="margin:12px 0 0 20px;">
    <?php foreach ($prestasi as $u): ?>
      <li style="margin-bottom:6px;"><?= e($u) ?></li>
    <?php endforeach; ?>
  </ol>
</div>

<div class="card" style="margin-bottom:16px;">
  <h3>Legalitas &amp; badan hukum</h3>
  <div class="table-wrap" style="margin-top:10px;">
    <table>
      <tbody>
        <tr><th>No. badan hukum / SK</th><td><?= e($s['no_badan_hukum'] ?: '—') ?></td></tr>
        <tr><th>No. akta / PAD</th><td><?= e($p('no_akta')) ?></td></tr>
        <tr><th>Tanggal badan hukum</th><td><?= e(tgl($p('tgl_badan_hukum'))) ?></td></tr>
        <tr><th>Nomor Induk Koperasi (NIK)</th><td><?= e($s['nik_koperasi'] ?: '—') ?></td></tr>
        <tr><th>NIB</th><td><?= e($s['nib'] ?: '—') ?></td></tr>
        <tr><th>Bidang usaha (KBLI)</th><td><?= trim((string)($s['kbli'] ?? '')) !== '' ? nl2br(e($s['kbli'])) : nl2br(e(kbli_usaha_default())) ?></td></tr>
        <tr><th>Sertifikasi</th><td><?= trim((string)($s['sertifikasi'] ?? '')) !== '' ? nl2br(e($s['sertifikasi'])) : '—' ?></td></tr>
        <tr><th>NPWP badan</th><td><?= e($s['npwp'] ?: '—') ?></td></tr>
        <tr><th>Izin operasional (IUSP)</th><td><?= e($s['iusp'] ?: '—') ?></td></tr>
        <tr><th>IUP (jika ada)</th><td><?= e($s['iup'] ?: '—') ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <h3>Struktur organisasi</h3>
  <p style="font-size:14px;color:var(--muted);margin:8px 0 16px;">
    <?= nl2br(e($p('rapat_anggota'))) ?>
  </p>
  <?php
  $kotak = static function (array $u): string {
      $w = $u['warna'] ?? 'biru';
      $html = '<div class="bts-box bts-' . e($w) . '">';
      $html .= '<div class="bts-hd">' . e($u['nama']) . '</div>';
      if (($u['orang'] ?? '') !== '') {
          $html .= '<div class="bts-nm">' . e($u['orang']) . '</div>';
      }
      foreach ($u['sub'] ?? [] as $srow) {
          if (($srow['nama'] ?? '') === '' && ($srow['orang'] ?? '') === '') {
              continue;
          }
          $html .= '<div class="bts-sub">' . e($srow['nama']) . '</div>';
          if (($srow['orang'] ?? '') !== '') {
              $html .= '<div class="bts-nm">' . e($srow['orang']) . '</div>';
          }
      }
      $html .= '</div>';
      return $html;
  };
  ?>
  <div class="bts-chart">
    <div class="bts-title">
      STRUKTUR ORGANISASI<br>
      <?= e(strtoupper($s['nama_koperasi'] ?? 'Koperasi Produsen')) ?>
      <?php if (!empty($stuk['masa_jabatan'])): ?><br>MASA JABATAN <?= e(strtoupper($stuk['masa_jabatan'])) ?><?php endif; ?>
    </div>
    <div class="bts-row"><div class="bts-box bts-abu"><div class="bts-hd">R A T</div></div></div>
    <div class="bts-v"></div>
    <div class="bts-row bts-3">
      <div class="bts-box">
        <div class="bts-hd bts-abu">Pembina &amp; Penasehat</div>
        <ol class="bts-ol">
          <?php foreach ($stuk['pembina'] as $i => $n): ?><li><?= e($n) ?></li><?php endforeach; ?>
        </ol>
      </div>
      <div class="bts-box">
        <div class="bts-hd bts-abu">Pengurus</div>
        <div class="bts-sub">Ketua</div><div class="bts-nm"><?= e($stuk['ketua'] ?: '—') ?></div>
        <?php if (!empty($stuk['wakil_ketua'])): ?><div class="bts-sub">Wk. Ketua</div><div class="bts-nm"><?= e($stuk['wakil_ketua']) ?></div><?php endif; ?>
        <div class="bts-sub">Sekretaris</div><div class="bts-nm"><?= e($stuk['sekretaris'] ?: '—') ?></div>
        <?php if (!empty($stuk['wakil_sekretaris'])): ?><div class="bts-sub">Wk. Sekretaris</div><div class="bts-nm"><?= e($stuk['wakil_sekretaris']) ?></div><?php endif; ?>
        <div class="bts-sub">Bendahara</div><div class="bts-nm"><?= e($stuk['bendahara'] ?: '—') ?></div>
      </div>
      <div class="bts-box">
        <div class="bts-hd bts-abu">Badan Pengawas</div>
        <div class="bts-sub">Ketua</div><div class="bts-nm"><?= e($stuk['pengawas_ketua'] ?: '—') ?></div>
        <?php foreach ($stuk['pengawas_anggota'] as $n): ?>
          <div class="bts-sub">Anggota</div><div class="bts-nm"><?= e($n) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bts-v"></div>
    <?php
    $opsBox = [];
    foreach ([['Manajer', $stuk['manajer'] ?? ''], ['KTU', $stuk['ktu'] ?? ''], ['Kasir', $stuk['kasir'] ?? '']] as $ob) {
        if (trim((string)$ob[1]) !== '') { $opsBox[] = '<div class="bts-box bts-ungu"><div class="bts-hd">' . e($ob[0]) . '</div><div class="bts-nm">' . e($ob[1]) . '</div></div>'; }
    }
    ?>
    <?php if ($opsBox): ?>
    <div class="bts-row"><?= implode('', $opsBox) ?></div>
    <div class="bts-v"></div>
    <?php endif; ?>
    <div class="bts-split">
      <div class="bts-col">
        <div class="bts-row"><?php foreach ($stuk['unit_kiri'] as $u) echo $kotak($u); ?></div>
        <?php if (!empty($stuk['unit_bawah'])): ?>
          <div class="bts-v"></div>
          <div class="bts-row"><?php foreach ($stuk['unit_bawah'] as $u) echo $kotak($u); ?></div>
        <?php endif; ?>
      </div>
      <div class="bts-col">
        <div class="bts-row"><?php foreach ($stuk['unit_kanan'] as $u) echo $kotak($u); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Jangkauan &amp; kontak</h3>
  <div class="grid-2" style="margin-top:12px;">
    <p><strong>Alamat kantor pusat</strong><br><?= e($s['alamat'] ?: '—') ?></p>
    <p><strong>Telepon</strong><br><?= e($s['telepon'] ?: '—') ?></p>
    <p><strong>WhatsApp</strong><br><?= e($s['whatsapp'] ?: '—') ?></p>
    <p><strong>Email</strong><br><?= e($s['email'] ?: '—') ?></p>
  </div>
  <p style="margin-top:10px;"><strong>Kantor cabang</strong><br><?= !empty($s['cabang']) ? nl2br(e($s['cabang'])) : 'Tidak ada / belum diisi.' ?></p>
  <p style="margin-top:10px;"><strong>Media sosial resmi</strong><br><?= !empty($s['sosmed']) ? nl2br(e($s['sosmed'])) : '—' ?></p>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
