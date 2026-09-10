<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$local = str_contains($host, 'localhost') || str_starts_with($host, '127.0.0.1');
$cookiePath = '/';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($dir !== '/' && $dir !== '.' && $dir !== '') {
        $cookiePath = rtrim($dir, '/') . '/';
    }
}
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $https && !$local,
    'httponly' => true,
    'samesite' => ($https && !$local) ? 'None' : 'Lax',
]);
session_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'koperasi_bina_tani');
// XAMPP default: root tanpa sandi. Ubah jika MySQL Anda beda.
define('DB_USER', 'root');
define('DB_PASS', '');
define('AUTH_KEY', 'BinaTani-9f3cA7e2D1b8-KqWm4pR6sX0uYvHz-nJ5tL8cG2');

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $attempts = [
        [DB_USER, DB_PASS],
        ['root', ''],
        ['root', 'root'],
        ['koperasi', 'koperasi123'],
    ];
    $last = null;
    foreach ($attempts as [$user, $pass]) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            return $pdo;
        } catch (Throwable $e) {
            $last = $e;
        }
    }
    throw $last ?? new RuntimeException('Gagal konek database');
}

function setting(): array {
    static $s = null;
    if ($s === null) {
        ensure_pengaturan_schema();
        ensure_kelompok_schema();
        ensure_tbs_schema();
        ensure_akuntansi_schema();
        ensure_logistik_schema();
        ensure_pinjaman_schema();
        ensure_pengalihan_schema();
        ensure_simpanan_sukarela_schema();
        $s = db()->query('SELECT * FROM pengaturan WHERE id = 1')->fetch() ?: [];
    }
    return $s;
}

function setting_refresh(): array {
    $s = db()->query('SELECT * FROM pengaturan WHERE id = 1')->fetch() ?: [];
    return $s;
}


function default_struktur_bts(): array {
    return [
        'pembina' => "Dinas Koperasi & UKM Pasbar\nDinas Perkebunan Pasbar",
        'ketua' => 'Dt. Unyil',
        'sekretaris' => 'Indra Gunawan',
        'bendahara' => 'H. Jhoni Bustanil',
        'pengawas_ketua' => 'Ahmad Zeni',
        'pengawas_anggota' => "H. Rommy Candra\nH. Alman Gampo Alam",
        'ktu' => 'Megawati',
        'kasir' => 'Fatmayani',
        'unit_kiri' => [
            ['nama' => 'Pembukuan Keuangan', 'orang' => 'Megawati', 'warna' => 'biru'],
            ['nama' => 'Keanggotaan', 'orang' => 'Fadya F.P', 'warna' => 'biru'],
            ['nama' => 'Amprahan', 'orang' => 'Iwit', 'warna' => 'biru'],
            ['nama' => 'Pra PSR Sapras', 'orang' => 'Ayu Lestari', 'warna' => 'merah'],
        ],
        'unit_bawah' => [
            ['nama' => 'Kebersihan Kantor', 'orang' => 'Rina', 'warna' => 'biru'],
            ['nama' => 'Surat-surat Arsip', 'orang' => 'Meldawati', 'warna' => 'biru'],
        ],
        'unit_kanan' => [
            ['nama' => 'Saprodi', 'orang' => '', 'warna' => 'hijau', 'sub' => "Pencatatan|Megawati\nGudang|Rangga"],
            ['nama' => 'USP', 'orang' => 'Fatmayani', 'warna' => 'hijau', 'sub' => ''],
            ['nama' => 'Pembibitan', 'orang' => '', 'warna' => 'hijau', 'sub' => "Pencatatan|Meldawati\nKoor. Lapangan|Rangga"],
            ['nama' => 'Alat Berat', 'orang' => 'Iwit', 'warna' => 'hijau', 'sub' => ''],
            ['nama' => 'ISPO', 'orang' => 'H. Jhoni Bustanil', 'warna' => 'hijau', 'sub' => ''],
        ],
    ];
}

function struktur_baris_unit(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        if (is_string($r)) {
            $out[] = ['nama' => trim($r), 'orang' => '', 'warna' => 'biru', 'sub' => []];
            continue;
        }
        $sub = [];
        $raw = $r['sub'] ?? [];
        if (is_string($raw)) {
            foreach (preg_split('/\r\n|\r|\n/', $raw) as $ln) {
                $ln = trim($ln);
                if ($ln === '') {
                    continue;
                }
                $p = array_map('trim', explode('|', $ln, 2));
                $sub[] = ['nama' => $p[0], 'orang' => $p[1] ?? ''];
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $srow) {
                if (is_string($srow)) {
                    $sub[] = ['nama' => $srow, 'orang' => ''];
                } else {
                    $sub[] = ['nama' => trim((string)($srow['nama'] ?? '')), 'orang' => trim((string)($srow['orang'] ?? ''))];
                }
            }
        }
        $nm = trim((string)($r['nama'] ?? ''));
        $or = trim((string)($r['orang'] ?? ''));
        if ($nm === '' && $or === '' && !$sub) {
            continue;
        }
        $warna = $r['warna'] ?? 'biru';
        if (!in_array($warna, ['biru', 'hijau', 'merah', 'ungu'], true)) {
            $warna = 'biru';
        }
        $out[] = ['nama' => $nm ?: 'Unit', 'orang' => $or, 'warna' => $warna, 'sub' => $sub];
    }
    return $out;
}

function struktur_koperasi(?array $s = null): array {
    $s = $s ?? setting();
    $d = json_decode((string)($s['struktur_json'] ?? ''), true);
    if (!is_array($d)) {
        $d = [];
    }
    $def = default_struktur_bts();
    $isi = static function (string $k) use ($d, $def): string {
        $v = trim((string)($d[$k] ?? ''));
        return $v !== '' ? $v : (string)$def[$k];
    };
    $lines = static function (string $txt): array {
        $o = [];
        foreach (preg_split('/\r\n|\r|\n/', $txt) as $ln) {
            $ln = trim($ln);
            if ($ln !== '') {
                $o[] = $ln;
            }
        }
        return $o;
    };
    $kiri = struktur_baris_unit($d['unit_kiri'] ?? $def['unit_kiri']);
    $bawah = struktur_baris_unit($d['unit_bawah'] ?? $def['unit_bawah']);
    $kanan = struktur_baris_unit($d['unit_kanan'] ?? $def['unit_kanan']);
    if (!$kiri) {
        $kiri = struktur_baris_unit($def['unit_kiri']);
    }
    if (!$kanan) {
        $kanan = struktur_baris_unit($def['unit_kanan']);
    }
    $ketua = $isi('ketua');
    if ($ketua === '' && !empty($d['ketua_umum'])) {
        $ketua = trim((string)$d['ketua_umum']);
    }
    if ($ketua === '' && !empty($s['ketua'])) {
        $ketua = (string)$s['ketua'];
    }
    $pwKetua = $isi('pengawas_ketua');
    $pwAnggotaTxt = trim((string)($d['pengawas_anggota'] ?? $def['pengawas_anggota']));
    $pwAnggota = $lines($pwAnggotaTxt);
    return [
        'pembina' => $lines($isi('pembina')),
        'pembina_txt' => $isi('pembina'),
        'ketua' => $ketua,
        'sekretaris' => $isi('sekretaris'),
        'bendahara' => $isi('bendahara'),
        'pengawas_ketua' => $pwKetua,
        'pengawas_anggota' => $pwAnggota,
        'pengawas_anggota_txt' => $pwAnggotaTxt,
        'ktu' => $isi('ktu'),
        'kasir' => $isi('kasir'),
        'unit_kiri' => $kiri,
        'unit_bawah' => $bawah,
        'unit_kanan' => $kanan,
        'ketua_umum' => $ketua,
        'pengurus' => array_values(array_filter([
            ['jabatan' => 'Ketua', 'nama' => $ketua],
            ['jabatan' => 'Sekretaris', 'nama' => $isi('sekretaris')],
            ['jabatan' => 'Bendahara', 'nama' => $isi('bendahara')],
        ], static function ($r) { return $r['nama'] !== ''; })),
        'pengawas' => array_merge(
            $pwKetua !== '' ? [['jabatan' => 'Ketua Pengawas', 'nama' => $pwKetua]] : [],
            array_map(static function ($n) { return ['jabatan' => 'Anggota Pengawas', 'nama' => $n]; }, $pwAnggota)
        ),
    ];
}

function default_profil_koperasi(): array {
    return [
        'nama_koperasi' => 'Koperasi Serba Usaha Bina Tani Sejahtera',
        'jenis_koperasi' => 'Koperasi Serba Usaha (KSU)',
        'tanggal_berdiri' => '2015-02-05',
        'tgl_badan_hukum' => '2015-03-04',
        'no_akta' => '132/PAD/III.19/BPMP2T/III-2015',
        'alamat' => 'Tanjung Pangkal, Kejorongan Tanjuang Pangka, Nagari Lingkuang Aua, Kec. Pasaman, Kabupaten Pasaman Barat',
        'sejarah' => "Koperasi ini berawal bergabung dalam KUD Lingkuang II. Pada bulan Juni 2015 memekarkan diri menjadi Koperasi Jasa Bina Tani Sejahtera, dan pada 1 Januari 2015 mulai beraktivitas mengacu pada UU Koperasi Nomor 17 Tahun 2012.\n\nSetelah UU tersebut dibatalkan dan berlaku kembali UU Nomor 25 Tahun 1992, pada Kamis 5 Februari 2015 di Aula YAPTIP Pasaman Baru diadakan Rapat Anggota Khusus merevisi Anggaran Dasar. Atas arahan Dinas Koperindagkop & UKM Pasaman Barat, nama diubah menjadi Koperasi Serba Usaha Bina Tani Sejahtera.",
        'visi' => 'Mewujudkan kesejahteraan anggota khususnya dan masyarakat pada umumnya.',
        'misi' => 'Membuka usaha-usaha koperasi yang potensial, serta menciptakan pelayanan yang prima demi meningkatkan kesejahteraan anggota.',
        'motto' => 'Profesionalisme pengurus dan partisipasi anggota yang tinggi, koperasi akan berkembang pesat.',
        'nilai_koperasi' => 'Profesional, partisipatif, kekeluargaan, dan pelayanan prima.',
        'kegiatan_usaha' => "Pengelolaan Kebun Anggota\nSaprodi\nSimpan Pinjam\nTransportasi & Alat berat\nPembibitan Kelapa Sawit",
        'prestasi' => "Juara I Koperasi Berpotensi Berprestasi TH 2017 Tingkat Kabupaten Pasaman Barat\nJuara I Koperasi Berpotensi Berprestasi TH 2018 Tingkat Kabupaten Pasaman Barat",
        'karyawan_ket' => "9 orang\n3 orang karyawan tetap\n6 orang karyawan kontrak",
        'rapat_anggota' => 'Rapat Anggota Tahunan (RAT) adalah pemegang kekuasaan tertinggi koperasi. Pengawas mengawasi operasional dan keuangan; pengurus mengelola kegiatan sehari-hari.',
    ];
}

function profil_isi(array $s, string $kunci): string {
    $v = trim((string)($s[$kunci] ?? ''));
    if ($v !== '') {
        return $v;
    }
    return (string)(default_profil_koperasi()[$kunci] ?? '');
}

