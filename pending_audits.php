<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$auditorFilter = $_GET['auditor'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$cycle = active_cycle();
$pdo = db();

[$scopeSql, $scopeParams] = scope_where('a');

$where  = [$scopeSql, 'a.cycle = ?', "a.status IN ('not_started','in_progress')"];
$params = array_merge($scopeParams, [$cycle]);

if (is_admin() && $auditorFilter !== 'all') {
    $where[] = 'u.id = ?';
    $params[] = (int) $auditorFilter;
}
if ($q !== '') {
    $where[] = '(e.name LIKE ? OR e.emp_no LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

$rows = $pdo->prepare(
    "SELECT a.id AS allocation_id, e.id AS emp_id, e.emp_no, e.name, e.designation, a.status, a.progress, u.name AS auditor_name, u.id AS auditor_id
     FROM allocations a
     JOIN employees e ON e.id = a.employee_id
     JOIN users u ON u.id = a.auditor_id
     WHERE $whereSql
     ORDER BY e.name
     LIMIT 1000"
);
$rows->execute($params);
$outstanding = $rows->fetchAll();

$compliance = checklist_compliance();
$auditors = $pdo->query("SELECT id, name FROM users WHERE role='auditor' ORDER BY name")->fetchAll();

$pageTitle = 'Pending Audits';
$activeNav = 'pending';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Pending Audits</h1>
    <p>Allocated audits that are not started or still in draft</p>
  </div>
  <div class="page-actions">
    <form method="get"><input class="btn" style="cursor:text" type="text" name="q" placeholder="Search..." value="<?= h($q) ?>"></form>
    <a class="btn btn-primary" href="api/export_excel.php?type=pending_audits">&#8595; Export Excel</a>
    <?php if (is_admin()): ?><button class="btn" onclick="openModal('modal-new-allocation')">+ New Allocation</button><?php endif; ?>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px;">
  <div class="card"><div class="stat-label">Total Pending</div><div class="stat-value c-warning"><?= count($outstanding) ?></div></div>
  <?php foreach (array_slice($compliance, 0, 3) as $c): ?>
    <div class="card">
      <div class="stat-label"><?= h($c['item']['audit_item']) ?></div>
      <div class="stat-value"><?= $c['pending'] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (is_admin()): ?>
<div class="pill-row">
  <a class="pill <?= $auditorFilter === 'all' ? 'active' : '' ?>" href="?auditor=all">All</a>
  <?php foreach ($auditors as $a): ?>
    <a class="pill <?= (string) $auditorFilter === (string) $a['id'] ? 'active' : '' ?>" href="?auditor=<?= (int) $a['id'] ?>"><?= h($a['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h3>Outstanding Audits</h3>
    <span class="panel-tag"><?= count($outstanding) ?> shown</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Emp No</th><th>Employee</th><th>Auditor</th><th>Progress</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$outstanding): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="ic">&#9989;</div>Nothing pending in this scope.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($outstanding as $o): ?>
          <tr class="clickable" onclick="location.href='my_audits.php?id=<?= (int) $o['emp_id'] ?>&scope=all'">
            <td class="muted-cell"><?= h($o['emp_no']) ?></td>
            <td><div class="emp-name"><?= h($o['name']) ?></div><div class="emp-sub"><?= h($o['designation']) ?></div></td>
            <td class="muted-cell"><?= h($o['auditor_name']) ?></td>
            <td><?= (int) $o['progress'] ?>%</td>
            <td><?= status_badge($o['status']) ?></td>
            <td>&rsaquo;</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (is_admin()): include __DIR__ . '/includes/modal_new_allocation.php'; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
