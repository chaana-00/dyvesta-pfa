<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$totalEmployees = (int) db()->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
$totalAllocations = count_allocations();
$completed  = count_allocations('completed');
$pending    = count_allocations('not_started') + count_allocations('in_progress');
$issues     = count_allocations('issues_found');
$mismatches = hris_mismatch_count();
$compliance = checklist_compliance();
$dist       = status_distribution();
$balance    = is_admin() ? allocation_balance() : [];

$pctCompleted = $totalAllocations > 0 ? round(($completed / $totalAllocations) * 100) : 0;
$pctPending   = $totalAllocations > 0 ? round(($pending / $totalAllocations) * 100) : 0;

function donut_color(int $pct): string
{
    if ($pct >= 90) return '#22c55e';
    if ($pct >= 70) return '#f59e0b';
    return '#f43f5e';
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>Personal file audit progress for <?= h(active_cycle()) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn" href="api/export_excel.php?type=dashboard">&#8595; Export Excel</a>
    <a class="btn" href="dashboard_print.php" target="_blank">&#8595; Export PDF</a>
    <?php if (is_admin()): ?>
    <button class="btn btn-primary" onclick="openModal('modal-new-allocation')">+ New Allocation</button>
    <?php endif; ?>
    <a class="btn btn-primary" href="my_audits.php">+ Continue Auditing</a>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px;">
  <div class="card">
    <div class="stat-label">Total Employees</div>
    <div class="stat-value"><?= number_format($totalEmployees) ?></div>
    <div class="stat-sub">in scope for this cycle</div>
  </div>
  <div class="card">
    <div class="stat-label">Total Audits Completed</div>
    <div class="stat-value c-accent"><?= number_format($completed) ?></div>
    <div class="stat-sub"><?= $pctCompleted ?>% of scope submitted</div>
  </div>
  <div class="card">
    <div class="stat-label">Completed</div>
    <div class="stat-value c-success"><?= number_format($completed) ?></div>
    <div class="progress-track"><div class="progress-fill ok" style="width:<?= $pctCompleted ?>%"></div></div>
  </div>
  <div class="card">
    <div class="stat-label">Pending</div>
    <div class="stat-value c-warning"><?= number_format($pending) ?></div>
    <div class="progress-track"><div class="progress-fill warn" style="width:<?= $pctPending ?>%"></div></div>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px;">
  <div class="card">
    <div class="stat-label">Issues Found</div>
    <div class="stat-value c-danger"><?= number_format($issues) ?></div>
    <div class="stat-sub">require resolution</div>
  </div>
  <div class="card">
    <div class="stat-label">HRIS Mismatches</div>
    <div class="stat-value" style="color:var(--accent)"><?= number_format($mismatches) ?></div>
    <div class="stat-sub">designation / NIC / type</div>
  </div>
  <?php foreach (array_slice($compliance, 0, 2) as $c): ?>
    <div class="card">
      <div class="stat-label"><?= h($c['item']['audit_item']) ?></div>
      <div class="stat-value c-danger"><?= $c['issues'] ?></div>
      <div class="stat-sub"><?= $c['total'] > 0 ? round((($c['ok'] + $c['issues']) / $c['total']) * 100) : 0 ?>% of answered</div>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel" style="margin-bottom:16px;">
  <div class="panel-head">
    <h3>Checklist Compliance</h3>
    <span class="panel-tag"><?= count($compliance) ?> items</span>
  </div>
  <div class="compliance-grid">
    <?php foreach ($compliance as $c): $color = donut_color($c['pct']); ?>
      <div class="compliance-item">
        <div class="donut" style="background: conic-gradient(<?= $color ?> <?= $c['pct'] ?>%, var(--panel-2) 0);">
          <div class="donut-inner" style="color:<?= $color ?>"><?= $c['pct'] ?>%</div>
        </div>
        <div>
          <div class="compliance-title"><?= h($c['item']['audit_item']) ?></div>
          <div class="compliance-sub">
            <span class="ok"><?= $c['ok'] ?> OK</span> &nbsp;
            <span class="issue"><?= $c['issues'] ?> issues</span> &nbsp;
            <?= $c['pending'] ?> pending
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <div class="panel-head">
      <h3>Allocation Balance</h3>
      <span class="panel-tag"><?= number_format($totalAllocations) ?> allocations</span>
    </div>
    <?php if (is_admin()): ?>
      <?php if (!$balance): ?>
        <div class="empty-state"><div class="ic">&#128100;</div>No auditors have been set up yet.</div>
      <?php endif; ?>
      <?php foreach ($balance as $b):
        $pct = $b['allocated'] > 0 ? round(($b['completed'] / $b['allocated']) * 100) : 0; ?>
        <div class="bar-row">
          <div class="lbl"><?= h($b['name']) ?></div>
          <div class="track"><div class="fill" style="width:<?= $pct ?>%; background:var(--accent)"></div></div>
          <div class="val"><?= (int) $b['allocated'] ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="ic">&#128274;</div>Only administrators can view auditor allocation balance.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h3>Status Distribution</h3>
      <span class="panel-tag">this cycle</span>
    </div>
    <?php
    $distLabels = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'issues_found' => 'Issues Found'];
    $distColors = ['not_started' => '#f59e0b', 'in_progress' => '#38bdf8', 'completed' => '#22c55e', 'issues_found' => '#f43f5e'];
    foreach ($dist as $key => $val):
      $pct = $totalAllocations > 0 ? round(($val / $totalAllocations) * 100) : 0; ?>
      <div class="bar-row">
        <div class="lbl"><?= $distLabels[$key] ?></div>
        <div class="track"><div class="fill" style="width:<?= $pct ?>%; background:<?= $distColors[$key] ?>"></div></div>
        <div class="val"><?= (int) $val ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (is_admin()): include __DIR__ . '/includes/modal_new_allocation.php'; endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