function ensure_pengaturan_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = db()->query('SHOW COLUMNS FROM pengaturan')->fetchAll();
        $names = array_column($cols, 'Field');
        if (!in_array('tanggal_berdiri', $names, true)) {
            db()->exec("ALTER TABLE pengaturan ADD COLUMN tanggal_berdiri DATE NULL");
            db()->exec("UPDATE pengaturan SET tanggal_berdiri = CONCAT(IFNULL(tahun_berdiri, YEAR(CURDATE())), '-01-01') WHERE tanggal_berdiri IS NULL");
        }
        if (!in_array('tahun_berdiri', $names, true)) {
            db()->exec("ALTER TABLE pengaturan ADD COLUMN tahun_berdiri YEAR NULL");
        }
        if (!in_array('simpanan_wajib', $names, true)) {
            db()->exec("ALTER TABLE pengaturan ADD COLUMN simpanan_wajib DECIMAL(15,2) NOT NULL DEFAULT 50000");
        }
        if (!in_array('simpanan_pokok', $names, true)) {
            db()->exec("ALTER TABLE pengaturan ADD COLUMN simpanan_pokok DECIMAL(15,2) NOT NULL DEFAULT 500000");
        }
        if (!in_array('bagi_hasil_persen', $names, true)) {
            db()->exec("ALTER TABLE pengaturan ADD COLUMN bagi_hasil_persen DECIMAL(5,2) NOT NULL DEFAULT 1");
        }
        $legal = [
            'no_akta' => "VARCHAR(120) NULL",
            'no_badan_hukum' => "VARCHAR(120) NULL",
            'nib' => "VARCHAR(80) NULL",
            'npwp' => "VARCHAR(40) NULL",
            'iusp' => "VARCHAR(120) NULL",
            'iup' => "VARCHAR(120) NULL",
            'jenis_koperasi' => "VARCHAR(120) NULL",
            'sejarah' => "TEXT NULL",
            'logo_filosofi' => "TEXT NULL",
            'logo_file' => "VARCHAR(160) NULL",
            'nilai_koperasi' => "TEXT NULL",
            'nik_koperasi' => "VARCHAR(80) NULL",
            'rapat_anggota' => "TEXT NULL",
            'cabang' => "TEXT NULL",
            'whatsapp' => "VARCHAR(40) NULL",
            'sosmed' => "TEXT NULL",
            'struktur_json' => "LONGTEXT NULL",
            'motto' => "TEXT NULL",
            'kegiatan_usaha' => "TEXT NULL",
            'prestasi' => "TEXT NULL",
            'karyawan_ket' => "TEXT NULL",
            'tgl_badan_hukum' => "DATE NULL",
        ];
        foreach ($legal as $col => $def) {
            if (!in_array($col, $names, true)) {
                db()->exec("ALTER TABLE pengaturan ADD COLUMN `$col` $def");
                $names[] = $col;
            }
        }
    } catch (Throwable $e) {
        // tabel belum ada
    }
}

function ensure_kelompok_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS kelompok (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nomor INT NOT NULL UNIQUE,
            luas_tanah DECIMAL(12,2) NULL DEFAULT 0,
            lokasi VARCHAR(255) NULL,
            desa VARCHAR(80) NULL,
            kecamatan VARCHAR(80) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $addsK = [
            'kode_kelompok' => "VARCHAR(20) NULL",
            'nama_kelompok' => "VARCHAR(120) NULL",
            'nama_ketua' => "VARCHAR(100) NULL",
            'no_hp_ketua' => "VARCHAR(30) NULL",
            'wilayah_dusun' => "VARCHAR(120) NULL",
            'blok_hamparan' => "VARCHAR(120) NULL",
            'tanggal_terbentuk' => "DATE NULL",
        ];
        $kcols = array_column(db()->query('SHOW COLUMNS FROM kelompok')->fetchAll(), 'Field');
        foreach ($addsK as $col => $def) {
            if (!in_array($col, $kcols, true)) {
                db()->exec("ALTER TABLE kelompok ADD COLUMN `$col` $def");
            }
        }
        $n = (int)db()->query('SELECT COUNT(*) FROM kelompok')->fetchColumn();
        if ($n < 21) {
            $ins = db()->prepare('INSERT IGNORE INTO kelompok (nomor, kode_kelompok, nama_kelompok, luas_tanah, lokasi) VALUES (?, ?, ?, 0, ?)');
            for ($i = 1; $i <= 21; $i++) {
                $kode = 'KT-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $ins->execute([$i, $kode, 'Kelompok Tani ' . $i, '']);
            }
        }
        db()->exec("UPDATE kelompok SET kode_kelompok = CONCAT('KT-', LPAD(nomor, 2, '0')) WHERE kode_kelompok IS NULL OR kode_kelompok=''");
        db()->exec("UPDATE kelompok SET nama_kelompok = CONCAT('Kelompok Tani ', nomor) WHERE nama_kelompok IS NULL OR nama_kelompok=''");
        ensure_anggota_schema();
        $acols = array_column(db()->query('SHOW COLUMNS FROM anggota')->fetchAll(), 'Field');
        if (!in_array('id_kelompok', $acols, true)) {
            db()->exec('ALTER TABLE anggota ADD COLUMN id_kelompok INT NULL');
        }
        foreach (db()->query('SELECT id, nomor FROM kelompok') as $k) {
            db()->prepare('UPDATE anggota SET id_kelompok=? WHERE id_kelompok IS NULL AND CAST(kelompok_tani AS UNSIGNED)=?')->execute([(int)$k['id'], (int)$k['nomor']]);
        }
        ensure_anggota_kelompok_schema();
    } catch (Throwable $e) {
        // skip
    }
}

function ensure_anggota_kelompok_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS anggota_kelompok (
            id INT AUTO_INCREMENT PRIMARY KEY,
            anggota_id INT NOT NULL,
            id_kelompok INT NOT NULL,
            jabatan VARCHAR(50) NULL DEFAULT 'Anggota',
            UNIQUE KEY uq_ag_kel (anggota_id, id_kelompok)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ins = db()->prepare('INSERT IGNORE INTO anggota_kelompok (anggota_id,id_kelompok,jabatan) VALUES (?,?,?)');
        foreach (db()->query('SELECT id, id_kelompok, jabatan_kelompok FROM anggota WHERE id_kelompok IS NOT NULL') as $a) {
            $ins->execute([(int)$a['id'], (int)$a['id_kelompok'], $a['jabatan_kelompok'] ?: 'Anggota']);
        }
        foreach (db()->query('SELECT a.id, a.kelompok_tani, a.jabatan_kelompok, k.id kid FROM anggota a JOIN kelompok k ON k.nomor=CAST(a.kelompok_tani AS UNSIGNED) WHERE a.id_kelompok IS NULL AND a.kelompok_tani IS NOT NULL AND a.kelompok_tani<>\'\'') as $a) {
            $ins->execute([(int)$a['id'], (int)$a['kid'], $a['jabatan_kelompok'] ?: 'Anggota']);
        }
    } catch (Throwable $e) {
    }
}

