<?php
require __DIR__ . '/config.php';
require_login();
$title = 'Simpanan';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus']);
$set = setting();

if ($staff) {
    $mulaiLbl = tgl(tanggal_mulai_wajib_otomatis()->format('Y-m-d'));
    $nSync = sinkron_simpanan_wajib_semua($u['id'] ?? null);
    if ($nSync > 0) {
        flash('ok', "Simpanan wajib dilengkapi otomatis: $nSync setoran (sejak $mulaiLbl sampai bulan ini) untuk anggota aktif.");
        header('Location: simpanan.php');
        exit;
    }
}

$jenis = $pdo->query('SELECT * FROM jenis_simpanan ORDER BY id')->fetchAll();
$idPokok = null;
$idWajib = null;
$idSukarela = null;
foreach ($jenis as $j) {
    $kode = strtoupper((string)($j['kode'] ?? ''));
    $nm = strtolower((string)$j['nama']);
    if ($kode === 'SPK' || str_contains($nm, 'pokok')) {
        $idPokok = (int)$j['id'];
    }
    if ($kode === 'SWJ' || str_contains($nm, 'wajib')) {
        $idWajib = (int)$j['id'];
    }
    if ($kode === 'SSK' || str_contains($nm, 'sukarela')) {
        $idSukarela = (int)$j['id'];
    }
}

if ($staff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'satu';
    if ($act === 'wajib_semua') {
        if (!$idWajib) {
            flash('err', 'Jenis simpanan wajib belum ada.');
            header('Location: simpanan.php');
            exit;
        }
        $bulan = trim($_POST['bulan'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            flash('err', 'Bulan tidak valid.');
            header('Location: simpanan.php');
            exit;
        }
        $jumlah = (float)($_POST['jumlah'] ?? 0);
        if ($jumlah <= 0) {
            $jumlah = (float)($set['simpanan_wajib'] ?? 50000);
        }
        $tgl = date('Y-m-t', strtotime($bulan . '-01'));
        $nm = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $ket = 'Wajib ' . $nm[(int)substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);
        $anggotaAktif = $pdo->query("SELECT id FROM anggota WHERE status='aktif'")->fetchAll(PDO::FETCH_COLUMN);
        $cek = $pdo->prepare("SELECT COUNT(*) FROM simpanan WHERE anggota_id=? AND jenis_id=? AND DATE_FORMAT(tanggal,'%Y-%m')=?");
        $ins = $pdo->prepare('INSERT INTO simpanan (anggota_id,jenis_id,tanggal,jumlah,keterangan,created_by) VALUES (?,?,?,?,?,?)');
        $ok = 0;
        $skip = 0;
        foreach ($anggotaAktif as $aid) {
            $cek->execute([(int)$aid, $idWajib, $bulan]);
            if ((int)$cek->fetchColumn() > 0) {
                $skip++;
                continue;
            }
            $ins->execute([(int)$aid, $idWajib, $tgl, $jumlah, $ket, $u['id']]);
            $ok++;
        }
        flash('ok', "Simpanan wajib $ket dicatat untuk $ok anggota aktif." . ($skip ? " $skip sudah tercatat bulan itu, dilewati." : ''));
        header('Location: simpanan.php');
        exit;
    }

    $jid = (int)($idSukarela ?: $_POST['jenis_id']);
    $nom = (float)str_replace('.', '', $_POST['jumlah'] ?? '0');
    $tgls = $_POST['tanggal'] ?: date('Y-m-d');
    $sid = insert_simpanan_row((int)$_POST['anggota_id'], $jid, $tgls, $nom, trim($_POST['keterangan'] ?? 'Simpanan sukarela'), $u['id']);
    posting_jurnal($tgls, 'Simpanan sukarela', [
        ['kode' => '1111', 'posisi' => 'debit', 'nominal' => $nom],
        ['kode' => kode_simpanan_coa($jid), 'posisi' => 'kredit', 'nominal' => $nom],
    ], 'simpanan_sukarela', $sid, $u['id']);
    flash('ok', 'Simpanan sukarela tercatat & dijurnal.');
    header('Location: simpanan.php');
    exit;
}

$anggota = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();
$nAktif = count($anggota);
$anggotaFilter = $staff
    ? $pdo->query("SELECT id, no_anggota, nama FROM anggota ORDER BY nama")->fetchAll()
    : [];

$fAnggota = $staff ? (int)($_GET['anggota_id'] ?? 0) : (int)($u['anggota_id'] ?? 0);
$fJenis = (int)($_GET['jenis_id'] ?? 0);
$fBulan = trim($_GET['bulan'] ?? '');
if ($fBulan !== '' && !preg_match('/^\d{4}-\d{2}$/', $fBulan)) {
    $fBulan = '';
}

$where = [];
$params = [];
if ($fAnggota > 0) {
    $where[] = 's.anggota_id=?';
    $params[] = $fAnggota;
} elseif (!$staff) {
    $where[] = 's.anggota_id=?';
    $params[] = (int)($u['anggota_id'] ?? 0);
}
if ($fJenis > 0) {
    $where[] = 's.jenis_id=?';
    $params[] = $fJenis;
}
if ($fBulan !== '') {
    $where[] = "DATE_FORMAT(s.tanggal,'%Y-%m')=?";
    $params[] = $fBulan;
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$ordSim = sql_urut([
    'tanggal' => 's.tanggal',
    'no' => 'a.no_anggota',
    'nama' => 'a.nama',
    'jenis' => 'j.nama',
    'jumlah' => 's.jumlah',
    'ket' => 's.keterangan',
], 's.tanggal DESC, s.id');
$sqlRows = 'SELECT s.*, a.nama, a.no_anggota, j.nama jenis FROM ' . sql_union_simpanan('s') . " JOIN anggota a ON a.id=s.anggota_id JOIN jenis_simpanan j ON j.id=s.jenis_id $sqlWhere ORDER BY $ordSim";
if (!$where) {
    $sqlRows .= ' LIMIT 200';
}
$st = $pdo->prepare($sqlRows);
$st->execute($params);
$rows = $st->fetchAll();

$sqlRekap = "SELECT j.nama, COALESCE(SUM(
    CASE
      WHEN (IFNULL(j.kode,'') IN ('SPK','SWJ') OR j.nama LIKE '%pokok%' OR j.nama LIKE '%wajib%')
           AND IFNULL(a.status,'') NOT IN ('aktif','pasif')
      THEN 0
      ELSE s.jumlah
    END
  ),0) total
  FROM jenis_simpanan j
  LEFT JOIN " . sql_union_simpanan('s') . " ON s.jenis_id=j.id";
