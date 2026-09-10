<?php
require __DIR__ . '/config.php';
require_staff();
ensure_pinjaman_schema();
$title = 'Antrean truk TBS';
$pdo = db();
$u = auth();

$hariNama = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
$rentang = [1 => 'KT-01–04', 2 => 'KT-05–08', 3 => 'KT-09–12', 4 => 'KT-13–16', 5 => 'KT-17–21'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'isi';
    if ($act === 'isi') {
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $dow = (int)date('N', strtotime($tgl));
        if ($dow > 5) {
            flash('err', 'Antrean hanya Senin–Jumat.');
            header('Location: tbs_antrean.php');
            exit;
        }
        $idk = (int)$_POST['id_kelompok'];
        $kel = $pdo->prepare('SELECT * FROM kelompok WHERE id=?');
        $kel->execute([$idk]);
        $k = $kel->fetch();
        if (!$k) {
            flash('err', 'Kelompok tidak ditemukan.');
            header('Location: tbs_antrean.php');
            exit;
        }
        if (hari_antrean_kelompok((int)$k['nomor']) !== $dow) {
            flash('err', 'Kelompok ' . $k['kode_kelompok'] . ' jadwalnya hari ' . $hariNama[hari_antrean_kelompok((int)$k['nomor'])] . ' (' . $rentang[hari_antrean_kelompok((int)$k['nomor'])] . ').');
            header('Location: tbs_antrean.php?minggu=' . date('o-\WW', strtotime($tgl)));
            exit;
        }
        $pdo->prepare('INSERT INTO antrean_truk (tanggal,id_kelompok,slot,no_plat,nama_sopir,status,keterangan,created_by) VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE no_plat=VALUES(no_plat), nama_sopir=VALUES(nama_sopir), status=VALUES(status), keterangan=VALUES(keterangan)')
            ->execute([
                $tgl, $idk, max(1, (int)$_POST['slot']),
                trim($_POST['no_plat'] ?? ''), trim($_POST['nama_sopir'] ?? ''),
                $_POST['status'] ?? 'terjadwal', trim($_POST['keterangan'] ?? ''), $u['id'],
            ]);
        flash('ok', 'Slot antrean disimpan.');
    }
    header('Location: tbs_antrean.php?minggu=' . ($_POST['minggu'] ?? date('o-\WW')));
    exit;
}

$minggu = $_GET['minggu'] ?? date('o-\WW');
if (!preg_match('/^(\d{4})-W(\d{2})$/', $minggu, $m)) {
    $minggu = date('o-\WW');
    preg_match('/^(\d{4})-W(\d{2})$/', $minggu, $m);
}
$senin = new DateTime();
$senin->setISODate((int)$m[1], (int)$m[2]);
$hari = [];
for ($i = 0; $i < 5; $i++) {
    $d = clone $senin;
    $d->modify('+' . $i . ' day');
    $hari[] = $d->format('Y-m-d');
}
$prev = (clone $senin)->modify('-1 week')->format('o-\WW');
$next = (clone $senin)->modify('+1 week')->format('o-\WW');

$kelompok = $pdo->query('SELECT * FROM kelompok ORDER BY nomor')->fetchAll();
$byNomor = [];
foreach ($kelompok as $k) {
    $byNomor[(int)$k['nomor']] = $k;
}

$slots = [];
$st = $pdo->prepare('SELECT a.*, k.kode_kelompok, k.nama_kelompok, k.nomor FROM antrean_truk a JOIN kelompok k ON k.id=a.id_kelompok WHERE a.tanggal BETWEEN ? AND ?');
$st->execute([$hari[0], $hari[4]]);
foreach ($st as $r) {
    $slots[$r['tanggal']][(int)$r['id_kelompok']][(int)$r['slot']] = $r;
}

include __DIR__ . '/includes/app_header.php';
?>
<div class="row" style="margin-bottom:14px;align-items:center;">
  <a class="btn btn-ghost" href="tbs_antrean.php?minggu=<?= e($prev) ?>">← Minggu lalu</a>
  <strong>Minggu <?= e($minggu) ?></strong>
  <a class="btn btn-ghost" href="tbs_antrean.php?minggu=<?= e($next) ?>">Minggu depan →</a>
  <a class="btn btn-ghost" href="tbs_sj.php">Surat jalan</a>
</div>
<p style="font-size:13px;color:var(--muted);margin-bottom:14px;">
  Jadwal tetap: Senin KT-01–04 · Selasa KT-05–08 · Rabu KT-09–12 · Kamis KT-13–16 · Jumat KT-17–21.
</p>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Hari</th>
        <th>Kelompok</th>
        <th>Slot 1</th>
        <th>Slot 2</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($hari as $tgl):
      $dow = (int)date('N', strtotime($tgl));
      $nomors = array_keys(array_filter($byNomor, fn($k) => hari_antrean_kelompok((int)$k['nomor']) === $dow));
    ?>
      <?php $first = true; foreach ($nomors as $nom): $k = $byNomor[$nom]; ?>
      <tr>
        <?php if ($first): ?>
        <td rowspan="<?= count($nomors) ?>"><strong><?= e($hariNama[$dow]) ?></strong><br><small><?= tgl($tgl) ?></small></td>
        <?php $first = false; endif; ?>
        <td><?= e($k['kode_kelompok']) ?><br><small><?= e($k['nama_kelompok']) ?></small></td>
        <?php for ($slot = 1; $slot <= 2; $slot++):
          $s = $slots[$tgl][(int)$k['id']][$slot] ?? null;
        ?>
        <td>
          <?php if ($s): ?>
            <strong><?= e($s['no_plat'] ?: '—') ?></strong><br>
            <small><?= e($s['nama_sopir']) ?> · <?= e($s['status']) ?></small>
          <?php else: ?>
            <small style="color:var(--muted);">kosong</small>
          <?php endif; ?>
          <form method="post" style="margin-top:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="isi">
            <input type="hidden" name="minggu" value="<?= e($minggu) ?>">
            <input type="hidden" name="tanggal" value="<?= e($tgl) ?>">
            <input type="hidden" name="id_kelompok" value="<?= (int)$k['id'] ?>">
            <input type="hidden" name="slot" value="<?= $slot ?>">
            <input name="no_plat" placeholder="Plat" value="<?= e($s['no_plat'] ?? '') ?>" style="margin-bottom:4px;">
            <input name="nama_sopir" placeholder="Sopir" value="<?= e($s['nama_sopir'] ?? '') ?>" style="margin-bottom:4px;">
            <select name="status" style="margin-bottom:4px;">
              <?php foreach (['terjadwal','berangkat','selesai','batal'] as $stt): ?>
                <option value="<?= $stt ?>" <?= (($s['status'] ?? '') === $stt) ? 'selected' : '' ?>><?= $stt ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-green btn-sm">Simpan</button>
          </form>
        </td>
        <?php endfor; ?>
      </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
