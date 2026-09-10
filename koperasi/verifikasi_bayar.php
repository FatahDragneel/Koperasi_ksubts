<?php
require __DIR__ . '/config.php';
require_staff();
ensure_pinjaman_schema();
$title = 'Verifikasi pembayaran';
$pdo = db();
$u = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $act = $_POST['act'] ?? '';
    $st = $pdo->prepare('SELECT * FROM bukti_bayar_pinjaman WHERE id=?');
    $st->execute([$id]);
    $b = $st->fetch();
    if (!$b || $b['status'] !== 'menunggu') {
        flash('err', 'Pengajuan tidak valid atau sudah diproses.');
        header('Location: verifikasi_bayar.php');
        exit;
    }
    if ($act === 'tolak') {
        $cat = trim($_POST['catatan'] ?? '');
        if ($cat === '') {
            flash('err', 'Penolakan wajib diisi keterangan.');
            header('Location: verifikasi_bayar.php');
            exit;
        }
        $pdo->prepare("UPDATE bukti_bayar_pinjaman SET status='ditolak', catatan_admin=?, verified_by=?, verified_at=NOW() WHERE id=?")
            ->execute([$cat, $u['id'], $id]);
        flash('ok', 'Bukti ditolak. Anggota melihat keterangannya.');
    } elseif ($act === 'setujui') {
        $jumlah = (float)$b['jumlah'];
        $angs = catat_angsuran_pecah((int)$b['pinjaman_id'], $jumlah, $b['tanggal'], 'Verifikasi bukti #'.$id, $u['id'], 'bukti', 0, true);
        if (!$angs) {
            flash('err', 'Gagal mencatat angsuran. Cek sisa pinjaman.');
            header('Location: verifikasi_bayar.php');
            exit;
        }
        $pdo->prepare("UPDATE bukti_bayar_pinjaman SET status='diverifikasi', catatan_admin=?, angsuran_id=?, verified_by=?, verified_at=NOW() WHERE id=?")
            ->execute([trim($_POST['catatan'] ?? 'Diverifikasi'), $angs, $u['id'], $id]);
        flash('ok', 'Diverifikasi. Angsuran tercatat ' . rupiah($jumlah) . '.');
    }
    header('Location: verifikasi_bayar.php');
    exit;
}

$filter = $_GET['s'] ?? 'menunggu';
if (!in_array($filter, ['menunggu', 'diverifikasi', 'ditolak', 'semua'], true)) {
    $filter = 'menunggu';
}
$sql = "SELECT b.*, p.no_pinjaman, p.sisa, a.nama, a.no_anggota
    FROM bukti_bayar_pinjaman b
    JOIN pinjaman p ON p.id=b.pinjaman_id
    JOIN anggota a ON a.id=b.anggota_id";
if ($filter !== 'semua') {
    $sql .= ' WHERE b.status=' . $pdo->quote($filter);
}
$sql .= ' ORDER BY ' . sql_urut([
    'nama' => 'a.nama',
    'pinjaman' => 'p.no_pinjaman',
    'tanggal' => 'b.tanggal',
    'jumlah' => 'b.jumlah',
    'status' => 'b.status',
], "FIELD(b.status,'menunggu','diverifikasi','ditolak'), b.id DESC");
$rows = $pdo->query($sql)->fetchAll();
$nTunggu = (int)$pdo->query("SELECT COUNT(*) FROM bukti_bayar_pinjaman WHERE status='menunggu'")->fetchColumn();

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;color:var(--muted);">Anggota mengunggah foto bukti. Baru setelah Anda setujui, angsuran masuk buku dan dijurnal.</p>
<div class="row" style="margin-bottom:14px;">
  <a class="btn <?= $filter==='menunggu'?'btn-green':'btn-ghost' ?>" href="?s=menunggu">Menunggu (<?= $nTunggu ?>)</a>
  <a class="btn <?= $filter==='diverifikasi'?'btn-green':'btn-ghost' ?>" href="?s=diverifikasi">Diverifikasi</a>
  <a class="btn <?= $filter==='ditolak'?'btn-green':'btn-ghost' ?>" href="?s=ditolak">Ditolak</a>
  <a class="btn <?= $filter==='semua'?'btn-green':'btn-ghost' ?>" href="?s=semua">Semua</a>
  <a class="btn btn-ghost" href="angsuran.php">Daftar angsuran</a>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr><?= th_urut('nama','Anggota') ?><?= th_urut('pinjaman','Pinjaman') ?><?= th_urut('tanggal','Tgl / jumlah') ?><th>Bukti</th><?= th_urut('status','Status') ?><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['nama']) ?></strong><br><small><?= e($r['no_anggota']) ?></small></td>
        <td><?= e($r['no_pinjaman']) ?><br><small>Sisa sekarang <?= rupiah($r['sisa']) ?></small></td>
        <td><?= tgl($r['tanggal']) ?><br><?= rupiah($r['jumlah']) ?><?= $r['keterangan'] ? '<br><small>'.e($r['keterangan']).'</small>' : '' ?></td>
        <td><a class="btn btn-ghost btn-sm" href="berkas.php?jenis=bukti&id=<?= (int)$r['id'] ?>" target="_blank">Lihat gambar</a></td>
        <td>
          <span class="badge b-<?= e($r['status'] === 'diverifikasi' ? 'lunas' : ($r['status'] === 'ditolak' ? 'ditolak' : 'pengajuan')) ?>"><?= e($r['status']) ?></span>
          <?php if ($r['catatan_admin']): ?><br><small><?= e($r['catatan_admin']) ?></small><?php endif; ?>
        </td>
        <td>
          <?php if ($r['status'] === 'menunggu'): ?>
          <form method="post" style="margin-bottom:8px;" onsubmit="return confirm('Setujui dan catat angsuran?');">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="setujui">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input name="catatan" placeholder="Catatan (opsional)" style="margin-bottom:6px;">
            <button class="btn btn-green btn-sm">Setujui</button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="tolak">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input name="catatan" required placeholder="Alasan tolak (wajib)" style="margin-bottom:6px;">
            <button class="btn btn-danger btn-sm">Tolak</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6">Tidak ada data.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
