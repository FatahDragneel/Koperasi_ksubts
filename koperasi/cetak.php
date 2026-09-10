<?php
require __DIR__ . '/config.php';
require_login();
$s = setting();
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus'], true);
$jenis = $_GET['jenis'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

function print_head(string $judul, array $s): void { ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= e($judul) ?></title>
<style>
  body { font-family: Georgia, serif; color: #1a241c; margin: 24px; }
  .kop { display: flex; justify-content: space-between; border-bottom: 3px solid #1b6b3a; padding-bottom: 10px; margin-bottom: 18px; }
  h1 { font-size: 18px; margin: 0; color: #0f4224; }
  small { color: #5c6b60; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { border: 1px solid #ccc; padding: 7px 8px; text-align: left; }
  th { background: #e8f5e9; }
  .meta { margin-bottom: 14px; font-size: 13px; }
  .ttd { display: flex; justify-content: space-between; margin-top: 40px; }
  .ttd div { width: 40%; text-align: center; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">Cetak</button> <a href="javascript:history.back()">Kembali</a></p>
<div class="kop">
  <div>
    <h1><?= e($s['nama_koperasi']) ?></h1>
    <small><?= e($s['alamat']) ?> · <?= e($s['telepon']) ?></small>
  </div>
  <div><strong><?= e($judul) ?></strong><br><small><?= date('d/m/Y H:i') ?></small></div>
</div>
<?php }

if ($jenis === 'anggota') {
    if (!$staff) { http_response_code(403); exit('Khusus pengurus'); }
    $rows = $pdo->query('SELECT * FROM anggota ORDER BY no_anggota')->fetchAll();
    $kelCetak = [];
    $luasCetak = [];
    try {
        foreach ($pdo->query('SELECT ak.anggota_id, k.kode_kelompok FROM anggota_kelompok ak JOIN kelompok k ON k.id=ak.id_kelompok ORDER BY k.nomor') as $kr) {
            $kelCetak[(int)$kr['anggota_id']][] = $kr['kode_kelompok'];
        }
        foreach ($pdo->query('SELECT anggota_id, COALESCE(SUM(luas_hektar),0) t FROM lahan_sawit GROUP BY anggota_id') as $lr) {
            $luasCetak[(int)$lr['anggota_id']] = (float)$lr['t'];
        }
    } catch (Throwable $e) {
    }
    print_head('Daftar Anggota', $s); ?>
    <table>
      <thead><tr><th>No. Anggota</th><th>Nama</th><th>Kelompok</th><th>Luas (ha)</th><th>STDB</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['no_anggota']) ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= !empty($kelCetak[(int)$r['id']]) ? e(implode(', ', $kelCetak[(int)$r['id']])) : label_kelompok($r['kelompok_tani']) ?></td>
          <td><?= e($luasCetak[(int)$r['id']] ?? $r['luas_tanah']) ?></td>
          <td><?= e($r['stdb']) ?></td>
          <td><?= e($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
} elseif ($jenis === 'simpanan') {
    $sql = "SELECT s.*, a.nama, a.no_anggota, j.nama jenis FROM " . sql_union_simpanan('s') . " JOIN anggota a ON a.id=s.anggota_id JOIN jenis_simpanan j ON j.id=s.jenis_id";
    $params = [];
    $fAnggota = $staff ? (int)($_GET['anggota_id'] ?? 0) : (int)($u['anggota_id'] ?? 0);
    $fJenis = (int)($_GET['jenis_id'] ?? 0);
    $fBulan = trim($_GET['bulan'] ?? '');
    if ($fBulan !== '' && !preg_match('/^\d{4}-\d{2}$/', $fBulan)) {
        $fBulan = '';
    }
    $w = [];
    if (!$staff) {
        $w[] = 's.anggota_id=?';
        $params[] = (int)($u['anggota_id'] ?? 0);
    } elseif ($fAnggota > 0) {
        $w[] = 's.anggota_id=?';
        $params[] = $fAnggota;
    }
    if ($fJenis > 0) {
        $w[] = 's.jenis_id=?';
        $params[] = $fJenis;
    }
    if ($fBulan !== '') {
        $w[] = "DATE_FORMAT(s.tanggal,'%Y-%m')=?";
        $params[] = $fBulan;
    }
    if ($id) {
        $w[] = 's.id=?';
        $params[] = $id;
    }
    if ($w) {
        $sql .= ' WHERE ' . implode(' AND ', $w);
    }
    $sql .= ' ORDER BY s.tanggal DESC, s.id DESC';
    $st = $pdo->prepare($sql); $st->execute($params); $rows = $st->fetchAll();
    $judulCetak = $id ? 'Bukti Setoran Simpanan' : 'Daftar Transaksi Simpanan';
    if (!$id && ($fAnggota || $fJenis || $fBulan !== '')) {
        $judulCetak = 'Hasil Pencarian Simpanan';
    }
    print_head($judulCetak, $s);
    if (!$id):
        $ketFilter = [];
        if ($fAnggota) {
            $na = $pdo->prepare('SELECT no_anggota, nama FROM anggota WHERE id=?');
            $na->execute([$fAnggota]);
            $ar = $na->fetch();
            if ($ar) {
                $ketFilter[] = 'Anggota: ' . $ar['no_anggota'] . ' — ' . $ar['nama'];
            }
        }
        if ($fJenis) {
            $nj = $pdo->prepare('SELECT nama FROM jenis_simpanan WHERE id=?');
            $nj->execute([$fJenis]);
            $jn = $nj->fetchColumn();
            if ($jn) {
                $ketFilter[] = 'Jenis: ' . $jn;
            }
        }
        if ($fBulan !== '') {
            $ketFilter[] = 'Bulan: ' . $fBulan;
        }
        if ($ketFilter): ?>
      <div class="meta"><?= e(implode(' · ', $ketFilter)) ?> · <?= count($rows) ?> transaksi · Total <?= rupiah(array_sum(array_column($rows, 'jumlah'))) ?></div>
        <?php endif;
    endif;
    if ($id && $rows): $r = $rows[0]; ?>
      <div class="meta">No. Anggota: <?= e($r['no_anggota']) ?> · <?= e($r['nama']) ?></div>
      <table>
        <tr><th>Tanggal</th><td><?= tgl($r['tanggal']) ?></td></tr>
        <tr><th>Jenis</th><td><?= e($r['jenis']) ?></td></tr>
        <tr><th>Jumlah</th><td><?= rupiah($r['jumlah']) ?></td></tr>
        <tr><th>Keterangan</th><td><?= e($r['keterangan']) ?></td></tr>
      </table>
      <div class="ttd"><div>Anggota<br><br><br><?= e($r['nama']) ?></div><div>Petugas<br><br><br><?= e($s['ketua']) ?></div></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Tanggal</th><th>No</th><th>Nama</th><th>Jenis</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= tgl($r['tanggal']) ?></td><td><?= e($r['no_anggota']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['jenis']) ?></td><td><?= rupiah($r['jumlah']) ?></td><td><?= e($r['keterangan']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif;
} elseif ($jenis === 'pinjaman') {
    $sql = "SELECT p.*, a.nama, a.no_anggota FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id";
    $params = [];
    if (!$staff) { $sql .= ' WHERE p.anggota_id=?'; $params[] = $u['anggota_id']; }
    if ($id) { $sql .= (str_contains($sql, 'WHERE') ? ' AND' : ' WHERE') . ' p.id=?'; $params[] = $id; }
    $sql .= ' ORDER BY p.id DESC';
    $st = $pdo->prepare($sql); $st->execute($params); $rows = $st->fetchAll();
    print_head($id ? 'Bukti Pinjaman' : 'Daftar Pinjaman', $s);
    if ($id && $rows): $r = $rows[0]; ?>
      <table>
        <tr><th>No. Pinjaman</th><td><?= e($r['no_pinjaman']) ?></td></tr>
        <tr><th>Anggota</th><td><?= e($r['no_anggota'].' — '.$r['nama']) ?></td></tr>
        <tr><th>Tanggal</th><td><?= tgl($r['tanggal']) ?></td></tr>
        <tr><th>Pokok</th><td><?= rupiah($r['jumlah']) ?></td></tr>
        <tr><th>Bagi hasil</th><td><?= e($r['bagi_hasil_persen']) ?>% / bulan × <?= (int)$r['tenor'] ?> bulan</td></tr>
        <tr><th>Total tagihan</th><td><?= rupiah($r['total_tagihan']) ?></td></tr>
        <tr><th>Sisa</th><td><?= rupiah($r['sisa']) ?></td></tr>
        <tr><th>Status</th><td><?= e($r['status']) ?></td></tr>
        <tr><th>Keperluan</th><td><?= e($r['keperluan']) ?></td></tr>
      </table>
      <div class="ttd"><div>Anggota<br><br><br><?= e($r['nama']) ?></div><div>Pengurus<br><br><br><?= e($s['ketua']) ?></div></div>
    <?php else: ?>
      <table>
        <thead><tr><th>No</th><th>Nama</th><th>Pokok</th><th>Total</th><th>Sisa</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= e($r['no_pinjaman']) ?></td><td><?= e($r['nama']) ?></td><td><?= rupiah($r['jumlah']) ?></td><td><?= rupiah($r['total_tagihan']) ?></td><td><?= rupiah($r['sisa']) ?></td><td><?= e($r['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif;
} elseif ($jenis === 'angsuran') {
    if (!$staff && !$id) {
        $sql = "SELECT g.*, p.no_pinjaman, a.nama FROM angsuran g JOIN pinjaman p ON p.id=g.pinjaman_id JOIN anggota a ON a.id=p.anggota_id WHERE p.anggota_id=? ORDER BY g.id DESC";
        $st = $pdo->prepare($sql); $st->execute([$u['anggota_id']]); $rows = $st->fetchAll();
    } elseif ($id) {
        $st = $pdo->prepare("SELECT g.*, p.no_pinjaman, a.nama, p.anggota_id FROM angsuran g JOIN pinjaman p ON p.id=g.pinjaman_id JOIN anggota a ON a.id=p.anggota_id WHERE g.id=?");
        $st->execute([$id]); $rows = $st->fetchAll();
        if (!$staff && $rows && (int)$rows[0]['anggota_id'] !== (int)$u['anggota_id']) { exit('Akses ditolak'); }
    } else {
        $rows = $pdo->query("SELECT g.*, p.no_pinjaman, a.nama FROM angsuran g JOIN pinjaman p ON p.id=g.pinjaman_id JOIN anggota a ON a.id=p.anggota_id ORDER BY g.id DESC")->fetchAll();
    }
    print_head($id ? 'Bukti Pembayaran Angsuran' : 'Daftar Angsuran', $s);
    if ($id && $rows): $r = $rows[0]; ?>
      <table>
        <tr><th>Tanggal</th><td><?= tgl($r['tanggal']) ?></td></tr>
        <tr><th>No. Pinjaman</th><td><?= e($r['no_pinjaman']) ?></td></tr>
        <tr><th>Anggota</th><td><?= e($r['nama']) ?></td></tr>
        <tr><th>Angsuran ke</th><td><?= (int)$r['angsuran_ke'] ?></td></tr>
        <tr><th>Jumlah bayar</th><td><?= rupiah($r['jumlah']) ?></td></tr>
        <tr><th>Pokok</th><td><?= rupiah($r['pokok'] ?? 0) ?></td></tr>
        <tr><th>Bagi hasil</th><td><?= rupiah($r['bagi_hasil'] ?? 0) ?></td></tr>
        <tr><th>Denda</th><td><?= rupiah($r['denda'] ?? 0) ?></td></tr>
        <tr><th>Keterangan</th><td><?= e($r['keterangan']) ?></td></tr>
      </table>
      <p><small>Dicatat petugas setelah anggota membayar di kantor koperasi.</small></p>
      <div class="ttd"><div>Anggota<br><br><br><?= e($r['nama']) ?></div><div>Petugas kasir<br><br><br>................</div></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Tanggal</th><th>No. Pinjaman</th><th>Nama</th><th>Ke</th><th>Jumlah</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= tgl($r['tanggal']) ?></td><td><?= e($r['no_pinjaman']) ?></td><td><?= e($r['nama']) ?></td><td><?= (int)$r['angsuran_ke'] ?></td><td><?= rupiah($r['jumlah']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif;
} else {
    exit('Jenis cetak tidak dikenal');
}
echo '</body></html>';
