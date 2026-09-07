<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$pdo    = db();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $empNo       = trim($_POST['emp_no'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $supNo       = trim($_POST['supervisor_no'] ?? '');

    if ($empNo === '' || $name === '') {
        json_out(['ok' => false, 'message' => 'Employee No and Name are required.']);
    }

    $supervisorId = null;
    if ($supNo !== '') {
        $s = $pdo->prepare("SELECT id FROM users WHERE emp_no = ? AND role = 'auditor'");
        $s->execute([$supNo]);
        $s = $s->fetch();
        if (!$s) {
            json_out(['ok' => false, 'message' => "Auditor $supNo was not found. Create the auditor first under Auditor Master."]);
        }
        $supervisorId = $s['id'];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO employees (emp_no, name, designation, supervisor_id, status)
             VALUES (?, ?, ?, ?, "active")
             ON DUPLICATE KEY UPDATE name = VALUES(name), designation = VALUES(designation), supervisor_id = VALUES(supervisor_id)'
        );
        $stmt->execute([$empNo, $name, $designation ?: null, $supervisorId]);

        if ($supervisorId) {
            $emp = $pdo->prepare('SELECT id FROM employees WHERE emp_no = ?');
            $emp->execute([$empNo]);
            $emp = $emp->fetch();
            get_or_create_allocation((int) $emp['id'], (int) $supervisorId);
        }

        json_out(['ok' => true, 'message' => 'Employee saved.']);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'Could not save employee — check the employee no. is unique.']);
    }
}

if ($action === 'set_supervisor') {
    $empNo = trim($_POST['emp_no'] ?? '');
    $supNo = trim($_POST['supervisor_no'] ?? '');

    if ($empNo === '' || $supNo === '') {
        json_out(['ok' => false, 'message' => 'Both fields are required.']);
    }

    $emp = $pdo->prepare('SELECT id FROM employees WHERE emp_no = ?');
    $emp->execute([$empNo]);
    $emp = $emp->fetch();
    if (!$emp) {
        json_out(['ok' => false, 'message' => 'Employee not found.']);
    }

    $sup = $pdo->prepare("SELECT id FROM users WHERE emp_no = ? AND role = 'auditor'");
    $sup->execute([$supNo]);
    $sup = $sup->fetch();
    if (!$sup) {
        json_out(['ok' => false, 'message' => "Auditor $supNo was not found."]);
    }

    $upd = $pdo->prepare('UPDATE employees SET supervisor_id = ? WHERE id = ?');
    $upd->execute([$sup['id'], $emp['id']]);

    get_or_create_allocation((int) $emp['id'], (int) $sup['id']);

    json_out(['ok' => true, 'message' => 'Supervisor updated.']);
}

if ($action === 'set_status') {
    $empNo  = trim($_POST['emp_no'] ?? '');
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['active', 'inactive'], true)) {
        json_out(['ok' => false, 'message' => 'Invalid status.']);
    }

    $stmt = $pdo->prepare('UPDATE employees SET status = ? WHERE emp_no = ?');
    $stmt->execute([$status, $empNo]);

    json_out(['ok' => true, 'message' => 'Status updated.']);
}

json_out(['ok' => false, 'message' => 'Unknown action.']);
