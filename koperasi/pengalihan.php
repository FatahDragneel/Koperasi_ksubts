<?php
require __DIR__ . '/config.php';
require_login();
ensure_pengalihan_schema();
ensure_anggota_schema();
$title = 'Pengalihan keanggotaan & lahan';
$pdo = db();
$u = auth();
$staff = in_array($u['role'], ['admin', 'pengurus'], true);

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    $aid = 0;
    if (ctype_digit($q)) {
        $aid = (int)$q;
    } else {
        $st = $pdo->prepare('SELECT id FROM anggota WHERE no_anggota=? OR nik=? LIMIT 1');
        $st->execute([$q, $q]);
        $aid = (int)$st->fetchColumn();
    }
    $r = $aid ? ringkas_anggota_pengalihan($aid) : null;
    if (!$r) {
        echo json_encode(['ok' => false]);
        exit;
    }
    if (!$staff && (int)$u['anggota_id'] !== $aid) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $lahanTxt = [];
    foreach ($r['lahan'] as $l) {
        $lahanTxt[] = ($l['kode_kelompok'] ?: '—') . ' · ' . ($l['lokasi_desa'] ?: 'lahan') . ' · ' . $l['luas_hektar'] . ' ha';
    }
    $kelTxt = [];
    foreach ($r['kelompok'] as $k) {
        $kelTxt[] = $k['kode_kelompok'];
    }
    echo json_encode([
        'ok' => true,
        'id' => $r['id'],
        'no_anggota' => $r['no_anggota'],
        'nama' => $r['nama'],
        'status' => $r['status'],
        'pokok' => $r['pokok'],
        'wajib' => $r['wajib'],
        'sukarela' => $r['sukarela'],
        'utang' => $r['utang'],
        'lahan' => $lahanTxt,
        'kelompok' => $kelTxt,
        'pokok_rp' => rupiah($r['pokok']),
        'wajib_rp' => rupiah($r['wajib']),
        'sukarela_rp' => rupiah($r['sukarela']),
        'utang_rp' => rupiah($r['utang']),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? 'kirim';
    if ($act === 'kirim') {
        $lamaId = (int)($_POST['anggota_lama_id'] ?? 0);
        if (!$staff) {
            $lamaId = (int)$u['anggota_id'];
        }
        $ringkas = $lamaId ? ringkas_anggota_pengalihan($lamaId) : null;
        $namaBaru = trim((string)($_POST['nama'] ?? $_POST['nama_baru'] ?? ''));
        $nikBaru = trim((string)($_POST['nik'] ?? $_POST['nik_baru'] ?? ''));
        $hpBaru = trim((string)($_POST['no_hp'] ?? $_POST['hp_baru'] ?? ''));
        $opsi = ($_POST['sukarela_opsi'] ?? 'alihkan') === 'tunai' ? 'tunai' : 'alihkan';
        $opsiPw = ($_POST['pokok_wajib_opsi'] ?? 'alihkan') === 'baru' ? 'baru' : 'alihkan';
        $userBaru = trim((string)($_POST['username'] ?? ''));
        $passBaru = (string)($_POST['password'] ?? '');
        $kelIds = array_values(array_filter(array_map('intval', (array)($_POST['kelompok_id'] ?? []))));
        $luasArr = array_values((array)($_POST['luas_kel'] ?? []));
        $kelJson = [];
        foreach ($kelIds as $i => $kid) {
            if ($kid > 0) {
                $kelJson[] = ['id' => $kid, 'luas' => (float)($luasArr[$i] ?? 0)];
            }
        }
        if (!$ringkas || $ringkas['status'] !== 'aktif') {
            flash('err', 'Anggota lama tidak valid / tidak aktif.');
        } elseif ($ringkas['utang'] > 0) {
            flash('err', 'Anggota lama masih punya sisa pinjaman. Tidak bisa dialihkan.');
        } elseif ($namaBaru === '' || $nikBaru === '') {
            flash('err', 'Isi nama dan NIK anggota baru.');
        } elseif ($userBaru !== '' && username_sudah_dipakai($userBaru)) {
            flash('err', 'Username anggota baru sudah dipakai.');
        } else {
            try {
                $sjb = simpan_berkas_pengalihan('file_sjb');
                $ba = simpan_berkas_pengalihan('file_ba');
                $dataBaru = [
                    'jenis_kelamin' => $_POST['jenis_kelamin'] ?? 'L',
                    'tempat_lahir' => trim((string)($_POST['tempat_lahir'] ?? '')),
                    'tanggal_lahir' => trim((string)($_POST['tanggal_lahir'] ?? '')),
                    'alamat' => trim((string)($_POST['alamat'] ?? '')),
                    'desa' => trim((string)($_POST['desa'] ?? '')),
                    'kecamatan' => trim((string)($_POST['kecamatan'] ?? '')),
                    'no_hp' => $hpBaru,
                    'pekerjaan' => trim((string)($_POST['pekerjaan'] ?? 'Petani')),
                    'stdb' => (($_POST['stdb'] ?? '') === 'sudah') ? 'sudah' : 'belum',
                    'no_stdb' => trim((string)($_POST['no_stdb'] ?? '')),
                    'username' => $userBaru,
                    'password_hash' => strlen($passBaru) >= 6 ? password_hash($passBaru, PASSWORD_DEFAULT) : null,
                    'kelompok' => $kelJson,
                    'foto' => simpan_berkas_anggota('foto'),
                    'ktp_file' => simpan_berkas_anggota('ktp_file'),
                    'sertifikat_file' => simpan_berkas_anggota('sertifikat_file'),
                ];
                $seq = 1 + (int)$pdo->query('SELECT COUNT(*) FROM pengalihan_hak')->fetchColumn();
                $no = 'ALIH-' . date('Y') . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
                $jsonBaru = json_encode($dataBaru, JSON_UNESCAPED_UNICODE);
                $adaKol = [];
                try {
                    $adaKol = array_column($pdo->query('SHOW COLUMNS FROM pengalihan_hak')->fetchAll(), 'Field');
                } catch (Throwable $e) {
                    $adaKol = [];
                }
                $map = [
                    'no_pengalihan' => $no,
                    'tanggal' => date('Y-m-d'),
                    'anggota_lama_id' => $lamaId,
                    'nama_baru' => $namaBaru,
                    'nik_baru' => $nikBaru,
                    'no_hp_baru' => $hpBaru,
                    'sukarela_opsi' => $opsi,
                    'pokok_wajib_opsi' => $opsiPw,
                    'file_sjb' => $sjb,
                    'file_ba' => $ba,
                    'saldo_pokok' => $ringkas['pokok'],
                    'saldo_wajib' => $ringkas['wajib'],
                    'saldo_sukarela' => $ringkas['sukarela'],
                    'status' => 'pengajuan',
                    'created_by' => $u['id'] ?? null,
                    'catatan' => $jsonBaru,
                ];
                $fields = [];
                $vals = [];
                foreach ($map as $f => $v) {
                    if (!$adaKol || in_array($f, $adaKol, true)) {
                        $fields[] = $f;
                        $vals[] = $v;
                    }
                }
                $ph = implode(',', array_fill(0, count($fields), '?'));
                $pdo->prepare('INSERT INTO pengalihan_hak (' . implode(',', $fields) . ') VALUES (' . $ph . ')')->execute($vals);
                flash('ok', 'Pengajuan '.$no.' masuk antrean persetujuan pengurus.');
            } catch (Throwable $e) {
                flash('err', 'Gagal simpan pengajuan: ' . $e->getMessage());
            }
        }
        header('Location: pengalihan.php');
        exit;
    }
    if ($staff && $act === 'setujui') {
        $err = proses_setujui_pengalihan((int)$_POST['id'], $u['id'] ?? null);
        flash($err ? 'err' : 'ok', $err ?: 'Pengalihan disetujui. ID baru dibuat, simpanan & lahan dipindah, anggota lama nonaktif.');
        header('Location: pengalihan.php');
        exit;
    }
    if ($staff && $act === 'tolak') {
        $cat = trim((string)($_POST['catatan'] ?? ''));
        if ($cat === '') {
            flash('err', 'Penolakan wajib ada keterangan.');
        } else {
            $pdo->prepare("UPDATE pengalihan_hak SET status='ditolak', catatan=? WHERE id=? AND status='pengajuan'")
                ->execute([$cat, (int)$_POST['id']]);
            flash('ok', 'Pengajuan ditolak.');
        }
        header('Location: pengalihan.php');
        exit;
    }
}

if ($staff) {
    $rows = $pdo->query("SELECT p.*, a.no_anggota AS no_lama, a.nama AS nama_lama, b.no_anggota AS no_baru
        FROM pengalihan_hak p
        JOIN anggota a ON a.id=p.anggota_lama_id
        LEFT JOIN anggota b ON b.id=p.anggota_baru_id
        ORDER BY " . sql_urut([
            'no' => 'p.no_pengalihan',
            'tanggal' => 'p.tanggal',
            'lama' => 'a.nama',
            'baru' => 'p.nama_baru',
            'pokok' => 'p.saldo_pokok',
            'sukarela' => 'p.saldo_sukarela',
            'status' => 'p.status',
        ], 'p.id DESC') . "")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT p.*, a.no_anggota AS no_lama, a.nama AS nama_lama, b.no_anggota AS no_baru
        FROM pengalihan_hak p
        JOIN anggota a ON a.id=p.anggota_lama_id
        LEFT JOIN anggota b ON b.id=p.anggota_baru_id
        WHERE p.anggota_lama_id=? OR p.anggota_baru_id=?
        ORDER BY " . sql_urut([
            'no' => 'p.no_pengalihan',
            'tanggal' => 'p.tanggal',
            'lama' => 'a.nama',
            'baru' => 'p.nama_baru',
            'pokok' => 'p.saldo_pokok',
            'sukarela' => 'p.saldo_sukarela',
            'status' => 'p.status',
        ], 'p.id DESC'));
    $st->execute([(int)$u['anggota_id'], (int)$u['anggota_id']]);
    $rows = $st->fetchAll();
}

