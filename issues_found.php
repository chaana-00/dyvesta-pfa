<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$auditorFilter = $_GET['auditor'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$cycle = active_cycle();
$pdo = db();

[$scopeSql, $scopeParams] = scope_where('a');

$where  = [$scopeSql, 'a.cycle = ?', "a.status = 'issues_found'"];
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
    "SELECT a.id AS allocation_id, e.id AS emp_id, e.emp_no, e.name, e.designation, u.name AS auditor_name, u.id as auditor_id
     FROM allocations a
     JOIN employees e ON e.id = a.employee_id
     JOIN users u ON u.id = a.auditor_id
     WHERE $whereSql
     ORDER BY e.name
     LIMIT 1000"
);
$rows->execute($params);
$flagged = $rows->fetchAll();

$items = $pdo->query('SELECT * FROM checklist_items ORDER BY sort_order, id')->fetchAll();

// preload all answers for the flagged allocations
$answersByAlloc = [];
if ($flagged) {
    $ids = array_column($flagged, 'allocation_id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ansStmt = $pdo->prepare("SELECT * FROM audit_answers WHERE allocation_id IN ($in)");
    $ansStmt->execute($ids);
    foreach ($ansStmt->fetchAll() as $a) {
        $answersByAlloc[$a['allocation_id']][$a['checklist_item_id']] = $a;
    }
}

$compliance = checklist_compliance();
$auditors = $pdo->query("SELECT id, name FROM users WHERE role='auditor' ORDER BY name")->fetchAll();

$pageTitle = 'Issues Found';
$activeNav = 'issues';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Issues Found</h1>
    <p>Audits submitted with a No or Incorrect answer — awaiting review and resolution</p>
  </div>
  <div class="page-actions">
    <form method="get"><input class="btn" style="cursor:text" type="text" name="q" placeholder="Search..." value="<?= h($q) ?>"></form>
    <a class="btn btn-primary" href="api/export_excel.php?type=issues_found">&#8595; Export Excel</a>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px;">
  <div class="card"><div class="stat-label">Total Issues</div><div class="stat-value c-danger"><?= count($flagged) ?></div></div>
  <?php foreach (array_slice($compliance, 0, 3) as $c): ?>
    <div class="card">
      <div class="stat-label"><?= h($c['item']['audit_item']) ?></div>
      <div class="stat-value c-danger"><?= $c['issues'] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (is_admin()): ?>
<div class="pill-row" id="auditor-pills">
  <a class="pill <?= $auditorFilter === 'all' ? 'active' : '' ?>" href="?auditor=all">All</a>
  <?php foreach ($auditors as $a): ?>
    <a class="pill <?= (string) $auditorFilter === (string) $a['id'] ? 'active' : '' ?>" href="?auditor=<?= (int) $a['id'] ?>"><?= h($a['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h3>Flagged Audits</h3>
    <span class="panel-tag"><?= count($flagged) ?> shown</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Emp No</th><th>Employee</th><th>Auditor</th>
          <?php foreach ($items as $it): ?><th><?= h($it['audit_item']) ?></th><?php endforeach; ?>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$flagged): ?>
          <tr><td colspan="<?= 4 + count($items) ?>"><div class="empty-state"><div class="ic">&#9989;</div>No open issues in this scope.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($flagged as $f): ?>
          <tr>
            <td class="muted-cell"><?= h($f['emp_no']) ?></td>
            <td><div class="emp-name"><?= h($f['name']) ?></div><div class="emp-sub"><?= h($f['designation']) ?></div></td>
            <td class="muted-cell"><?= h($f['auditor_name']) ?></td>
            <?php foreach ($items as $it):
              $ans = $answersByAlloc[$f['allocation_id']][$it['id']] ?? null; ?>
              <td title="<?= h($ans['remark'] ?? '') ?>"><?= answer_badge($ans['answer'] ?? null) ?></td>
            <?php endforeach; ?>
            <td><a class="btn btn-sm" href="my_audits.php?id=<?= (int) $f['emp_id'] ?>&scope=all">Review</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
