<?php
require __DIR__ . '/config.php';
require_staff();
ensure_tbs_schema();
ensure_logistik_schema();
$title = 'Timbangan TBS';
$pdo = db();
$u = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idk = (int)($_POST['id_kelompok'] ?? 0);
    $kel = $pdo->prepare('SELECT * FROM kelompok WHERE id=?');
    $kel->execute([$idk]);
    $kel = $kel->fetch();
    if (!$kel) {
        flash('err', 'Pilih kelompok tani.');
        header('Location: tbs_timbang.php');
        exit;
    }
    $masuk = (float)$_POST['berat_masuk'];
    $keluar = (float)$_POST['berat_keluar'];
    $bruto = max(0, $masuk - $keluar);
    $pot = (float)$_POST['persen_potongan'];
    $netto = $bruto * (1 - $pot / 100);
    $kat = $_POST['kategori_umur'] ?? 'dewasa';
    if (!in_array($kat, ['muda', 'dewasa', 'super'], true)) {
        $kat = 'dewasa';
    }
    $hr = harga_tbs_hari(date('Y-m-d'), $kat);
    $harga = (float)($hr['harga_beli'] ?? 0);
    $brutoUang = $netto * $harga;
    $feeKg = (float)($kel['fee_per_kg'] ?? 0);
    $fee = $netto * $feeKg;
    $sisaUang = max(0, $brutoUang - $fee);
    $potSap = 0;
    $potAngs = 0;
    if (!empty($_POST['potong_saprodi'])) {
        $potSap = potong_piutang_saprodi_kelompok($idk, $sisaUang);
        $sisaUang -= $potSap;
    }
    if (!empty($_POST['potong_angsuran'])) {
        $potAngs = potong_angsuran_kelompok($idk, $sisaUang, $u['id']);
        $sisaUang -= $potAngs;
    }
    $bersih = $brutoUang - $fee - $potSap - $potAngs;
    $pdo->prepare('INSERT INTO timbangan_tbs (tanggal,anggota_id,lahan_id,id_kelompok,no_polisi,berat_masuk,berat_keluar,berat_bruto,persen_potongan,berat_netto,kategori_umur,harga_per_kg,total_bruto_uang,fee_kelompok_per_kg,fee_kelompok,potong_angsuran,potong_saprodi,bersih_petani,keterangan,created_by)
        VALUES (NOW(),NULL,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $idk, trim($_POST['no_polisi'] ?? ''),
            $masuk, $keluar, $bruto, $pot, $netto, $kat, $harga, $brutoUang, $feeKg, $fee, $potAngs, $potSap, $bersih,
            trim($_POST['keterangan'] ?? ''), $u['id'],
        ]);
    $tid = (int)$pdo->lastInsertId();
    $baris = [
        ['kode' => '5112', 'posisi' => 'debit', 'nominal' => $brutoUang],
    ];
    if ($bersih > 0) {
        $baris[] = ['kode' => '1111', 'posisi' => 'kredit', 'nominal' => $bersih];
    }
    if ($fee > 0) {
        $baris[] = ['kode' => '2211', 'posisi' => 'kredit', 'nominal' => $fee];
    }
    if ($potAngs > 0) {
        $baris[] = ['kode' => '1211', 'posisi' => 'kredit', 'nominal' => $potAngs];
    }
    if ($potSap > 0) {
        $baris[] = ['kode' => '1212', 'posisi' => 'kredit', 'nominal' => $potSap];
    }
    posting_jurnal(date('Y-m-d'), 'TBS kelompok '.$kel['kode_kelompok'].' #'.$tid, $baris, 'tbs', $tid, $u['id']);
    flash('ok', 'Timbangan kelompok '.$kel['kode_kelompok'].' tercatat. Bersih ' . rupiah($bersih) . '.');
    header('Location: tbs_nota.php?id=' . $tid);
    exit;
}

