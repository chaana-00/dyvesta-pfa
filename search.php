<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    [$scopeSql, $scopeParams] = scope_where('a');
    $stmt = db()->prepare(
        "SELECT a.id AS allocation_id, e.id AS emp_id, e.emp_no, e.name, e.designation, a.status, u.name AS auditor_name
         FROM employees e
         LEFT JOIN allocations a ON a.employee_id = e.id AND a.cycle = ? AND $scopeSql
         LEFT JOIN users u ON u.id = a.auditor_id
         WHERE e.name LIKE ? OR e.emp_no LIKE ?
         ORDER BY e.name LIMIT 100"
    );
    $stmt->execute(array_merge([active_cycle()], $scopeParams, ["%$q%", "%$q%"]));
    $results = $stmt->fetchAll();
}

$pageTitle = 'Search';
$activeNav = '';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Search results</h1>
    <p><?= h($q) !== '' ? 'Matches for "' . h($q) . '"' : 'Enter a search term above' ?></p>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Employees</h3><span class="panel-tag"><?= count($results) ?> found</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Emp No</th><th>Employee</th><th>Auditor</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$results): ?>
          <tr><td colspan="5"><div class="empty-state">No matches found.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($results as $r): ?>
          <tr class="clickable" onclick="location.href='my_audits.php?id=<?= (int) $r['emp_id'] ?>&scope=all'">
            <td class="muted-cell"><?= h($r['emp_no']) ?></td>
            <td><div class="emp-name"><?= h($r['name']) ?></div><div class="emp-sub"><?= h($r['designation']) ?></div></td>
            <td class="muted-cell"><?= h($r['auditor_name'] ?? '—') ?></td>
            <td><?= $r['status'] ? status_badge($r['status']) : '<span class="badge badge-muted">Unallocated</span>' ?></td>
            <td>&rsaquo;</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
