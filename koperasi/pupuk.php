<?php
require __DIR__ . '/config.php';
require_login();
ensure_pupuk_schema();
ensure_akuntansi_schema();
$title = 'Pupuk organik';
$pdo = db();
$u = auth();
$staff = in_array($u['role'] ?? '', ['admin', 'pengurus'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$staff) {
        flash('err', 'Hanya pengurus yang boleh mencatat.');
        header('Location: pupuk.php');
        exit;
    }
    $act = $_POST['act'] ?? '';
    if ($act === 'produk') {
        $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $harga = (float)str_replace('.', '', (string)($_POST['harga_jual'] ?? '0'));
        if ($kode === '' || $nama === '') {
            flash('err', 'Kode dan nama produk wajib diisi.');
        } else {
            try {
                $pdo->prepare('INSERT INTO pupuk_produk (kode,nama,jenis,satuan,harga_jual,keterangan) VALUES (?,?,?,?,?,?)')
                    ->execute([$kode, $nama, trim((string)($_POST['jenis'] ?? '')), trim((string)($_POST['satuan'] ?? 'kg')) ?: 'kg', $harga, trim((string)($_POST['keterangan'] ?? ''))]);
                flash('ok', 'Produk pupuk ditambahkan.');
            } catch (Throwable $e) {
                $pdo->prepare('UPDATE pupuk_produk SET nama=?, jenis=?, satuan=?, harga_jual=?, keterangan=?, aktif=1 WHERE kode=?')
                    ->execute([$nama, trim((string)($_POST['jenis'] ?? '')), trim((string)($_POST['satuan'] ?? 'kg')) ?: 'kg', $harga, trim((string)($_POST['keterangan'] ?? '')), $kode]);
                flash('ok', 'Produk ' . $kode . ' diperbarui.');
            }
        }
    } elseif ($act === 'produksi') {
        $pid = (int)($_POST['produk_id'] ?? 0);
        $tgl = trim((string)($_POST['tanggal'] ?? '')) ?: date('Y-m-d');
        $jml = (float)str_replace(',', '.', (string)($_POST['jumlah'] ?? '0'));
        $biaya = (float)str_replace('.', '', (string)($_POST['biaya'] ?? '0'));
        if ($pid < 1 || $jml <= 0) {
            flash('err', 'Pilih produk dan isi jumlah hasil produksi.');
        } else {
            $hpp = $jml > 0 ? round($biaya / $jml, 2) : 0;
            $pdo->prepare("INSERT INTO pupuk_mutasi (tanggal,produk_id,arah,jumlah,harga_satuan,total_nilai,pihak,keterangan,created_by) VALUES (?,?, 'masuk',?,?,?,?,?,?)")
                ->execute([$tgl, $pid, $jml, $hpp, $biaya, 'Unit produksi', trim((string)($_POST['keterangan'] ?? 'Hasil produksi')), $u['id']]);
            $mid = (int)$pdo->lastInsertId();
            if ($biaya > 0) {
                posting_jurnal($tgl, 'Biaya produksi pupuk', [
                    ['kode' => '1312', 'posisi' => 'debit', 'nominal' => $biaya],
                    ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => $biaya],
                ], 'pupuk_produksi', $mid, $u['id']);
            }
            flash('ok', 'Hasil produksi dicatat. Stok bertambah ' . number_format($jml, 2, ',', '.') . '.');
        }
    } elseif ($act === 'jual') {
        $pid = (int)($_POST['produk_id'] ?? 0);
        $tgl = trim((string)($_POST['tanggal'] ?? '')) ?: date('Y-m-d');
        $jml = (float)str_replace(',', '.', (string)($_POST['jumlah'] ?? '0'));
        $hs = (float)str_replace('.', '', (string)($_POST['harga_satuan'] ?? '0'));
        $aid = (int)($_POST['anggota_id'] ?? 0);
        $cara = ($_POST['cara_bayar'] ?? 'tunai') === 'piutang' ? 'piutang' : 'tunai';
        $pihak = trim((string)($_POST['pihak'] ?? ''));
        if ($pid < 1 || $jml <= 0 || $hs < 0) {
            flash('err', 'Lengkapi produk, jumlah, dan harga jual.');
        } elseif ($jml > stok_pupuk($pid) + 0.0001) {
            flash('err', 'Stok tidak cukup. Sisa ' . number_format(stok_pupuk($pid), 2, ',', '.') . '.');
        } elseif ($cara === 'piutang' && $aid < 1) {
            flash('err', 'Penjualan piutang wajib memilih anggota.');
        } else {
            if ($aid > 0) {
                $nm = $pdo->prepare('SELECT nama FROM anggota WHERE id=?');
                $nm->execute([$aid]);
                $pihak = ($nm->fetchColumn() ?: $pihak);
            }
            if ($pihak === '') {
                $pihak = 'Umum';
            }
            $total = $jml * $hs;
            $pdo->prepare("INSERT INTO pupuk_mutasi (tanggal,produk_id,arah,jumlah,harga_satuan,total_nilai,pihak,anggota_id,cara_bayar,keterangan,created_by) VALUES (?,?, 'keluar',?,?,?,?,?,?,?,?)")
                ->execute([$tgl, $pid, $jml, $hs, $total, $pihak, $aid > 0 ? $aid : null, $cara, trim((string)($_POST['keterangan'] ?? 'Penjualan')), $u['id']]);
            $mid = (int)$pdo->lastInsertId();
            if ($total > 0) {
                $debit = $cara === 'tunai' ? '1111' : '1212';
                posting_jurnal($tgl, 'Penjualan pupuk (' . $cara . ')', [
                    ['kode' => $debit, 'posisi' => 'debit', 'nominal' => $total],
                    ['kode' => '4114', 'posisi' => 'kredit', 'nominal' => $total],
                ], 'pupuk_jual', $mid, $u['id']);
            }
            flash('ok', 'Penjualan dicatat: ' . rupiah($total) . '.');
        }
    }
    header('Location: pupuk.php');
    exit;
}