include __DIR__ . '/includes/app_header.php';
?>
<p style="color:var(--muted);font-size:14px;margin-bottom:14px;">
  Alih hak lahan &amp; simpanan: pengajuan → pengurus setujui → ID baru aktif, ID lama nonaktif (Keluar - Alih Hak).
  WhatsApp/email otomatis belum terhubung; notifikasi tercatat di Pengumuman internal.
</p>
<div class="row" style="margin-bottom:16px;justify-content:flex-end;">
  <button class="btn btn-green" type="button" onclick="openModal('mAlih')">+ Pengajuan pengalihan</button>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?= th_urut('no','No') ?>
        <?= th_urut('tanggal','Tanggal') ?>
        <?= th_urut('lama','Anggota lama') ?>
        <?= th_urut('baru','Calon / ID baru') ?>
        <?= th_urut('pokok','Pokok + wajib') ?>
        <?= th_urut('sukarela','Sukarela') ?>
        <th>Berkas</th>
        <?= th_urut('status','Status') ?>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['no_pengalihan']) ?></td>
        <td><?= tgl($r['tanggal']) ?></td>
        <td><?= e($r['nama_lama']) ?><br><small><?= e($r['no_lama']) ?></small></td>
        <td>
          <?= e($r['nama_baru']) ?>
          <?php if (!empty($r['no_baru'])): ?><br><small><?= e($r['no_baru']) ?></small><?php endif; ?>
        </td>
        <td><?= rupiah((float)$r['saldo_pokok'] + (float)$r['saldo_wajib']) ?><br><small><?= (($r['pokok_wajib_opsi'] ?? 'alihkan') === 'baru') ? 'setor baru' : 'pindah ke ID baru' ?></small></td>
        <td><?= rupiah($r['saldo_sukarela']) ?><br><small><?= e($r['sukarela_opsi']) ?></small></td>
        <td>
          <?php if ($r['file_sjb']): ?><a class="btn btn-ghost btn-sm" href="berkas.php?id=<?= (int)$r['id'] ?>&jenis=sjb" target="_blank">SJB</a><?php endif; ?>
          <?php if ($r['file_ba']): ?><a class="btn btn-ghost btn-sm" href="berkas.php?id=<?= (int)$r['id'] ?>&jenis=ba" target="_blank">BA</a><?php endif; ?>
        </td>
        <td>
          <span class="badge b-<?= e($r['status']==='disetujui'?'lunas':($r['status']==='ditolak'?'ditolak':'pengajuan')) ?>"><?= e($r['status']) ?></span>
          <?php if ($r['status']==='ditolak' && $r['catatan']): ?><br><small><?= e($r['catatan']) ?></small><?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($staff && $r['status']==='pengajuan'): ?>
            <form method="post" onsubmit="return confirm('Setujui pengalihan? ID baru akan dibuat dan anggota lama nonaktif.');">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="setujui">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-green btn-sm">Setujui</button>
            </form>
            <button class="btn btn-danger btn-sm" type="button" onclick="tolakAlih(<?= (int)$r['id'] ?>)">Tolak</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="9">Belum ada pengajuan pengalihan.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-bg" id="mAlih">
  <form class="modal" method="post" enctype="multipart/form-data" style="max-width:640px;max-height:92vh;overflow:auto;">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="kirim">
    <h3>Pengajuan pengalihan hak</h3>
    <label>ID / no. anggota lama</label>
    <?php if ($staff): ?>
      <div class="row">
        <input name="q_lama" id="qLama" placeholder="AGT-0001 atau NIK" style="flex:1;">
        <button class="btn btn-ghost" type="button" onclick="cariLama()">Cek</button>
      </div>
      <input type="hidden" name="anggota_lama_id" id="lamaId">
    <?php else: ?>
      <input type="hidden" name="anggota_lama_id" id="lamaId" value="<?= (int)$u['anggota_id'] ?>">
      <p id="boxLama" style="background:#f4faf5;padding:12px;border-radius:12px;font-size:14px;">Memuat data Anda…</p>
    <?php endif; ?>
    <div id="boxLamaStaff" style="display:none;background:#f4faf5;padding:12px;border-radius:12px;margin-top:10px;font-size:14px;"></div>
    <h4 style="margin:16px 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--green);">Data anggota baru (sama seperti form anggota)</h4>
    <div class="grid-2">
      <div><label>Nama lengkap</label><input name="nama" required></div>
      <div><label>NIK</label><input name="nik" required maxlength="16"></div>
    </div>
    <div class="grid-2">
      <div>
        <label>Jenis kelamin</label>
        <select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
      </div>
      <div><label>No. HP</label><input name="no_hp"></div>
    </div>
    <div class="grid-2">
      <div><label>Tempat lahir</label><input name="tempat_lahir"></div>
      <div><label>Tanggal lahir</label><input type="date" name="tanggal_lahir"></div>
    </div>
    <label>Alamat</label><textarea name="alamat"></textarea>
    <div class="grid-2">
      <div><label>Desa</label><input name="desa"></div>
      <div><label>Kecamatan</label><input name="kecamatan"></div>
    </div>
    <label>Pekerjaan</label><input name="pekerjaan" value="Petani">
    <?= html_slot_kelompok(5, [], 'Kelompok anggota baru', true) ?>
    <div class="grid-2">
      <div><label>STDB</label><select name="stdb"><option value="belum">Belum</option><option value="sudah">Sudah</option></select></div>
      <div><label>Nomor STDB</label><input name="no_stdb"></div>
    </div>
    <div class="grid-2">
      <div><label>Foto diri</label><input type="file" name="foto" accept="image/*"></div>
      <div><label>Foto KTP</label><input type="file" name="ktp_file" accept="image/*"></div>
    </div>
    <label>Sertifikat tanah</label><input type="file" name="sertifikat_file" accept="image/*">
    <div class="grid-2">
      <div><label>Username (opsional)</label><input name="username"></div>
      <div><label>Kata sandi (min. 6, opsional)</label><input type="password" name="password" minlength="6"></div>
    </div>
    <p style="font-size:12px;color:var(--muted);">Kalau username/sandi kosong, otomatis: nama+ID / anggota123.</p>
    <label>Surat jual beli lahan (PDF/JPG)</label>
    <input type="file" name="file_sjb" accept="image/*,.pdf">
    <label>Berita acara pengalihan simpanan (PDF/JPG)</label>
    <input type="file" name="file_ba" accept="image/*,.pdf">
    <label>Simpanan pokok &amp; wajib</label>
    <select name="pokok_wajib_opsi">
      <option value="alihkan">Ya — pindahkan ke ID baru (anggota lama nonaktif)</option>
      <option value="baru">Tidak — ID baru setor pokok &amp; wajib baru (anggota lama pasif)</option>
    </select>
    <label>Simpanan sukarela</label>
    <select name="sukarela_opsi">
      <option value="alihkan">Alihkan ke ID baru</option>
      <option value="tunai">Ambil tunai oleh anggota lama</option>
    </select>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mAlih')">Batal</button>
      <button class="btn btn-green">Kirim pengajuan</button>
    </div>
  </form>
