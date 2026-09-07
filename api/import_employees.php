<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/XLSX.php';
require_admin();

if (empty($_FILES['file']['tmp_name'])) {
    redirect('../master_data.php?tab=employee&import=nofile');
}

$rows = XLSXReader::read($_FILES['file']['tmp_name']);

if (count($rows) < 2) {
    redirect('../master_data.php?tab=employee&import=empty');
}

$header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
$map = [];
foreach ($header as $i => $col) {
    if (str_contains($col, 'employee no')) $map['emp_no'] = $i;
    elseif (str_contains($col, 'employee name') || $col === 'name') $map['name'] = $i;
    elseif (str_contains($col, 'designation')) $map['designation'] = $i;
    elseif (str_contains($col, 'supervisor')) $map['supervisor'] = $i;
}

if (!isset($map['emp_no'], $map['name'])) {
    redirect('../master_data.php?tab=employee&import=badcolumns');
}

$pdo = db();
$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'INSERT INTO employees (emp_no, name, designation, supervisor_id, status)
     VALUES (?, ?, ?, ?, "active")
     ON DUPLICATE KEY UPDATE name = VALUES(name), designation = VALUES(designation), supervisor_id = VALUES(supervisor_id)'
);
$supStmt = $pdo->prepare("SELECT id FROM users WHERE emp_no = ? AND role = 'auditor'");

$imported = 0;
for ($i = 1; $i < count($rows); $i++) {
    $r = $rows[$i];
    $empNo = trim((string) ($r[$map['emp_no']] ?? ''));
    $name  = trim((string) ($r[$map['name']] ?? ''));
    if ($empNo === '' || $name === '') {
        continue;
    }
    $designation = trim((string) ($r[$map['designation']] ?? ''));
    $supervisorNo = trim((string) ($r[$map['supervisor']] ?? ''));

    $supervisorId = null;
    if ($supervisorNo !== '') {
        $supStmt->execute([$supervisorNo]);
        $s = $supStmt->fetch();
        $supervisorId = $s ? $s['id'] : null;
    }

    $stmt->execute([$empNo, $name, $designation ?: null, $supervisorId]);
    $imported++;
}

$pdo->commit();

redirect('../master_data.php?tab=employee&import=ok&count=' . $imported);
