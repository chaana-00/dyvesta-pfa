<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$totalEmployees = (int) db()->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
$totalAllocations = count_allocations();
$completed  = count_allocations('completed');
$pending    = count_allocations('not_started') + count_allocations('in_progress');
$issues     = count_allocations('issues_found');
$compliance = checklist_compliance();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DYVESTA · Dashboard Export</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  body { background:#fff; color:#111; padding:36px; }
  table { width:100%; border-collapse:collapse; margin-top:14px; }
  th, td { border:1px solid #ddd; padding:8px 10px; font-size:12.5px; text-align:left; }
  h1 { margin-bottom:4px; }
  .muted { color:#666; margin-top:0; }
</style>
</head>
<body onload="window.print()">
  <h1>DYVESTA — Personal File Audit</h1>
  <p class="muted"><?= h(active_cycle()) ?> · Generated <?= date('Y-m-d H:i') ?></p>

  <table>
    <tr><th>Total Employees</th><td><?= $totalEmployees ?></td></tr>
    <tr><th>Total Allocations</th><td><?= $totalAllocations ?></td></tr>
    <tr><th>Completed</th><td><?= $completed ?></td></tr>
    <tr><th>Pending</th><td><?= $pending ?></td></tr>
    <tr><th>Issues Found</th><td><?= $issues ?></td></tr>
  </table>

  <h3>Checklist Compliance</h3>
  <table>
    <tr><th>Audit Item</th><th>OK</th><th>Issues</th><th>Pending</th><th>Compliance %</th></tr>
    <?php foreach ($compliance as $c): ?>
      <tr>
        <td><?= h($c['item']['audit_item']) ?></td>
        <td><?= $c['ok'] ?></td>
        <td><?= $c['issues'] ?></td>
        <td><?= $c['pending'] ?></td>
        <td><?= $c['pct'] ?>%</td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
