<?php
require __DIR__ . '/config.php';
if (auth()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = try_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', ['admin', 'pengurus']);
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
  <title>Login Admin · <?= e($s['nama_koperasi']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .auth-wrap.admin .auth-art {
      background:
        linear-gradient(160deg, rgba(20,28,24,.95), rgba(15,66,36,.75)),
        radial-gradient(circle at 70% 20%, rgba(232,197,71,.28), transparent 45%);
    }
  </style>
</head>
<body>
<div class="auth-wrap admin">
  <div class="auth-art">
    <a class="brand" href="index.php">
      <div class="logo"><?= e(strtoupper(implode('', array_map(static function ($w) { return substr($w, 0, 1); }, array_slice(preg_split('/\s+/', trim((string)($s['nama_koperasi'] ?? 'KP'))), 0, 2))))) ?></div>
      <div><small>Area internal</small><strong>Pengurus &amp; Admin</strong></div>
    </a>
    <div>
      <div class="kicker">Khusus pengurus</div>
      <h1 style="font-size:38px;line-height:1.15;margin:16px 0;">Login administrasi</h1>
      <p>Verifikasi anggota, setujui pinjaman, catat pembayaran, atur bagi hasil dan simpanan.</p>
    </div>
    <p style="opacity:.85;font-size:13px;">Contoh: <strong>admin</strong> / admin123</p>
  </div>
  <div class="auth-form">
    <form class="form-box" method="post">
      <?= csrf_field() ?>
      <div class="kicker" style="background:#fff8e1;color:#8d6e00;border-color:#e8c547;">Login admin</div>
      <h1>Panel pengurus</h1>
      <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
      <label>Username pengurus</label>
      <input name="username" required autofocus placeholder="admin">
      <label>Kata sandi</label>
      <input type="password" name="password" required>
      <button class="btn btn-gold" style="width:100%;margin-top:18px;" type="submit">Masuk admin</button>
      <p style="margin-top:16px;font-size:13px;text-align:center;">
        Anggota? <a href="login-anggota.php" style="color:var(--green);font-weight:700;">Login anggota</a><br>
        <a href="index.php" style="color:var(--green);font-weight:700;">← Situs umum</a>
      </p>
    </form>
  </div>
</div>
</body>
</html>