function kelompok_anggota(int $anggotaId): array {
    ensure_anggota_kelompok_schema();
    $st = db()->prepare('SELECT ak.*, k.nomor, k.kode_kelompok, k.nama_kelompok, k.nama_ketua, k.no_hp_ketua, k.wilayah_dusun, k.lokasi
        FROM anggota_kelompok ak JOIN kelompok k ON k.id=ak.id_kelompok
        WHERE ak.anggota_id=? ORDER BY k.nomor');
    $st->execute([$anggotaId]);
    return $st->fetchAll();
}

function tambah_anggota_ke_kelompok(int $anggotaId, int $idKelompok, string $jabatan = 'Anggota'): void {
    ensure_anggota_kelompok_schema();
    $list = jabatan_kelompok_opsi();
    if (!in_array($jabatan, $list, true)) {
        $jabatan = 'Anggota';
    }
    $k = db()->prepare('SELECT nomor FROM kelompok WHERE id=?');
    $k->execute([$idKelompok]);
    $nomor = $k->fetchColumn();
    if (!$nomor) {
        return;
    }
    if ($jabatan === 'Ketua') {
        db()->prepare("UPDATE anggota_kelompok SET jabatan='Anggota' WHERE id_kelompok=? AND jabatan='Ketua'")->execute([$idKelompok]);
    }
    db()->prepare('INSERT INTO anggota_kelompok (anggota_id,id_kelompok,jabatan) VALUES (?,?,?) ON DUPLICATE KEY UPDATE jabatan=VALUES(jabatan)')
        ->execute([$anggotaId, $idKelompok, $jabatan]);
    $cek = db()->prepare('SELECT id_kelompok FROM anggota WHERE id=?');
    $cek->execute([$anggotaId]);
    $utama = $cek->fetchColumn();
    if (!$utama) {
        db()->prepare('UPDATE anggota SET id_kelompok=?, kelompok_tani=?, plasma=1, jabatan_kelompok=? WHERE id=?')
            ->execute([$idKelompok, (string)$nomor, $jabatan, $anggotaId]);
    } elseif ((int)$utama === $idKelompok) {
        db()->prepare('UPDATE anggota SET jabatan_kelompok=? WHERE id=?')->execute([$jabatan, $anggotaId]);
    } else {
        db()->prepare('UPDATE anggota SET plasma=1 WHERE id=?')->execute([$anggotaId]);
    }
    if ($jabatan === 'Ketua') {
        sinkron_ketua_kelompok($idKelompok);
    }
}

function keluar_anggota_dari_kelompok(int $anggotaId, int $idKelompok): void {
    ensure_anggota_kelompok_schema();
    db()->prepare('DELETE FROM anggota_kelompok WHERE anggota_id=? AND id_kelompok=?')->execute([$anggotaId, $idKelompok]);
    $cek = db()->prepare('SELECT id_kelompok FROM anggota WHERE id=?');
    $cek->execute([$anggotaId]);
    if ((int)$cek->fetchColumn() === $idKelompok) {
        $lain = db()->prepare('SELECT ak.id_kelompok, ak.jabatan, k.nomor FROM anggota_kelompok ak JOIN kelompok k ON k.id=ak.id_kelompok WHERE ak.anggota_id=? ORDER BY k.nomor LIMIT 1');
        $lain->execute([$anggotaId]);
        $r = $lain->fetch();
        if ($r) {
            db()->prepare('UPDATE anggota SET id_kelompok=?, kelompok_tani=?, jabatan_kelompok=? WHERE id=?')
                ->execute([$r['id_kelompok'], (string)$r['nomor'], $r['jabatan'], $anggotaId]);
        } else {
            db()->prepare('UPDATE anggota SET id_kelompok=NULL, kelompok_tani=NULL, jabatan_kelompok=NULL WHERE id=?')->execute([$anggotaId]);
        }
    }
    sinkron_ketua_kelompok($idKelompok);
}

function sinkron_keanggotaan_anggota(int $anggotaId, array $idKelompokList): void {
    $idKelompokList = array_values(array_unique(array_filter(array_map('intval', $idKelompokList))));
    $punya = [];
    foreach (kelompok_anggota($anggotaId) as $r) {
        $punya[] = (int)$r['id_kelompok'];
    }
    foreach ($idKelompokList as $kid) {
        if ($kid > 0 && !in_array($kid, $punya, true)) {
            tambah_anggota_ke_kelompok($anggotaId, $kid, 'Anggota');
        }
    }
    foreach ($punya as $kid) {
        if (!in_array($kid, $idKelompokList, true)) {
            keluar_anggota_dari_kelompok($anggotaId, $kid);
        }
    }
    if ($idKelompokList) {
        db()->prepare('UPDATE anggota SET plasma=1 WHERE id=?')->execute([$anggotaId]);
    }
}

function set_luas_lahan_kelompok(int $anggotaId, int $idKelompok, float $luas): void {
    if ($idKelompok < 1 || $luas <= 0) {
        return;
    }
    ensure_tbs_schema();
    $st = db()->prepare('SELECT id FROM lahan_sawit WHERE anggota_id=? AND id_kelompok=? ORDER BY id LIMIT 1');
    $st->execute([$anggotaId, $idKelompok]);
    $lid = $st->fetchColumn();
    if ($lid) {
        db()->prepare('UPDATE lahan_sawit SET luas_hektar=? WHERE id=?')->execute([$luas, $lid]);
    } else {
        db()->prepare('INSERT INTO lahan_sawit (anggota_id,id_kelompok,legalitas,luas_hektar,jumlah_pokok,tahun_tanam,lokasi_desa) VALUES (?,?,?,?,?,?,?)')
            ->execute([$anggotaId, $idKelompok, 'SKT', $luas, 0, null, null]);
    }
}

function html_pilih_kelompok_luas(array $terpilihIds = [], array $luasPerKel = []): string {
    $html = '<p style="font-size:13px;color:var(--muted);margin:6px 0 8px;">Centang kelompok (boleh lebih dari satu) dan isi luas lahan di kelompok itu.</p><div class="kel-multi">';
    try {
        $rows = db()->query('SELECT id, nomor, kode_kelompok, nama_kelompok FROM kelompok ORDER BY nomor')->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }
    foreach ($rows as $r) {
        $kid = (int)$r['id'];
        $cek = in_array($kid, $terpilihIds, true) ? ' checked' : '';
        $luas = $luasPerKel[$kid] ?? '';
        $kode = $r['kode_kelompok'] ?: ('KT-' . str_pad((string)$r['nomor'], 2, '0', STR_PAD_LEFT));
        $html .= '<div class="check" style="align-items:center;gap:12px;">'
            . '<label style="flex:1;margin:0;display:flex;gap:8px;align-items:center;font-weight:600;">'
            . '<input type="checkbox" name="kelompok_id[]" value="' . $kid . '"' . $cek . ' style="width:auto;">'
            . e($kode . ' — ' . ($r['nama_kelompok'] ?: ('Kelompok ' . $r['nomor'])))
            . '</label>'
            . '<span style="font-size:12px;white-space:nowrap;">Luas (ha)</span>'
            . '<input name="luas_kel[' . $kid . ']" type="number" step="0.01" min="0" value="' . e((string)$luas) . '" style="max-width:110px;margin:0;" placeholder="0">'
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function ensure_anggota_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS anggota (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_anggota VARCHAR(20) UNIQUE NOT NULL,
            nik VARCHAR(20),
            nama VARCHAR(100) NOT NULL,
            jenis_kelamin VARCHAR(5) DEFAULT 'L',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $acols = array_column(db()->query('SHOW COLUMNS FROM anggota')->fetchAll(), 'Field');
        $adds = [
            'kelompok_tani' => "VARCHAR(120) NULL",
            'plasma' => "TINYINT(1) NOT NULL DEFAULT 0",
            'punya_tanah' => "TINYINT(1) NOT NULL DEFAULT 0",
            'luas_tanah' => "DECIMAL(10,2) NULL",
            'stdb' => "VARCHAR(10) NOT NULL DEFAULT 'belum'",
            'no_stdb' => "VARCHAR(50) NULL",
            'foto' => "VARCHAR(120) NULL",
            'ktp_file' => "VARCHAR(120) NULL",
            'sertifikat_file' => "VARCHAR(120) NULL",
            'catatan_verifikasi' => "TEXT NULL",
            'jabatan_kelompok' => "VARCHAR(50) NULL DEFAULT 'Anggota'",
            'username' => "VARCHAR(50) NULL",
            'password' => "VARCHAR(255) NULL",
        ];
        foreach ($adds as $col => $def) {
            if (!in_array($col, $acols, true)) {
                db()->exec("ALTER TABLE anggota ADD COLUMN `$col` $def");
            }
        }
        db()->exec("ALTER TABLE anggota MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        migrasi_login_ke_anggota();
    } catch (Throwable $e) {
        // skip
    }
}

function migrasi_login_ke_anggota(): void {
    try {
        $st = db()->query("SELECT * FROM users WHERE role='anggota' AND anggota_id IS NOT NULL");
        foreach ($st as $u) {
            $ag = db()->prepare('SELECT username, password FROM anggota WHERE id=?');
            $ag->execute([$u['anggota_id']]);
            $a = $ag->fetch();
            if (!$a) {
                continue;
            }
            if (empty($a['username'])) {
                db()->prepare('UPDATE anggota SET username=?, password=? WHERE id=?')
                    ->execute([$u['username'], $u['password'], $u['anggota_id']]);
            }
        }
        db()->exec("DELETE FROM users WHERE role='anggota'");
    } catch (Throwable $e) {
    }
}

function sinkron_ketua_kelompok(int $idKelompok): void {
    $pdo = db();
    $k = $pdo->prepare('SELECT id, nomor FROM kelompok WHERE id=?');
    $k->execute([$idKelompok]);
    $kel = $k->fetch();
    if (!$kel) {
        return;
    }
    $ketua = null;
    foreach (anggota_di_kelompok((int)$kel['nomor']) as $a) {
        if (($a['jabatan'] ?? $a['jabatan_kelompok'] ?? '') === 'Ketua') {
            $ketua = $a;
            break;
        }
    }
    $pdo->prepare('UPDATE kelompok SET nama_ketua=?, no_hp_ketua=? WHERE id=?')
        ->execute([$ketua['nama'] ?? '', $ketua['no_hp'] ?? '', $idKelompok]);
}

function set_jabatan_kelompok(int $anggotaId, int $idKelompok, string $jabatan): void {
    $pdo = db();
    $list = jabatan_kelompok_opsi();
    if (!in_array($jabatan, $list, true)) {
        $jabatan = 'Anggota';
    }
    $k = $pdo->prepare('SELECT nomor FROM kelompok WHERE id=?');
    $k->execute([$idKelompok]);
    $nomor = (int)$k->fetchColumn();
    if ($jabatan === 'Ketua') {
        foreach (anggota_di_kelompok($nomor) as $a) {
            if ((int)$a['id'] !== $anggotaId && ($a['jabatan_kelompok'] ?? '') === 'Ketua') {
                $pdo->prepare("UPDATE anggota SET jabatan_kelompok='Anggota' WHERE id=?")->execute([$a['id']]);
            }
        }
    }
    tambah_anggota_ke_kelompok($anggotaId, $idKelompok, $jabatan);
    sinkron_ketua_kelompok($idKelompok);
}

function jabatan_kelompok_opsi(): array {
    return ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Anggota'];
}

function ensure_kas_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS kas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            arah ENUM('masuk','keluar') NOT NULL,
            kategori VARCHAR(80) NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // skip
    }
}

function ensure_tbs_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS lahan_sawit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            anggota_id INT NOT NULL,
            id_kelompok INT NULL,
            legalitas VARCHAR(20) DEFAULT 'SKT',
            luas_hektar DECIMAL(10,2) DEFAULT 0,
            jumlah_pokok INT DEFAULT 0,
            tahun_tanam YEAR NULL,
            lokasi_desa VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS harga_tbs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal_berlaku DATE NOT NULL,
            kategori VARCHAR(20) NOT NULL,
            harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
            harga_jual_pks DECIMAL(12,2) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_harga (tanggal_berlaku, kategori)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS timbangan_tbs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATETIME NOT NULL,
            anggota_id INT NOT NULL,
            lahan_id INT NULL,
            id_kelompok INT NULL,
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
            bersih_petani DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $kcols = array_column(db()->query('SHOW COLUMNS FROM kelompok')->fetchAll(), 'Field');
        if (!in_array('fee_per_kg', $kcols, true)) {
            db()->exec('ALTER TABLE kelompok ADD COLUMN fee_per_kg DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
        try {
            db()->exec('ALTER TABLE timbangan_tbs MODIFY anggota_id INT NULL');
        } catch (Throwable $e) {
        }
        try {
            $lcols = array_column(db()->query('SHOW COLUMNS FROM lahan_sawit')->fetchAll(), 'Field');
            if (!in_array('atas_nama_shm', $lcols, true)) {
                db()->exec('ALTER TABLE lahan_sawit ADD COLUMN atas_nama_shm VARCHAR(120) NULL');
            }
            if (!in_array('no_shm', $lcols, true)) {
                db()->exec('ALTER TABLE lahan_sawit ADD COLUMN no_shm VARCHAR(80) NULL');
            }
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
        // skip
    }
}

function kategori_umur_sawit(?int $tahunTanam): string {
    if (!$tahunTanam) {
        return 'dewasa';
    }
    $umur = (int)date('Y') - $tahunTanam;
    if ($umur <= 5) {
        return 'muda';
    }
    if ($umur <= 10) {
        return 'dewasa';
    }
    return 'super';
}

function harga_tbs_hari(string $tanggal, string $kategori): array {
    $st = db()->prepare('SELECT * FROM harga_tbs WHERE tanggal_berlaku<=? AND kategori=? ORDER BY tanggal_berlaku DESC LIMIT 1');
    $st->execute([$tanggal, $kategori]);
    $row = $st->fetch();
    return $row ?: ['harga_beli' => 0, 'harga_jual_pks' => 0];
}

function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function sort_params(): array {
    $k = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['urut'] ?? ''));
    $d = strtolower((string)($_GET['arah'] ?? 'asc'));
    if ($d !== 'desc') {
        $d = 'asc';
    }
    return [$k, $d];
}

function sql_urut(array $map, string $defaultSql): string {
    [$k, $d] = sort_params();
    if ($k === '' || !isset($map[$k])) {
        return $defaultSql;
    }
    return $map[$k] . ' ' . strtoupper($d);
}

function th_urut(string $key, string $label): string {
    [$k, $d] = sort_params();
    $next = ($k === $key && $d === 'asc') ? 'desc' : 'asc';
    $q = $_GET;
    $q['urut'] = $key;
    $q['arah'] = $next;
    $sorted = ($k === $key) ? ($d === 'desc' ? 'sorted-desc' : 'sorted-asc') : '';
    $href = '?' . http_build_query($q);
    return '<th class="' . $sorted . '"><a class="th-sort" href="' . e($href) . '">' . e($label) . '</a></th>';
}

function persist_auth(array $user): void {
    $_SESSION['user'] = $user;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Permintaan ditolak (keamanan). Muat ulang halaman, lalu kirim lagi.');
    }
}

function login_throttle_file(string $key): string {
    $dir = sys_get_temp_dir() . '/koperasi_throttle';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir . '/' . hash('sha256', $key);
}

function login_throttle_hit(string $user): ?string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $f = login_throttle_file($ip . '|' . strtolower($user));
    $n = 0;
    $t = time();
    if (is_file($f)) {
        $d = json_decode((string)file_get_contents($f), true) ?: [];
        if (($d['until'] ?? 0) > $t) {
            return 'Terlalu banyak percobaan. Coba lagi beberapa menit.';
        }
        if (($d['start'] ?? 0) < $t - 900) {
            $d = ['n' => 0, 'start' => $t];
        }
        $n = (int)($d['n'] ?? 0);
    }
    $n++;
    $data = ['n' => $n, 'start' => $t];
    if ($n >= 5) {
        $data['until'] = $t + 900;
        file_put_contents($f, json_encode($data), LOCK_EX);
        return 'Terlalu banyak percobaan. Coba lagi 15 menit.';
    }
    file_put_contents($f, json_encode($data), LOCK_EX);
    return null;
}

function login_throttle_clear(string $user): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $f = login_throttle_file($ip . '|' . strtolower($user));
    if (is_file($f)) {
        @unlink($f);
    }
}

