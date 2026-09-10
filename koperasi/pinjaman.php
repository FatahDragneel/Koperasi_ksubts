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
ensure_pinjaman_schema();
$title = 'Pinjaman';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin','pengurus']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'ajukan';
    if ($act === 'ajukan') {
        if ($staff) {
            $aid = (int)($_POST['anggota_id'] ?? 0);
            if ($aid < 1) {
                flash('err', 'Pilih anggota.');
                header('Location: pinjaman.php');
                exit;
            }
        } else {
            $aid = (int)$u['anggota_id'];
            if (status_anggota($aid) === 'pasif') {
                flash('err', 'Anggota pasif tidak boleh mengajukan pinjaman. Anda bisa menarik simpanan di menu Penarikan.');
                header('Location: pinjaman.php');
                exit;
            }
        }
        $jumlah = (float)str_replace('.', '', $_POST['jumlah']);
        $bagi = (float)(setting()['bagi_hasil_persen'] ?? 1);
        $tenor = (int)$_POST['tenor'];
        $total = hitung_tagihan_pinjaman($jumlah, $bagi, $tenor);
        $tglAju = trim((string)($_POST['tanggal'] ?? '')) ?: date('Y-m-d');
        $seq = 1 + (int)$pdo->query('SELECT COUNT(*) FROM pengajuan_pinjaman')->fetchColumn();
        $noG = 'PGJ-' . date('Y') . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        $no = 'PJM-' . date('Y') . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO pengajuan_pinjaman (no_pengajuan,anggota_id,tanggal,jumlah,bagi_hasil_persen,tenor,keperluan,agunan,status) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$noG, $aid, $tglAju, $jumlah, $bagi, $tenor, trim($_POST['keperluan'] ?? ''), trim($_POST['agunan'] ?? ''), 'pengajuan']);
        $gid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO pinjaman (no_pinjaman,anggota_id,tanggal,jumlah,bagi_hasil_persen,tenor,keperluan,status,total_tagihan,sisa,agunan,pengajuan_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$no, $aid, $tglAju, $jumlah, $bagi, $tenor, trim($_POST['keperluan'] ?? ''), 'pengajuan', $total, $total, trim($_POST['agunan'] ?? ''), $gid]);
        flash('ok', $staff
            ? 'Pengajuan anggota tercatat ('.$noG.'). Setujui atau tolak di daftar.'
            : 'Pengajuan terkirim ('.$noG.'). Menunggu persetujuan admin.');
    } elseif ($staff && $act === 'status') {
        $id = (int)$_POST['id'];
        $stt = $_POST['status'];
        $cat = trim($_POST['catatan'] ?? '');
        if ($stt === 'ditolak' && $cat === '') {
            flash('err', 'Penolakan wajib diisi keterangan untuk anggota.');
            header('Location: pinjaman.php');
            exit;
        }
        $pj = $pdo->prepare('SELECT * FROM pinjaman WHERE id=?');
        $pj->execute([$id]);
        $pjr = $pj->fetch();
        if (!$pjr || $pjr['status'] !== 'pengajuan') {
            flash('err', 'Pengajuan tidak valid.');
            header('Location: pinjaman.php');
            exit;
        }
        if ($stt === 'ditolak') {
            $pdo->prepare("UPDATE pinjaman SET status='ditolak', catatan=? WHERE id=?")->execute([$cat, $id]);
            if ($pjr['pengajuan_id']) {
                $pdo->prepare("UPDATE pengajuan_pinjaman SET status='ditolak', catatan=? WHERE id=?")->execute([$cat, $pjr['pengajuan_id']]);
            }
            flash('ok', 'Pinjaman ditolak. Keterangan tampil di akun anggota.');
        } else {
            $tglCair = $_POST['tanggal_cair'] ?? date('Y-m-d');
            $noPer = trim($_POST['no_perjanjian'] ?? '') ?: ('PRJ-' . $pjr['no_pinjaman']);
            $pdo->prepare("UPDATE pengajuan_pinjaman SET status='disetujui', catatan=? WHERE id=?")->execute([$cat, $pjr['pengajuan_id']]);
            $pdo->prepare('INSERT INTO pencairan_pinjaman (pengajuan_id,no_pinjaman,no_perjanjian,tanggal_cair,jumlah_cair,total_tagihan,sisa,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$pjr['pengajuan_id'], $pjr['no_pinjaman'], $noPer, $tglCair, $pjr['jumlah'], $pjr['total_tagihan'], $pjr['total_tagihan'], 'berjalan', $u['id']]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE pinjaman SET status='berjalan', catatan=?, pencairan_id=?, no_perjanjian=? WHERE id=?")
                ->execute([$cat, $cid, $noPer, $id]);
            posting_jurnal($tglCair, 'Pencairan '.$pjr['no_pinjaman'], [
                ['kode' => '1211', 'posisi' => 'debit', 'nominal' => (float)$pjr['jumlah']],
                ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => (float)$pjr['jumlah']],
            ], 'pencairan', $cid, $u['id']);
            flash('ok', 'Disetujui dan dicairkan. Jurnal pencairan tercatat.');
        }
    }
    header('Location: pinjaman.php'); exit;
}

