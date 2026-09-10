<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Verifikasi pendaftaran';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $act = $_POST['act'] ?? '';
    $cat = trim($_POST['catatan_verifikasi'] ?? '');
    if ($act === 'setujui') {
        $pdo->prepare("UPDATE anggota SET status='aktif', catatan_verifikasi=? WHERE id=?")->execute([$cat, $id]);
        catat_simpanan_awal_anggota($id, auth()['id'] ?? null);
        $pokok = rupiah(setting()['simpanan_pokok'] ?? 500000);
        $wajib = rupiah(setting()['simpanan_wajib'] ?? 50000);
        flash('ok', "Disetujui. Simpanan pokok $pokok dan simpanan wajib $wajib/bulan dari tahun berdiri sampai bulan lalu dicatat.");
    } elseif ($act === 'tolak') {
        $pdo->prepare("UPDATE anggota SET status='nonaktif', catatan_verifikasi=? WHERE id=?")->execute([$cat ?: 'Data tidak sesuai', $id]);
        flash('ok', 'Pendaftaran ditolak.');
    }
    header('Location: verifikasi.php');
    exit;
}

$pending = $pdo->query("SELECT * FROM anggota WHERE status='pending' ORDER BY id DESC")->fetchAll();
$lihat = isset($_GET['id']) ? (int)$_GET['id'] : (int)($pending[0]['id'] ?? 0);
$detail = null;
if ($lihat) {
    $st = $pdo->prepare('SELECT * FROM anggota WHERE id=?');
    $st->execute([$lihat]);
    $detail = $st->fetch();
}
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:14px;color:var(--muted);">Admin memeriksa foto, KTP, sertifikat tanah, kelompok, dan luas. Setujui hanya jika data sesuai.</p>
<div class="cards" style="grid-template-columns:1fr;">
  <div class="card">
    <h3>Antrian pendaftaran</h3>
    <div class="table-wrap" style="margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th>No. anggota</th>
            <th>Nama</th>
            <th>Kelompok</th>
            <th>HP</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
          <tr>
            <td><?= e($p['no_anggota']) ?></td>
            <td><?= e($p['nama']) ?></td>
            <td><?= label_kelompok($p['kelompok_tani']) ?></td>
            <td><?= e($p['no_hp'] ?: '—') ?></td>
            <td><span class="badge b-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><a class="btn btn-green btn-sm" href="?id=<?= (int)$p['id'] ?>">Periksa</a></td>
          </tr>
        <?php endforeach; if (!$pending): ?>
          <tr><td colspan="6">Tidak ada pendaftaran menunggu.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <?php if (!$detail): ?>
      <p>Pilih pendaftar di kiri.</p>
    <?php else: ?>
      <div class="row" style="justify-content:space-between;">
        <h3><?= e($detail['nama']) ?> · <?= e($detail['no_anggota']) ?></h3>
        <span class="badge b-<?= e($detail['status']) ?>"><?= e($detail['status']) ?></span>
      </div>
      <p style="margin:10px 0;">
        <strong>Kelompok:</strong> <?= label_kelompok($detail['kelompok_tani']) ?><br>
        <strong>Plasma:</strong> <?= $detail['plasma'] ? 'Ya' : 'Tidak' ?> ·
        <strong>Punya tanah:</strong> <?= $detail['punya_tanah'] ? 'Ya' : 'Tidak' ?> ·
        <strong>Luas:</strong> <?= e($detail['luas_tanah']) ?> ha<br>
        <strong>STDB:</strong> <?= e($detail['stdb']) ?> <?= e($detail['no_stdb']) ?><br>
        <strong>NIK:</strong> <?= e($detail['nik']) ?> · <strong>HP:</strong> <?= e($detail['no_hp']) ?><br>
        <?= e($detail['alamat']) ?> — <?= e($detail['desa']) ?>, <?= e($detail['kecamatan']) ?>
      </p>
      <div class="docs">
        <?php foreach ([['foto','Foto diri'],['ktp_file','KTP'],['sertifikat_file','Sertifikat tanah']] as [$f,$cap]):
          $jenis = $f === 'ktp_file' ? 'ktp' : ($f === 'sertifikat_file' ? 'sertifikat' : 'foto');
          $src = !empty($detail[$f]) ? berkas_url((int)$detail['id'], $jenis) : '';
          if (!$src) continue; ?>
        <figure class="zoom" data-src="<?= e($src) ?>" data-cap="<?= e($cap) ?>">
          <img src="<?= e($src) ?>" alt="<?= e($cap) ?>">
          <figcaption><?= e($cap) ?><?= $f==='sertifikat_file' ? ' ('.e($detail['luas_tanah']).' ha)' : '' ?> · klik perbesar</figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php if ($detail['status'] === 'pending'): ?>
      <form method="post" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
        <label>Catatan verifikasi</label>
        <textarea name="catatan_verifikasi" placeholder="Mis. data sesuai / KTP buram"></textarea>
        <div class="row" style="margin-top:12px;">
          <button class="btn btn-green" name="act" value="setujui">Data sesuai · Setujui</button>
          <button class="btn btn-danger" name="act" value="tolak">Tidak sesuai · Tolak</button>
        </div>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
