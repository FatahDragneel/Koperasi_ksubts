<?php
require __DIR__ . '/config.php';
$s = setting();
$title = $s['nama_koperasi'] . ' · Koperasi Simpan Pinjam';
$pdo = db();
$nAnggota = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status='aktif'")->fetchColumn();
$nSimpan = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM simpanan")->fetchColumn();
$nPinjam = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM pinjaman WHERE status IN ('berjalan','disetujui','lunas')")->fetchColumn();
$berita = $pdo->query("SELECT * FROM pengumuman WHERE publik=1 ORDER BY tanggal DESC LIMIT 3")->fetchAll();
include __DIR__ . '/includes/public_header.php';
?>
<?php if ($m = flash('ok')): ?>
  <div class="container" style="padding-top:16px;"><div class="alert alert-ok"><?= e($m) ?></div></div>
<?php endif; ?>
<header class="hero" id="beranda">
  <div class="container hero-grid">
    <div>
      <div class="kicker">Didirikan <?= !empty($s['tanggal_berdiri']) ? tgl($s['tanggal_berdiri']) : e($s['tahun_berdiri']) ?> · Padang, Sumatera Barat</div>
      <h1>Tumbuh bersama,<br>sejahtera bersama.</h1>
      <p class="lead">Koperasi Serba Usaha Bina Tani Sejahtera mendampingi petani dan pelaku usaha tani melalui simpanan, pinjaman produktif, dan solidaritas ekonomi desa.</p>
      <div class="hero-actions">
        <a class="btn btn-gold" href="login-anggota.php">Masuk Anggota</a>
        <a class="btn btn-outline" href="login-admin.php">Login Admin</a>
      </div>
      <div class="hero-stats">
        <div class="stat"><b><?= $nAnggota ?>+</b><span>Anggota aktif</span></div>
        <div class="stat"><b><?= rupiah($nSimpan) ?></b><span>Total simpanan</span></div>
        <div class="stat"><b><?= rupiah($nPinjam) ?></b><span>Pinjaman tersalur</span></div>
      </div>
    </div>
    <div class="hero-card">
      <h3>Kenapa bergabung?</h3>
      <ul class="list-check">
        <li><span class="dot">1</span> Simpanan pokok, wajib, dan sukarela yang dicatat transparan.</li>
        <li><span class="dot">2</span> Pinjaman produktif untuk pupuk, bibit, alat, dan usaha tani.</li>
        <li><span class="dot">3</span> Bagi hasil <?= e($s['bagi_hasil_persen'] ?? 1) ?>% per bulan, tenor fleksibel, proses kekeluargaan.</li>
        <li><span class="dot">4</span> SHU dibagikan sesuai partisipasi anggota.</li>
        <li><span class="dot">5</span> Pendampingan usaha dan informasi pasar hasil tani.</li>
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
        <h2>Koperasi petani, untuk petani.</h2>
      </div>
      <p style="color:var(--muted);margin-bottom:18px;">
        KSU Bina Tani Sejahtera berdiri tahun <?= e($s['tahun_berdiri']) ?> di Padang sebagai wadah ekonomi kerakyatan.
        Kami mengelola simpan pinjam secara profesional agar anggota memperoleh permodalan yang adil tanpa terjerat rentenir.
      </p>
      <div class="timeline">
        <div class="tl"><div class="year">2012</div><div>Pendirian koperasi oleh kelompok tani se-Kuranji dan sekitarnya.</div></div>
        <div class="tl"><div class="year">2016</div><div>Unit simpan pinjam resmi beroperasi dengan pengawasan pengurus dan pengawas.</div></div>
        <div class="tl"><div class="year">2020</div><div>Digitalisasi pencatatan simpanan, pinjaman, dan angsuran anggota.</div></div>
        <div class="tl"><div class="year">2026</div><div>Portal anggota daring: cek saldo, ajukan pinjaman, dan pantau angsuran.</div></div>
      </div>
      <p style="margin-top:18px;font-size:14px;"><strong>Ketua:</strong> <?= e($s['ketua']) ?></p>
    </div>
  </div>
</section>

<section id="layanan" style="background:#fff;border-block:1px solid #ece6d6;">
  <div class="container">
    <div class="sec-title">
      <span>Layanan</span>
      <h2>Simpan · Pinjam · Sejahtera</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">⛁</div>
        <h3>Simpanan Anggota</h3>
        <p>Simpanan pokok, wajib, sukarela, dan hari raya. Setiap setoran tercatat dan dapat dicek di portal.</p>
      </div>
      <div class="card">
        <div class="icon">⇄</div>
        <h3>Pinjaman Produktif</h3>
        <p>Pembiayaan usaha tani dengan bagi hasil <?= e($s['bagi_hasil_persen'] ?? 1) ?>% per bulan, tenor 3–12 bulan, proses pengajuan di pengurus.</p>
      </div>
      <div class="card">
        <div class="icon">↻</div>
        <h3>Angsuran Mudah</h3>
        <p>Bayar di kantor koperasi. Sisa pinjaman dan riwayat angsuran tampil real-time di akun anggota.</p>
      </div>
      <div class="card">
        <div class="icon">▣</div>
        <h3>Laporan Transparan</h3>
        <p>Pengurus menyusun laporan simpan pinjam. RAT membahas SHU dan rencana kerja tahunan.</p>
      </div>
      <div class="card">
        <div class="icon">☺</div>
        <h3>Keanggotaan</h3>
        <p>Terbuka bagi petani, peternak, dan pelaku usaha tani di Sumatera Barat yang memenuhi syarat.</p>
      </div>
      <div class="card">
        <div class="icon">✦</div>
        <h3>Pendampingan</h3>
        <p>Informasi pupuk, jadwal tanam, dan akses pasar. Koperasi lebih dari sekadar uang.</p>
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
      <div class="card"><h3>1. Plasma &amp; lahan</h3><p>Wajib anggota kelompok tani plasma dan mempunyai tanah. Lapor status STDB (sudah/belum).</p></div>
      <div class="card"><h3>2. Simpanan Pokok</h3><p>Setoran simpanan pokok <?= rupiah($s['simpanan_pokok'] ?? 500000) ?> sekali seumur keanggotaan.</p></div>
      <div class="card"><h3>3. Simpanan Wajib</h3><p><?= rupiah($s['simpanan_wajib'] ?? 50000) ?> per bulan. Disiplin menabung menjadi syarat pinjaman.</p></div>
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
      <p style="margin-top:10px;"><strong>Telepon</strong> <?= e($s['telepon']) ?><br><strong>Email</strong> <?= e($s['email']) ?></p>
      <p style="margin-top:10px;"><strong>Jam layanan</strong><br>Senin–Jumat 08.00–16.00 WIB<br>Sabtu 08.00–12.00 WIB</p>
    </div>
    <div class="about-panel">
      <h3>Siap menjadi anggota?</h3>
      <p>Daftar daring jika Anda petani plasma dan punya lahan, atau datang ke kantor dengan fotokopi KTP.</p>
      <p style="margin:16px 0 24px;opacity:.9;">Pengurus memverifikasi pendaftaran sebelum akun diaktifkan.</p>
      <a class="btn btn-gold" href="registrasi.php">Daftar anggota</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
