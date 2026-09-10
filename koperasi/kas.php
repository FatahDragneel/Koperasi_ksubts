<?php
require __DIR__ . '/config.php';
require_staff();
ensure_kas_schema();
$title = 'Kas koperasi';
$pdo = db();
$u = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $arah = ($_POST['arah'] ?? '') === 'keluar' ? 'keluar' : 'masuk';
    $jumlah = (float)str_replace('.', '', $_POST['jumlah'] ?? '0');
    if ($jumlah <= 0) {
        flash('err', 'Jumlah harus lebih dari 0.');
    } else {
        $pdo->prepare('INSERT INTO kas (tanggal,arah,kategori,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)')
            ->execute([
                $_POST['tanggal'] ?: date('Y-m-d'),
                $arah,
                trim($_POST['kategori'] ?? 'Lainnya'),
                $jumlah,
                trim($_POST['keterangan'] ?? ''),
                $u['id'],
            ]);
        $kid = (int)$pdo->lastInsertId();
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $ket = 'Kas ' . $arah . ' · ' . trim($_POST['kategori'] ?? '');
        if ($arah === 'masuk') {
            posting_jurnal($tgl, $ket, [
                ['kode' => '1111', 'posisi' => 'debit', 'nominal' => $jumlah],
                ['kode' => '4112', 'posisi' => 'kredit', 'nominal' => $jumlah],
            ], 'kas', $kid, $u['id']);
        } else {
            posting_jurnal($tgl, $ket, [
                ['kode' => '5111', 'posisi' => 'debit', 'nominal' => $jumlah],
                ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => $jumlah],
            ], 'kas', $kid, $u['id']);
        }
        flash('ok', 'Transaksi kas dicatat & dijurnal.');
    }
    header('Location: kas.php');
    exit;
}

if (isset($_GET['hapus'])) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
    } else {
        $pdo->prepare('DELETE FROM kas WHERE id=?')->execute([(int)$_GET['hapus']]);
        flash('ok', 'Transaksi kas dihapus.');
    }
    header('Location: kas.php');
    exit;
}

$totSimpan = (float)$pdo->query('SELECT COALESCE(SUM(jumlah),0) FROM simpanan')->fetchColumn();
$totAngsur = (float)$pdo->query('SELECT COALESCE(SUM(jumlah),0) FROM angsuran')->fetchColumn();
$totCair = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM pinjaman WHERE status IN ('berjalan','disetujui','lunas')")->fetchColumn();
$totKasMasuk = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE arah='masuk'")->fetchColumn();
$totKasKeluar = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE arah='keluar'")->fetchColumn();
$masuk = $totSimpan + $totAngsur + $totKasMasuk;
$keluar = $totCair + $totKasKeluar;
$saldo = $masuk - $keluar;

$mutasi = [];
foreach ($pdo->query("SELECT s.tanggal, s.jumlah, a.nama, j.nama jenis FROM simpanan s JOIN anggota a ON a.id=s.anggota_id JOIN jenis_simpanan j ON j.id=s.jenis_id") as $r) {
    $mutasi[] = ['tanggal' => $r['tanggal'], 'arah' => 'masuk', 'uraian' => 'Simpanan '.$r['jenis'].' · '.$r['nama'], 'jumlah' => $r['jumlah']];
}
foreach ($pdo->query("SELECT g.tanggal, g.jumlah, a.nama, p.no_pinjaman FROM angsuran g JOIN pinjaman p ON p.id=g.pinjaman_id JOIN anggota a ON a.id=p.anggota_id") as $r) {
    $mutasi[] = ['tanggal' => $r['tanggal'], 'arah' => 'masuk', 'uraian' => 'Angsuran '.$r['no_pinjaman'].' · '.$r['nama'], 'jumlah' => $r['jumlah']];
}
foreach ($pdo->query("SELECT tanggal, jumlah, no_pinjaman, anggota_id FROM pinjaman WHERE status IN ('berjalan','disetujui','lunas')") as $r) {
    $nm = $pdo->prepare('SELECT nama FROM anggota WHERE id=?');
    $nm->execute([$r['anggota_id']]);
    $mutasi[] = ['tanggal' => $r['tanggal'], 'arah' => 'keluar', 'uraian' => 'Pencairan '.$r['no_pinjaman'].' · '.$nm->fetchColumn(), 'jumlah' => $r['jumlah']];
}
foreach ($pdo->query('SELECT * FROM kas ORDER BY id DESC') as $r) {
    $mutasi[] = ['tanggal' => $r['tanggal'], 'arah' => $r['arah'], 'uraian' => $r['kategori'].($r['keterangan'] ? ' · '.$r['keterangan'] : ''), 'jumlah' => $r['jumlah'], 'id' => $r['id']];
}
usort($mutasi, function ($a, $b) {
    return strcmp($b['tanggal'], $a['tanggal']);
});

include __DIR__ . '/includes/app_header.php';
?>
<div class="kpis">
  <div class="kpi"><span>Uang masuk</span><b><?= rupiah($masuk) ?></b></div>
  <div class="kpi"><span>Uang keluar</span><b><?= rupiah($keluar) ?></b></div>
  <div class="kpi"><span>Saldo kas</span><b><?= rupiah($saldo) ?></b></div>
  <div class="kpi"><span>Pencairan pinjaman</span><b><?= rupiah($totCair) ?></b></div>
</div>
<p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
  Masuk = simpanan + angsuran + kas lain. Keluar = pencairan pinjaman (bukan ditolak) + pengeluaran kas.
  Pinjaman ditolak tidak dihitung.
</p>

<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="openModal('mKas')">+ Catat kas lain</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Tanggal</th><th>Uraian</th><th>Masuk</th><th>Keluar</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($mutasi, 0, 250) as $m): ?>
      <tr>
        <td><?= tgl($m['tanggal']) ?></td>
        <td><?= e($m['uraian']) ?></td>
        <td><?= $m['arah']==='masuk' ? rupiah($m['jumlah']) : '—' ?></td>
        <td><?= $m['arah']==='keluar' ? rupiah($m['jumlah']) : '—' ?></td>
        <td>
          <?php if (!empty($m['id'])): ?>
            <a class="btn btn-ghost btn-sm" href="?hapus=<?= (int)$m['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Hapus transaksi kas ini?')">Hapus</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$mutasi): ?>
      <tr><td colspan="5">Belum ada mutasi.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mKas">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <h3>Kas lain (bukan simpan/pinjam)</h3>
    <label>Arah</label>
    <select name="arah">
      <option value="masuk">Masuk</option>
      <option value="keluar">Keluar</option>
    </select>
    <label>Kategori</label>
    <select name="kategori">
      <option>Operasional</option>
      <option>Honor pengurus</option>
      <option>ATK / kantor</option>
      <option>Pendapatan lain</option>
      <option>Hibah / infak</option>
      <option>Lainnya</option>
    </select>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
      <div><label>Jumlah (Rp)</label><input name="jumlah" required></div>
    </div>
    <label>Keterangan</label>
    <input name="keterangan">
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mKas')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
