<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$rows = $pdo->query(
    "SELECT id, supervisor_id FROM employees WHERE status = 'active' AND supervisor_id IS NOT NULL"
)->fetchAll();

$count = 0;
foreach ($rows as $r) {
    get_or_create_allocation((int) $r['id'], (int) $r['supervisor_id']);
    $count++;
}

redirect('../employee_allocation.php?synced=' . $count);