function read_auth_cookie(?string $raw): ?array {
    if (!$raw || !str_contains($raw, '.')) {
        return null;
    }
    [$payload, $sig] = explode('.', $raw, 2);
    if (!hash_equals(hash_hmac('sha256', $payload, AUTH_KEY), $sig)) {
        return null;
    }
    $u = json_decode(base64_decode($payload), true);
    return is_array($u) ? $u : null;
}

function consume_ticket(?string $t): ?array {
    if (!$t || !preg_match('/^[a-f0-9]{32}$/', $t)) {
        return null;
    }
    $f = sys_get_temp_dir() . '/koperasi_tickets/' . $t;
    if (!is_file($f)) {
        return null;
    }
    $u = json_decode((string)file_get_contents($f), true);
    @unlink($f);
    return is_array($u) ? $u : null;
}

function issue_ticket(array $user): string {
    $dir = sys_get_temp_dir() . '/koperasi_tickets';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $t = bin2hex(random_bytes(16));
    file_put_contents($dir . '/' . $t, json_encode($user));
    return $t;
}

function auth(): ?array {
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    $u = consume_ticket($_GET['t'] ?? null);
    if ($u) {
        $_SESSION['user'] = $u;
        return $u;
    }
    return null;
}

function require_login(): void {
    if (!auth()) {
        header('Location: login.php');
        exit;
    }
}

function require_staff(): void {
    require_login();
    if (!in_array(auth()['role'], ['admin', 'pengurus'], true)) {
        header('Location: dashboard.php');
        exit;
    }
}

function flash(string $key, ?string $val = null) {
    if ($val !== null) {
        $_SESSION['flash'][$key] = $val;
        return;
    }
    $m = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $m;
}

function tgl($d): string {
    if (!$d) {
        return '-';
    }
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($d);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function upload_image(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
        return null;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        return null;
    }
    $mime = $info['mime'] ?? '';
    $ok = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $type = $info[2] ?? 0;
    if (!isset($ok[$type]) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $name = $field . '_' . bin2hex(random_bytes(12)) . '.jpg';
    $dest = $dir . '/' . $name;
    if ($type === IMAGETYPE_JPEG) {
        $src = @imagecreatefromjpeg($tmp);
    } elseif ($type === IMAGETYPE_PNG) {
        $src = @imagecreatefrompng($tmp);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($tmp);
    } else {
        $src = false;
    }
    if (!$src) {
        $name = $field . '_' . bin2hex(random_bytes(12)) . '.' . $ok[$type];
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return null;
        }
        @chmod($dest, 0640);
        return $name;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1 || $w > 8000 || $h > 8000) {
        imagedestroy($src);
        return null;
    }
    $max = 1600;
    $nw = $w;
    $nh = $h;
    if ($w > $max || $h > $max) {
        $scale = $max / max($w, $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
    }
    $out = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($out, $dest, 82);
    imagedestroy($src);
    imagedestroy($out);
    @chmod($dest, 0640);
    return $name;
}

function berkas_url(int $anggotaId, string $jenis): string {
    return 'berkas.php?id=' . $anggotaId . '&jenis=' . rawurlencode($jenis);
}

function simpan_berkas_anggota(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($_FILES[$field]['size'] ?? 0) > 10 * 1024 * 1024) {
        return null;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    $ok = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    $type = $info[2] ?? 0;
    if (!$info || !isset($ok[$type])) {
        return null;
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ok[$type];
    if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
        return null;
    }
    @chmod($dir . '/' . $name, 0640);
    return $name;
}

function update_berkas_anggota(int $anggotaId): void {
    $set = [];
    $par = [];
    foreach (['foto', 'ktp_file', 'sertifikat_file'] as $f) {
        $nama = simpan_berkas_anggota($f);
        if ($nama) {
            $set[] = "`$f`=?";
            $par[] = $nama;
        }
    }
    if (!$set) {
        return;
    }
    $par[] = $anggotaId;
    db()->prepare('UPDATE anggota SET ' . implode(',', $set) . ' WHERE id=?')->execute($par);
}

function start_user_session(array $row): void {
    session_regenerate_id(true);
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    persist_auth([
        'id' => $row['id'],
        'username' => $row['username'],
        'nama' => $row['nama'],
        'role' => $row['role'],
        'anggota_id' => $row['anggota_id'] ?? null,
        'status' => $row['status'] ?? null,
    ]);
}

function username_sudah_dipakai(string $username, ?int $kecualiAnggotaId = null): bool {
    $st = db()->prepare("SELECT id FROM users WHERE username=? AND role IN ('admin','pengurus')");
    $st->execute([$username]);
    if ($st->fetch()) {
        return true;
    }
    $sql = 'SELECT id FROM anggota WHERE username=?';
    $par = [$username];
    if ($kecualiAnggotaId) {
        $sql .= ' AND id<>?';
        $par[] = $kecualiAnggotaId;
    }
    $st = db()->prepare($sql);
    $st->execute($par);
    return (bool)$st->fetch();
}

function try_login(string $username, string $password, array $roles): string {
    $wait = login_throttle_hit($username);
    if ($wait) {
        return $wait;
    }
    ensure_anggota_schema();
    $khususAnggota = in_array('anggota', $roles, true) && !in_array('admin', $roles, true);
    if ($khususAnggota) {
        $st = db()->prepare('SELECT * FROM anggota WHERE username = ?');
        $st->execute([$username]);
        $a = $st->fetch();
        if (!$a || empty($a['password']) || !password_verify($password, $a['password'])) {
            $stU = db()->prepare("SELECT id FROM users WHERE username=? AND role IN ('admin','pengurus')");
            $stU->execute([$username]);
            if ($stU->fetch()) {
                return 'Ini akun admin. Masuk lewat halaman Login Admin.';
            }
            return 'Username atau kata sandi tidak sesuai.';
        }
        login_throttle_clear($username);
        if (($a['status'] ?? '') === 'pending') {
            return 'Pendaftaran masih menunggu persetujuan pengurus.';
        }
        if (($a['status'] ?? '') === 'nonaktif') {
            return 'Keanggotaan nonaktif. Hubungi pengurus.';
        }
        start_user_session([
            'id' => 0,
            'username' => $a['username'],
            'nama' => $a['nama'],
            'role' => 'anggota',
            'anggota_id' => $a['id'],
            'status' => $a['status'] ?? 'aktif',
        ]);
        return '';
    }
    $st = db()->prepare("SELECT * FROM users WHERE username = ? AND role IN ('admin','pengurus')");
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password'])) {
        $cekAgt = db()->prepare('SELECT id FROM anggota WHERE username=?');
        $cekAgt->execute([$username]);
        if ($cekAgt->fetch()) {
            return 'Ini akun anggota. Masuk lewat halaman Login Anggota.';
        }
        return 'Username atau kata sandi tidak sesuai.';
    }
    login_throttle_clear($username);
    if (!in_array($row['role'], $roles, true)) {
        return 'Ini akun admin/pengurus. Masuk lewat halaman Login Admin.';
    }
    start_user_session($row);
    return '';
}

function after_login_redirect(): void {
    $t = issue_ticket($_SESSION['user']);
    header('Location: dashboard.php?t=' . $t);
    exit;
}

function catat_simpanan_pokok(int $anggotaId, ?int $by = null): void {
    $jumlah = (float)(setting()['simpanan_pokok'] ?? 500000);
    if ($jumlah <= 0) {
        return;
    }
    $jenis = db()->query("SELECT id FROM jenis_simpanan WHERE kode='SPK' LIMIT 1")->fetchColumn();
    if (!$jenis) {
        $jenis = db()->query('SELECT id FROM jenis_simpanan ORDER BY id LIMIT 1')->fetchColumn();
    }
    if (!$jenis) {
        return;
    }
    $cek = db()->prepare('SELECT COUNT(*) FROM simpanan WHERE anggota_id=? AND jenis_id=?');
    $cek->execute([$anggotaId, $jenis]);
    if ((int)$cek->fetchColumn() > 0) {
        return;
    }
    db()->prepare('INSERT INTO simpanan (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)')
        ->execute([$anggotaId, $jenis, date('Y-m-d'), $jumlah, 'Simpanan pokok otomatis (pengaturan)', $by]);
}

function jenis_simpanan_id(string $kode): ?int {
    $id = db()->query("SELECT id FROM jenis_simpanan WHERE kode=" . db()->quote($kode) . " LIMIT 1")->fetchColumn();
    return $id ? (int)$id : null;
}

function ensure_simpanan_sukarela_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS simpanan_sukarela (
            id INT AUTO_INCREMENT PRIMARY KEY,
            anggota_id INT NOT NULL,
            jenis_id INT NOT NULL,
            tanggal DATE NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $jid = jenis_simpanan_id('SSK');
        if ($jid) {
            $n = (int)db()->query('SELECT COUNT(*) FROM simpanan_sukarela')->fetchColumn();
            if ($n === 0) {
                $ada = (int)db()->query('SELECT COUNT(*) FROM simpanan WHERE jenis_id=' . (int)$jid)->fetchColumn();
                if ($ada > 0) {
                    db()->exec('INSERT INTO simpanan_sukarela (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by,created_at)
                        SELECT anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by,created_at FROM simpanan WHERE jenis_id=' . (int)$jid);
                    db()->exec('DELETE FROM simpanan WHERE jenis_id=' . (int)$jid);
                }
            }
        }
    } catch (Throwable $e) {
    }
}

function tabel_simpanan_jenis(?int $jenisId): string {
    ensure_simpanan_sukarela_schema();
    $ssk = jenis_simpanan_id('SSK');
    if ($ssk && $jenisId && (int)$jenisId === $ssk) {
        return 'simpanan_sukarela';
    }
    return 'simpanan';
}

function sql_union_simpanan(string $alias = 's'): string {
    ensure_simpanan_sukarela_schema();
    return "(SELECT id, anggota_id, jenis_id, tanggal, jumlah, keterangan, created_by, created_at FROM simpanan
        UNION ALL
        SELECT id, anggota_id, jenis_id, tanggal, jumlah, keterangan, created_by, created_at FROM simpanan_sukarela) $alias";
}

