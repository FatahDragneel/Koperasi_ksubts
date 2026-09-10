<?php
require __DIR__ . '/config.php';
require_staff();
ensure_pinjaman_schema();
$pdo = db();
$u = auth();
$id = (int)($_GET['id'] ?? $_POST['pinjaman_id'] ?? 0);

$st = $pdo->prepare('SELECT p.*, a.nama, a.no_anggota, a.no_hp FROM pinjaman p JOIN anggota a ON a.id=p.anggota_id WHERE p.id=?');
$st->execute([$id]);
$pin = $st->fetch();
if (!$pin) {
    flash('err', 'Pinjaman tidak ditemukan.');
    header('Location: angsuran.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($pin['status'], ['berjalan', 'disetujui'], true)) {
        flash('err', 'Pinjaman tidak dapat dibayar.');
        header('Location: angsuran_detail.php?id=' . $id);
        exit;
    }
    $jumlah = isset($_POST['jumlah']) && $_POST['jumlah'] !== ''
        ? (float)str_replace('.', '', $_POST['jumlah'])
        : 0;
    $denda = isset($_POST['denda']) && $_POST['denda'] !== ''
        ? (float)str_replace('.', '', $_POST['denda'])
        : 0;
    $tenor = max(1, (int)$pin['tenor']);
    $sudah = (int)$pdo->query('SELECT COUNT(*) FROM angsuran WHERE pinjaman_id=' . $id)->fetchColumn();
    $sisaKe = $tenor - $sudah;
    $cicilan = $sisaKe > 0 ? round(((float)$pin['sisa']) / $sisaKe, 0) : (float)$pin['sisa'];
    if ($jumlah <= 0) {
        $jumlah = min((float)$pin['sisa'], $cicilan);
    }
    $jumlah = min($jumlah, (float)$pin['sisa']);
    $tgla = trim((string)($_POST['tanggal'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $tgla);
    if (!$dt || $dt->format('Y-m-d') !== $tgla) {
        $tgla = date('Y-m-d');
    }
    $aid = catat_angsuran_pecah($id, $jumlah, $tgla, trim($_POST['keterangan'] ?? 'Iuran ke-'.($sudah+1)), $u['id'], 'tunai', $denda);
    if ($aid) {
        flash('ok', 'Iuran tercatat (pokok + bagi hasil' . ($denda > 0 ? ' + denda' : '') . ').');
    } else {
        flash('err', 'Pembayaran gagal.');
    }
    header('Location: angsuran_detail.php?id=' . $id);
    exit;
}

$paid = [];
$pst = $pdo->prepare('SELECT * FROM angsuran WHERE pinjaman_id=? ORDER BY angsuran_ke');
$pst->execute([$id]);
foreach ($pst as $g) {
    $paid[(int)$g['angsuran_ke']] = $g;
}
$tenor = max(1, (int)$pin['tenor']);
$pokokCicil = round((float)$pin['total_tagihan'] / $tenor, 0);
$next = null;
for ($ke = 1; $ke <= $tenor; $ke++) {
    if (empty($paid[$ke])) {
        $next = $ke;
        break;
    }
}

$title = 'Detail angsuran · ' . $pin['nama'];
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;"><a class="btn btn-ghost btn-sm" href="angsuran.php">← Daftar peminjam</a></p>
<?php
$bagiPinjam = max(0, (float)$pin['total_tagihan'] - (float)$pin['jumlah']);
?>
<div class="kpis">
  <div class="kpi"><span>Peminjam</span><b><?= e($pin['nama']) ?></b></div>
  <div class="kpi"><span>No. pinjaman</span><b><?= e($pin['no_pinjaman']) ?></b></div>
  <div class="kpi"><span>Sisa tagihan</span><b><?= rupiah($pin['sisa']) ?></b></div>
  <div class="kpi"><span>Bagi hasil pinjaman ini</span><b><?= rupiah($bagiPinjam) ?></b></div>
</div>
<p style="margin-bottom:14px;color:var(--muted);font-size:14px;">
  <?= e($pin['no_anggota']) ?> · HP <?= e($pin['no_hp']) ?> · <?= e($pin['keperluan']) ?><br>
  Pokok <?= rupiah($pin['jumlah']) ?> · Bagi hasil <?= e($pin['bagi_hasil_persen']) ?>% · Tenor <?= (int)$pin['tenor'] ?> bln · Total <?= rupiah($pin['total_tagihan']) ?>
</p>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Iuran ke</th><th>Tagihan</th><th>Pokok</th><th>Bagi hasil</th><th>Denda</th><th>Status</th><th>Tgl bayar</th><th>Dibayar</th><th></th></tr>
    </thead>
    <tbody>
    <?php for ($ke = 1; $ke <= $tenor; $ke++):
      $b = $paid[$ke] ?? null;
      $tagih = $ke < $tenor ? $pokokCicil : ((float)$pin['total_tagihan'] - $pokokCicil * ($tenor - 1));
    ?>
      <tr>
        <td><?= $ke ?> / <?= $tenor ?></td>
        <td><?= rupiah($tagih) ?></td>
        <td><?= $b ? rupiah($b['pokok'] ?? 0) : '—' ?></td>
        <td><?= $b ? rupiah($b['bagi_hasil'] ?? 0) : '—' ?></td>
        <td><?= $b ? rupiah($b['denda'] ?? 0) : '—' ?></td>
        <td><?php if ($b): ?><span class="badge b-lunas">Lunas</span><?php else: ?><span class="badge b-pengajuan">Belum bayar</span><?php endif; ?></td>
        <td><?= $b ? tgl($b['tanggal']) : '—' ?></td>
        <td><?= $b ? rupiah($b['jumlah']) : '—' ?></td>
        <td>
          <?php if ($b): ?>
            <a class="btn btn-ghost btn-sm" href="cetak.php?jenis=angsuran&id=<?= (int)$b['id'] ?>" target="_blank">Cetak</a>
          <?php elseif ($next === $ke && in_array($pin['status'], ['berjalan','disetujui'], true)): ?>
            <form method="post" onsubmit="return confirm('Catat pembayaran iuran ke-<?= $ke ?> atas nama <?= e($pin['nama']) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="pinjaman_id" value="<?= $id ?>">
              <input type="hidden" name="jumlah" value="<?= (int)min((float)$pin['sisa'], $tagih) ?>">
              <input type="hidden" name="keterangan" value="Iuran ke-<?= $ke ?>">
              <label style="font-size:11px;margin:0;">Tanggal bayar</label>
              <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required style="width:150px;margin-bottom:6px;">
              <label style="font-size:11px;margin:0;">Denda (opsional)</label>
              <input name="denda" placeholder="0" style="width:90px;margin-bottom:6px;">
              <button class="btn btn-green btn-sm">Bayar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