if ($staff) {
    $rows = $pdo->query("SELECT p.*, a.nama, a.no_anggota, g.no_pengajuan, c.tanggal_cair, c.no_perjanjian
        FROM pinjaman p
        JOIN anggota a ON a.id=p.anggota_id
        LEFT JOIN pengajuan_pinjaman g ON g.id=p.pengajuan_id
        LEFT JOIN pencairan_pinjaman c ON c.id=p.pencairan_id
        ORDER BY " . sql_urut([
            'no' => 'p.no_pinjaman',
            'nama' => 'a.nama',
            'tanggal' => 'p.tanggal',
            'pokok' => 'p.jumlah',
            'tenor' => 'p.tenor',
            'total' => 'p.total_tagihan',
            'sisa' => 'p.sisa',
            'status' => 'p.status',
        ], 'p.id DESC') . "")->fetchAll();
    $anggota = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT p.*, a.nama, a.no_anggota, g.no_pengajuan, c.tanggal_cair, c.no_perjanjian
        FROM pinjaman p
        JOIN anggota a ON a.id=p.anggota_id
        LEFT JOIN pengajuan_pinjaman g ON g.id=p.pengajuan_id
        LEFT JOIN pencairan_pinjaman c ON c.id=p.pencairan_id
        WHERE p.anggota_id=? ORDER BY " . sql_urut([
            'no' => 'p.no_pinjaman',
            'nama' => 'a.nama',
            'tanggal' => 'p.tanggal',
            'pokok' => 'p.jumlah',
            'tenor' => 'p.tenor',
            'total' => 'p.total_tagihan',
            'sisa' => 'p.sisa',
            'status' => 'p.status',
        ], 'p.id DESC'));
    $st->execute([$u['anggota_id']]);
    $rows = $st->fetchAll();
    $anggota = [];
}
include __DIR__ . '/includes/app_header.php';
?>
<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <a class="btn btn-ghost" href="cetak.php?jenis=pinjaman" target="_blank">Cetak daftar</a>
  <?php if (!$staff): ?>
  <a class="btn btn-ghost" href="pinjaman_bayar.php">Kirim bukti bayar</a>
  <?php endif; ?>
  <?php if ($staff || status_anggota((int)($u['anggota_id'] ?? 0)) !== 'pasif'): ?>
  <button class="btn btn-green" type="button" onclick="openModal('mPinjam')"><?= $staff ? '+ Ajukan pinjaman anggota' : '+ Ajukan pinjaman' ?></button>
  <?php else: ?>
  <span style="font-size:13px;color:var(--muted);">Status pasif: tidak bisa ajukan pinjaman. Gunakan menu Penarikan simpanan.</span>
  <?php endif; ?>
