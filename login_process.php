<?php
require_once __DIR__ . '/includes/functions.php';

$email = trim($_POST['email'] ?? '');
$pass  = (string) ($_POST['password'] ?? '');

if ($email === '' || $pass === '') {
    redirect('index.php?err=1');
}

$stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    redirect('index.php?err=1');
}

if ($user['status'] !== 'active') {
    redirect('index.php?deactivated=1');
}

$_SESSION['user'] = [
    'id'     => (int) $user['id'],
    'emp_no' => $user['emp_no'],
    'name'   => $user['name'],
    'email'  => $user['email'],
    'role'   => $user['role'],
];

redirect('dashboard.php');
