<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$pdo    = db();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $empNo = trim($_POST['emp_no'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($empNo === '' || $name === '' || $email === '') {
        json_out(['ok' => false, 'message' => 'All fields are required.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'message' => 'Enter a valid email address.']);
    }

    $password = random_password();
    $hash     = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (emp_no, name, email, password_hash, role, status)
             VALUES (?, ?, ?, ?, "auditor", "active")'
        );
        $stmt->execute([$empNo, $name, $email, $hash]);

        json_out(['ok' => true, 'message' => 'Auditor created.', 'password' => $password]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'Could not create auditor — that employee no. or email is already in use.']);
    }
}

if ($action === 'edit') {
    $id    = (int) ($_POST['id'] ?? 0);
    $empNo = trim($_POST['emp_no'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$id || $empNo === '' || $name === '' || $email === '') {
        json_out(['ok' => false, 'message' => 'All fields are required.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'message' => 'Enter a valid email address.']);
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET emp_no = ?, name = ?, email = ? WHERE id = ? AND role = 'auditor'");
        $stmt->execute([$empNo, $name, $email, $id]);
        json_out(['ok' => true, 'message' => 'Auditor updated.']);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'Could not update — that employee no. or email is already in use.']);
    }
}

if ($action === 'reset_password') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        json_out(['ok' => false, 'message' => 'Missing auditor id.']);
    }

    $password = random_password();
    $hash     = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'auditor'");
    $stmt->execute([$hash, $id]);

    json_out(['ok' => true, 'message' => 'Password reset.', 'password' => $password]);
}

if ($action === 'remove') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        json_out(['ok' => false, 'message' => 'Missing auditor id.']);
    }

    $check = $pdo->prepare('SELECT COUNT(*) c FROM allocations WHERE auditor_id = ?');
    $check->execute([$id]);
    if ((int) $check->fetch()['c'] > 0) {
        json_out(['ok' => false, 'message' => 'This auditor still has allocations. Reassign their employees first.']);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'auditor'");
    $stmt->execute([$id]);

    json_out(['ok' => true, 'message' => 'Auditor removed.']);
}

json_out(['ok' => false, 'message' => 'Unknown action.']);
