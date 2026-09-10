<?php
/**
 * Jalankan sekali: http://localhost/koperasi/setup.php
 * Membuat database + SEMUA tabel jika belum ada. Tidak menghapus data.
 */
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Setup hanya boleh dijalankan dari komputer server (localhost).');
}
error_reporting(E_ALL);
ini_set('display_errors', '0');

$host = '127.0.0.1';
$name = 'koperasi_bina_tani';
$tries = [['root', ''], ['root', 'root'], ['koperasi', 'koperasi123']];

$pdo = null;
$used = '';
foreach ($tries as [$user, $pass]) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $used = $user;
        break;
    } catch (Throwable $e) {
        continue;
    }
}
if (!$pdo) {
    exit('Tidak bisa konek MySQL. Aktifkan MySQL di XAMPP.');
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

function has_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch();
}
function has_table(PDO $pdo, string $table): bool {
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return (bool)$st->fetch();
}

$log = [];
$add = function (string $sql) use ($pdo, &$log) {
    try {
        $pdo->exec($sql);
        $log[] = 'OK: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 140);
    } catch (Throwable $e) {
        $log[] = 'LEWATI: ' . $e->getMessage();
    }
};

$sqlFile = __DIR__ . '/database.sql';
if (!is_file($sqlFile)) {
    exit('File database.sql tidak ditemukan.');
}