function insert_simpanan_row(int $anggotaId, int $jenisId, string $tgl, float $jumlah, string $ket, ?int $by = null): int {
    $tbl = tabel_simpanan_jenis($jenisId);
    db()->prepare("INSERT INTO `$tbl` (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$anggotaId, $jenisId, $tgl, $jumlah, $ket, $by]);
    return (int)db()->lastInsertId();
}


function tanggal_berdiri_koperasi(): DateTime {
    $s = setting();
    if (!empty($s['tanggal_berdiri'])) {
        $d = DateTime::createFromFormat('Y-m-d', substr((string)$s['tanggal_berdiri'], 0, 10));
        if ($d) {
            $d->modify('first day of this month');
            return $d;
        }
    }
    $tahun = (int)($s['tahun_berdiri'] ?? 2012);
    if ($tahun < 1900) {
        $tahun = 2012;
    }
    return new DateTime(sprintf('%04d-01-01', $tahun));
}

function tanggal_saldo_awal_simpanan(): string {
    return '2025-12-31';
}

function tanggal_mulai_wajib_otomatis(): DateTime {
    return new DateTime('2026-01-01');
}

function ket_saldo_awal_simpanan(): string {
    return 'Saldo awal s.d. 31 Des 2025';
}

function simpan_saldo_awal(int $anggotaId, string $kodeJenis, float $jumlah, ?int $by = null): bool {
    $jid = jenis_simpanan_id($kodeJenis);
    if (!$jid) {
        return false;
    }
    $ket = ket_saldo_awal_simpanan();
    $tgl = tanggal_saldo_awal_simpanan();
    $tbl = tabel_simpanan_jenis($jid);
    $st = db()->prepare("SELECT id FROM `$tbl` WHERE anggota_id=? AND jenis_id=? AND keterangan=? ORDER BY id LIMIT 1");
    $st->execute([$anggotaId, $jid, $ket]);
    $id = $st->fetchColumn();
    if ($id) {
        db()->prepare("UPDATE `$tbl` SET jumlah=?, tanggal=? WHERE id=?")->execute([$jumlah, $tgl, $id]);
        return true;
    }
    insert_simpanan_row($anggotaId, $jid, $tgl, $jumlah, $ket, $by);
    return true;
}

function catat_simpanan_wajib_tunggakan(int $anggotaId, ?int $by = null): int {
    $perBulan = (float)(setting()['simpanan_wajib'] ?? 50000);
    if ($perBulan <= 0) {
        return 0;
    }
    $jenis = jenis_simpanan_id('SWJ');
    if (!$jenis) {
        $row = db()->query("SELECT id FROM jenis_simpanan WHERE nama LIKE '%wajib%' LIMIT 1")->fetchColumn();
        $jenis = $row ? (int)$row : null;
    }
    if (!$jenis) {
        return 0;
    }
    $st = db()->prepare('SELECT DATE_FORMAT(tanggal, "%Y-%m") ym FROM simpanan WHERE anggota_id=? AND jenis_id=? AND keterangan<>?');
    $st->execute([$anggotaId, $jenis, ket_saldo_awal_simpanan()]);
    $punya = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $ins = db()->prepare('INSERT INTO simpanan (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)');
    $cur = tanggal_mulai_wajib_otomatis();
    try {
        $daftar = db()->prepare('SELECT tanggal_daftar FROM anggota WHERE id=?');
        $daftar->execute([$anggotaId]);
        $td = $daftar->fetchColumn();
        if ($td) {
            $dd = DateTime::createFromFormat('Y-m-d', substr((string)$td, 0, 10));
            if ($dd) {
                $dd->modify('first day of this month');
                if ($dd > $cur) {
                    $cur = $dd;
                }
            }
        }
    } catch (Throwable $e) {
    }
    $akhir = new DateTime('first day of this month');
    if ($akhir < $cur) {
        return 0;
    }
    $n = 0;
    $bulanNama = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    while ($cur <= $akhir) {
        $ym = $cur->format('Y-m');
        if (!isset($punya[$ym])) {
            $tgl = $cur->format('Y-m-t');
            $ket = 'Wajib ' . $bulanNama[(int)$cur->format('n')] . ' ' . $cur->format('Y');
            $ins->execute([$anggotaId, $jenis, $tgl, $perBulan, $ket, $by]);
            $n++;
        }
        $cur->modify('+1 month');
    }
    return $n;
}

function sinkron_simpanan_wajib_semua(?int $by = null): int {
    $ids = db()->query("SELECT id FROM anggota WHERE status='aktif'")->fetchAll(PDO::FETCH_COLUMN);
    $total = 0;
    foreach ($ids as $id) {
        $total += catat_simpanan_wajib_tunggakan((int)$id, $by);
    }
    return $total;
}

function catat_simpanan_awal_anggota(int $anggotaId, ?int $by = null): void {
    catat_simpanan_pokok($anggotaId, $by);
    catat_simpanan_wajib_tunggakan($anggotaId, $by);
}

function label_kelompok($v): string {
    $n = nomor_kelompok($v);
    return $n > 0 ? 'Kelompok ' . $n : '—';
}

function nomor_kelompok($v): int {
    if ($v === null || $v === '') {
        return 0;
    }
    $s = trim((string)$v);
    if ($s !== '' && ctype_digit($s)) {
        $n = (int)$s;
        return ($n >= 1 && $n <= 21) ? $n : 0;
    }
    if (preg_match('/(\d{1,2})/', $s, $m)) {
        $n = (int)$m[1];
        return ($n >= 1 && $n <= 21) ? $n : 0;
    }
    return 0;
}

function anggota_di_kelompok(int $nomor): array {
    ensure_anggota_kelompok_schema();
    $k = db()->prepare('SELECT id FROM kelompok WHERE nomor=?');
    $k->execute([$nomor]);
    $kid = $k->fetchColumn();
    $out = [];
    if ($kid) {
        $st = db()->prepare('SELECT a.*, ak.jabatan AS jabatan_kelompok FROM anggota a
            JOIN anggota_kelompok ak ON ak.anggota_id=a.id AND ak.id_kelompok=?
            ORDER BY a.nama');
        $st->execute([(int)$kid]);
        $out = $st->fetchAll();
    }
    if (!$out) {
        $rows = db()->query('SELECT * FROM anggota ORDER BY nama')->fetchAll();
        foreach ($rows as $a) {
            if (nomor_kelompok($a['kelompok_tani'] ?? '') === $nomor) {
                $out[] = $a;
            }
        }
    }
    usort($out, function ($x, $y) {
        $order = ['Ketua' => 1, 'Wakil Ketua' => 2, 'Sekretaris' => 3, 'Bendahara' => 4, 'Anggota' => 5];
        $jx = $order[$x['jabatan_kelompok'] ?? 'Anggota'] ?? 9;
        $jy = $order[$y['jabatan_kelompok'] ?? 'Anggota'] ?? 9;
        if ($jx !== $jy) {
            return $jx <=> $jy;
        }
        return strcasecmp($x['nama'], $y['nama']);
    });
    return $out;
}

function options_kelompok_id(array $selectedIds = [], array $kecualiIds = []): string {
    $html = '';
    try {
        $rows = db()->query('SELECT id, nomor, kode_kelompok, nama_kelompok FROM kelompok ORDER BY nomor')->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }
    $kecualiIds = array_map('intval', $kecualiIds);
    foreach ($rows as $r) {
        $kid = (int)$r['id'];
        if (in_array($kid, $kecualiIds, true)) {
            continue;
        }
        $sel = in_array($kid, $selectedIds, true) ? ' selected' : '';
        $kode = $r['kode_kelompok'] ?: ('KT-' . str_pad((string)$r['nomor'], 2, '0', STR_PAD_LEFT));
        $html .= '<option value="' . $kid . '"' . $sel . '>'
            . e($kode . ' — ' . ($r['nama_kelompok'] ?: ('Kelompok ' . $r['nomor'])))
            . '</option>';
    }
    return $html;
}

function html_slot_kelompok(int $max = 5, array $kecualiIds = [], string $labelJml = 'Berapa kelompok yang dimiliki?', bool $bolehNol = false): string {
    $opts = '<option value="">Pilih kelompok</option>' . options_kelompok_id([], $kecualiIds);
    $html = '<label>' . e($labelJml) . '</label>';
    $html .= '<select name="jml_kelompok" id="jmlKel" onchange="tampilSlotKel()">';
    if ($bolehNol) {
        $html .= '<option value="0" selected>Tidak menambah</option>';
    }
    for ($i = 1; $i <= $max; $i++) {
        $sel = (!$bolehNol && $i === 1) ? ' selected' : '';
        $html .= '<option value="' . $i . '"' . $sel . '>' . $i . ' kelompok</option>';
    }
    $html .= '</select><div id="slotKel" style="margin-top:10px;">';
    for ($i = 1; $i <= $max; $i++) {
        $on = !$bolehNol && $i === 1;
        $html .= '<div class="grid-2 slot-kel" data-n="' . $i . '"' . ($on ? '' : ' style="display:none"') . '>';
        $labelSlot = $bolehNol ? ('Kelompok baru ' . $i) : ('Kelompok ' . $i);
        $html .= '<div><label>' . e($labelSlot) . '</label><select name="kelompok_id[]"' . ($on ? '' : ' disabled') . '>' . $opts . '</select></div>';
        $html .= '<div><label>Luas di kelompok ini (ha)</label><input name="luas_kel[]" type="number" step="0.01" min="0" placeholder="0"' . ($on ? '' : ' disabled') . '></div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function options_kelompok($selected = ''): string {
    $sel = nomor_kelompok($selected);
    $html = '<option value="">Pilih kelompok</option>';
    try {
        $rows = db()->query('SELECT id, nomor, kode_kelompok, nama_kelompok FROM kelompok ORDER BY nomor')->fetchAll();
        if ($rows) {
            foreach ($rows as $r) {
                $html .= '<option value="' . (int)$r['nomor'] . '"' . ($sel === (int)$r['nomor'] ? ' selected' : '') . '>'
                    . e(($r['kode_kelompok'] ?: ('KT-' . str_pad((string)$r['nomor'], 2, '0', STR_PAD_LEFT))) . ' — ' . ($r['nama_kelompok'] ?: ('Kelompok ' . $r['nomor'])))
                    . '</option>';
            }
            return $html;
        }
    } catch (Throwable $e) {
        // fallback
    }
    for ($i = 1; $i <= 21; $i++) {
        $html .= '<option value="' . $i . '"' . ($sel === $i ? ' selected' : '') . '>KT-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . ' — Kelompok ' . $i . '</option>';
    }
    return $html;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $kosongKarenaUkuran = empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    if ($kosongKarenaUkuran) {
        $_SESSION['flash']['err'] = 'Data terlalu besar. Perkecil foto (total unggahan di bawah 30 MB), lalu daftar lagi.';
    } elseif ($script !== 'setup.php') {
        csrf_verify();
    }
}

function ensure_akuntansi_schema(): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS coa_akun (
            kode VARCHAR(10) PRIMARY KEY,
            nama VARCHAR(120) NOT NULL,
            kategori VARCHAR(30) NOT NULL,
            saldo_normal ENUM('debit','kredit') NOT NULL DEFAULT 'debit'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS jurnal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_bukti VARCHAR(30) NOT NULL,
            tanggal DATE NOT NULL,
            keterangan VARCHAR(255) NOT NULL,
            sumber VARCHAR(40) NULL,
            sumber_id INT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS jurnal_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            jurnal_id INT NOT NULL,
            kode_akun VARCHAR(10) NOT NULL,
            posisi ENUM('debit','kredit') NOT NULL,
            nominal DECIMAL(15,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ((int)db()->query('SELECT COUNT(*) FROM coa_akun')->fetchColumn() === 0) {
            $ins = db()->prepare('INSERT INTO coa_akun (kode,nama,kategori,saldo_normal) VALUES (?,?,?,?)');
            foreach ([
                ['1111','Kas tunai','Aset','debit'],
                ['1112','Bank','Aset','debit'],
                ['1211','Piutang pinjaman anggota','Aset','debit'],
                ['1311','Persediaan / TBS','Aset','debit'],
                ['2111','Simpanan pokok','Kewajiban','kredit'],
                ['2112','Simpanan wajib','Kewajiban','kredit'],
                ['2113','Simpanan sukarela','Kewajiban','kredit'],
                ['2211','Utang kas kelompok','Kewajiban','kredit'],
                ['3111','Modal / ekuitas','Ekuitas','kredit'],
                ['4111','Pendapatan bagi hasil pinjaman','Pendapatan','kredit'],
                ['4112','Pendapatan lain','Pendapatan','kredit'],
                ['4113','Pendapatan margin TBS','Pendapatan','kredit'],
                ['5111','Biaya operasional','Biaya','debit'],
                ['5112','Pembelian TBS petani','Biaya','debit'],
                ['1212','Piutang saprodi','Aset','debit'],
            ] as $r) { $ins->execute($r); }
        }
    } catch (Throwable $e) {}
}

function jurnal_sudah(string $sumber, int $sumberId): bool {
    $st = db()->prepare('SELECT id FROM jurnal WHERE sumber=? AND sumber_id=?');
    $st->execute([$sumber, $sumberId]);
    return (bool)$st->fetch();
}

function posting_jurnal(string $tanggal, string $ket, array $baris, ?string $sumber = null, ?int $sumberId = null, ?int $by = null): ?int {
    $d = 0; $k = 0;
    foreach ($baris as $b) {
        $n = (float)$b['nominal'];
        if ($n <= 0) continue;
        if (($b['posisi'] ?? '') === 'debit') $d += $n; else $k += $n;
    }
    if ($d <= 0 || abs($d - $k) > 0.5) return null;
    if ($sumber && $sumberId && jurnal_sudah($sumber, $sumberId)) return null;
    $no = 'JUR-' . date('Ymd', strtotime($tanggal)) . '-' . str_pad((string)(1 + (int)db()->query('SELECT COUNT(*) FROM jurnal')->fetchColumn()), 4, '0', STR_PAD_LEFT);
    db()->prepare('INSERT INTO jurnal (no_bukti,tanggal,keterangan,sumber,sumber_id,created_by) VALUES (?,?,?,?,?,?)')
        ->execute([$no, $tanggal, $ket, $sumber, $sumberId, $by]);
    $jid = (int)db()->lastInsertId();
    $ins = db()->prepare('INSERT INTO jurnal_detail (jurnal_id,kode_akun,posisi,nominal) VALUES (?,?,?,?)');
    foreach ($baris as $b) {
        if ((float)$b['nominal'] <= 0) continue;
        $ins->execute([$jid, $b['kode'], $b['posisi'], $b['nominal']]);
    }
    return $jid;
}

function kode_simpanan_coa(int $jenisId): string {
    $k = db()->prepare('SELECT kode FROM jenis_simpanan WHERE id=?');
    $k->execute([$jenisId]);
    $kode = (string)$k->fetchColumn();
    if ($kode === 'SPK') return '2111';
    if ($kode === 'SWJ') return '2112';
    return '2113';
}

function ensure_logistik_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS surat_jalan_pks (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS invoice_pks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surat_jalan_id INT NOT NULL,
            no_nota_pks VARCHAR(50) NOT NULL,
            berat_netto_pks DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_transfer DECIMAL(15,2) NOT NULL DEFAULT 0,
            susut_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
            tanggal_cair DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS saprodi (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS shu_alokasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tahun_buku INT NOT NULL,
            anggota_id INT NOT NULL,
            jasa_modal DECIMAL(15,2) NOT NULL DEFAULT 0,
            jasa_usaha DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_shu DECIMAL(15,2) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_shu (tahun_buku, anggota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $tcols = array_column(db()->query('SHOW COLUMNS FROM timbangan_tbs')->fetchAll(), 'Field');
        if (!in_array('surat_jalan_id', $tcols, true)) db()->exec('ALTER TABLE timbangan_tbs ADD COLUMN surat_jalan_id INT NULL');
        if (!in_array('potong_saprodi', $tcols, true)) db()->exec('ALTER TABLE timbangan_tbs ADD COLUMN potong_saprodi DECIMAL(15,2) NOT NULL DEFAULT 0');
        $pcols = array_column(db()->query('SHOW COLUMNS FROM pinjaman')->fetchAll(), 'Field');
        if (!in_array('agunan', $pcols, true)) db()->exec('ALTER TABLE pinjaman ADD COLUMN agunan VARCHAR(120) NULL');
        if (!in_array('no_perjanjian', $pcols, true)) db()->exec('ALTER TABLE pinjaman ADD COLUMN no_perjanjian VARCHAR(40) NULL');
        $acols = array_column(db()->query('SHOW COLUMNS FROM angsuran')->fetchAll(), 'Field');
        if (!in_array('sumber_bayar', $acols, true)) db()->exec("ALTER TABLE angsuran ADD COLUMN sumber_bayar VARCHAR(40) DEFAULT 'tunai'");
        $ag = array_column(db()->query('SHOW COLUMNS FROM anggota')->fetchAll(), 'Field');
        if (!in_array('status_keanggotaan', $ag, true)) db()->exec("ALTER TABLE anggota ADD COLUMN status_keanggotaan VARCHAR(20) DEFAULT 'Biasa'");
    } catch (Throwable $e) {}
}

function ensure_pinjaman_schema(): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $pdo = db();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pengajuan_pinjaman (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_pengajuan VARCHAR(30) NOT NULL UNIQUE,
            anggota_id INT NOT NULL,
            tanggal DATE NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            bagi_hasil_persen DECIMAL(5,2) NOT NULL DEFAULT 1,
            tenor INT NOT NULL DEFAULT 1,
            keperluan VARCHAR(255) NULL,
            agunan VARCHAR(120) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pengajuan',
            catatan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pencairan_pinjaman (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pengajuan_id INT NOT NULL,
            no_pinjaman VARCHAR(30) NOT NULL UNIQUE,
            no_perjanjian VARCHAR(40) NULL,
            tanggal_cair DATE NOT NULL,
            jumlah_cair DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_tagihan DECIMAL(15,2) NOT NULL DEFAULT 0,
            sisa DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'berjalan',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $acols = array_column($pdo->query('SHOW COLUMNS FROM angsuran')->fetchAll(), 'Field');
        foreach ([
            'pencairan_id' => 'INT NULL',
            'pokok' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'bagi_hasil' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'denda' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'sumber_bayar' => "VARCHAR(40) DEFAULT 'tunai'",
        ] as $col => $def) {
            if (!in_array($col, $acols, true)) {
                $pdo->exec("ALTER TABLE angsuran ADD COLUMN `$col` $def");
            }
        }
        $pcols = array_column($pdo->query('SHOW COLUMNS FROM pinjaman')->fetchAll(), 'Field');
        if (!in_array('pengajuan_id', $pcols, true)) $pdo->exec('ALTER TABLE pinjaman ADD COLUMN pengajuan_id INT NULL');
        if (!in_array('pencairan_id', $pcols, true)) $pdo->exec('ALTER TABLE pinjaman ADD COLUMN pencairan_id INT NULL');
        if (!in_array('agunan', $pcols, true)) $pdo->exec('ALTER TABLE pinjaman ADD COLUMN agunan VARCHAR(120) NULL');
        $pdo->exec("CREATE TABLE IF NOT EXISTS bukti_bayar_pinjaman (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS antrean_truk (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { migrasi_pecah_pinjaman(); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
}

function upload_bukti_bayar(string $field = 'bukti'): ?string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($_FILES[$field]['size'] ?? 0) > 8 * 1024 * 1024) {
        return null;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    $ext = 'jpg';
    if ($info && !empty($info['mime'])) {
        if ($info['mime'] === 'image/png') $ext = 'png';
        elseif ($info['mime'] === 'image/webp') $ext = 'webp';
        elseif ($info['mime'] !== 'image/jpeg') return null;
    } else {
        $raw = (string)@file_get_contents($tmp, false, null, 0, 12);
        if (strncmp($raw, "\xFF\xD8\xFF", 3) !== 0 && strncmp($raw, "\x89PNG", 4) !== 0) {
            return null;
        }
        if (strncmp($raw, "\x89PNG", 4) === 0) $ext = 'png';
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'bukti_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        return null;
    }
    @chmod($dir . '/' . $name, 0640);
    return $name;
}

function migrasi_pecah_pinjaman(): void {
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM pengajuan_pinjaman')->fetchColumn() > 0) return;
    $rows = $pdo->query('SELECT * FROM pinjaman ORDER BY id')->fetchAll();
    if (!$rows) return;
    $insG = $pdo->prepare('INSERT INTO pengajuan_pinjaman (no_pengajuan,anggota_id,tanggal,jumlah,bagi_hasil_persen,tenor,keperluan,agunan,status,catatan) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $insC = $pdo->prepare('INSERT INTO pencairan_pinjaman (pengajuan_id,no_pinjaman,no_perjanjian,tanggal_cair,jumlah_cair,total_tagihan,sisa,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        $stG = in_array($r['status'], ['berjalan','lunas','disetujui'], true) ? 'disetujui' : $r['status'];
        $noG = 'PGJ-' . preg_replace('/^PJM-/', '', (string)$r['no_pinjaman']);
        $insG->execute([$noG, $r['anggota_id'], $r['tanggal'], $r['jumlah'], $r['bagi_hasil_persen'] ?? 1, $r['tenor'], $r['keperluan'] ?? '', $r['agunan'] ?? null, $stG, $r['catatan'] ?? null]);
        $gid = (int)$pdo->lastInsertId();
        $cid = null;
        if (in_array($r['status'], ['berjalan','lunas','disetujui'], true)) {
            $insC->execute([$gid, $r['no_pinjaman'], $r['no_perjanjian'] ?? null, $r['tanggal'], $r['jumlah'], $r['total_tagihan'], $r['sisa'], $r['status'] === 'disetujui' ? 'berjalan' : $r['status'], null]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE angsuran SET pencairan_id=? WHERE pinjaman_id=?')->execute([$cid, $r['id']]);
        }
        $pdo->prepare('UPDATE pinjaman SET pengajuan_id=?, pencairan_id=? WHERE id=?')->execute([$gid, $cid, $r['id']]);
    }
    foreach ($pdo->query('SELECT * FROM angsuran WHERE (pokok IS NULL OR pokok=0) AND jumlah>0') as $g) {
        $pj = $pdo->prepare('SELECT * FROM pinjaman WHERE id=?');
        $pj->execute([$g['pinjaman_id']]);
        $p = $pj->fetch();
        if (!$p) continue;
        $tot = (float)$p['total_tagihan'];
        $pokokAwal = (float)$p['jumlah'];
        $rasio = $tot > 0 ? ((float)$g['jumlah'] / $tot) : 1;
        $pok = round($pokokAwal * $rasio, 0);
        $bh = max(0, (float)$g['jumlah'] - $pok);
        $pdo->prepare('UPDATE angsuran SET pokok=?, bagi_hasil=? WHERE id=?')->execute([$pok, $bh, $g['id']]);
    }
}

function hitung_tagihan_pinjaman(float $pokok, float $persen, int $tenor): float {
    return $pokok + ($pokok * $persen / 100 * max(1, $tenor));
}

function pecah_cicilan(array $cair, array $aju, float $jumlah, float $denda = 0): array {
    $tenor = max(1, (int)$aju['tenor']);
    $pokokAwal = (float)$aju['jumlah'];
    $total = (float)$cair['total_tagihan'];
    $bhTotal = max(0, $total - $pokokAwal);
    $pdo = db();
    $st = $pdo->prepare('SELECT COALESCE(SUM(pokok),0) sp, COALESCE(SUM(bagi_hasil),0) sb FROM angsuran WHERE pencairan_id=? OR pinjaman_id=?');
    $st->execute([(int)($cair['id'] ?? 0), (int)($cair['pinjaman_legacy_id'] ?? 0)]);
    $sum = $st->fetch();
    $sisaPokok = max(0, $pokokAwal - (float)$sum['sp']);
    $sisaBh = max(0, $bhTotal - (float)$sum['sb']);
    $bayar = max(0, $jumlah - $denda);
    $bh = min($sisaBh, min($bayar, round($bhTotal / $tenor, 0)));
    $pok = min($sisaPokok, max(0, $bayar - $bh));
    if ($pok + $bh < $bayar) {
        $sisa = $bayar - $pok - $bh;
        $tambahPok = min($sisaPokok - $pok, $sisa);
        $pok += $tambahPok;
        $sisa -= $tambahPok;
        $bh += min($sisaBh - $bh, $sisa);
    }
    return ['pokok' => $pok, 'bagi_hasil' => $bh, 'denda' => $denda, 'jumlah' => $pok + $bh + $denda];
}

function catat_angsuran_pecah(int $pinjamanId, float $jumlah, string $tgl, string $ket, ?int $by, string $sumber = 'tunai', float $denda = 0, bool $jurnal = true): ?int {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM pinjaman WHERE id=?');
    $st->execute([$pinjamanId]);
    $p = $st->fetch();
    if (!$p || !in_array($p['status'], ['berjalan', 'disetujui'], true)) return null;
    if ($jumlah <= 0 && $denda <= 0) return null;
    $jumlah = min($jumlah, (float)$p['sisa']);
    $aju = $p;
    if (!empty($p['pengajuan_id'])) {
        $g = $pdo->prepare('SELECT * FROM pengajuan_pinjaman WHERE id=?');
        $g->execute([$p['pengajuan_id']]);
        $aju = $g->fetch() ?: $p;
    }
    $cair = ['id' => (int)($p['pencairan_id'] ?? 0), 'total_tagihan' => $p['total_tagihan'], 'pinjaman_legacy_id' => $p['id']];
    $pecah = pecah_cicilan($cair, $aju, $jumlah, $denda);
    $ke = 1 + (int)$pdo->query('SELECT COUNT(*) FROM angsuran WHERE pinjaman_id=' . (int)$pinjamanId)->fetchColumn();
    $pdo->prepare('INSERT INTO angsuran (pinjaman_id,pencairan_id,angsuran_ke,tanggal,jumlah,pokok,bagi_hasil,denda,keterangan,sumber_bayar,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$pinjamanId, $p['pencairan_id'] ?: null, $ke, $tgl, $pecah['jumlah'], $pecah['pokok'], $pecah['bagi_hasil'], $pecah['denda'], $ket, $sumber, $by]);
    $aid = (int)$pdo->lastInsertId();
    $sisa = max(0, (float)$p['sisa'] - ($pecah['pokok'] + $pecah['bagi_hasil']));
    $status = $sisa <= 0 ? 'lunas' : 'berjalan';
    $pdo->prepare('UPDATE pinjaman SET sisa=?, status=? WHERE id=?')->execute([$sisa, $status, $pinjamanId]);
    if (!empty($p['pencairan_id'])) {
        $pdo->prepare('UPDATE pencairan_pinjaman SET sisa=?, status=? WHERE id=?')->execute([$sisa, $status, $p['pencairan_id']]);
    }
    if ($jurnal) {
        $baris = [['kode' => '1111', 'posisi' => 'debit', 'nominal' => $pecah['jumlah']]];
        $kredit = $pecah['pokok'] + $pecah['bagi_hasil'];
        if ($kredit > 0) $baris[] = ['kode' => '1211', 'posisi' => 'kredit', 'nominal' => $kredit];
        if ($pecah['denda'] > 0) $baris[] = ['kode' => '4112', 'posisi' => 'kredit', 'nominal' => $pecah['denda']];
        if (count($baris) === 1) $baris[] = ['kode' => '1211', 'posisi' => 'kredit', 'nominal' => $pecah['jumlah']];
        posting_jurnal($tgl, 'Angsuran '.$p['no_pinjaman'].' ke-'.$ke, $baris, 'angsuran', $aid, $by);
    }
    return $aid;
}

function hari_antrean_kelompok(int $nomor): int {
    if ($nomor <= 4) return 1;
    if ($nomor <= 8) return 2;
    if ($nomor <= 12) return 3;
    if ($nomor <= 16) return 4;
    return 5;
}

function ids_anggota_kelompok(int $idKelompok): array {
    $k = db()->prepare('SELECT id, nomor FROM kelompok WHERE id=?');
    $k->execute([$idKelompok]);
    $kel = $k->fetch();
    if (!$kel) {
        return [];
    }
    $nomor = (int)$kel['nomor'];
    $ids = [];
    foreach (db()->query('SELECT id, id_kelompok, kelompok_tani FROM anggota WHERE status=\'aktif\'') as $a) {
        if ((int)($a['id_kelompok'] ?? 0) === $idKelompok || nomor_kelompok($a['kelompok_tani'] ?? '') === $nomor) {
            $ids[] = (int)$a['id'];
        }
    }
    return $ids;
}

function potong_piutang_saprodi_kelompok(int $idKelompok, float $dana): float {
    $pakai = 0;
    foreach (ids_anggota_kelompok($idKelompok) as $aid) {
        if ($dana <= 0) {
            break;
        }
        $p = potong_piutang_saprodi($aid, $dana);
        $dana -= $p;
        $pakai += $p;
    }
    return $pakai;
}

function potong_angsuran_kelompok(int $idKelompok, float $dana, ?int $by): float {
    if ($dana <= 0) {
        return 0;
    }
    $ids = ids_anggota_kelompok($idKelompok);
    if (!$ids) {
        return 0;
    }
    $in = implode(',', array_map('intval', $ids));
    $rows = db()->query("SELECT id, sisa, anggota_id FROM pinjaman WHERE anggota_id IN ($in) AND status IN ('berjalan','disetujui') AND sisa>0 ORDER BY id")->fetchAll();
    $pakai = 0;
    foreach ($rows as $pj) {
        if ($dana <= 0) {
            break;
        }
        $pot = min((float)$pj['sisa'], $dana);
        if ($pot <= 0) {
            continue;
        }
        catat_angsuran_pecah((int)$pj['id'], $pot, date('Y-m-d'), 'Potong hasil TBS kelompok', $by, 'tbs', 0, false);
        $dana -= $pot;
        $pakai += $pot;
    }
    return $pakai;
}

function potong_piutang_saprodi(int $anggotaId, float $dana): float {
    if ($dana <= 0) return 0;
    $rows = db()->prepare("SELECT * FROM saprodi WHERE anggota_id=? AND sisa_piutang>0 ORDER BY id");
    $rows->execute([$anggotaId]);
    $pakai = 0;
    foreach ($rows as $r) {
        if ($dana <= 0) break;
        $pot = min((float)$r['sisa_piutang'], $dana);
        $sisa = (float)$r['sisa_piutang'] - $pot;
        db()->prepare("UPDATE saprodi SET sisa_piutang=?, status_bayar=? WHERE id=?")
            ->execute([$sisa, $sisa <= 0 ? 'lunas' : 'piutang', $r['id']]);
        $dana -= $pot;
        $pakai += $pot;
    }
    return $pakai;
}


function ensure_pengalihan_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS pengalihan_hak (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_pengalihan VARCHAR(30) NOT NULL UNIQUE,
            tanggal DATE NOT NULL,
            anggota_lama_id INT NOT NULL,
            nama_baru VARCHAR(100) NOT NULL,
            nik_baru VARCHAR(20) NULL,
            no_hp_baru VARCHAR(20) NULL,
            sukarela_opsi VARCHAR(20) NOT NULL DEFAULT 'alihkan',
            pokok_wajib_opsi VARCHAR(20) NOT NULL DEFAULT 'alihkan',
            file_sjb VARCHAR(160) NULL,
            file_ba VARCHAR(160) NULL,
            saldo_pokok DECIMAL(15,2) NOT NULL DEFAULT 0,
            saldo_wajib DECIMAL(15,2) NOT NULL DEFAULT 0,
            saldo_sukarela DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pengajuan',
            catatan TEXT NULL,
            anggota_baru_id INT NULL,
            created_by INT NULL,
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pc = array_column(db()->query('SHOW COLUMNS FROM pengalihan_hak')->fetchAll(), 'Field');
        $adds = [
            'sukarela_opsi' => "VARCHAR(20) NOT NULL DEFAULT 'alihkan'",
            'pokok_wajib_opsi' => "VARCHAR(20) NOT NULL DEFAULT 'alihkan'",
            'file_sjb' => 'VARCHAR(160) NULL',
            'file_ba' => 'VARCHAR(160) NULL',
            'saldo_pokok' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'saldo_wajib' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'saldo_sukarela' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'pengajuan'",
            'catatan' => 'TEXT NULL',
            'anggota_baru_id' => 'INT NULL',
            'created_by' => 'INT NULL',
            'approved_by' => 'INT NULL',
            'approved_at' => 'DATETIME NULL',
            'data_baru' => 'LONGTEXT NULL',
        ];
        foreach ($adds as $col => $def) {
            if (!in_array($col, $pc, true)) {
                try {
                    db()->exec("ALTER TABLE pengalihan_hak ADD COLUMN `$col` $def");
                    $pc[] = $col;
                } catch (Throwable $e) {
                }
            }
        }
        try {
            db()->exec('ALTER TABLE simpanan MODIFY jumlah DECIMAL(15,2) NOT NULL DEFAULT 0');
        } catch (Throwable $e) {
        }
        try {
            db()->exec("ALTER TABLE anggota MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
    }
}

function saldo_simpanan_jenis(int $anggotaId, string $kode): float {
    $jid = jenis_simpanan_id($kode);
    if (!$jid) {
        return 0;
    }
    $tbl = tabel_simpanan_jenis($jid);
    $st = db()->prepare("SELECT COALESCE(SUM(jumlah),0) FROM `$tbl` WHERE anggota_id=? AND jenis_id=?");
    $st->execute([$anggotaId, $jid]);
    return (float)$st->fetchColumn();
}

function ringkas_anggota_pengalihan(int $anggotaId): ?array {
    $st = db()->prepare('SELECT * FROM anggota WHERE id=?');
    $st->execute([$anggotaId]);
    $a = $st->fetch();
    if (!$a) {
        return null;
    }
    $lahan = [];
    try {
        $ls = db()->prepare('SELECT l.*, k.kode_kelompok FROM lahan_sawit l LEFT JOIN kelompok k ON k.id=l.id_kelompok WHERE l.anggota_id=? ORDER BY l.id');
        $ls->execute([$anggotaId]);
        $lahan = $ls->fetchAll();
    } catch (Throwable $e) {
    }
    $utang = 0;
    try {
        $u = db()->prepare("SELECT COALESCE(SUM(sisa),0) FROM pinjaman WHERE anggota_id=? AND status IN ('berjalan','disetujui') AND sisa>0");
        $u->execute([$anggotaId]);
        $utang = (float)$u->fetchColumn();
    } catch (Throwable $e) {
    }
    return [
        'id' => (int)$a['id'],
        'no_anggota' => $a['no_anggota'],
        'nama' => $a['nama'],
        'nik' => $a['nik'],
        'no_hp' => $a['no_hp'],
        'status' => $a['status'],
        'pokok' => saldo_simpanan_jenis($anggotaId, 'SPK'),
        'wajib' => saldo_simpanan_jenis($anggotaId, 'SWJ'),
        'sukarela' => saldo_simpanan_jenis($anggotaId, 'SSK'),
        'utang' => $utang,
        'lahan' => $lahan,
        'kelompok' => kelompok_anggota($anggotaId),
    ];
}

function simpan_berkas_pengalihan(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($_FILES[$field]['size'] ?? 0) > 8 * 1024 * 1024) {
        return null;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'] ?? '', PATHINFO_EXTENSION));
    $info = @getimagesize($tmp);
    if ($info) {
        $ok = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $type = $info[2] ?? 0;
        if (!isset($ok[$type])) {
            return null;
        }
        $ext = $ok[$type];
    } elseif ($ext !== 'pdf') {
        $raw = (string)@file_get_contents($tmp, false, null, 0, 5);
        if (strncmp($raw, '%PDF', 4) !== 0) {
            return null;
        }
        $ext = 'pdf';
    }
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
        return null;
    }
    @chmod($dir . '/' . $name, 0640);
    return $name;
}

function proses_setujui_pengalihan(int $id, ?int $by = null): string {
    ensure_pengalihan_schema();
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM pengalihan_hak WHERE id=?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p || $p['status'] !== 'pengajuan') {
        return 'Pengajuan tidak valid.';
    }
    $lamaId = (int)$p['anggota_lama_id'];
    $ringkas = ringkas_anggota_pengalihan($lamaId);
    if (!$ringkas) {
        return 'Anggota lama tidak ditemukan.';
    }
    if ($ringkas['utang'] > 0) {
        return 'Anggota lama masih punya sisa pinjaman. Lunasi dulu.';
    }
    $pdo->beginTransaction();
    try {
        $n = 1 + (int)$pdo->query('SELECT COUNT(*) FROM anggota')->fetchColumn();
        $noBaru = 'AGT-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        $lama = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
        $lama->execute([$lamaId]);
        $al = $lama->fetch();
        $dbaru = [];
        $sumberJson = (string)($p['data_baru'] ?? '');
        if ($sumberJson === '' && !empty($p['catatan']) && strncmp(trim((string)$p['catatan']), '{', 1) === 0) {
            $sumberJson = (string)$p['catatan'];
        }
        if ($sumberJson !== '') {
            $tmp = json_decode($sumberJson, true);
            if (is_array($tmp)) {
                $dbaru = $tmp;
            }
        }
        $jk = $dbaru['jenis_kelamin'] ?? ($al['jenis_kelamin'] ?? 'L');
        $pdo->prepare('INSERT INTO anggota (no_anggota,nik,nama,jenis_kelamin,tempat_lahir,tanggal_lahir,alamat,desa,kecamatan,no_hp,pekerjaan,kelompok_tani,plasma,punya_tanah,luas_tanah,stdb,no_stdb,tanggal_daftar,status,jabatan_kelompok,foto,ktp_file,sertifikat_file) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $noBaru,
                trim((string)$p['nik_baru']),
                trim((string)$p['nama_baru']),
                $jk,
                $dbaru['tempat_lahir'] ?? ($al['tempat_lahir'] ?? ''),
                ($dbaru['tanggal_lahir'] ?? '') ?: ($al['tanggal_lahir'] ?: null),
                $dbaru['alamat'] ?? ($al['alamat'] ?? ''),
                $dbaru['desa'] ?? ($al['desa'] ?? ''),
                $dbaru['kecamatan'] ?? ($al['kecamatan'] ?? ''),
                trim((string)($dbaru['no_hp'] ?? $p['no_hp_baru'])),
                $dbaru['pekerjaan'] ?? ($al['pekerjaan'] ?? 'Petani'),
                $al['kelompok_tani'] ?? '',
                1,
                1,
                $al['luas_tanah'] ?? null,
                (($dbaru['stdb'] ?? '') === 'sudah') ? 'sudah' : ($al['stdb'] ?? 'belum'),
                $dbaru['no_stdb'] ?? ($al['no_stdb'] ?? ''),
                date('Y-m-d'),
                'aktif',
                'Anggota',
                $dbaru['foto'] ?? null,
                $dbaru['ktp_file'] ?? null,
                $dbaru['sertifikat_file'] ?? null,
            ]);
        $baruId = (int)$pdo->lastInsertId();
        $uname = trim((string)($dbaru['username'] ?? ''));
        if ($uname === '' || username_sudah_dipakai($uname)) {
            $uname = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', (string)$p['nama_baru'])[0])) ?: 'anggota';
            $uname .= $baruId;
            if (username_sudah_dipakai($uname)) {
                $uname .= $baruId;
            }
        }
        $hash = !empty($dbaru['password_hash']) ? $dbaru['password_hash'] : password_hash('anggota123', PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE anggota SET username=?, password=? WHERE id=?')->execute([$uname, $hash, $baruId]);
        $kelBaru = $dbaru['kelompok'] ?? [];
        if ($kelBaru) {
            foreach ($kelBaru as $gk) {
                $kid = (int)($gk['id'] ?? 0);
                if ($kid > 0) {
                    tambah_anggota_ke_kelompok($baruId, $kid, 'Anggota');
                    set_luas_lahan_kelompok($baruId, $kid, (float)($gk['luas'] ?? 0));
                }
            }
        } else {
            foreach (kelompok_anggota($lamaId) as $gk) {
                tambah_anggota_ke_kelompok($baruId, (int)$gk['id_kelompok'], 'Anggota');
            }
        }
        try {
            $pdo->prepare('UPDATE lahan_sawit SET anggota_id=? WHERE anggota_id=?')->execute([$baruId, $lamaId]);
        } catch (Throwable $e) {
        }
        foreach (kelompok_anggota($lamaId) as $gk) {
            keluar_anggota_dari_kelompok($lamaId, (int)$gk['id_kelompok']);
        }
        $ins = $pdo->prepare('INSERT INTO simpanan (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)');
        $tgl = date('Y-m-d');
        $ket = 'Pengalihan hak ' . $p['no_pengalihan'];
        $alihPw = (($p['pokok_wajib_opsi'] ?? 'alihkan') === 'baru') ? 'baru' : 'alihkan';
        $baris = [];
        if ($alihPw === 'alihkan') {
            foreach ([['SPK', (float)$p['saldo_pokok'], '2111'], ['SWJ', (float)$p['saldo_wajib'], '2112']] as $pair) {
                $jid = jenis_simpanan_id($pair[0]);
                $nom = $pair[1];
                if (!$jid || $nom <= 0) {
                    continue;
                }
                $ins->execute([$lamaId, $jid, $tgl, -$nom, $ket . ' keluar', $by]);
                $ins->execute([$baruId, $jid, $tgl, $nom, $ket . ' masuk', $by]);
                $baris[] = ['kode' => $pair[2], 'posisi' => 'debit', 'nominal' => $nom];
                $baris[] = ['kode' => $pair[2], 'posisi' => 'kredit', 'nominal' => $nom];
            }
        } else {
            catat_simpanan_awal_anggota($baruId, $by);
        }
        $suk = (float)$p['saldo_sukarela'];
        $jidS = jenis_simpanan_id('SSK');
        if ($jidS && $suk > 0) {
            if ($p['sukarela_opsi'] === 'tunai') {
                insert_simpanan_row($lamaId, $jidS, $tgl, -$suk, $ket . ' sukarela diambil tunai', $by);
                posting_jurnal($tgl, 'Pengambilan sukarela pengalihan '.$p['no_pengalihan'], [
                    ['kode' => '2113', 'posisi' => 'debit', 'nominal' => $suk],
                    ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => $suk],
                ], 'pengalihan_sukarela', $id, $by);
            } else {
                insert_simpanan_row($lamaId, $jidS, $tgl, -$suk, $ket . ' sukarela alih', $by);
                insert_simpanan_row($baruId, $jidS, $tgl, $suk, $ket . ' sukarela masuk', $by);
                $baris[] = ['kode' => '2113', 'posisi' => 'debit', 'nominal' => $suk];
                $baris[] = ['kode' => '2113', 'posisi' => 'kredit', 'nominal' => $suk];
            }
        }
        if ($baris) {
            posting_jurnal($tgl, 'Mutasi simpanan pengalihan '.$p['no_pengalihan'].' '.$al['nama'].' → '.$p['nama_baru'], $baris, 'pengalihan', $id, $by);
        }
        $statusLama = $alihPw === 'alihkan' ? 'nonaktif' : 'pasif';
        $ketLama = $alihPw === 'alihkan'
            ? ('Keluar - Alih Hak ke '.$noBaru.' (pokok & wajib pindah)')
            : ('Pasif - Alih Hak lahan ke '.$noBaru.' (pokok & wajib tetap di ID lama; ID baru setor simpanan baru)');
        $pdo->prepare('UPDATE anggota SET status=?, catatan_verifikasi=?, plasma=0 WHERE id=?')
            ->execute([$statusLama, $ketLama, $lamaId]);
        $pdo->prepare("UPDATE pengalihan_hak SET status='disetujui', anggota_baru_id=?, approved_by=?, approved_at=NOW() WHERE id=?")
            ->execute([$baruId, $by, $id]);
        try {
            $pdo->prepare('INSERT INTO pengumuman (judul,isi,tanggal,publik) VALUES (?,?,?,0)')
                ->execute([
                    'Pengalihan hak '.$p['no_pengalihan'],
                    'ID '.$al['no_anggota'].' '.$al['nama'].' nonaktif (Keluar - Alih Hak). ID baru '.$noBaru.' '.$p['nama_baru'].' aktif. Username '.$uname.' / sandi anggota123.',
                    $tgl,
                ]);
        } catch (Throwable $e) {
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return '';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable $e2) {
            }
        }
        return $e->getMessage();
    }
}

function status_anggota(?int $id): string {
    if (!$id) {
        return '';
    }
    $st = db()->prepare('SELECT status FROM anggota WHERE id=?');
    $st->execute([$id]);
    return (string)($st->fetchColumn() ?: '');
}

function ensure_penarikan_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS penarikan_simpanan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_penarikan VARCHAR(30) NOT NULL UNIQUE,
            anggota_id INT NOT NULL,
            tanggal DATE NOT NULL,
            saldo_pokok DECIMAL(15,2) NOT NULL DEFAULT 0,
            saldo_wajib DECIMAL(15,2) NOT NULL DEFAULT 0,
            saldo_sukarela DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pengajuan',
            catatan TEXT NULL,
            created_by INT NULL,
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
    }
}

function proses_setujui_penarikan(int $id, ?int $by = null): string {
    ensure_penarikan_schema();
    ensure_akuntansi_schema();
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM penarikan_simpanan WHERE id=?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p || $p['status'] !== 'pengajuan') {
        return 'Pengajuan tidak valid.';
    }
    $aid = (int)$p['anggota_id'];
    $ag = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
    $ag->execute([$aid]);
    $a = $ag->fetch();
    if (!$a) {
        return 'Anggota tidak ditemukan.';
    }
    $utang = 0;
    try {
        $u = $pdo->prepare("SELECT COALESCE(SUM(sisa),0) FROM pinjaman WHERE anggota_id=? AND status IN ('berjalan','disetujui') AND sisa>0");
        $u->execute([$aid]);
        $utang = (float)$u->fetchColumn();
    } catch (Throwable $e) {
    }
    if ($utang > 0) {
        return 'Masih ada sisa pinjaman. Lunasi dulu.';
    }
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO simpanan (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)');
        $tgl = date('Y-m-d');
        $ket = 'Penarikan simpanan ' . $p['no_penarikan'];
        $baris = [];
        foreach ([['SPK', (float)$p['saldo_pokok'], '2111'], ['SWJ', (float)$p['saldo_wajib'], '2112'], ['SSK', (float)$p['saldo_sukarela'], '2113']] as $pair) {
            $jid = jenis_simpanan_id($pair[0]);
            $nom = $pair[1];
            if (!$jid || $nom <= 0) {
                continue;
            }
            insert_simpanan_row($aid, $jid, $tgl, -$nom, $ket, $by);
            $baris[] = ['kode' => $pair[2], 'posisi' => 'debit', 'nominal' => $nom];
        }
        $total = (float)$p['total'];
        if ($total > 0) {
            $baris[] = ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => $total];
            posting_jurnal($tgl, $ket . ' ' . $a['nama'], $baris, 'penarikan', $id, $by);
        }
        $pdo->prepare("UPDATE anggota SET status='nonaktif', catatan_verifikasi=? WHERE id=?")
            ->execute(['Nonaktif setelah penarikan simpanan ' . $p['no_penarikan'], $aid]);
        $pdo->prepare("UPDATE penarikan_simpanan SET status='disetujui', approved_by=?, approved_at=NOW() WHERE id=?")
            ->execute([$by, $id]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return '';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
        }
        return $e->getMessage();
    }
}
