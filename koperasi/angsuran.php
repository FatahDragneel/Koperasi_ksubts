<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Angsuran pinjaman';
$pdo = db();

$ord = sql_urut([
    'nama' => 'a.nama',
    'no' => 'p.no_pinjaman',
    'pokok' => 'p.jumlah',
    'tenor' => 'p.tenor',
    'bayar' => 'sudah_bayar',
    'sisa' => 'p.sisa',
    'status' => 'p.status',
], "FIELD(p.status,'berjalan','disetujui','lunas'), a.nama");
$rows = $pdo->query("
  SELECT p.*, a.nama, a.no_anggota,
    (SELECT COUNT(*) FROM angsuran g WHERE g.pinjaman_id=p.id) AS sudah_bayar
  FROM pinjaman p
  JOIN anggota a ON a.id=p.anggota_id
  WHERE p.status IN ('berjalan','disetujui','lunas')
  ORDER BY $ord
")->fetchAll();

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;color:var(--muted);font-size:14px;">Daftar anggota yang meminjam. Buka <strong>Detail</strong> untuk jadwal iuran dan tombol bayar.</p>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?= th_urut('nama', 'Peminjam') ?>
        <?= th_urut('no', 'No. Pinjaman') ?>
        <?= th_urut('pokok', 'Pokok') ?>
        <?= th_urut('tenor', 'Tenor') ?>
        <?= th_urut('bayar', 'Sudah bayar') ?>
        <?= th_urut('sisa', 'Sisa') ?>
        <?= th_urut('status', 'Status') ?>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['nama']) ?></strong><br><small><?= e($r['no_anggota']) ?></small></td>
        <td><?= e($r['no_pinjaman']) ?><br><small><?= e($r['keperluan']) ?></small></td>
        <td><?= rupiah($r['jumlah']) ?></td>
        <td><?= (int)$r['tenor'] ?> bulan</td>
        <td><?= (int)$r['sudah_bayar'] ?> / <?= (int)$r['tenor'] ?></td>
        <td><?= rupiah($r['sisa']) ?></td>
        <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><a class="btn btn-green btn-sm" href="angsuran_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="8">Belum ada anggota yang pinjamannya disetujui.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