// Ambil hanya CREATE TABLE ... ; dari database.sql (tanpa DROP / INSERT)
$raw = file_get_contents($sqlFile);
if (preg_match_all('/CREATE TABLE[\s\S]+?;/i', $raw, $m)) {
    foreach ($m[0] as $create) {
        $add(preg_replace('/CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $create, 1));
    }
}

// Kolom yang sering kurang di DB lama
$cols = [
    'anggota' => [
        'kelompok_tani' => "VARCHAR(120) NULL",
        'id_kelompok' => "INT NULL",
        'jabatan_kelompok' => "VARCHAR(50) NULL DEFAULT 'Anggota'",
        'plasma' => "TINYINT(1) NOT NULL DEFAULT 0",
        'punya_tanah' => "TINYINT(1) NOT NULL DEFAULT 0",
        'luas_tanah' => "DECIMAL(10,2) NULL",
        'stdb' => "VARCHAR(10) NOT NULL DEFAULT 'belum'",
        'no_stdb' => "VARCHAR(50) NULL",
        'foto' => "VARCHAR(120) NULL",
        'ktp_file' => "VARCHAR(120) NULL",
        'sertifikat_file' => "VARCHAR(120) NULL",
        'catatan_verifikasi' => "TEXT NULL",
        'status_keanggotaan' => "VARCHAR(20) DEFAULT 'Biasa'",
        'username' => "VARCHAR(50) NULL",
        'password' => "VARCHAR(255) NULL",
    ],
    'pengaturan' => [
        'bagi_hasil_persen' => "DECIMAL(5,2) NOT NULL DEFAULT 1.00",
        'simpanan_pokok' => "DECIMAL(15,2) NOT NULL DEFAULT 500000",
        'simpanan_wajib' => "DECIMAL(15,2) NOT NULL DEFAULT 50000",
        'tanggal_berdiri' => "DATE NULL",
        'jenis_koperasi' => "VARCHAR(120) NULL",
        'kbli' => "TEXT NULL",
        'sertifikasi' => "TEXT NULL",
        'ba_json' => "LONGTEXT NULL",
        'struktur_json' => "LONGTEXT NULL",
    ],
    'pinjaman' => [
        'bagi_hasil_persen' => "DECIMAL(5,2) DEFAULT 1.00",
        'agunan' => "VARCHAR(120) NULL",
        'no_perjanjian' => "VARCHAR(40) NULL",
        'pengajuan_id' => "INT NULL",
        'pencairan_id' => "INT NULL",
    ],
    'angsuran' => [
        'pencairan_id' => "INT NULL",
        'pokok' => "DECIMAL(15,2) NOT NULL DEFAULT 0",
        'bagi_hasil' => "DECIMAL(15,2) NOT NULL DEFAULT 0",
        'denda' => "DECIMAL(15,2) NOT NULL DEFAULT 0",
        'sumber_bayar' => "VARCHAR(40) DEFAULT 'tunai'",
    ],
    'timbangan_tbs' => [
        'surat_jalan_id' => "INT NULL",
        'potong_saprodi' => "DECIMAL(15,2) NOT NULL DEFAULT 0",
    ],
    'kelompok' => [
        'kode_kelompok' => "VARCHAR(20) NULL",
        'nama_kelompok' => "VARCHAR(120) NULL",
        'nama_ketua' => "VARCHAR(100) NULL",
        'no_hp_ketua' => "VARCHAR(30) NULL",
        'wilayah_dusun' => "VARCHAR(120) NULL",
        'blok_hamparan' => "VARCHAR(120) NULL",
        'tanggal_terbentuk' => "DATE NULL",
        'fee_per_kg' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    ],
];
foreach ($cols as $table => $list) {
    if (!has_table($pdo, $table)) {
        continue;
    }
    foreach ($list as $col => $def) {
        if (!has_col($pdo, $table, $col)) {
            $add("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        }
    }
}
if (has_table($pdo, 'pinjaman') && has_col($pdo, 'pinjaman', 'bunga_persen') && !has_col($pdo, 'pinjaman', 'bagi_hasil_persen')) {
    $add("ALTER TABLE pinjaman CHANGE bunga_persen bagi_hasil_persen DECIMAL(5,2) DEFAULT 1.00");
}
if (has_table($pdo, 'timbangan_tbs')) {
    $add("ALTER TABLE timbangan_tbs MODIFY anggota_id INT NULL");
}

if (has_table($pdo, 'kelompok') && (int)$pdo->query('SELECT COUNT(*) FROM kelompok')->fetchColumn() < 21) {
    for ($i = 1; $i <= 21; $i++) {
        $kode = 'KT-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $add("INSERT IGNORE INTO kelompok (nomor, kode_kelompok, nama_kelompok, luas_tanah) VALUES ($i, '$kode', 'Kelompok Tani $i', 0)");
    }
}
if (has_table($pdo, 'jenis_simpanan') && (int)$pdo->query('SELECT COUNT(*) FROM jenis_simpanan')->fetchColumn() === 0) {
    $add("INSERT INTO jenis_simpanan (kode,nama,keterangan,wajib) VALUES
      ('SPK','Simpanan Pokok','Dibayar sekali saat menjadi anggota',1),
      ('SWJ','Simpanan Wajib','Dibayar setiap bulan oleh anggota aktif',1),
      ('SSK','Simpanan Sukarela','Simpanan bebas sesuai kemampuan anggota',0),
      ('SHR','Simpanan Hari Raya','Tabungan khusus menjelang hari raya',0)");
}
if (has_table($pdo, 'pengaturan') && (int)$pdo->query('SELECT COUNT(*) FROM pengaturan')->fetchColumn() === 0) {
    $add("INSERT INTO pengaturan (id,nama_koperasi,alamat,telepon,email,tahun_berdiri,tanggal_berdiri,ketua,bagi_hasil_persen,simpanan_pokok,simpanan_wajib,jenis_koperasi)
      VALUES (1,'Koperasi Produsen Ramah Lingkungan Pasaman Barat','Simpang Empat, Kabupaten Pasaman Barat, Provinsi Sumatera Barat','','taniramahlingkungan.official@gmail.com',2026,'2026-09-09','Indra Gunawan',1,150000,10000,'Koperasi Produsen')");
}
if (has_table($pdo, 'coa_akun') && (int)$pdo->query('SELECT COUNT(*) FROM coa_akun')->fetchColumn() === 0) {
    $add("INSERT INTO coa_akun (kode,nama,kategori,saldo_normal) VALUES
      ('1111','Kas tunai','Aset','debit'),('1112','Bank','Aset','debit'),
      ('1211','Piutang pinjaman anggota','Aset','debit'),('1212','Piutang saprodi','Aset','debit'),
      ('1311','Persediaan / TBS','Aset','debit'),('1312','Persediaan pupuk organik','Aset','debit'),
      ('2111','Simpanan pokok','Kewajiban','kredit'),('2112','Simpanan wajib','Kewajiban','kredit'),
      ('2113','Simpanan sukarela','Kewajiban','kredit'),('2211','Utang kas kelompok','Kewajiban','kredit'),
      ('3111','Modal / ekuitas','Ekuitas','kredit'),
      ('4111','Pendapatan bagi hasil pinjaman','Pendapatan','kredit'),
      ('4112','Pendapatan lain','Pendapatan','kredit'),('4113','Pendapatan margin TBS','Pendapatan','kredit'),
      ('4114','Pendapatan pupuk organik','Pendapatan','kredit'),
      ('5111','Biaya operasional','Biaya','debit'),('5112','Pembelian TBS petani','Biaya','debit'),
      ('5113','Biaya produksi pupuk','Biaya','debit')");
}
if (has_table($pdo, 'users') && (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    $add("INSERT INTO users (username,password,nama,role) VALUES
      ('admin','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator Koperasi','admin')");
}

$dir = __DIR__ . '/uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
    $log[] = 'OK: folder uploads dibuat';
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Setup Koperasi</title>
<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;line-height:1.5}code{background:#eee;padding:2px 6px} .ok{color:#0f4224}</style>
</head>
<body>
<h1>Setup database selesai</h1>
<p>Koneksi: <code><?= htmlspecialchars($used) ?></code> · database <code><?= htmlspecialchars($name) ?></code></p>
<p class="ok"><strong><?= count($tables) ?> tabel</strong> di MySQL:</p>
<p><?= htmlspecialchars(implode(', ', $tables)) ?></p>
<details><summary>Log</summary><ul><?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul></details>
<p>Data lama tidak dihapus. Untuk instalasi baru + data contoh, impor <code>database.sql</code> di phpMyAdmin.</p>
<p><a href="index.php">Beranda</a> · <a href="login-admin.php">Login admin</a></p>
</body>
</html>
