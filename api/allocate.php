<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$empNo     = trim($_POST['emp_no'] ?? '');
$auditorNo = trim($_POST['auditor_no'] ?? '');

if ($empNo === '' || $auditorNo === '') {
    json_out(['ok' => false, 'message' => 'Employee no and auditor emp no are both required.']);
}

$pdo = db();

$emp = $pdo->prepare('SELECT id FROM employees WHERE emp_no = ?');
$emp->execute([$empNo]);
$emp = $emp->fetch();
if (!$emp) {
    json_out(['ok' => false, 'message' => "Employee $empNo was not found. Add them under Master Data first."]);
}

$auditor = $pdo->prepare("SELECT id FROM users WHERE emp_no = ? AND role = 'auditor' AND status = 'active'");
$auditor->execute([$auditorNo]);
$auditor = $auditor->fetch();
if (!$auditor) {
    json_out(['ok' => false, 'message' => "Auditor $auditorNo was not found (or is inactive). Add them under Master Data \u{2192} Auditor Master."]);
}

// Keep the Employee Master's supervisor field in sync with the allocation.
$upd = $pdo->prepare('UPDATE employees SET supervisor_id = ? WHERE id = ?');
$upd->execute([$auditor['id'], $emp['id']]);

get_or_create_allocation((int) $emp['id'], (int) $auditor['id']);

json_out(['ok' => true, 'message' => 'Allocation saved.']);
