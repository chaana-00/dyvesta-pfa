<?php
require_once __DIR__ . '/includes/functions.php';

// First-run: if there is no admin user yet, send the visitor to setup.
$stmt = db()->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'");
if ((int) $stmt->fetch()['c'] === 0) {
    redirect('setup.php');
}

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if (isset($_GET['err'])) {
    $error = 'Invalid email or password.';
}
if (isset($_GET['deactivated'])) {
    $error = 'This account has been deactivated. Contact your administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(app_name()) ?> · Sign in</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo"><?= h(substr(app_name(), 0, 1)) ?></div>
    <h1><?= h(app_name()) ?></h1>
    <div class="auth-sub">PERSONAL FILE AUDIT</div>

    <?php if ($error): ?>
      <div class="auth-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login_process.php">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>

    <div class="auth-footnote">
      An administrator creates your account under Users &amp; Roles
    </div>
  </div>
</div>
</body>
</html>
