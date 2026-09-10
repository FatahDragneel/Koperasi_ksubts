# Koperasi Produsen Ramah Lingkungan

Aplikasi web (PHP + MySQL) untuk mengelola **Koperasi Produsen** yang berfokus pada
pertanian ramah lingkungan. Dikembangkan dari aplikasi KSU Bina Tani Sejahtera dan
diubah menjadi koperasi produsen dengan usaha utama **produksi pupuk organik**.

> Nama bawaan — *Koperasi Produsen Hijau Tani Lestari* — adalah **contoh**.
> Ganti dengan nama resmi koperasi Anda lewat menu **Pengaturan** (atau otomatis
> dari halaman **Berita Acara**). Lihat "Memakai data koperasi sendiri" di bawah.

## Bidang usaha (KBLI)

| KBLI | Bidang | Peran |
|------|--------|-------|
| 20124 | Produksi pupuk organik | Utama |
| 46752 | Perdagangan besar pupuk | Pendukung |
| 47763 | Perdagangan eceran pupuk | Pendukung |
| 46202 | Perdagangan besar hasil pertanian tanaman minyak (cth. TBS) | Pendukung |

## Fitur

- **Situs publik**: beranda, profil, KBLI usaha, legalitas, pengumuman, kontak.
- **Berita Acara Pendirian** (`berita_acara.php`): formulir rapat pendirian
  (daftar ≥ 9 pendiri, pengurus/pengawas terpilih, modal, rencana usaha, kuasa),
  cetak dokumen resmi (PDF), dan tombol **terapkan ke struktur & pengaturan**.
- **Struktur organisasi**: bagan RAT → pembina, pengurus, pengawas, KTU/kasir,
  unit kantor & unit usaha — semua bisa diubah di **Pengaturan**.
- **Unit usaha pupuk organik** (`pupuk.php`): master produk, catat hasil
  produksi + biaya, penjualan tunai/piutang ke anggota/umum, stok otomatis,
  dan jurnal akuntansi otomatis (persediaan 1312, pendapatan 4114).
- **Simpan pinjam**: simpanan pokok/wajib/sukarela, pinjaman + angsuran,
  penarikan, pengalihan hak, SHU.
- **Niaga TBS & kelompok tani**: lahan, timbangan, harga, surat jalan PKS,
  invoice, antrean truk, saprodi.
- **Akuntansi**: COA, jurnal umum/otomatis, buku besar, laporan, kas.

## Instalasi lokal (XAMPP)

1. Salin folder `koperasi/` ke `C:\xampp\htdocs\koperasi`.
2. Jalankan Apache + MySQL, buka `http://localhost/koperasi/setup.php`
   (membuat database `koperasi_bina_tani` + semua tabel, tanpa menghapus data).
   Untuk data contoh: impor `koperasi/database.sql` via phpMyAdmin.
3. Login awal: `admin` / `admin123` — **segera ganti sandinya**.

Instalasi hosting: ikuti `koperasi/PANDUAN-HOSTINGER.txt`.

## Memakai data koperasi sendiri

1. Login admin → buka **📜 Berita acara** → isi nomor, tanggal/tempat rapat,
   daftar pendiri (`Nama|NIK|Alamat` per baris), pengurus & pengawas terpilih,
   modal, lalu **Simpan & terapkan ke struktur**.
2. Buka **Pengaturan** → lengkapi nama resmi, alamat, NIB, NIK koperasi, NPWP,
   nomor akta/badan hukum, KBLI, sertifikasi, logo, dan unit-unit usaha.
3. Hasilnya tampil di **Profil koperasi**, bagan struktur, dan situs publik.

Catatan: nama database MySQL (`koperasi_bina_tani`) hanya nama teknis internal
dan tidak tampil di aplikasi, jadi tidak wajib diganti. Kalau ingin diganti,
ubah serentak di `config.php`, `database.sql`, dan `setup.php`.
