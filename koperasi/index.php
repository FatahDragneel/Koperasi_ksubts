<?php
require __DIR__ . '/config.php';
$s = setting();
$title = $s['nama_koperasi'] . ' · Koperasi Produsen Ramah Lingkungan';
$pdo = db();
$nAnggota = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status='aktif'")->fetchColumn();
$nSimpan = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM simpanan")->fetchColumn();
$nilaiPupuk = 0;
try {
    foreach (daftar_pupuk_stok() as $p) {
        $nilaiPupuk += (float)$p['stok'] * (float)$p['harga_jual'];
    }
} catch (Throwable $e) {
}
$kbli = [];
foreach (preg_split('/\r\n|\r|\n/', trim((string)($s['kbli'] ?? '')) !== '' ? (string)$s['kbli'] : kbli_usaha_default()) as $ln) {
    $ln = trim($ln);
    if ($ln !== '') {
        $kbli[] = $ln;
    }
}
$berita = $pdo->query("SELECT * FROM pengumuman WHERE publik=1 ORDER BY tanggal DESC LIMIT 3")->fetchAll();
include __DIR__ . '/includes/public_header.php';
?>
<?php if ($m = flash('ok')): ?>
  <div class="container" style="padding-top:16px;"><div class="alert alert-ok"><?= e($m) ?></div></div>
<?php endif; ?>
<header class="hero" id="beranda">
  <div class="container hero-grid">
    <div>
      <div class="kicker">Didirikan <?= !empty($s['tanggal_berdiri']) ? tgl($s['tanggal_berdiri']) : e($s['tahun_berdiri']) ?> · Sumatera Barat</div>
      <h1>Dari alam,<br>untuk kesejahteraan bersama.</h1>
      <p class="lead"><?= e($s['nama_koperasi']) ?> adalah koperasi produsen ramah lingkungan: memproduksi pupuk organik dari bahan baku lokal, memasarkan pupuk dan hasil pertanian anggota secara adil dan transparan.</p>
      <div class="hero-actions">
        <a class="btn btn-gold" href="login-anggota.php">Masuk Anggota</a>
        <a class="btn btn-outline" href="login-admin.php">Login Admin</a>
      </div>
      <div class="hero-stats">
        <div class="stat"><b><?= $nAnggota ?>+</b><span>Anggota aktif</span></div>
        <div class="stat"><b><?= rupiah($nilaiPupuk) ?></b><span>Persediaan pupuk</span></div>
        <div class="stat"><b><?= rupiah($nSimpan) ?></b><span>Total simpanan</span></div>
      </div>
    </div>
    <div class="hero-card">
      <h3>Kenapa bergabung?</h3>
      <ul class="list-check">
        <li><span class="dot">1</span> Pupuk organik produksi sendiri dengan harga khusus anggota.</li>
        <li><span class="dot">2</span> Hasil panen (termasuk TBS) ditampung dan diniagakan bersama.</li>
        <li><span class="dot">3</span> Simpanan pokok, wajib, dan sukarela yang dicatat transparan.</li>
        <li><span class="dot">4</span> Pinjaman produktif untuk sarana dan usaha tani anggota.</li>
        <li><span class="dot">5</span> Pendampingan budidaya ramah lingkungan &amp; ISPO.</li>
      </ul>
    </div>
  </div>
</header>

