<?php
require __DIR__ . '/config.php';
require_login();
if (!function_exists('status_anggota')) {
    function status_anggota(?int $id): string {
        if (!$id) {
            return '';
        }
        try {
            $st = db()->prepare('SELECT status FROM anggota WHERE id=?');
            $st->execute([$id]);
            return (string)($st->fetchColumn() ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }
}
$title = 'Dashboard';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin','pengurus']);

if ($staff) {
    $kpi = [
        'anggota_aktif' => $pdo->query("SELECT COUNT(*) FROM anggota WHERE status='aktif'")->fetchColumn(),
        'anggota_pasif' => $pdo->query("SELECT COUNT(*) FROM anggota WHERE status='pasif'")->fetchColumn(),
        'anggota' => $pdo->query("SELECT COUNT(*) FROM anggota WHERE status IN ('aktif','pasif')")->fetchColumn(),
        'simpanan' => $pdo->query("SELECT COALESCE(SUM(s.jumlah),0) FROM " . sql_union_simpanan('s') . " JOIN anggota a ON a.id=s.anggota_id WHERE a.status IN ('aktif','pasif')")->fetchColumn(),
        'pinjaman' => $pdo->query("SELECT COALESCE(SUM(p.sisa),0) FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE p.status IN ('berjalan','disetujui') AND a.status IN ('aktif','pasif')")->fetchColumn(),
        'pengajuan' => $pdo->query("SELECT COUNT(*) FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE p.status='pengajuan' AND a.status IN ('aktif','pasif')")->fetchColumn(),
    ];
    $pinjaman = $pdo->query("SELECT p.*, a.nama FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id ORDER BY p.id DESC LIMIT 6")->fetchAll();
    $simpanan = $pdo->query("SELECT s.*, a.nama, j.nama jenis FROM " . sql_union_simpanan('s') . " JOIN anggota a ON a.id=s.anggota_id JOIN jenis_simpanan j ON j.id=s.jenis_id ORDER BY s.tanggal DESC, s.id DESC LIMIT 6")->fetchAll();
    $kpi['pupuk_stok'] = 0;
    $kpi['pupuk_jual'] = 0;
    try {
        foreach (daftar_pupuk_stok() as $pr) {
            $kpi['pupuk_stok'] += (float)$pr['stok'] * (float)$pr['harga_jual'];
        }
        $kpi['pupuk_jual'] = (float)$pdo->query("SELECT COALESCE(SUM(total_nilai),0) FROM pupuk_mutasi WHERE arah='keluar' AND DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
    } catch (Throwable $e) {
    }
} else {
    $aid = (int)$u['anggota_id'];
    $stAgt = status_anggota($aid);
    $st = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM " . sql_union_simpanan('s') . " WHERE s.anggota_id=?");
    $st->execute([$aid]);
    $totSimpan = $st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(sisa),0) FROM pinjaman WHERE anggota_id=? AND status IN ('berjalan','disetujui')");
    $st->execute([$aid]);
    $sisaPinjam = $st->fetchColumn();
    $st = $pdo->prepare("SELECT p.*, a.nama FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE p.anggota_id=? ORDER BY p.id DESC");
    $st->execute([$aid]);
    $pinjaman = $st->fetchAll();
    $st = $pdo->prepare("SELECT s.*, j.nama jenis FROM " . sql_union_simpanan('s') . " JOIN jenis_simpanan j ON j.id=s.jenis_id WHERE s.anggota_id=? ORDER BY s.tanggal DESC, s.id DESC LIMIT 8");
    $st->execute([$aid]);
    $simpanan = $st->fetchAll();
    $jmlAnggota = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status IN ('aktif','pasif')")->fetchColumn();
    $jmlAktif = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status='aktif'")->fetchColumn();
    $jmlPasif = (int)$pdo->query("SELECT COUNT(*) FROM anggota WHERE status='pasif'")->fetchColumn();
}
include __DIR__ . '/includes/app_header.php';
?>
<?php if ($staff): ?>
<div class="kpis">
  <div class="kpi"><span>Jumlah anggota</span><b><?= (int)$kpi['anggota'] ?></b>
    <small style="display:block;margin-top:6px;font-weight:500;color:var(--muted);">Aktif <?= (int)$kpi['anggota_aktif'] ?> · Pasif <?= (int)$kpi['anggota_pasif'] ?></small>
  </div>
  <div class="kpi"><span>Total simpanan</span><b><?= rupiah($kpi['simpanan']) ?></b></div>
  <div class="kpi"><span>Sisa pinjaman</span><b><?= rupiah($kpi['pinjaman']) ?></b></div>
  <div class="kpi"><span>Pengajuan baru</span><b><?= (int)$kpi['pengajuan'] ?></b></div>
  <div class="kpi"><span>Persediaan pupuk</span><b><?= rupiah($kpi['pupuk_stok']) ?></b></div>
  <div class="kpi"><span>Jual pupuk bulan ini</span><b><?= rupiah($kpi['pupuk_jual']) ?></b></div>
</div>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><span>Jumlah anggota sekarang</span><b><?= $jmlAnggota ?></b>
    <small style="display:block;margin-top:6px;font-weight:500;color:var(--muted);">Aktif <?= $jmlAktif ?> · Pasif <?= $jmlPasif ?></small>
  </div>
  <div class="kpi"><span>Total simpanan saya</span><b><?= rupiah($totSimpan) ?></b></div>
  <div class="kpi"><span>Sisa pinjaman</span><b><?= rupiah($sisaPinjam) ?></b></div>
  <div class="kpi"><span>Status saya</span><b><?= e($stAgt !== '' ? $stAgt : 'aktif') ?></b></div>
</div>
<?php endif; ?>

<div class="cards" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3 style="margin-bottom:12px;">Pinjaman terbaru</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No</th><th><?= $staff?'Anggota':'Keperluan' ?></th><th>Jumlah</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pinjaman as $p): ?>
          <tr>
            <td><?= e($p['no_pinjaman']) ?></td>
            <td><?= e($staff ? $p['nama'] : $p['keperluan']) ?></td>
            <td><?= rupiah($p['jumlah']) ?></td>
            <td><span class="badge b-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
          </tr>
        <?php endforeach; if (!$pinjaman): ?>
          <tr><td colspan="4">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <h3 style="margin-bottom:12px;">Setoran simpanan</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Tanggal</th><th><?= $staff?'Anggota':'Jenis' ?></th><th>Jumlah</th></tr></thead>
        <tbody>
        <?php foreach ($simpanan as $srow): ?>
          <tr>
            <td><?= tgl($srow['tanggal']) ?></td>
            <td><?= e($staff ? $srow['nama'] : $srow['jenis']) ?></td>
            <td><?= rupiah($srow['jumlah']) ?></td>
          </tr>
        <?php endforeach; if (!$simpanan): ?>
          <tr><td colspan="3">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
