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
if (!function_exists('ensure_penarikan_schema')) {
    function ensure_penarikan_schema(): void {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS penarikan_simpanan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                no_penarikan VARCHAR(30) NOT NULL UNIQUE,
                anggota_id INT NOT NULL,
                tanggal DATE NOT NULL,
                saldo_pokok DECIMAL(15,2) NOT NULL DEFAULT 0,
                saldo_wajib DECIMAL(15,2) NOT NULL DEFAULT 0,
                saldo_sukarela DECIMAL(15,2) NOT NULL DEFAULT 0,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pengajuan',
                catatan TEXT NULL,
                created_by INT NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
        }
    }
}
ensure_penarikan_schema();
$title = 'Penarikan simpanan';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus'], true);
$stAgt = $staff ? 'aktif' : status_anggota((int)($u['anggota_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'ajukan';
    if ($act === 'ajukan' && !$staff) {
        $aid = (int)$u['anggota_id'];
        $stt = status_anggota($aid);
        if ($stt !== 'pasif') {
            flash('err', 'Penarikan seluruh simpanan hanya untuk anggota pasif.');
        } else {
            $ringkas = ringkas_anggota_pengalihan($aid);
            if (!$ringkas) {
                flash('err', 'Data anggota tidak ditemukan.');
            } elseif ($ringkas['utang'] > 0) {
                flash('err', 'Masih ada sisa pinjaman. Lunasi dulu.');
            } else {
                $ada = $pdo->prepare("SELECT id FROM penarikan_simpanan WHERE anggota_id=? AND status='pengajuan'");
                $ada->execute([$aid]);
                if ($ada->fetch()) {
                    flash('err', 'Masih ada pengajuan penarikan yang menunggu persetujuan.');
                } else {
                    $pok = (float)$ringkas['pokok'];
                    $waj = (float)$ringkas['wajib'];
                    $suk = (float)$ringkas['sukarela'];
                    $tot = $pok + $waj + $suk;
                    if ($tot <= 0) {
                        flash('err', 'Saldo simpanan sudah kosong.');
                    } else {
                        $seq = 1 + (int)$pdo->query('SELECT COUNT(*) FROM penarikan_simpanan')->fetchColumn();
                        $no = 'TARIK-' . date('Y') . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
                        $pdo->prepare('INSERT INTO penarikan_simpanan (no_penarikan,anggota_id,tanggal,saldo_pokok,saldo_wajib,saldo_sukarela,total,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                            ->execute([$no, $aid, date('Y-m-d'), $pok, $waj, $suk, $tot, 'pengajuan', $u['id'] ?? null]);
                        flash('ok', 'Pengajuan '.$no.' terkirim. Setelah disetujui, seluruh simpanan ditarik tunai dan keanggotaan menjadi nonaktif.');
                    }
                }
            }
        }
        header('Location: penarikan.php');
        exit;
    }
    if ($staff && $act === 'setujui') {
        $err = proses_setujui_penarikan((int)$_POST['id'], $u['id'] ?? null);
        flash($err ? 'err' : 'ok', $err ?: 'Penarikan disetujui. Simpanan ditarik tunai, anggota nonaktif.');
        header('Location: penarikan.php');
        exit;
    }
    if ($staff && $act === 'tolak') {
        $cat = trim((string)($_POST['catatan'] ?? ''));
        if ($cat === '') {
            flash('err', 'Penolakan wajib ada keterangan.');
        } else {
            $pdo->prepare("UPDATE penarikan_simpanan SET status='ditolak', catatan=? WHERE id=? AND status='pengajuan'")
                ->execute([$cat, (int)$_POST['id']]);
            flash('ok', 'Pengajuan ditolak.');
        }
        header('Location: penarikan.php');
        exit;
    }
}

if ($staff) {
    $ordP = sql_urut([
        'no' => 'p.no_penarikan',
        'tanggal' => 'p.tanggal',
        'nama' => 'a.nama',
        'pokok' => 'p.saldo_pokok',
        'wajib' => 'p.saldo_wajib',
        'sukarela' => 'p.saldo_sukarela',
        'total' => 'p.total',
        'status' => 'p.status',
    ], 'p.id DESC');
    $rows = $pdo->query("SELECT p.*, a.nama, a.no_anggota, a.status AS st_agt
        FROM penarikan_simpanan p JOIN anggota a ON a.id=p.anggota_id ORDER BY $ordP")->fetchAll();
} else {
    $ordP = sql_urut([
        'no' => 'p.no_penarikan',
        'tanggal' => 'p.tanggal',
        'nama' => 'a.nama',
        'pokok' => 'p.saldo_pokok',
        'wajib' => 'p.saldo_wajib',
        'sukarela' => 'p.saldo_sukarela',
        'total' => 'p.total',
        'status' => 'p.status',
    ], 'p.id DESC');
    $st = $pdo->prepare("SELECT p.*, a.nama, a.no_anggota, a.status AS st_agt
        FROM penarikan_simpanan p JOIN anggota a ON a.id=p.anggota_id WHERE p.anggota_id=? ORDER BY $ordP");
    $st->execute([(int)$u['anggota_id']]);
    $rows = $st->fetchAll();
    $ringkas = ringkas_anggota_pengalihan((int)$u['anggota_id']);
}

include __DIR__ . '/includes/app_header.php';
?>
<p style="color:var(--muted);font-size:14px;margin-bottom:14px;">
  Anggota <strong>pasif</strong> boleh masuk portal, tidak boleh ajukan pinjaman, dan boleh menarik seluruh simpanan.
  Setelah pengurus menyetujui, saldo ditarik tunai dan status menjadi <strong>nonaktif</strong>.
</p>
<?php if (!$staff && $stAgt === 'pasif' && !empty($ringkas)): ?>
  <div class="kpis">
    <div class="kpi"><span>Pokok</span><b><?= rupiah($ringkas['pokok']) ?></b></div>
    <div class="kpi"><span>Wajib</span><b><?= rupiah($ringkas['wajib']) ?></b></div>
    <div class="kpi"><span>Sukarela</span><b><?= rupiah($ringkas['sukarela']) ?></b></div>
    <div class="kpi"><span>Total</span><b><?= rupiah($ringkas['pokok']+$ringkas['wajib']+$ringkas['sukarela']) ?></b></div>
  </div>
  <div class="row" style="margin-bottom:16px;justify-content:flex-end;">
    <form method="post" onsubmit="return confirm('Ajukan penarikan seluruh simpanan? Setelah disetujui, keanggotaan menjadi nonaktif.');">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="ajukan">
      <button class="btn btn-gold">Ajukan penarikan seluruh simpanan</button>
    </form>
  </div>
<?php elseif (!$staff): ?>
  <p style="font-size:13px;color:var(--muted);">Menu ini aktif jika status keanggotaan Anda <strong>pasif</strong>.</p>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?= th_urut('no','No') ?><?= th_urut('tanggal','Tanggal') ?><?php if ($staff): ?><?= th_urut('nama','Anggota') ?><?php endif; ?>
        <?= th_urut('pokok','Pokok') ?><?= th_urut('wajib','Wajib') ?><?= th_urut('sukarela','Sukarela') ?><?= th_urut('total','Total') ?><?= th_urut('status','Status') ?><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['no_penarikan']) ?></td>
        <td><?= tgl($r['tanggal']) ?></td>
        <?php if ($staff): ?><td><?= e($r['nama']) ?><br><small><?= e($r['no_anggota']) ?> · <?= e($r['st_agt']) ?></small></td><?php endif; ?>
        <td><?= rupiah($r['saldo_pokok']) ?></td>
        <td><?= rupiah($r['saldo_wajib']) ?></td>
        <td><?= rupiah($r['saldo_sukarela']) ?></td>
        <td><?= rupiah($r['total']) ?></td>
        <td>
          <span class="badge b-<?= e($r['status']==='disetujui'?'lunas':($r['status']==='ditolak'?'ditolak':'pengajuan')) ?>"><?= e($r['status']) ?></span>
          <?php if ($r['status']==='ditolak' && $r['catatan']): ?><br><small><?= e($r['catatan']) ?></small><?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($staff && $r['status']==='pengajuan'): ?>
            <form method="post" onsubmit="return confirm('Setujui penarikan? Kas keluar, anggota nonaktif.');">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="setujui">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-green btn-sm">Setujui</button>
            </form>
            <form method="post" onsubmit="var c=prompt('Keterangan penolakan:'); if(!c) return false; this.catatan.value=c;">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="tolak">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="catatan" value="">
              <button class="btn btn-danger btn-sm">Tolak</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="<?= $staff ? 9 : 8 ?>">Belum ada pengajuan penarikan.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
