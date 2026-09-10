<?php
require __DIR__ . '/config.php';
require_login();
ensure_pinjaman_schema();
$pdo = db();
$u = auth();
if (in_array($u['role'], ['admin', 'pengurus'], true)) {
    header('Location: verifikasi_bayar.php');
    exit;
}
$aid = (int)($u['anggota_id'] ?? 0);
$title = 'Ajukan pembayaran';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new RuntimeException('Foto terlalu besar. Pakai foto di bawah 5 MB.');
        }
        $pid = (int)($_POST['pinjaman_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM pinjaman WHERE id=? AND anggota_id=? AND status IN ('berjalan','disetujui')");
        $st->execute([$pid, $aid]);
        $p = $st->fetch();
        if (!$p) {
            throw new RuntimeException('Pinjaman tidak ditemukan atau belum berjalan.');
        }
        $pending = $pdo->prepare("SELECT COUNT(*) FROM bukti_bayar_pinjaman WHERE pinjaman_id=? AND status='menunggu'");
        $pending->execute([$pid]);
        if ((int)$pending->fetchColumn() > 0) {
            throw new RuntimeException('Masih ada bukti bayar yang menunggu verifikasi admin.');
        }
        $raw = preg_replace('/[^\d]/', '', (string)($_POST['jumlah'] ?? ''));
        $jumlah = (float)$raw;
        if ($jumlah <= 0) {
            $jumlah = (float)$p['sisa'];
        }
        $jumlah = min($jumlah, (float)$p['sisa']);
        $file = upload_bukti_bayar('bukti');
        if (!$file) {
            throw new RuntimeException('Unggah foto bukti (JPG/PNG, di bawah 5 MB).');
        }
        $pdo->prepare('INSERT INTO bukti_bayar_pinjaman (pinjaman_id,anggota_id,tanggal,jumlah,file_bukti,keterangan,status) VALUES (?,?,?,?,?,?,?)')
            ->execute([$pid, $aid, ($_POST['tanggal'] ?? '') ?: date('Y-m-d'), $jumlah, $file, trim($_POST['keterangan'] ?? ''), 'menunggu']);
        flash('ok', 'Bukti pembayaran terkirim. Menunggu verifikasi admin.');
    } catch (Throwable $e) {
        flash('err', $e->getMessage());
    }
    header('Location: pinjaman_bayar.php');
    exit;
}

$pinjam = $pdo->prepare("SELECT * FROM pinjaman WHERE anggota_id=? AND status IN ('berjalan','disetujui','lunas') ORDER BY id DESC");
$pinjam->execute([$aid]);
$pinjam = $pinjam->fetchAll();
try {
    $bukti = $pdo->prepare('SELECT b.*, p.no_pinjaman FROM bukti_bayar_pinjaman b JOIN pinjaman p ON p.id=b.pinjaman_id WHERE b.anggota_id=? ORDER BY b.id DESC');
    $bukti->execute([$aid]);
    $bukti = $bukti->fetchAll();
} catch (Throwable $e) {
    $bukti = [];
}

include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;color:var(--muted);font-size:14px;">Unggah foto bukti bayar. Admin harus memverifikasi dulu sebelum angsuran masuk buku.</p>
<div class="cards" style="grid-template-columns:1fr 1fr;margin-bottom:18px;">
  <form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h3>Kirim bukti bayar</h3>
    <label>Pinjaman</label>
    <select name="pinjaman_id" id="pilihPinjam" required>
      <option value="">Pilih pinjaman</option>
      <?php foreach ($pinjam as $p):
        if (!in_array($p['status'], ['berjalan','disetujui'], true)) continue;
        $tenor = max(1, (int)$p['tenor']);
        $sudah = (int)$pdo->query('SELECT COUNT(*) FROM angsuran WHERE pinjaman_id='.(int)$p['id'])->fetchColumn();
        $bulanan = (int)round(((float)$p['total_tagihan']) / $tenor);
        if ($sudah >= $tenor - 1) {
            $bulanan = (int)round((float)$p['sisa']);
        }
        $bulanan = (int)min($bulanan, (float)$p['sisa']);
      ?>
        <option value="<?= (int)$p['id'] ?>"
          data-bulanan="<?= $bulanan ?>"
          data-sisa="<?= (int)$p['sisa'] ?>"
          data-ke="<?= $sudah + 1 ?>"
          data-tenor="<?= $tenor ?>">
          <?= e($p['no_pinjaman']) ?> · iuran <?= rupiah($bulanan) ?> · sisa <?= rupiah($p['sisa']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p id="infoCicilan" style="font-size:13px;color:var(--muted);margin:6px 0 10px;"></p>
    <div class="grid-2">
      <div><label>Tanggal bayar</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Jumlah iuran bulan ini (Rp)</label><input name="jumlah" id="jumlahBayar" required></div>
    </div>
    <label>Foto bukti (wajib, JPG/PNG kecil)</label>
    <input type="file" name="bukti" accept="image/*" required>
    <label>Keterangan</label>
    <input name="keterangan" placeholder="Transfer BRI / setor tunai">
    <button class="btn btn-green" style="margin-top:12px;">Kirim ke admin</button>
  </form>
  <div class="card">
    <h3>Pinjaman saya</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>No</th><th>Sisa</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pinjam as $p): ?>
          <tr>
            <td><?= e($p['no_pinjaman']) ?></td>
            <td><?= rupiah($p['sisa']) ?></td>
            <td><span class="badge b-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
          </tr>
        <?php endforeach; if (!$pinjam): ?>
          <tr><td colspan="3">Belum ada pinjaman berjalan.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Tanggal</th><th>Pinjaman</th><th>Jumlah</th><th>Bukti</th><th>Status</th><th>Catatan admin</th></tr></thead>
    <tbody>
    <?php foreach ($bukti as $b): ?>
      <tr>
        <td><?= tgl($b['tanggal']) ?></td>
        <td><?= e($b['no_pinjaman']) ?></td>
        <td><?= rupiah($b['jumlah']) ?></td>
        <td><a href="berkas.php?jenis=bukti&id=<?= (int)$b['id'] ?>" target="_blank">Lihat</a></td>
        <td><span class="badge b-<?= e($b['status'] === 'diverifikasi' ? 'lunas' : ($b['status'] === 'ditolak' ? 'ditolak' : 'pengajuan')) ?>"><?= e($b['status']) ?></span></td>
        <td><?= e($b['catatan_admin'] ?: '—') ?></td>
      </tr>
    <?php endforeach; if (!$bukti): ?>
      <tr><td colspan="6">Belum ada pengajuan bayar.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<script>
function isiCicilan() {
  const sel = document.getElementById('pilihPinjam');
  const opt = sel.options[sel.selectedIndex];
  const jml = document.getElementById('jumlahBayar');
  const info = document.getElementById('infoCicilan');
  if (!opt || !opt.value) {
    jml.value = '';
    info.textContent = '';
    return;
  }
  const n = parseInt(opt.dataset.bulanan || '0', 10);
  jml.value = String(n);
  info.textContent = 'Iuran ke-' + opt.dataset.ke + ' dari ' + opt.dataset.tenor
    + ' · otomatis diisi ' + n.toLocaleString('id-ID')
    + ' (sisa tagihan ' + parseInt(opt.dataset.sisa || '0', 10).toLocaleString('id-ID') + ')';
}
document.getElementById('pilihPinjam').addEventListener('change', isiCicilan);
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