if (isset($_GET['nonaktif']) && $staff) {
    if (!hash_equals(csrf_token(), (string)($_GET['_csrf'] ?? ''))) {
        flash('err', 'Permintaan tidak valid.');
    } else {
        $pdo->prepare('UPDATE pupuk_produk SET aktif=0 WHERE id=?')->execute([(int)$_GET['nonaktif']]);
        flash('ok', 'Produk dinonaktifkan.');
    }
    header('Location: pupuk.php');
    exit;
}

$produk = daftar_pupuk_stok();
$nilaiStok = 0;
foreach ($produk as $p) {
    $nilaiStok += (float)$p['stok'] * (float)$p['harga_jual'];
}
$jualBulan = (float)$pdo->query("SELECT COALESCE(SUM(total_nilai),0) FROM pupuk_mutasi WHERE arah='keluar' AND DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
if ($staff) {
    $anggota = $pdo->query("SELECT id, no_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama")->fetchAll();
    $riwayat = $pdo->query("SELECT m.*, pr.kode, pr.nama nama_produk, pr.satuan FROM pupuk_mutasi m JOIN pupuk_produk pr ON pr.id=m.produk_id ORDER BY m.tanggal DESC, m.id DESC LIMIT 150")->fetchAll();
} else {
    $anggota = [];
    $st = $pdo->prepare("SELECT m.*, pr.kode, pr.nama nama_produk, pr.satuan FROM pupuk_mutasi m JOIN pupuk_produk pr ON pr.id=m.produk_id WHERE m.anggota_id=? AND m.arah='keluar' ORDER BY m.tanggal DESC, m.id DESC LIMIT 50");
    $st->execute([(int)($u['anggota_id'] ?? 0)]);
    $riwayat = $st->fetchAll();
}
include __DIR__ . '/includes/app_header.php';
?>
<?php if ($staff): ?>
<div class="kpis">
  <div class="kpi"><span>Nilai persediaan pupuk</span><b><?= rupiah($nilaiStok) ?></b></div>
  <div class="kpi"><span>Penjualan bulan ini</span><b><?= rupiah($jualBulan) ?></b></div>
  <div class="kpi"><span>Jenis produk aktif</span><b><?= count($produk) ?></b></div>
</div>
<div class="row" style="margin-bottom:14px;justify-content:flex-end;gap:8px;">
  <button class="btn btn-ghost" type="button" onclick="openModal('mProduk')">+ Produk</button>
  <button class="btn btn-ghost" type="button" onclick="openModal('mProduksi')">+ Hasil produksi</button>
  <button class="btn btn-green" type="button" onclick="openModal('mJual')">+ Penjualan</button>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px;">
  <h3>Pupuk organik koperasi</h3>
  <p style="font-size:14px;color:var(--muted);margin-top:8px;">Anggota mendapat harga khusus. Untuk memesan, hubungi pengurus unit usaha. Riwayat pembelian Anda tampil di bawah.</p>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;">
  <h3 style="margin-bottom:12px;">Stok produk</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Kode</th><th>Produk</th><th>Satuan</th><th>Harga anggota</th><th>Stok</th><th>Nilai</th><?php if ($staff): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($produk as $p): ?>
        <tr>
          <td><?= e($p['kode']) ?></td>
          <td><strong><?= e($p['nama']) ?></strong><?php if (!empty($p['keterangan'])): ?><br><small style="color:var(--muted);"><?= e($p['keterangan']) ?></small><?php endif; ?></td>
          <td><?= e($p['satuan']) ?></td>
          <td><?= rupiah($p['harga_jual']) ?></td>
          <td><strong><?= number_format((float)$p['stok'], 2, ',', '.') ?></strong></td>
          <td><?= rupiah((float)$p['stok'] * (float)$p['harga_jual']) ?></td>
          <?php if ($staff): ?>
          <td><a class="btn btn-ghost btn-sm" href="?nonaktif=<?= (int)$p['id'] ?>&_csrf=<?= e(csrf_token()) ?>" onclick="return confirm('Nonaktifkan produk ini?')">Nonaktif</a></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; if (!$produk): ?>
        <tr><td colspan="7">Belum ada produk.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin-bottom:12px;"><?= $staff ? 'Mutasi terakhir' : 'Pembelian saya' ?></h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tanggal</th><th>Produk</th><?php if ($staff): ?><th>Arah</th><?php endif; ?><th>Jumlah</th><th>Harga</th><th>Total</th><th>Pihak</th><?php if ($staff): ?><th>Bayar</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td><?= tgl($r['tanggal']) ?></td>
          <td><?= e($r['kode'] . ' — ' . $r['nama_produk']) ?></td>
          <?php if ($staff): ?><td><?= $r['arah'] === 'masuk' ? 'Produksi' : 'Jual' ?></td><?php endif; ?>
          <td><?= number_format((float)$r['jumlah'], 2, ',', '.') . ' ' . e($r['satuan']) ?></td>
          <td><?= rupiah($r['harga_satuan']) ?></td>
          <td><?= rupiah($r['total_nilai']) ?></td>
          <td><?= e($r['pihak'] ?: '—') ?></td>
          <?php if ($staff): ?><td><?= e($r['cara_bayar']) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; if (!$riwayat): ?>
        <tr><td colspan="8">Belum ada mutasi.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($staff): ?>
<div class="modal-bg" id="mProduk">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="produk">
    <h3>Tambah / ubah produk</h3>
    <div class="grid-2">
      <div><label>Kode</label><input name="kode" placeholder="PO-G002" required></div>
      <div><label>Jenis</label><input name="jenis" placeholder="granul / cair / kompos"></div>
    </div>
    <label>Nama produk</label>
    <input name="nama" placeholder="Pupuk Organik ..." required>
    <div class="grid-2">
      <div><label>Satuan</label><input name="satuan" value="kg"></div>
      <div><label>Harga jual (Rp)</label><input name="harga_jual" value="0"></div>
    </div>
    <label>Keterangan</label>
    <input name="keterangan">
    <div class="row" style="margin-top:14px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mProduk')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>

<div class="modal-bg" id="mProduksi">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="produksi">
    <h3>Catat hasil produksi</h3>
    <label>Produk</label>
    <select name="produk_id" required>
      <?php foreach ($produk as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['kode'] . ' — ' . $p['nama'] . ' (' . $p['satuan'] . ')') ?></option><?php endforeach; ?>
    </select>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Jumlah hasil</label><input name="jumlah" type="number" step="0.01" min="0" placeholder="0" required></div>
    </div>
    <label>Total biaya produksi (Rp) — bahan, upah, kemas</label>
    <input name="biaya" value="0">
    <label>Keterangan</label>
    <input name="keterangan" placeholder="cth. Batch fermentasi #...">
    <p style="font-size:12px;color:var(--muted);margin-top:8px;">Dijurnal otomatis: Persediaan pupuk (D) / Kas (K) sebesar biaya.</p>
    <div class="row" style="margin-top:14px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mProduksi')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>

<div class="modal-bg" id="mJual">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="jual">
    <h3>Catat penjualan</h3>
    <label>Produk</label>
    <select name="produk_id" id="jualProduk" onchange="isiHarga()" required>
      <?php foreach ($produk as $p): ?><option value="<?= (int)$p['id'] ?>" data-harga="<?= (float)$p['harga_jual'] ?>"><?= e($p['kode'] . ' — ' . $p['nama'] . ' (stok ' . number_format((float)$p['stok'], 1, ',', '.') . ' ' . $p['satuan'] . ')') ?></option><?php endforeach; ?>
    </select>
    <div class="grid-2">
      <div><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Jumlah</label><input name="jumlah" type="number" step="0.01" min="0" placeholder="0" required></div>
    </div>
    <label>Harga satuan (Rp)</label>
    <input name="harga_satuan" id="jualHarga" required>
    <label>Anggota pembeli (opsional — wajib jika piutang)</label>
    <select name="anggota_id">
      <option value="0">Umum / non-anggota</option>
      <?php foreach ($anggota as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['no_anggota'] . ' — ' . $a['nama']) ?></option><?php endforeach; ?>
    </select>
    <div class="grid-2">
      <div><label>Nama pembeli umum</label><input name="pihak" placeholder="isi jika non-anggota"></div>
      <div><label>Cara bayar</label><select name="cara_bayar"><option value="tunai">Tunai</option><option value="piutang">Piutang</option></select></div>
    </div>
    <label>Keterangan</label>
    <input name="keterangan" placeholder="cth. Nota ...">
    <div class="row" style="margin-top:14px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mJual')">Batal</button>
      <button class="btn btn-green">Simpan</button>
    </div>
  </form>
</div>
<script>
function isiHarga() {
  var s = document.getElementById('jualProduk');
  if (!s || !s.options.length || s.selectedIndex < 0) return;
  var h = s.options[s.selectedIndex].getAttribute('data-harga') || '0';
  document.getElementById('jualHarga').value = h;
}
isiHarga();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
