<?php
require __DIR__ . '/config.php';
if (auth()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $error = try_login($user, $pass, ['anggota']);
    if ($error !== '' && (stripos($error, 'pasif') !== false || stripos($error, 'nonaktif') !== false)) {
        $st = db()->prepare('SELECT * FROM anggota WHERE username=?');
        $st->execute([$user]);
        $a = $st->fetch();
        if ($a && !empty($a['password']) && password_verify($pass, $a['password']) && ($a['status'] ?? '') === 'pasif') {
            start_user_session([
                'id' => 0,
                'username' => $a['username'],
                'nama' => $a['nama'],
                'role' => 'anggota',
                'anggota_id' => $a['id'],
                'status' => 'pasif',
            ]);
            $error = '';
        }
    }
    if ($error === '') {
        after_login_redirect();
    }
}
$s = setting();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Anggota · <?= e($s['nama_koperasi']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-art">
    <a class="brand" href="index.php">
      <div class="logo"><?= e(strtoupper(implode('', array_map(static function ($w) { return substr($w, 0, 1); }, array_slice(preg_split('/\s+/', trim((string)($s['nama_koperasi'] ?? 'KP'))), 0, 2))))) ?></div>
      <div><small><?= e($s['jenis_koperasi'] ?? 'Koperasi Produsen') ?></small><strong><?= e($s['nama_koperasi']) ?></strong></div>
    </a>
    <div>
      <div class="kicker">Portal anggota</div>
      <h1 style="font-size:38px;line-height:1.15;margin:16px 0;">Masuk ke akun anggota</h1>
      <p>Lihat simpanan, ajukan pinjaman, dan pantau angsuran. Akun aktif setelah admin menyetujui pendaftaran.</p>
    </div>
    <p style="opacity:.85;font-size:13px;">Contoh: <strong>budi</strong> / admin123 · <strong>siti</strong> / admin123</p>
  </div>
  <div class="auth-form">
    <form class="form-box" method="post">
      <?= csrf_field() ?>
      <div class="kicker" style="background:#e8f5e9;color:var(--green);border-color:#c8e6c9;">Login anggota</div>
      <h1>Selamat datang</h1>
      <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
      <label>Username anggota</label>
      <input name="username" required autofocus placeholder="username Anda">
      <label>Kata sandi</label>
      <input type="password" name="password" required>
      <button class="btn btn-green" style="width:100%;margin-top:18px;" type="submit">Masuk anggota</button>
      <p style="margin-top:16px;font-size:13px;text-align:center;">
        Pengurus? <a href="login-admin.php" style="color:var(--green);font-weight:700;">Login admin</a><br>
        <a href="index.php" style="color:var(--green);font-weight:700;">← Situs umum</a>
      </p>
    </form>
  </div>
</div>
</body>
</html>
