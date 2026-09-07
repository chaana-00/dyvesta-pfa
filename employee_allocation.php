<?php
require_once __DIR__ . '/includes/functions.php';
require_admin();

$pdo = db();
$cycle = active_cycle();
$auditorFilter = $_GET['auditor'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($auditorFilter !== 'all') {
    $where[] = 'u.id = ?';
    $params[] = (int) $auditorFilter;
}
if ($q !== '') {
    $where[] = '(e.name LIKE ? OR e.emp_no LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

// Every active employee, left-joined to their allocation for this cycle
$rows = $pdo->prepare(
    "SELECT e.id AS emp_id, e.emp_no, e.name, e.designation, u.id AS auditor_id, u.name AS auditor_name,
            a.id AS allocation_id, a.status, a.progress
     FROM employees e
     LEFT JOIN users u ON u.id = e.supervisor_id
     LEFT JOIN allocations a ON a.employee_id = e.id AND a.cycle = ?
     WHERE e.status = 'active' AND $whereSql
     ORDER BY e.name
     LIMIT 1000"
);
$rows->execute(array_merge([$cycle], $params));
$allocations = $rows->fetchAll();

$totalEmployees = (int) $pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
$totalAllocated = (int) $pdo->query("SELECT COUNT(*) c FROM allocations WHERE cycle = " . $pdo->quote($cycle))->fetch()['c'];
$unallocated = $totalEmployees - $totalAllocated;
$auditors = $pdo->query("SELECT id, name FROM users WHERE role='auditor' AND status='active' ORDER BY name")->fetchAll();
$avgPerAuditor = count($auditors) > 0 ? round($totalAllocated / count($auditors)) : 0;

$pageTitle = 'Employee Allocation';
$activeNav = 'allocation';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Employee Allocation</h1>
    <p>Who audits whom for <?= h($cycle) ?> — one allocation per employee per cycle</p>
  </div>
  <div class="page-actions">
    <form method="get"><input class="btn" style="cursor:text" type="text" name="q" placeholder="Search..." value="<?= h($q) ?>"></form>
    <a class="btn" href="api/sync_allocations.php">&#8635; Load Employee Master</a>
    <a class="btn" href="api/export_excel.php?type=allocations">&#8595; Export Excel</a>
    <a class="btn" href="master_data.php?tab=employee">&#8593; Upload Employee Master</a>
    <button class="btn btn-primary" onclick="openModal('modal-new-allocation')">+ New Allocation</button>
  </div>
</div>

<?php if (isset($_GET['synced'])): ?>
<div class="import-note" style="margin-bottom:18px;"><b><?= (int) $_GET['synced'] ?></b> allocation(s) synced from the Employee Master's Supervisor column.</div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:18px;">
  <div class="card"><div class="stat-label">Allocations</div><div class="stat-value"><?= number_format($totalAllocated) ?></div><div class="stat-sub">this cycle</div></div>
  <div class="card"><div class="stat-label">Auditors</div><div class="stat-value"><?= count($auditors) ?></div><div class="stat-sub">with allocations</div></div>
  <div class="card"><div class="stat-label">Unallocated</div><div class="stat-value <?= $unallocated > 0 ? 'c-warning' : 'c-success' ?>"><?= max(0, $unallocated) ?></div><div class="stat-sub"><?= $unallocated > 0 ? 'employees missing an auditor' : 'all employees covered' ?></div></div>
  <div class="card"><div class="stat-label">Avg per Auditor</div><div class="stat-value c-accent"><?= $avgPerAuditor ?></div><div class="stat-sub">employees</div></div>
</div>

<div class="pill-row">
  <a class="pill <?= $auditorFilter === 'all' ? 'active' : '' ?>" href="?auditor=all">All</a>
  <?php foreach ($auditors as $a): ?>
    <a class="pill <?= (string) $auditorFilter === (string) $a['id'] ? 'active' : '' ?>" href="?auditor=<?= (int) $a['id'] ?>"><?= h($a['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>Allocations</h3>
    <span class="panel-tag"><?= count($allocations) ?> shown</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Emp No</th><th>Employee</th><th>Designation</th><th>Auditor</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (!$allocations): ?>
          <tr><td colspan="7"><div class="empty-state">No employees found. Add them under Master Data.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($allocations as $r): ?>
          <tr>
            <td class="muted-cell"><?= h($r['emp_no']) ?></td>
            <td class="emp-name"><?= h($r['name']) ?></td>
            <td class="muted-cell"><?= h($r['designation']) ?></td>
            <td class="muted-cell"><?= h($r['auditor_name'] ?? '—') ?></td>
            <td><?= $r['allocation_id'] ? (int) $r['progress'] . '%' : '—' ?></td>
            <td><?= $r['allocation_id'] ? status_badge($r['status']) : '<span class="badge badge-muted">Unallocated</span>' ?></td>
            <td>
              <button class="btn btn-sm" onclick="openReassign(<?= js_arg($r['emp_no']) ?>,<?= js_arg($r['name']) ?>)">&#8646; Reassign</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/modal_new_allocation.php'; ?>

<div class="modal-overlay" id="modal-reassign">
  <div class="modal-box">
    <div class="modal-head">Reassign Auditor</div>
    <div class="modal-body">
      <div class="field"><label>Employee</label><input type="text" id="ra-emp-label" disabled></div>
      <input type="hidden" id="ra-emp-no">
      <div class="field"><label>New Auditor (employee no)</label><input type="text" id="ra-auditor-no" placeholder="e.g. 105153"></div>
    </div>
    <div class="modal-foot">
      <button class="btn" onclick="closeModal('modal-reassign')">Cancel</button>
      <button class="btn btn-primary" onclick="submitReassign()">Confirm</button>
    </div>
  </div>
</div>

<?php $extraScript = <<<'JS'
function openReassign(empNo, empName) {
  document.getElementById('ra-emp-no').value = empNo;
  document.getElementById('ra-emp-label').value = empName + ' (' + empNo + ')';
  document.getElementById('ra-auditor-no').value = '';
  openModal('modal-reassign');
}
async function submitReassign() {
  const empNo = document.getElementById('ra-emp-no').value;
  const auditorNo = document.getElementById('ra-auditor-no').value.trim();
  if (!auditorNo) { toast('Enter the new auditor\'s employee no.', 'error'); return; }
  const res = await apiPost('api/allocate.php', { emp_no: empNo, auditor_no: auditorNo });
  if (res.ok) {
    toast('Reassigned.', 'success');
    closeModal('modal-reassign');
    setTimeout(() => location.reload(), 500);
  } else {
    toast(res.message || 'Could not reassign.', 'error');
  }
}
JS;
require __DIR__ . '/includes/footer.php'; ?>