</div>
<div class="modal-bg" id="mTolakAlih">
  <form class="modal" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="tolak">
    <input type="hidden" name="id" id="tolakAlihId">
    <h3>Tolak pengalihan</h3>
    <label>Keterangan (wajib)</label>
    <textarea name="catatan" required></textarea>
    <div class="row" style="margin-top:16px;justify-content:flex-end;">
      <button type="button" class="btn btn-ghost" onclick="closeModal('mTolakAlih')">Batal</button>
      <button class="btn btn-danger">Kirim</button>
    </div>
  </form>
</div>
<script>
function tampilLama(d) {
  var html = '<strong>' + d.nama + '</strong> · ' + d.no_anggota + ' · ' + d.status +
    '<br>Pokok ' + d.pokok_rp + ' · Wajib ' + d.wajib_rp + ' · Sukarela ' + d.sukarela_rp +
    '<br>Lahan: ' + (d.lahan.length ? d.lahan.join('; ') : '—') +
    '<br>Kelompok: ' + (d.kelompok.length ? d.kelompok.join(', ') : '—');
  if (d.utang > 0) html += '<br><span style="color:#c0392b">Masih ada sisa pinjaman ' + d.utang_rp + '</span>';
  var box = document.getElementById('boxLamaStaff') || document.getElementById('boxLama');
  if (box) { box.style.display = 'block'; box.innerHTML = html; }
  document.getElementById('lamaId').value = d.id;
}
function cariLama() {
  var q = (document.getElementById('qLama') || {}).value || '';
  fetch('pengalihan.php?ajax=1&q=' + encodeURIComponent(q))
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { alert('Anggota tidak ditemukan.'); return; }
      tampilLama(d);
    });
}
function tolakAlih(id) {
  document.getElementById('tolakAlihId').value = id;
  openModal('mTolakAlih');
}
<?php if (!$staff && !empty($u['anggota_id'])): ?>
fetch('pengalihan.php?ajax=1&q=<?= (int)$u['anggota_id'] ?>').then(function(r){return r.json();}).then(function(d){ if(d.ok) tampilLama(d); });
<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/app_footer.php'; ?>
