<?php
require_once __DIR__ . '/includes/functions.php';

$stmt = db()->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'");
if ((int) $stmt->fetch()['c'] > 0) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empNo = trim($_POST['emp_no'] ?? 'ADMIN001');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if ($name === '' || $email === '' || $pass === '') {
        $error = 'Please fill in every field.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins = db()->prepare(
                'INSERT INTO users (emp_no, name, email, password_hash, role, status)
                 VALUES (?, ?, ?, ?, "admin", "active")'
            );
            $ins->execute([$empNo, $name, $email, $hash]);
            redirect('index.php?created=1');
        } catch (PDOException $e) {
            $error = 'Could not create the account — that employee no. or email may already be in use.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(app_name()) ?> · First-time setup</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:440px;">
    <div class="auth-logo"><?= h(substr(app_name(), 0, 1)) ?></div>
    <h1><?= h(app_name()) ?></h1>
    <div class="auth-sub">PERSONAL FILE AUDIT — FIRST-TIME SETUP</div>

    <div class="setup-note">
      No admin account exists yet. Create the first one below — this will
      be the <b class="accent">Admin</b> login used to manage Users &amp; Roles,
      Master Data and Allocations.
    </div>

    <?php if ($error): ?>
      <div class="auth-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Employee No</label>
        <input type="text" name="emp_no" value="ADMIN001" required>
      </div>
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" value="Chanaka" required>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="chanaka@yourcompany.com" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="password2" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Admin Account</button>
    </form>

    <div class="auth-footnote">
      This screen only appears once, while the <code>users</code> table is empty.
    </div>
  </div>
</div>
</body>
</html>
