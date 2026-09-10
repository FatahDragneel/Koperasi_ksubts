<?php
require __DIR__ . '/config.php';
require_staff();
$title = 'Saldo awal simpanan';
$pdo = db();
$u = auth();
$idWajib = jenis_simpanan_id('SWJ');
$idSukarela = jenis_simpanan_id('SSK');
$ket = ket_saldo_awal_simpanan();
$tgl = tanggal_saldo_awal_simpanan();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wajibArr = (array)($_POST['wajib'] ?? []);
    $sukArr = (array)($_POST['sukarela'] ?? []);
    $ids = array_unique(array_map('intval', array_merge(array_keys($wajibArr), array_keys($sukArr))));
    $n = 0;
    foreach ($ids as $aid) {
        if ($aid < 1) {
            continue;
        }
        $w = (float)str_replace(['.', ','], ['', '.'], (string)($wajibArr[$aid] ?? '0'));
        $s = (float)str_replace(['.', ','], ['', '.'], (string)($sukArr[$aid] ?? '0'));
        if ($idWajib) {
            simpan_saldo_awal($aid, 'SWJ', $w, $u['id'] ?? null);
        }
        if ($idSukarela) {
            simpan_saldo_awal($aid, 'SSK', $s, $u['id'] ?? null);
        }
        $n++;
    }
    flash('ok', "Saldo awal wajib & sukarela (s.d. 31 Des 2025) disimpan untuk $n anggota. Wajib bulanan otomatis mulai 1 Jan 2026.");
    header('Location: simpanan_saldo_awal.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT a.id, a.no_anggota, a.nama, a.status,
    (SELECT s.jumlah FROM simpanan s WHERE s.anggota_id=a.id AND s.jenis_id=" . (int)$idWajib . " AND s.keterangan=" . $pdo->quote($ket) . " ORDER BY s.id LIMIT 1) wajib,
    (SELECT s.jumlah FROM simpanan_sukarela s WHERE s.anggota_id=a.id AND s.jenis_id=" . (int)$idSukarela . " AND s.keterangan=" . $pdo->quote($ket) . " ORDER BY s.id LIMIT 1) sukarela
  FROM anggota a WHERE a.status IN ('aktif','pasif')";
$par = [];
if ($q !== '') {
    $sql .= ' AND (a.nama LIKE ? OR a.no_anggota LIKE ?)';
    $par = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY a.no_anggota, a.nama';
$st = $pdo->prepare($sql);
$st->execute($par);
$rows = $st->fetchAll();
include __DIR__ . '/includes/app_header.php';
?>
<p style="margin-bottom:12px;"><a class="btn btn-ghost btn-sm" href="simpanan.php">← Simpanan</a></p>
<p style="color:var(--muted);font-size:14px;margin-bottom:14px;">
  Isi <strong>saldo awal</strong> simpanan wajib dan sukarela per anggota (akumulasi dari berdiri sampai <strong>31 Desember 2025</strong>).
  Setoran wajib otomatis tiap bulan hanya dari <strong>1 Januari 2026</strong> sampai bulan ini. Bukan amprah.
</p>
<form method="get" class="row" style="margin-bottom:12px;">
  <input name="q" value="<?= e($q) ?>" placeholder="Cari nama / nomor" style="flex:1;max-width:320px;">
  <button class="btn btn-ghost">Cari</button>
</form>
<form method="post" id="formSaldo" onsubmit="return kumpulSaldo();">
  <?= csrf_field() ?>
  <input type="hidden" name="payload" id="payload" value="">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No. Anggota</th>
          <th>Nama</th>
          <th>Status</th>
          <th>Saldo awal wajib (Rp)</th>
          <th>Saldo awal sukarela (Rp)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= e($r['no_anggota']) ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td><input class="in-wajib" type="number" min="0" step="1" value="<?= (int)($r['wajib'] ?? 0) ?>"></td>
          <td><input class="in-suk" type="number" min="0" step="1" value="<?= (int)($r['sukarela'] ?? 0) ?>"></td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="5">Tidak ada anggota.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p style="font-size:12px;color:var(--muted);margin:10px 0;">Tanggal: <?= e(tgl($tgl)) ?> · <?= e($ket) ?></p>
  <button class="btn btn-green">Simpan saldo awal</button>
</form>
<script>
function kumpulSaldo() {
  const rows = [];
  document.querySelectorAll('tr[data-id]').forEach(function (tr) {
    rows.push({
      id: parseInt(tr.getAttribute('data-id'), 10),
      wajib: parseFloat(tr.querySelector('.in-wajib').value || '0') || 0,
      sukarela: parseFloat(tr.querySelector('.in-suk').value || '0') || 0
    });
  });
  document.getElementById('payload').value = JSON.stringify(rows);
  return true;
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