<section id="tentang">
  <div class="container about-grid">
    <div class="about-panel">
      <h3>Visi</h3>
      <p><?= e($s['visi']) ?></p>
      <h3 style="margin-top:24px;">Misi</h3>
      <p class="misi"><?= e($s['misi']) ?></p>
    </div>
    <div>
      <div class="sec-title" style="text-align:left;margin-bottom:18px;">
        <span>Tentang Kami</span>
        <h2>Koperasi produsen, untuk petani.</h2>
      </div>
      <p style="color:var(--muted);margin-bottom:18px;">
        <?= e($s['nama_koperasi']) ?> memproduksi pupuk organik dari limbah pertanian, kotoran ternak, dan bahan lokal lainnya,
        menekan pemakaian pupuk kimia, memperbaiki kesuburan tanah, dan meningkatkan nilai tambah panen anggota.
      </p>
      <h3 style="margin-bottom:10px;">Bidang usaha (KBLI)</h3>
      <div class="timeline">
        <?php foreach ($kbli as $k): ?>
        <div class="tl"><div class="year">✓</div><div><?= e($k) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($s['ketua'])): ?>
      <p style="margin-top:18px;font-size:14px;"><strong>Ketua:</strong> <?= e($s['ketua']) ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="layanan" style="background:#fff;border-block:1px solid #ece6d6;">
  <div class="container">
    <div class="sec-title">
      <span>Unit Usaha</span>
      <h2>Produksi · Niaga · Sejahtera</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">🌱</div>
        <h3>Produksi Pupuk Organik</h3>
        <p>Pupuk granul, cair, dan kompos dari bahan baku lokal. Mutu dikontrol tiap batch produksi.</p>
      </div>
      <div class="card">
        <div class="icon">⇄</div>
        <h3>Perdagangan Pupuk</h3>
        <p>Penjualan grosir, kemitraan, dan eceran melalui toko tani. Anggota mendapat harga khusus.</p>
      </div>
      <div class="card">
        <div class="icon">🌴</div>
        <h3>Niaga Hasil Pertanian</h3>
        <p>Penampungan dan pemasaran hasil pertanian tanaman minyak (cth. TBS) milik anggota.</p>
      </div>
      <div class="card">
        <div class="icon">⛁</div>
        <h3>Simpan Pinjam Anggota</h3>
        <p>Simpanan pokok, wajib, dan sukarela plus pinjaman produktif untuk usaha tani anggota.</p>
      </div>
      <div class="card">
        <div class="icon">✦</div>
        <h3>ISPO &amp; Pendampingan</h3>
        <p>Pengawalan sertifikasi, STDB, dan praktik budidaya ramah lingkungan di kebun anggota.</p>
      </div>
      <div class="card">
        <div class="icon">▣</div>
        <h3>Laporan Transparan</h3>
        <p>Pembukuan terkomputerisasi. RAT membahas SHU dan rencana kerja tahunan.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <div class="sec-title">
      <span>Syarat Keanggotaan</span>
      <h2>Bergabung itu mudah</h2>
    </div>
    <div class="cards">
      <div class="card"><h3>1. Petani &amp; pelaku usaha tani</h3><p>Terbuka bagi petani, peternak, dan pelaku usaha tani yang mendukung pertanian ramah lingkungan.</p></div>
      <div class="card"><h3>2. Simpanan Pokok</h3><p>Setoran simpanan pokok <?= rupiah($s['simpanan_pokok'] ?? 500000) ?> sekali seumur keanggotaan.</p></div>
      <div class="card"><h3>3. Simpanan Wajib</h3><p><?= rupiah($s['simpanan_wajib'] ?? 50000) ?> per bulan. Disiplin menabung menjadi syarat layanan usaha.</p></div>
    </div>
  </div>
</section>

<section id="berita" style="background:#fff;border-block:1px solid #ece6d6;">
  <div class="container">
    <div class="sec-title">
      <span>Pengumuman</span>
      <h2>Berita koperasi</h2>
    </div>
    <div class="cards">
      <?php foreach ($berita as $b): ?>
      <div class="card">
        <div style="font-size:12px;color:var(--green);font-weight:700;"><?= tgl($b['tanggal']) ?></div>
        <h3 style="margin:8px 0;"><?= e($b['judul']) ?></h3>
        <p><?= e($b['isi']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="kontak">
  <div class="container contact-grid">
    <div class="card">
      <div class="sec-title" style="text-align:left;margin-bottom:12px;">
        <span>Kontak</span>
        <h2>Kantor koperasi</h2>
      </div>
      <p><strong>Alamat</strong><br><?= e($s['alamat']) ?></p>
      <p style="margin-top:10px;"><strong>Telepon</strong> <?= e($s['telepon'] ?: '—') ?><br><strong>Email</strong> <?= e($s['email'] ?: '—') ?></p>
      <p style="margin-top:10px;"><strong>Jam layanan</strong><br>Senin–Jumat 08.00–16.00 WIB<br>Sabtu 08.00–12.00 WIB</p>
    </div>
    <div class="about-panel">
      <h3>Siap menjadi anggota?</h3>
      <p>Datang ke kantor dengan fotokopi KTP — pengurus mendaftarkan dan mengaktifkan akun Anda.</p>
      <p style="margin:16px 0 24px;opacity:.9;">Lihat lokasi dan kontak kami di bawah.</p>
      <a class="btn btn-gold" href="index.php#kontak">Hubungi kami</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
