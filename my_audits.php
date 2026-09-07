<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user  = current_user();
$scope = $_GET['scope'] ?? 'mine';
if (!is_admin()) {
    $scope = 'mine';
}
$statusFilter = $_GET['status'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$pdo = db();
$cycle = active_cycle();

$where  = ['a.cycle = ?'];
$params = [$cycle];

if ($scope === 'mine') {
    $where[] = 'a.auditor_id = ?';
    $params[] = $user['id'];
}
if ($statusFilter !== 'all') {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(e.name LIKE ? OR e.emp_no LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

$list = $pdo->prepare(
    "SELECT a.id AS allocation_id, a.status, a.progress, e.id AS emp_id, e.emp_no, e.name, e.designation,
            u.name AS auditor_name
     FROM allocations a
     JOIN employees e ON e.id = a.employee_id
     JOIN users u ON u.id = a.auditor_id
     WHERE $whereSql
     ORDER BY e.name
     LIMIT 500"
);
$list->execute($params);
$employees = $list->fetchAll();

// Summary cards (scoped to current filter set minus status filter)
$scopeParams = $scope === 'mine' ? [$cycle, $user['id']] : [$cycle];
$scopeSql = $scope === 'mine' ? 'a.cycle = ? AND a.auditor_id = ?' : 'a.cycle = ?';

function scoped_count(PDO $pdo, string $sql, array $params, ?string $status = null): int
{
    $full = "SELECT COUNT(*) c FROM allocations a WHERE $sql" . ($status ? ' AND a.status = ?' : '');
    if ($status) $params[] = $status;
    $stmt = $pdo->prepare($full);
    $stmt->execute($params);
    return (int) $stmt->fetch()['c'];
}

$inScope   = scoped_count($pdo, $scopeSql, $scopeParams);
$completed = scoped_count($pdo, $scopeSql, $scopeParams, 'completed');
$pendingC  = scoped_count($pdo, $scopeSql, $scopeParams, 'not_started') + scoped_count($pdo, $scopeSql, $scopeParams, 'in_progress');
$issuesC   = scoped_count($pdo, $scopeSql, $scopeParams, 'issues_found');

// Findings = total "no" answers across scope
$findingsSql = "SELECT COUNT(*) c FROM audit_answers aa
                JOIN allocations a ON a.id = aa.allocation_id
                WHERE $scopeSql AND aa.answer = 'no'";
$fstmt = $pdo->prepare($findingsSql);
$fstmt->execute($scopeParams);
$findings = (int) $fstmt->fetch()['c'];

// Selected employee detail
$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : ($employees[0]['emp_id'] ?? 0);
$selected = null;
$items = [];

if ($selectedId) {
    $sel = $pdo->prepare(
        "SELECT a.id AS allocation_id, a.status, a.progress, e.id AS emp_id, e.emp_no, e.name, e.designation,
                u.name AS auditor_name, u.id as auditor_id
         FROM allocations a
         JOIN employees e ON e.id = a.employee_id
         JOIN users u ON u.id = a.auditor_id
         WHERE e.id = ? AND a.cycle = ?"
    );
    $sel->execute([$selectedId, $cycle]);
    $selected = $sel->fetch();

    if ($selected) {
        ensure_answer_rows((int) $selected['allocation_id']);
        $itemsStmt = $pdo->prepare(
            "SELECT ci.id, ci.audit_item, ci.category, ci.answer_type, aa.answer, aa.remark
             FROM checklist_items ci
             LEFT JOIN audit_answers aa ON aa.checklist_item_id = ci.id AND aa.allocation_id = ?
             ORDER BY ci.sort_order, ci.id"
        );
        $itemsStmt->execute([$selected['allocation_id']]);
        $items = $itemsStmt->fetchAll();
    }
}

$pageTitle = 'My Audits';
$activeNav = 'my_audits';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Audits</h1>
    <p>Standardized <?= count($items) ?: 6 ?>-point checklist — a remark is mandatory for every No / Incorrect answer</p>
  </div>
  <div class="page-actions">
    <form method="get" style="display:flex;gap:10px;">
      <input type="hidden" name="id" value="<?= (int) $selectedId ?>">
      <input class="btn" style="cursor:text" type="text" name="q" placeholder="Search..." value="<?= h($q) ?>">
      <?php if (is_admin()): ?>
      <select class="btn" name="scope" onchange="this.form.submit()">
        <option value="mine" <?= $scope === 'mine' ? 'selected' : '' ?>>My Assigned</option>
        <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All Employees</option>
      </select>
      <?php endif; ?>
    </form>
    <?php if (is_admin()): ?>
    <button class="btn" onclick="openModal('modal-new-allocation')">&#8646; Allocate</button>
    <?php endif; ?>
    <a class="btn btn-primary" href="api/export_excel.php?type=my_audits&scope=<?= h($scope) ?>">&#8595; Export Excel</a>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:8px;">
  <div class="card"><div class="stat-label">In Scope</div><div class="stat-value"><?= $inScope ?></div><div class="stat-sub">assigned to me</div></div>
  <div class="card"><div class="stat-label">Completed</div><div class="stat-value c-success"><?= $completed ?></div></div>
  <div class="card"><div class="stat-label">Pending</div><div class="stat-value c-warning"><?= $pendingC ?></div></div>
  <div class="card"><div class="stat-label">Issues Found</div><div class="stat-value c-danger"><?= $issuesC ?></div></div>
</div>
<div class="grid grid-4" style="margin-bottom:18px;">
  <div class="card"><div class="stat-label">Findings</div><div class="stat-value c-danger"><?= $findings ?></div><div class="stat-sub">No / Incorrect answers</div></div>
</div>

<div class="split">
  <div class="panel">
    <div class="panel-head">
      <h3>Employees</h3>
      <span class="panel-tag"><?= count($employees) ?> shown</span>
    </div>
    <div class="table-wrap" style="max-height:640px; overflow-y:auto;">
      <table>
        <thead><tr><th>Emp No</th><th>Employee</th><th>Auditor</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$employees): ?>
          <tr><td colspan="4"><div class="empty-state">No employees allocated in this scope yet.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($employees as $e): ?>
          <tr class="clickable <?= $e['emp_id'] == $selectedId ? 'selected' : '' ?>"
              onclick="location.href='my_audits.php?id=<?= (int) $e['emp_id'] ?>&scope=<?= h($scope) ?>'">
            <td class="muted-cell"><?= h($e['emp_no']) ?></td>
            <td>
              <div class="emp-name"><?= h($e['name']) ?></div>
              <div class="emp-sub"><?= h($e['designation']) ?></div>
            </td>
            <td class="muted-cell"><?= h($e['auditor_name']) ?></td>
            <td><?= status_badge($e['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h3>Employee Audit Details</h3>
      <span class="panel-tag"><?= h($user['name']) ?></span>
    </div>

    <?php if (!$selected): ?>
      <div class="empty-state"><div class="ic">&#128203;</div>Select an employee from the list to begin auditing.</div>
    <?php else: ?>
      <div class="audit-detail-head">
        <div class="audit-emp">
          <div class="avatar"><?= h(strtoupper(substr($selected['name'], 0, 2))) ?></div>
          <div>
            <div class="nm"><?= h($selected['name']) ?> <span class="badge badge-muted">EMP <?= h($selected['emp_no']) ?></span></div>
            <div class="ds"><?= h($selected['designation']) ?></div>
            <div class="ds">Auditor: <?= h($selected['auditor_name']) ?> · <?= (int) $selected['progress'] ?>% complete</div>
          </div>
        </div>
        <div id="audit-status-badge"><?= status_badge($selected['status']) ?></div>
      </div>

      <div id="audit-items">
        <?php foreach ($items as $i => $it): ?>
          <div class="audit-item-row" data-item-id="<?= (int) $it['id'] ?>">
            <div class="audit-item-num"><?= $i + 1 ?></div>
            <div class="audit-item-title">
              <?= h($it['audit_item']) ?>
              <?php if ($it['category']): ?><span class="audit-item-cat"><?= h($it['category']) ?></span><?php endif; ?>
            </div>
            <select class="answer-select" onchange="onAnswerChange(<?= (int) $selected['allocation_id'] ?>, <?= (int) $it['id'] ?>, this)">
              <option value="" <?= $it['answer'] === null ? 'selected' : '' ?>>— Select —</option>
              <option value="yes" <?= $it['answer'] === 'yes' ? 'selected' : '' ?>>&#10003; Yes</option>
              <option value="no"  <?= $it['answer'] === 'no'  ? 'selected' : '' ?>>&#10007; No</option>
              <option value="na"  <?= $it['answer'] === 'na'  ? 'selected' : '' ?>>&#10003; N/A</option>
            </select>
          </div>
          <div class="remark-box <?= $it['answer'] === 'no' ? '' : 'hidden' ?>" id="remark-wrap-<?= (int) $it['id'] ?>">
            <div class="remark-hint">Remark required for a "No" answer</div>
            <textarea placeholder="Explain the issue found..." onblur="saveRemark(<?= (int) $selected['allocation_id'] ?>, <?= (int) $it['id'] ?>, this.value)"><?= h($it['remark'] ?? '') ?></textarea>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (is_admin()): include __DIR__ . '/includes/modal_new_allocation.php'; endif; ?>

<?php $extraScript = <<<'JS'
async function onAnswerChange(allocationId, itemId, selectEl) {
  const value = selectEl.value;
  const remarkWrap = document.getElementById('remark-wrap-' + itemId);
  if (value === 'no') {
    remarkWrap.classList.remove('hidden');
  } else {
    remarkWrap.classList.add('hidden');
  }
  const res = await apiPost('api/save_answer.php', { allocation_id: allocationId, item_id: itemId, answer: value });
  if (res.ok) {
    toast('Saved.', 'success');
    if (res.status_badge) document.getElementById('audit-status-badge').innerHTML = res.status_badge;
  } else {
    toast(res.message || 'Could not save.', 'error');
  }
}
async function saveRemark(allocationId, itemId, remark) {
  const res = await apiPost('api/save_answer.php', { allocation_id: allocationId, item_id: itemId, remark: remark, remark_only: 1 });
  if (res.ok) { toast('Remark saved.', 'success'); }
}
JS;
require __DIR__ . '/includes/footer.php'; ?>
