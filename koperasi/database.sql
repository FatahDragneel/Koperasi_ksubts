-- Koperasi Produsen Ramah Lingkungan Pasaman Barat
-- CARA PAKAI (pilih SALAH SATU):
--   A. Instalasi baru + data contoh: impor file ini ke database KOSONG
--      (phpMyAdmin -> buat DB -> tab Impor -> pilih file ini -> Go).
--   B. Database sudah ada / sudah dibuka setup.php: JANGAN impor file ini,
--      cukup buka setup.php (menambah tabel/kolom yang kurang tanpa hapus data).
-- ERROR #1062 Duplicate entry? Artinya DB sudah terisi (habis setup.php atau
-- impor 2x). Solusi: JANGAN impor ulang (data sudah ada), ATAU kosongkan dulu:
-- phpMyAdmin -> klik database -> centang semua tabel -> Hapus/Drop -> impor ulang.
CREATE DATABASE IF NOT EXISTS koperasi_bina_tani CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE koperasi_bina_tani;

CREATE TABLE IF NOT EXISTS pengaturan (
  id INT PRIMARY KEY DEFAULT 1,
  nama_koperasi VARCHAR(150) DEFAULT 'Koperasi Produsen Ramah Lingkungan Pasaman Barat',
  alamat TEXT,
  telepon VARCHAR(30),
  email VARCHAR(80),
  tahun_berdiri YEAR,
  tanggal_berdiri DATE NULL,
  ketua VARCHAR(100),
  visi TEXT,
  misi TEXT,
  bagi_hasil_persen DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  simpanan_pokok DECIMAL(15,2) NOT NULL DEFAULT 500000,
  simpanan_wajib DECIMAL(15,2) NOT NULL DEFAULT 50000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kelompok (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor INT NOT NULL UNIQUE,
  kode_kelompok VARCHAR(20) NULL,
  nama_kelompok VARCHAR(120) NULL,
  nama_ketua VARCHAR(100) NULL,
  no_hp_ketua VARCHAR(30) NULL,
  wilayah_dusun VARCHAR(120) NULL,
  blok_hamparan VARCHAR(120) NULL,
  tanggal_terbentuk DATE NULL,
  luas_tanah DECIMAL(12,2) NULL DEFAULT 0,
  lokasi VARCHAR(255) NULL,
  desa VARCHAR(80) NULL,
  kecamatan VARCHAR(80) NULL,
  fee_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anggota (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_anggota VARCHAR(20) UNIQUE NOT NULL,
  nik VARCHAR(20),
  nama VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') DEFAULT 'L',
  tempat_lahir VARCHAR(80),
  tanggal_lahir DATE,
  alamat TEXT,
  desa VARCHAR(80),
  kecamatan VARCHAR(80),
  no_hp VARCHAR(20),
  pekerjaan VARCHAR(80),
  kelompok_tani VARCHAR(120) NULL,
  id_kelompok INT NULL,
  jabatan_kelompok VARCHAR(50) NULL DEFAULT 'Anggota',
  plasma TINYINT(1) NOT NULL DEFAULT 0,
  punya_tanah TINYINT(1) NOT NULL DEFAULT 0,
  luas_tanah DECIMAL(10,2) NULL,
  stdb VARCHAR(10) NOT NULL DEFAULT 'belum',
  no_stdb VARCHAR(50) NULL,
  foto VARCHAR(120) NULL,
  ktp_file VARCHAR(120) NULL,
  sertifikat_file VARCHAR(120) NULL,
  catatan_verifikasi TEXT NULL,
  status_keanggotaan VARCHAR(20) DEFAULT 'Biasa',
  tanggal_daftar DATE,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  username VARCHAR(50) NULL,
  password VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  role ENUM('admin','pengurus','anggota') NOT NULL DEFAULT 'anggota',
  anggota_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jenis_simpanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(10) UNIQUE NOT NULL,
  nama VARCHAR(80) NOT NULL,
  keterangan TEXT,
  wajib TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simpanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anggota_id INT NOT NULL,
  jenis_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  keterangan VARCHAR(255),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simpanan_sukarela (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anggota_id INT NOT NULL,
  jenis_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengajuan_pinjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_pengajuan VARCHAR(30) UNIQUE NOT NULL,
  anggota_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  bagi_hasil_persen DECIMAL(5,2) DEFAULT 1.00,
  tenor INT NOT NULL,
  keperluan VARCHAR(255),
  agunan VARCHAR(120),
  status VARCHAR(20) DEFAULT 'pengajuan',
  catatan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pinjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_pinjaman VARCHAR(20) UNIQUE NOT NULL,
  anggota_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  bagi_hasil_persen DECIMAL(5,2) DEFAULT 1.00,
  tenor INT NOT NULL,
  keperluan VARCHAR(255),
  status VARCHAR(20) DEFAULT 'pengajuan',
  total_tagihan DECIMAL(15,2) DEFAULT 0,
  sisa DECIMAL(15,2) DEFAULT 0,
  catatan TEXT,
  agunan VARCHAR(120) NULL,
  no_perjanjian VARCHAR(40) NULL,
  pengajuan_id INT NULL,
  pencairan_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pencairan_pinjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pengajuan_id INT NOT NULL,
  no_pinjaman VARCHAR(30) UNIQUE NOT NULL,
  no_perjanjian VARCHAR(40),
  tanggal_cair DATE NOT NULL,
  jumlah_cair DECIMAL(15,2) NOT NULL,
  total_tagihan DECIMAL(15,2) NOT NULL,
  sisa DECIMAL(15,2) NOT NULL,
  status VARCHAR(20) DEFAULT 'berjalan',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS angsuran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pinjaman_id INT NOT NULL,
  pencairan_id INT NULL,
  angsuran_ke INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  pokok DECIMAL(15,2) NOT NULL DEFAULT 0,
  bagi_hasil DECIMAL(15,2) NOT NULL DEFAULT 0,
  denda DECIMAL(15,2) NOT NULL DEFAULT 0,
  sumber_bayar VARCHAR(40) DEFAULT 'tunai',
  keterangan VARCHAR(255),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pengumuman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  isi TEXT NOT NULL,
  tanggal DATE NOT NULL,
  publik TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  arah ENUM('masuk','keluar') NOT NULL,
  kategori VARCHAR(80) NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lahan_sawit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anggota_id INT NOT NULL,
  id_kelompok INT NULL,
  legalitas VARCHAR(20) DEFAULT 'SKT',
  luas_hektar DECIMAL(10,2) DEFAULT 0,
  jumlah_pokok INT DEFAULT 0,
  tahun_tanam YEAR NULL,
  lokasi_desa VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS harga_tbs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal_berlaku DATE NOT NULL,
  kategori VARCHAR(20) NOT NULL,
  harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
  harga_jual_pks DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_harga (tanggal_berlaku, kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timbangan_tbs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  anggota_id INT NULL,
  lahan_id INT NULL,
  id_kelompok INT NULL,
  surat_jalan_id INT NULL,
  no_polisi VARCHAR(20) NULL,
  berat_masuk DECIMAL(12,2) NOT NULL DEFAULT 0,
  berat_keluar DECIMAL(12,2) NOT NULL DEFAULT 0,
  berat_bruto DECIMAL(12,2) NOT NULL DEFAULT 0,
  persen_potongan DECIMAL(5,2) NOT NULL DEFAULT 0,
  berat_netto DECIMAL(12,2) NOT NULL DEFAULT 0,
  kategori_umur VARCHAR(20) NULL,
  harga_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_bruto_uang DECIMAL(15,2) NOT NULL DEFAULT 0,
  fee_kelompok_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  fee_kelompok DECIMAL(15,2) NOT NULL DEFAULT 0,
  potong_angsuran DECIMAL(15,2) NOT NULL DEFAULT 0,
  potong_saprodi DECIMAL(15,2) NOT NULL DEFAULT 0,
  bersih_petani DECIMAL(15,2) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_jalan_pks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_sj VARCHAR(30) NOT NULL UNIQUE,
  tanggal DATE NOT NULL,
  no_plat VARCHAR(20) NOT NULL,
  nama_sopir VARCHAR(80) NOT NULL,
  pabrik_tujuan VARCHAR(120) NOT NULL,
  estimasi_tonase DECIMAL(12,2) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'berangkat',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_pks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  surat_jalan_id INT NOT NULL,
  no_nota_pks VARCHAR(50) NOT NULL,
  berat_netto_pks DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_transfer DECIMAL(15,2) NOT NULL DEFAULT 0,
  susut_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  tanggal_cair DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saprodi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  anggota_id INT NOT NULL,
  item_barang VARCHAR(120) NOT NULL,
  kuantitas DECIMAL(12,2) NOT NULL DEFAULT 1,
  harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_harga DECIMAL(15,2) NOT NULL DEFAULT 0,
  status_bayar VARCHAR(30) DEFAULT 'piutang',
  sisa_piutang DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shu_alokasi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tahun_buku INT NOT NULL,
  anggota_id INT NOT NULL,
  jasa_modal DECIMAL(15,2) NOT NULL DEFAULT 0,
  jasa_usaha DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_shu DECIMAL(15,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_shu (tahun_buku, anggota_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pupuk_produk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  jenis VARCHAR(60) NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT 'kg',
  harga_jual DECIMAL(15,2) NOT NULL DEFAULT 0,
  keterangan TEXT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pupuk_mutasi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  produk_id INT NOT NULL,
  arah ENUM('masuk','keluar') NOT NULL,
  jumlah DECIMAL(14,2) NOT NULL DEFAULT 0,
  harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
  pihak VARCHAR(120) NULL,
  anggota_id INT NULL,
  cara_bayar VARCHAR(20) NOT NULL DEFAULT 'tunai',
  keterangan VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coa_akun (
  kode VARCHAR(10) PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  kategori VARCHAR(30) NOT NULL,
  saldo_normal ENUM('debit','kredit') NOT NULL DEFAULT 'debit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jurnal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_bukti VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  keterangan VARCHAR(255) NOT NULL,
  sumber VARCHAR(40) NULL,
  sumber_id INT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jurnal_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jurnal_id INT NOT NULL,
  kode_akun VARCHAR(10) NOT NULL,
  posisi ENUM('debit','kredit') NOT NULL,
  nominal DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bukti_bayar_pinjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pinjaman_id INT NOT NULL,
  anggota_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
  file_bukti VARCHAR(160) NOT NULL,
  keterangan VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'menunggu',
  catatan_admin TEXT NULL,
  angsuran_id INT NULL,
  verified_by INT NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anggota_kelompok (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anggota_id INT NOT NULL,
  id_kelompok INT NOT NULL,
  jabatan VARCHAR(50) NULL DEFAULT 'Anggota',
  UNIQUE KEY uq_ag_kel (anggota_id, id_kelompok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS antrean_truk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  id_kelompok INT NOT NULL,
  slot TINYINT NOT NULL DEFAULT 1,
  no_plat VARCHAR(20) NULL,
  nama_sopir VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'terjadwal',
  keterangan VARCHAR(255) NULL,
  created_by INT NULL,
  UNIQUE KEY uq_antrean (tanggal, id_kelompok, slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pengaturan (id, nama_koperasi, alamat, telepon, email, tahun_berdiri, tanggal_berdiri, ketua, visi, misi, bagi_hasil_persen, simpanan_pokok, simpanan_wajib) VALUES
(1, 'Koperasi Produsen Ramah Lingkungan Pasaman Barat',
 'Simpang Empat, Kabupaten Pasaman Barat, Provinsi Sumatera Barat – Indonesia 26567',
 '', 'taniramahlingkungan.official@gmail.com', 2026, '2026-09-09', 'Indra Gunawan',
 'Mewujudkan kesejahteraan anggota melalui koperasi produsen yang ramah lingkungan, mandiri, dan berkelanjutan.',
 '1. Memperkuat kelembagaan dan pemberdayaan anggota.\n2. Mengembangkan usaha produksi pupuk organik serta perdagangan pupuk dan hasil pertanian.\n3. Meningkatkan kapasitas SDM melalui pelatihan dan pendampingan.\n4. Mengembangkan ekonomi kerakyatan yang adil dan berkelanjutan.',
 1.00, 150000, 10000);

INSERT INTO jenis_simpanan (kode, nama, keterangan, wajib) VALUES
('SPK', 'Simpanan Pokok', 'Dibayar sekali saat menjadi anggota', 1),
('SWJ', 'Simpanan Wajib', 'Dibayar setiap bulan oleh anggota aktif', 1),
('SSK', 'Simpanan Sukarela', 'Simpanan bebas sesuai kemampuan anggota', 0),
('SHR', 'Simpanan Hari Raya', 'Tabungan khusus menjelang hari raya', 0);

INSERT INTO kelompok (nomor, kode_kelompok, nama_kelompok, luas_tanah, fee_per_kg) VALUES
(1,'KT-01','Kelompok Tani 1',0,0),(2,'KT-02','Kelompok Tani 2',0,0),(3,'KT-03','Kelompok Tani 3',0,0),
(4,'KT-04','Kelompok Tani 4',0,0),(5,'KT-05','Kelompok Tani 5',0,0),(6,'KT-06','Kelompok Tani 6',0,0),
(7,'KT-07','Kelompok Tani 7',0,0),(8,'KT-08','Kelompok Tani 8',0,0),(9,'KT-09','Kelompok Tani 9',0,0),
(10,'KT-10','Kelompok Tani 10',0,0),(11,'KT-11','Kelompok Tani 11',0,0),(12,'KT-12','Kelompok Tani 12',0,0),
(13,'KT-13','Kelompok Tani 13',0,0),(14,'KT-14','Kelompok Tani 14',0,0),(15,'KT-15','Kelompok Tani 15',0,0),
(16,'KT-16','Kelompok Tani 16',0,0),(17,'KT-17','Kelompok Tani 17',0,0),(18,'KT-18','Kelompok Tani 18',0,0),
(19,'KT-19','Kelompok Tani 19',0,0),(20,'KT-20','Kelompok Tani 20',0,0),(21,'KT-21','Kelompok Tani 21',0,0);

INSERT INTO pupuk_produk (kode, nama, jenis, satuan, harga_jual, keterangan) VALUES
('PO-G001', 'Pupuk Organik Granul', 'granul', 'kg', 3500, 'Untuk sawit & pangan, kemasan 25/50 kg'),
('PO-C001', 'Pupuk Organik Cair', 'cair', 'liter', 25000, 'Untuk semprot daun & kocor'),
('PO-K001', 'Kompos Curah', 'kompos', 'kg', 1500, 'Pembenah tanah, curah');

INSERT INTO coa_akun (kode, nama, kategori, saldo_normal) VALUES
('1111','Kas tunai','Aset','debit'),
('1112','Bank','Aset','debit'),
('1211','Piutang pinjaman anggota','Aset','debit'),
('1212','Piutang saprodi','Aset','debit'),
('1311','Persediaan / TBS','Aset','debit'),
('1312','Persediaan pupuk organik','Aset','debit'),
('2111','Simpanan pokok','Kewajiban','kredit'),
('2112','Simpanan wajib','Kewajiban','kredit'),
('2113','Simpanan sukarela','Kewajiban','kredit'),
('2211','Utang kas kelompok','Kewajiban','kredit'),
('3111','Modal / ekuitas','Ekuitas','kredit'),
('4111','Pendapatan bagi hasil pinjaman','Pendapatan','kredit'),
('4112','Pendapatan lain','Pendapatan','kredit'),
('4113','Pendapatan margin TBS','Pendapatan','kredit'),
('4114','Pendapatan pupuk organik','Pendapatan','kredit'),
('5111','Biaya operasional','Biaya','debit'),
('5112','Pembelian TBS petani','Biaya','debit'),
('5113','Biaya produksi pupuk','Biaya','debit');

-- sandi: admin123 / anggota123
INSERT INTO users (username, password, nama, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator Koperasi', 'admin');

INSERT INTO anggota (no_anggota, nik, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, desa, kecamatan, no_hp, pekerjaan, kelompok_tani, id_kelompok, plasma, tanggal_daftar, status, username, password) VALUES
('AGT-0001', '1371010101800001', 'Budi Santoso', 'L', 'Padang', '1980-01-15', 'Jl. Sawahan No. 12', 'Gunung Pangilun', 'Padang Utara', '081234567890', 'Petani Padi', '1', 1, 1, '2015-03-10', 'aktif', 'budi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('AGT-0002', '1371010202850002', 'Siti Aminah', 'P', 'Solok', '1985-02-20', 'Jl. Andalas No. 8', 'Andalas', 'Padang Timur', '081298765432', 'Petani Sayur', '2', 2, 1, '2016-07-21', 'aktif', 'siti', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('AGT-0003', '1371010303780003', 'Hasan Basri', 'L', 'Pariaman', '1978-03-08', 'Kampung Dalam', 'Lubuk Begalung', 'Lubuk Begalung', '082112223333', 'Peternak', '3', 3, 1, '2014-01-05', 'aktif', 'hasan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('AGT-0004', '1371010404900004', 'Rina Marlina', 'P', 'Padang', '1990-04-12', 'Jl. Bypass No. 90', 'Kuranji', 'Kuranji', '085266778899', 'Pedagang Hasil Tani', '1', 1, 1, '2018-11-02', 'aktif', 'rina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('AGT-0005', '1371010505750005', 'Joni Iskandar', 'L', 'Bukittinggi', '1975-05-30', 'Jl. Raya Tabing', 'Batipuh Panjang', 'Koto Tangah', '081355667788', 'Petani Palawija', '4', 4, 1, '2013-09-18', 'aktif', 'joni', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO simpanan (anggota_id, jenis_id, tanggal, jumlah, keterangan, created_by) VALUES
(1, 1, '2015-03-10', 500000, 'Simpanan pokok awal', 1),
(1, 2, '2026-07-01', 50000, 'Simpanan wajib Juli', 1),
(1, 2, '2026-08-01', 50000, 'Simpanan wajib Agustus', 1),
(1, 3, '2026-06-15', 250000, 'Setoran sukarela', 1),
(2, 1, '2016-07-21', 500000, 'Simpanan pokok awal', 1),
(2, 2, '2026-08-01', 50000, 'Simpanan wajib Agustus', 1),
(3, 1, '2014-01-05', 500000, 'Simpanan pokok awal', 1),
(3, 3, '2026-05-10', 1000000, 'Tabungan panen', 1),
(4, 1, '2018-11-02', 500000, 'Simpanan pokok awal', 1),
(5, 1, '2013-09-18', 500000, 'Simpanan pokok awal', 1);

INSERT INTO pengajuan_pinjaman (no_pengajuan, anggota_id, tanggal, jumlah, bagi_hasil_persen, tenor, keperluan, status) VALUES
('PGJ-2026-001', 1, '2026-03-01', 5000000, 1.00, 10, 'Modal pupuk dan bibit musim tanam', 'disetujui'),
('PGJ-2026-002', 3, '2025-11-10', 3000000, 1.00, 6, 'Pembelian pakan ternak', 'disetujui'),
('PGJ-2026-003', 2, '2026-08-10', 2000000, 1.00, 8, 'Pengadaan alat pertanian', 'pengajuan');

INSERT INTO pinjaman (no_pinjaman, anggota_id, tanggal, jumlah, bagi_hasil_persen, tenor, keperluan, status, total_tagihan, sisa, pengajuan_id) VALUES
('PJM-2026-001', 1, '2026-03-01', 5000000, 1.00, 10, 'Modal pupuk dan bibit musim tanam', 'berjalan', 5500000, 4400000, 1),
('PJM-2026-002', 3, '2025-11-10', 3000000, 1.00, 6, 'Pembelian pakan ternak', 'lunas', 3180000, 0, 2),
('PJM-2026-003', 2, '2026-08-10', 2000000, 1.00, 8, 'Pengadaan alat pertanian', 'pengajuan', 2160000, 2160000, 3);

INSERT INTO pencairan_pinjaman (pengajuan_id, no_pinjaman, no_perjanjian, tanggal_cair, jumlah_cair, total_tagihan, sisa, status) VALUES
(1, 'PJM-2026-001', 'PRJ-PJM-2026-001', '2026-03-01', 5000000, 5500000, 4400000, 'berjalan'),
(2, 'PJM-2026-002', 'PRJ-PJM-2026-002', '2025-11-10', 3000000, 3180000, 0, 'lunas');

UPDATE pinjaman SET pencairan_id = 1 WHERE id = 1;
UPDATE pinjaman SET pencairan_id = 2 WHERE id = 2;

INSERT INTO angsuran (pinjaman_id, pencairan_id, angsuran_ke, tanggal, jumlah, pokok, bagi_hasil, denda, keterangan, created_by) VALUES
(1, 1, 1, '2026-04-01', 550000, 500000, 50000, 0, 'Angsuran ke-1', 1),
(1, 1, 2, '2026-05-01', 550000, 500000, 50000, 0, 'Angsuran ke-2', 1),
(2, 2, 1, '2025-12-10', 530000, 500000, 30000, 0, 'Angsuran ke-1', 1),
(2, 2, 2, '2026-01-10', 530000, 500000, 30000, 0, 'Angsuran ke-2', 1),
(2, 2, 3, '2026-02-10', 530000, 500000, 30000, 0, 'Angsuran ke-3', 1),
(2, 2, 4, '2026-03-10', 530000, 500000, 30000, 0, 'Angsuran ke-4', 1),
(2, 2, 5, '2026-04-10', 530000, 500000, 30000, 0, 'Angsuran ke-5', 1),
(2, 2, 6, '2026-05-10', 530000, 500000, 30000, 0, 'Pelunasan', 1);

INSERT INTO pengumuman (judul, isi, tanggal, publik) VALUES
('Rapat Anggota Tahunan 2026', 'RAT akan dilaksanakan sesuai undangan pengurus di Kantor Koperasi. Seluruh anggota diundang hadir untuk membahas laporan keuangan dan rencana kerja.', '2026-08-01', 1),
('Pupuk Organik Produksi Koperasi', 'Unit produksi pupuk organik telah beroperasi. Anggota mendapat harga khusus untuk pupuk granul, cair, dan kompos. Pemesanan melalui pengurus unit usaha.', '2026-08-10', 1),
('Jam Layanan Kantor', 'Kantor koperasi buka Senin–Jumat pukul 08.00–16.00 WIB dan Sabtu 08.00–12.00 WIB. Tutup pada hari libur nasional.', '2026-01-02', 1);
