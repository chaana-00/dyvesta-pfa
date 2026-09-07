<?php
/**
 * Include after setting: $pageTitle, $activeNav
 * Requires require_login() to have already been called by the page.
 */
require_once __DIR__ . '/functions.php';

$user = current_user();
$counts = nav_counts();
$initial = $user ? strtoupper(substr($user['name'], 0, 1)) : '?';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? app_name()) ?> · <?= h(app_name()) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark"><?= h(substr(app_name(), 0, 1)) ?></div>
      <div>
        <div class="brand-name"><?= h(app_name()) ?></div>
        <div class="brand-sub">PERSONAL FILE AUDIT</div>
      </div>
    </div>

    <div class="nav-group">
      <div class="nav-label">OVERVIEW</div>
      <div class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" onclick="location.href='dashboard.php'">
        <span class="left"><span class="ic">&#9635;</span>Dashboard</span>
      </div>
    </div>

    <div class="nav-group">
      <div class="nav-label">AUDIT</div>
      <div class="nav-link <?= $activeNav === 'my_audits' ? 'active' : '' ?>" onclick="location.href='my_audits.php'">
        <span class="left"><span class="ic">&#10003;</span>My Audits</span>
        <span class="nav-count"><?= (int) $counts['my_audits'] ?></span>
      </div>
      <div class="nav-link <?= $activeNav === 'issues' ? 'active' : '' ?>" onclick="location.href='issues_found.php'">
        <span class="left"><span class="ic">&#9888;</span>Issues Found</span>
        <span class="nav-count"><?= (int) $counts['issues'] ?></span>
      </div>
      <div class="nav-link <?= $activeNav === 'pending' ? 'active' : '' ?>" onclick="location.href='pending_audits.php'">
        <span class="left"><span class="ic">&#8635;</span>Pending Audits</span>
        <span class="nav-count"><?= (int) $counts['pending'] ?></span>
      </div>
    </div>

    <?php if (is_admin()): ?>
    <div class="nav-group">
      <div class="nav-label">ALLOCATION</div>
      <div class="nav-link <?= $activeNav === 'allocation' ? 'active' : '' ?>" onclick="location.href='employee_allocation.php'">
        <span class="left"><span class="ic">&#8646;</span>Employee Allocation</span>
        <span class="nav-count"><?= count_allocations() ?></span>
      </div>
    </div>

    <div class="nav-group">
      <div class="nav-label">SETUP</div>
      <div class="nav-link <?= $activeNav === 'master' ? 'active' : '' ?>" onclick="location.href='master_data.php'">
        <span class="left"><span class="ic">&#9633;</span>Master Data</span>
        <span class="nav-count">3</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
      <div class="util-row" style="position:relative;">
        <div class="icon-btn" id="quick-menu-btn" onclick="toggleQuickMenu()" title="Quick actions">&#10022;</div>
        <div class="icon-btn" onclick="toggleTheme()" title="Toggle theme">&#9789;</div>
        <?php if (is_admin()): ?>
        <div id="quick-menu" class="quick-menu">
          <a href="employee_allocation.php?new=1">+ New Allocation</a>
          <a href="master_data.php?tab=employee&amp;new=1">+ New Employee</a>
          <a href="master_data.php?tab=auditor&amp;new=1">+ New Auditor</a>
        </div>
        <?php endif; ?>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= h($initial) ?></div>
        <div class="who">
          <div class="name"><?= h($user['name'] ?? '') ?></div>
          <div class="role"><?= h(ucfirst($user['role'] ?? '')) ?></div>
        </div>
        <a class="icon-btn" href="logout.php" title="Sign out">&#8674;</a>
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="search-box">
        <span class="ic">&#128269;</span>
        <form method="get" action="search.php">
          <input type="text" name="q" placeholder="Search employees, emp no..." value="<?= h($_GET['q'] ?? '') ?>">
        </form>
      </div>
      <div class="topbar-spacer"></div>
      <div class="cycle-badge"><?= h(active_cycle()) ?></div>
      <div class="user-mini" onclick="location.href='logout.php'" title="Sign out">
        <div class="avatar"><?= h($initial) ?></div>
        <div>
          <div class="name"><?= h($user['name'] ?? '') ?></div>
          <div class="role"><?= h(ucfirst($user['role'] ?? '')) ?></div>
        </div>
      </div>
    </div>
    <div class="content">