$kelompok = $pdo->query('SELECT * FROM kelompok ORDER BY nomor')->fetchAll();
$list = $pdo->query("SELECT t.*, k.kode_kelompok, k.nama_kelompok, k.nama_ketua
    FROM timbangan_tbs t
    LEFT JOIN kelompok k ON k.id=t.id_kelompok
    ORDER BY t.id DESC LIMIT 100")->fetchAll();

$bulan = date('Y-m');
$prod = $pdo->query("SELECT k.kode_kelompok, k.nama_kelompok, COALESCE(SUM(t.berat_netto),0) kg, COALESCE(SUM(t.total_bruto_uang),0) uang
    FROM kelompok k LEFT JOIN timbangan_tbs t ON t.id_kelompok=k.id AND DATE_FORMAT(t.tanggal,'%Y-%m')='$bulan'
    GROUP BY k.id ORDER BY kg DESC")->fetchAll();

include __DIR__ . '/includes/app_header.php';
?>
<div class="cards" style="grid-template-columns:1.1fr .9fr;margin-bottom:18px;">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <h3>Timbang masuk per kelompok</h3>
    <label>Kelompok tani</label>
    <select name="id_kelompok" required>
      <option value="">Pilih kelompok</option>
      <?php foreach ($kelompok as $k): ?>
        <option value="<?= (int)$k['id'] ?>"><?= e(($k['kode_kelompok'] ?: ('KT-'.str_pad($k['nomor'],2,'0',STR_PAD_LEFT))).' — '.($k['nama_kelompok'] ?: ('Kelompok '.$k['nomor']))) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Kategori umur TBS</label>
    <select name="kategori_umur">
      <option value="muda">Muda (≤5 th)</option>
      <option value="dewasa" selected>Dewasa (6–10 th)</option>
      <option value="super">Super (&gt;10 th)</option>
    </select>
    <label>No. polisi</label>
    <input name="no_polisi" placeholder="BA 1234 XX">
    <div class="grid-2">
      <div><label>Berat masuk (kg)</label><input name="berat_masuk" type="number" step="0.01" min="0" required></div>
      <div><label>Berat keluar (kg)</label><input name="berat_keluar" type="number" step="0.01" min="0" value="0" required></div>
    </div>
    <label>Potongan grading (%)</label>
    <input name="persen_potongan" type="number" step="0.1" min="0" max="50" value="0">
    <label class="check"><input type="checkbox" name="potong_saprodi" value="1" style="width:auto;"><span>Potong piutang saprodi anggota kelompok ini</span></label>
    <label class="check"><input type="checkbox" name="potong_angsuran" value="1" style="width:auto;"><span>Potong angsuran anggota kelompok ini</span></label>
    <label>Keterangan</label>
    <input name="keterangan" placeholder="Mis. truk 1 hamparan A">
    <p style="font-size:12px;color:var(--muted);margin-top:8px;">Satu nota untuk satu kelompok. Harga mengikuti kategori umur yang dipilih. Isi harga TBS dulu jika belum ada.</p>
    <button class="btn btn-green" style="margin-top:12px;">Simpan timbangan</button>
  </form>
  <div class="card">
    <h3>Produksi <?= date('M Y') ?> per kelompok</h3>
    <div class="table-wrap" style="margin-top:10px;">
      <table>
        <thead><tr><th>Kelompok</th><th>Netto (kg)</th><th>Nilai</th></tr></thead>
        <tbody>
        <?php foreach ($prod as $p): if ((float)$p['kg'] <= 0) continue; ?>
          <tr>
            <td><?= e($p['kode_kelompok']) ?><br><small><?= e($p['nama_kelompok']) ?></small></td>
            <td><?= number_format((float)$p['kg'], 0, ',', '.') ?></td>
            <td><?= rupiah($p['uang']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:12px;"><a href="tbs_harga.php">Harga TBS</a> · <a href="tbs_antrean.php">Antrean truk</a> · <a href="tbs_sj.php">Surat jalan</a></p>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Waktu</th><th>Kelompok</th><th>Plat</th><th>Bruto</th><th>Pot %</th><th>Netto</th><th>Umur</th><th>Harga</th><th>Bruto Rp</th><th>Fee</th><th>Pot. utang</th><th>Bersih</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td><?= tgl($r['tanggal']) ?></td>
        <td><strong><?= e($r['kode_kelompok'] ?: '—') ?></strong><br><small><?= e($r['nama_kelompok'] ?: '') ?></small></td>
        <td><?= e($r['no_polisi'] ?: '—') ?></td>
        <td><?= number_format((float)$r['berat_bruto'], 0, ',', '.') ?></td>
        <td><?= e($r['persen_potongan']) ?></td>
        <td><?= number_format((float)$r['berat_netto'], 0, ',', '.') ?></td>
        <td><?= e($r['kategori_umur']) ?></td>
        <td><?= rupiah($r['harga_per_kg']) ?></td>
        <td><?= rupiah($r['total_bruto_uang']) ?></td>
        <td><?= rupiah($r['fee_kelompok']) ?></td>
        <td><?= rupiah((float)$r['potong_angsuran'] + (float)($r['potong_saprodi'] ?? 0)) ?></td>
        <td><strong><?= rupiah($r['bersih_petani']) ?></strong></td>
        <td><a class="btn btn-ghost btn-sm" href="tbs_nota.php?id=<?= (int)$r['id'] ?>">Nota</a></td>
      </tr>
    <?php endforeach; if (!$list): ?>
      <tr><td colspan="13">Belum ada timbangan.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
