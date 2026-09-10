# Koperasi Produsen Ramah Lingkungan Pasaman Barat

Aplikasi web (PHP + MySQL) untuk mengelola **Koperasi Produsen Ramah Lingkungan
Pasaman Barat** — koperasi produsen yang berfokus pada pertanian ramah lingkungan
dengan usaha utama **produksi pupuk organik**.

- **Kedudukan**: Simpang Empat, Kabupaten Pasaman Barat, Provinsi Sumatera Barat – Indonesia 26567
- **Email**: taniramahlingkungan.official@gmail.com
- **Pendirian**: Rapat Pendirian Rabu, 9 September 2026 di Gedung UPTD Balai
  Pelatihan dan Penyuluhan Pertanian Sumatera Barat —
  BA Nomor 001/PENDIRIAN/KOP/KPRL-PB/IX/2026
- **Wilayah keanggotaan**: utama Kecamatan Kinali; jangka waktu tidak terbatas
- **Modal**: simpanan pokok Rp150.000, simpanan wajib Rp10.000/bulan

## Susunan pengurus & pengawas (2026–2029)

| Jabatan | Nama |
|---------|------|
| Ketua | Indra Gunawan |
| Wk. Ketua | Ilham Pelemi |
| Sekretaris | Imam Ratili |
| Wk. Sekretaris | Tora Fanandres |
| Bendahara | Anton Suherman |
| Ketua Badan Pengawas | Ali Zamar, SH |
| Anggota Pengawas | Rusdi |
| Anggota Pengawas | Syamlidar |

## Bidang usaha (KBLI)

| KBLI | Bidang | Peran |
|------|--------|-------|
| 20124 | Produksi pupuk organik | Utama |
| 46752 | Perdagangan besar pupuk | Pendukung |
| 47763 | Perdagangan eceran pupuk | Pendukung |
| 46202 | Perdagangan besar hasil pertanian tanaman yang mengandung minyak | Pendukung |

## Fitur

- **Situs publik**: beranda, profil, KBLI usaha, legalitas, pengumuman, kontak.
- **Berita Acara Pendirian** (di **Pengaturan**): data rapat pendirian
  (pendiri, rencana usaha, kuasa) — pengurus & modal otomatis dari struktur
  dan simpanan (tidak isi dua kali), plus cetak dokumen resmi (PDF).
  > Daftar pendiri + pimpinan/notulis rapat + nama pembina/penasehat/manajer
  > belum diisi — lengkapi di Pengaturan.
- **Struktur organisasi**: bagan RAT → pembina/penasehat, pengurus
  (termasuk wakil ketua & wakil sekretaris), badan pengawas, manajer,
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
2. Jalankan Apache + MySQL, lalu pilih **salah satu**:
   - **Opsi A (disarankan)** — instalasi baru + data contoh: di phpMyAdmin buat
     database `koperasi_bina_tani`, tab **Import**, pilih `koperasi/database.sql`.
   - **Opsi B** — database sudah ada: buka `http://localhost/koperasi/setup.php`
     (melengkapi tabel/kolom yang kurang tanpa menghapus data).
   > Jangan lakukan keduanya berurutan — impor setelah setup menimbulkan
   > error `#1062 Duplicate entry`. Kalau sudah terlanjur: abaikan saja
   > (data sudah ada) atau kosongkan database lalu impor ulang dari Opsi A.
3. Login awal: `admin` / `admin123` — **segera ganti sandinya**.

Instalasi hosting: ikuti `koperasi/PANDUAN-HOSTINGER.txt`.

## Melengkapi data koperasi

1. Login admin → buka **Pengaturan** → kartu Berita acara: lengkapi daftar
   pendiri (`Nama|NIK|Alamat` per baris, minimal 9 orang), pimpinan/notulis,
   dan waktu rapat; kartu lain: NIB, NIK koperasi, NPWP, nomor akta/badan
   hukum, telepon, logo, pembina/penasehat, manajer, dan unit-unit usaha.
   Lalu **Simpan** — pengurus & modal otomatis dipakai dokumen BA.
3. Hasilnya tampil di **Profil koperasi**, bagan struktur, dan situs publik.

Catatan: nama database MySQL (`koperasi_bina_tani`) hanya nama teknis internal
dan tidak tampil di aplikasi, jadi tidak wajib diganti. Kalau ingin diganti,
ubah serentak di `config.php`, `database.sql`, dan `setup.php`.