if ($where) {
    $sqlRekap .= ' AND ' . implode(' AND ', $where);
}
$sqlRekap .= ' LEFT JOIN anggota a ON a.id=s.anggota_id GROUP BY j.id';
$st = $pdo->prepare($sqlRekap);
$st->execute($params);
$rekap = $st->fetchAll();

$qsCetak = http_build_query(array_filter([
    'jenis' => 'simpanan',
    'anggota_id' => $fAnggota ?: null,
    'jenis_id' => $fJenis ?: null,
    'bulan' => $fBulan !== '' ? $fBulan : null,
]));
$adaFilter = ($staff && $fAnggota) || $fJenis || $fBulan !== '';
include __DIR__ . '/includes/app_header.php';
?>
<div class="kpis">
  <?php foreach ($rekap as $r): ?>
    <div class="kpi"><span><?= e($r['nama']) ?></span><b><?= rupiah($r['total']) ?></b></div>
  <?php endforeach; ?>
</div>
<?php if ($staff): ?>
  <p style="font-size:13px;color:var(--muted);margin:-4px 0 14px;">Total pokok &amp; wajib hanya anggota aktif dan pasif. Nonaktif tidak dijumlahkan.</p>
<?php endif; ?>
<form method="get" class="card" style="margin-bottom:16px;padding:14px 16px;">
  <div class="row" style="flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <?php if ($staff): ?>
    <div style="min-width:220px;flex:1;">
      <label>Anggota</label>
      <select name="anggota_id">
        <option value="">Semua anggota</option>
        <?php foreach ($anggotaFilter as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= $fAnggota === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['no_anggota'].' — '.$a['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div style="min-width:160px;">
      <label>Jenis simpanan</label>
      <select name="jenis_id">
        <option value="">Semua jenis</option>
        <?php foreach ($jenis as $j): ?>
          <option value="<?= (int)$j['id'] ?>" <?= $fJenis === (int)$j['id'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:140px;">
      <label>Bulan</label>
      <input type="month" name="bulan" value="<?= e($fBulan) ?>">
    </div>
    <div class="row" style="gap:8px;">
      <button class="btn btn-green" type="submit">Cari</button>
      <?php if ($adaFilter): ?>
        <a class="btn btn-ghost" href="simpanan.php">Reset</a>
      <?php endif; ?>
    </div>
  </div>
</form>
<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <a class="btn btn-ghost" href="cetak.php?<?= e($qsCetak) ?>" target="_blank"><?= $adaFilter ? 'Cetak hasil pencarian' : 'Cetak daftar' ?></a>
  <?php if ($staff): ?>
  <button class="btn btn-green" type="button" onclick="openModal('mWajib')">+ Simpanan wajib (semua anggota)</button>
  <button class="btn btn-gold" type="button" onclick="openModal('mSukarela')">+ Simpanan sukarela</button>
  <?php endif; ?>
</div>
<?php if ($adaFilter): ?>
  <p style="font-size:13px;color:var(--muted);margin:-8px 0 12px;"><?= count($rows) ?> transaksi sesuai pencarian. Total tampilan: <?= rupiah(array_sum(array_column($rows, 'jumlah'))) ?>.</p>
<?php endif; ?>
<div class="table-wrap">
  <table>
    <thead><tr><?= th_urut('tanggal','Tanggal') ?><?= th_urut('no','No. Anggota') ?><?= th_urut('nama','Nama') ?><?= th_urut('jenis','Jenis') ?><?= th_urut('jumlah','Jumlah') ?><?= th_urut('ket','Keterangan') ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= tgl($r['tanggal']) ?></td>
        <td><?= e($r['no_anggota']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['jenis']) ?></td>
        <td><?= rupiah($r['jumlah']) ?></td>
        <td><?= e($r['keterangan']) ?></td>
        <td><a class="btn btn-ghost btn-sm" href="cetak.php?jenis=simpanan&id=<?= $r['id'] ?>" target="_blank">Cetak</a></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7">Belum ada setoran.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($staff): ?>
<div class="modal-bg" id="mWajib">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="wajib_semua">
    <h3>Simpanan wajib semua anggota</h3>
    <p style="font-size:13px;color:var(--muted);margin:8px 0 12px;">Dicatat sekaligus ke <?= (int)$nAktif ?> anggota aktif. Yang sudah setor bulan itu dilewati. Bukan potongan amprah. Wajib otomatis bulanan dihitung sejak bulan pendirian (<?= e(tgl(tanggal_mulai_wajib_otomatis()->format('Y-m-d'))) ?>).</p>
    <label>Bulan</label>
    <input type="month" name="bulan" value="<?= date('Y-m') ?>" required>
    <label>Jumlah per orang (Rp)</label>
    <input name="jumlah" type="number" min="0" step="1000" value="<?= (int)($set['simpanan_wajib'] ?? 50000) ?>" required>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mWajib')">Batal</button>
      <button class="btn btn-green">Catat untuk semua</button>
    </div>
  </form>
</div>
<div class="modal-bg" id="mSukarela">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="sukarela">
    <h3>Simpanan sukarela</h3>
    <label>Anggota</label>
    <select name="anggota_id" required>
      <?php foreach ($anggota as $a): ?>
        <option value="<?= $a['id'] ?>"><?= e($a['no_anggota'].' — '.$a['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
      <div><label>Jumlah (Rp)</label><input name="jumlah" required></div>
    </div>
    <label>Keterangan</label><input name="keterangan" value="Simpanan sukarela">
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mSukarela')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