</div>
<p style="font-size:13px;color:var(--muted);margin-bottom:12px;">Alur terpisah: <strong>pengajuan</strong> → admin setujui/tolak → <strong>pencairan</strong> (jurnal) → <strong>angsuran</strong> (pokok + bagi hasil + denda).</p>
<div class="table-wrap">
  <table>
    <thead><tr><?= th_urut('no','No') ?><?= th_urut('nama','Anggota') ?><?= th_urut('tanggal','Tanggal') ?><?= th_urut('pokok','Pokok') ?><?= th_urut('tenor','Tenor') ?><?= th_urut('total','Total') ?><?= th_urut('sisa','Sisa') ?><?= th_urut('status','Status') ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <?= e($r['no_pinjaman']) ?>
          <?php if (!empty($r['no_pengajuan'])): ?><br><small><?= e($r['no_pengajuan']) ?></small><?php endif; ?>
        </td>
        <td><?= e($r['nama']) ?><br><small><?= e($r['keperluan']) ?></small></td>
        <td><?= tgl($r['tanggal']) ?><?php if (!empty($r['tanggal_cair'])): ?><br><small>Cair <?= tgl($r['tanggal_cair']) ?></small><?php endif; ?></td>
        <td><?= rupiah($r['jumlah']) ?></td>
            <td><?= (int)$r['tenor'] ?> bln<br><small>Bagi hasil <?= $r['bagi_hasil_persen'] ?>% / bln</small></td>
        <td><?= rupiah($r['total_tagihan']) ?></td>
        <td><?= rupiah($r['sisa']) ?></td>
        <td>
          <span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
          <?php if ($r['status']==='ditolak' && !empty($r['catatan'])): ?>
            <br><small style="color:#c0392b;">Alasan: <?= e($r['catatan']) ?></small>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a class="btn btn-ghost btn-sm" href="cetak.php?jenis=pinjaman&id=<?= $r['id'] ?>" target="_blank">Cetak</a>
          <?php if ($staff && $r['status']==='pengajuan'): ?>
          <form method="post" class="actions">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-green btn-sm" name="status" value="disetujui">Setujui & cairkan</button>
          </form>
          <button class="btn btn-danger btn-sm" type="button" onclick="tolakPinjam(<?= (int)$r['id'] ?>, '<?= e($r['no_pinjaman']) ?>')">Tolak</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9">Belum ada pinjaman.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mPinjam">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="ajukan">
    <h3><?= $staff ? 'Catat pinjaman' : 'Pengajuan pinjaman' ?></h3>
    <?php if ($staff): ?>
    <label>Anggota</label>
    <select name="anggota_id" required>
      <?php foreach ($anggota as $a): ?>
        <option value="<?= $a['id'] ?>"><?= e($a['no_anggota'].' — '.$a['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Pokok pinjaman (Rp)</label><input name="jumlah" required placeholder="2000000"></div>
    </div>
    <div class="grid-2">
      <div><label>Bagi hasil % / bulan</label><input value="<?= e(setting()['bagi_hasil_persen'] ?? 1) ?>" readonly></div>
      <div><label>Tenor (bulan)</label><input name="tenor" type="number" min="1" max="24" value="6" required></div>
    </div>
    <label>Keperluan</label><input name="keperluan" required placeholder="Modal pupuk / alat tani">
    <label>Agunan (opsional)</label><input name="agunan" placeholder="Sertifikat lahan / BPKB">
    <p style="font-size:12px;color:var(--muted);margin-top:8px;">Bagi hasil mengikuti pengaturan koperasi (saat ini <?= e(setting()['bagi_hasil_persen'] ?? 1) ?>% / bulan). Total = pokok + (pokok × bagi hasil × tenor).</p>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mPinjam')">Batal</button>
      <button class="btn btn-green">Kirim</button>
    </div>
  </form>
</div>
<div class="modal-bg" id="mTolak">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="status">
    <input type="hidden" name="status" value="ditolak">
    <input type="hidden" name="id" id="tolakId">
    <h3>Tolak pinjaman</h3>
    <p id="tolakNo" style="font-size:13px;color:var(--muted);"></p>
    <label>Keterangan untuk anggota (wajib)</label>
    <textarea name="catatan" required placeholder="Contoh: simpanan wajib belum lengkap / keperluan tidak sesuai"></textarea>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mTolak')">Batal</button>
      <button class="btn btn-danger">Kirim penolakan</button>
    </div>
  </form>
</div>
<script>
function tolakPinjam(id, no) {
  document.getElementById('tolakId').value = id;
  document.getElementById('tolakNo').textContent = 'No. ' + no;
  openModal('mTolak');
}
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
