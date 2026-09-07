<?php
require_once __DIR__ . '/includes/functions.php';
require_admin();

$pdo = db();
$tab = $_GET['tab'] ?? 'employee';
if (!in_array($tab, ['employee', 'auditor', 'checklist'], true)) {
    $tab = 'employee';
}

$totalEmployees = (int) $pdo->query('SELECT COUNT(*) c FROM employees')->fetch()['c'];
$totalAuditors  = (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE role='auditor'")->fetch()['c'];
$totalChecklist = (int) $pdo->query('SELECT COUNT(*) c FROM checklist_items')->fetch()['c'];

$employees = $pdo->query(
    "SELECT e.*, u.name AS supervisor_name, u.emp_no AS supervisor_no
     FROM employees e LEFT JOIN users u ON u.id = e.supervisor_id
     ORDER BY e.name LIMIT 1000"
)->fetchAll();

$mdq = trim($_GET['q'] ?? '');
if ($mdq !== '' && $tab === 'employee') {
    $employees = array_values(array_filter($employees, function ($e) use ($mdq) {
        return stripos($e['name'], $mdq) !== false || stripos($e['emp_no'], $mdq) !== false;
    }));
}

$activeEmployees = 0; $noSupervisor = 0;
foreach ($employees as $e) {
    if ($e['status'] === 'active') $activeEmployees++;
    if (!$e['supervisor_id']) $noSupervisor++;
}

$auditors = $pdo->query(
    "SELECT u.*,
       (SELECT COUNT(*) FROM allocations al WHERE al.auditor_id = u.id AND al.cycle = " . $pdo->quote(active_cycle()) . ") AS allocated,
       (SELECT COUNT(*) FROM allocations al WHERE al.auditor_id = u.id AND al.cycle = " . $pdo->quote(active_cycle()) . " AND al.status='completed') AS completed
     FROM users u WHERE u.role = 'auditor' ORDER BY u.name LIMIT 1000"
)->fetchAll();
$avgLoad = $totalAuditors > 0 ? round(array_sum(array_column($auditors, 'allocated')) / $totalAuditors) : 0;

$checklist = $pdo->query('SELECT * FROM checklist_items ORDER BY sort_order, id')->fetchAll();
$catCounts = ['Documentation' => 0, 'HRIS Accuracy' => 0, 'Yes/No' => 0, 'Correct/Incorrect' => 0];
foreach ($checklist as $c) {
    if (isset($catCounts[$c['category']])) $catCounts[$c['category']]++;
    if ($c['answer_type'] === 'yes_no') $catCounts['Yes/No']++;
}

$pageTitle = 'Master Data';
$activeNav = 'master';
require __DIR__ . '/includes/header.php';
?>

<div class="tabs">
  <div class="tab <?= $tab === 'employee' ? 'active' : '' ?>" onclick="location.href='master_data.php?tab=employee'">Employee Master <span class="cnt"><?= $totalEmployees ?></span></div>
  <div class="tab <?= $tab === 'auditor' ? 'active' : '' ?>" onclick="location.href='master_data.php?tab=auditor'">Auditor Master <span class="cnt"><?= $totalAuditors ?></span></div>
  <div class="tab <?= $tab === 'checklist' ? 'active' : '' ?>" onclick="location.href='master_data.php?tab=checklist'">Audit Checklist Master <span class="cnt"><?= $totalChecklist ?></span></div>
</div>

<div style="height:22px;"></div>

<?php if (isset($_GET['import'])):
  $importMsgs = [
    'ok' => 'Import complete — ' . (int) ($_GET['count'] ?? 0) . ' row(s) processed.',
    'nofile' => 'No file was received. Please choose a .xlsx file.',
    'empty' => 'That file appears to be empty.',
    'badcolumns' => 'Could not find the expected columns in that file. Check the template and try again.',
  ];
  $msg = $importMsgs[$_GET['import']] ?? '';
  if ($msg): ?>
    <div class="import-note" style="margin-bottom:18px;"><?= h($msg) ?></div>
  <?php endif;
endif; ?>

<?php if ($tab === 'employee'): ?>

  <div class="page-head">
    <div>
      <h1>Employee Master</h1>
      <p>Everyone in scope for the audit. Supervisor is the auditor's employee no — that's what maps each employee to their auditor.</p>
    </div>
    <div class="page-actions">
      <form method="get"><input type="hidden" name="tab" value="employee"><input class="btn" style="cursor:text" type="text" name="q" placeholder="Search..."></form>
      <a class="btn" href="api/export_excel.php?type=template_employee">&#8595; Download Template</a>
      <a class="btn" href="api/export_excel.php?type=employee_master">Export Excel</a>
      <button class="btn btn-primary" onclick="openModal('modal-new-employee')">+ New Record</button>
    </div>
  </div>

  <div class="import-note"><b>Expected columns:</b> Employee No · Employee Name · Designation · Supervisor<br>Download the template to get the exact layout. Rows are matched on <b>Employee No</b> — existing records are updated, new ones are created.</div>

  <form id="import-employee-form" action="api/import_employees.php" method="post" enctype="multipart/form-data">
    <div class="dropzone" id="employee-dropzone">
      <strong>Drop the .xlsx file here, or click to browse</strong>
      <small>Nothing is written until you review the preview and confirm</small>
      <input type="file" id="employee-file" name="file" accept=".xlsx" class="hidden">
    </div>
  </form>

  <div class="grid grid-4" style="margin-bottom:18px;">
    <div class="card"><div class="stat-label">Total Employees</div><div class="stat-value"><?= $totalEmployees ?></div></div>
    <div class="card"><div class="stat-label">Active</div><div class="stat-value c-success"><?= $activeEmployees ?></div></div>
    <div class="card"><div class="stat-label">Inactive</div><div class="stat-value"><?= $totalEmployees - $activeEmployees ?></div></div>
    <div class="card"><div class="stat-label">No Supervisor</div><div class="stat-value <?= $noSupervisor > 0 ? 'c-warning' : 'c-success' ?>"><?= $noSupervisor ?></div><div class="stat-sub"><?= $noSupervisor === 0 ? 'all mapped' : 'needs mapping' ?></div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>Employees</h3><span class="panel-tag"><?= count($employees) ?> shown</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Employee No</th><th>Employee Name</th><th>Designation</th><th>Supervisor</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!$employees): ?><tr><td colspan="6"><div class="empty-state">No employees yet — add one or import an Excel file.</div></td></tr><?php endif; ?>
          <?php foreach ($employees as $e): ?>
            <tr>
              <td class="muted-cell"><?= h($e['emp_no']) ?></td>
              <td class="emp-name"><?= h($e['name']) ?></td>
              <td class="muted-cell"><?= h($e['designation']) ?></td>
              <td>
                <?php if ($e['supervisor_name']): ?>
                  <?= h($e['supervisor_name']) ?><div class="emp-sub"><?= h($e['supervisor_no']) ?></div>
                <?php else: ?><span class="muted-cell">—</span><?php endif; ?>
              </td>
              <td><span class="badge <?= $e['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= ucfirst($e['status']) ?></span></td>
              <td class="flex gap-8">
                <button class="btn btn-sm" onclick="openSupervisor(<?= js_arg($e['emp_no']) ?>,<?= js_arg($e['name']) ?>)">&#8646; Supervisor</button>
                <button class="btn btn-sm <?= $e['status'] === 'active' ? 'btn-danger' : '' ?>" onclick="toggleEmployee(<?= js_arg($e['emp_no']) ?>,<?= js_arg($e['status'] === 'active' ? 'inactive' : 'active') ?>)"><?= $e['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-overlay" id="modal-new-employee">
    <div class="modal-box">
      <div class="modal-head">New Employee</div>
      <div class="modal-body">
        <div class="field"><label>Employee No</label><input type="text" id="ne-emp-no" placeholder="e.g. 100999"></div>
        <div class="field"><label>Employee Name</label><input type="text" id="ne-name" placeholder="Full name"></div>
        <div class="field"><label>Designation</label><input type="text" id="ne-designation" placeholder="e.g. Software Engineer"></div>
        <div class="field"><label>Supervisor (auditor)</label><input type="text" id="ne-supervisor" placeholder="Auditor employee no"></div>
      </div>
      <div class="modal-foot">
        <button class="btn" onclick="closeModal('modal-new-employee')">Cancel</button>
        <button class="btn btn-primary" onclick="submitNewEmployee()">Confirm</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="modal-supervisor">
    <div class="modal-box">
      <div class="modal-head">Set Supervisor</div>
      <div class="modal-body">
        <div class="field"><label>Employee</label><input type="text" id="sv-emp-label" disabled></div>
        <input type="hidden" id="sv-emp-no">
        <div class="field"><label>Supervisor (auditor employee no)</label><input type="text" id="sv-auditor-no"></div>
      </div>
      <div class="modal-foot">
        <button class="btn" onclick="closeModal('modal-supervisor')">Cancel</button>
        <button class="btn btn-primary" onclick="submitSupervisor()">Confirm</button>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'auditor'): ?>

  <div class="page-head">
    <div>
      <h1>Auditor Master</h1>
      <p>The people who perform the audits. Each gets a login (password auto-generated) and sees only the employees they supervise.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openModal('modal-new-auditor')">+ New Auditor</button>
    </div>
  </div>

  <div class="import-note"><b>Step 1 of the setup.</b> Create each auditor here — they sign in with the email and password you set, and see only the employees they supervise. Then upload the Employee Master with each row's <b>Supervisor</b> set to an auditor's employee no.</div>

  <div class="grid grid-4" style="margin-bottom:18px;">
    <div class="card"><div class="stat-label">Auditors</div><div class="stat-value"><?= $totalAuditors ?></div></div>
    <div class="card"><div class="stat-label">Active</div><div class="stat-value c-success"><?= count(array_filter($auditors, fn($a) => $a['status'] === 'active')) ?></div></div>
    <div class="card"><div class="stat-label">Total Allocated</div><div class="stat-value c-accent"><?= array_sum(array_column($auditors, 'allocated')) ?></div><div class="stat-sub">employees supervised</div></div>
    <div class="card"><div class="stat-label">Avg Load</div><div class="stat-value" style="color:var(--accent)"><?= $avgLoad ?></div><div class="stat-sub">per auditor</div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>Auditors</h3><span class="panel-tag"><?= count($auditors) ?> records</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Employee No</th><th>Name</th><th>Email</th><th>Allocated</th><th>Completed</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!$auditors): ?><tr><td colspan="7"><div class="empty-state">No auditors yet — create the first one.</div></td></tr><?php endif; ?>
          <?php foreach ($auditors as $a): ?>
            <tr>
              <td class="muted-cell"><?= h($a['emp_no']) ?></td>
              <td class="emp-name"><?= h($a['name']) ?></td>
              <td class="muted-cell"><?= h($a['email']) ?></td>
              <td><?= (int) $a['allocated'] ?></td>
              <td class="c-success"><?= (int) $a['completed'] ?></td>
              <td><span class="badge <?= $a['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= ucfirst($a['status']) ?></span></td>
              <td class="flex gap-8">
                <button class="btn btn-sm" onclick="openEditAuditor(<?= (int) $a['id'] ?>,<?= js_arg($a['emp_no']) ?>,<?= js_arg($a['name']) ?>,<?= js_arg($a['email']) ?>)">Edit</button>
                <button class="btn btn-sm" onclick="resetPassword(<?= (int) $a['id'] ?>)">Reset Password</button>
                <button class="btn btn-sm btn-danger" onclick="removeAuditor(<?= (int) $a['id'] ?>)">Remove</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-overlay" id="modal-new-auditor">
    <div class="modal-box">
      <div class="modal-head">New Auditor</div>
      <div class="modal-body">
        <div class="field"><label>Employee No</label><input type="text" id="na2-emp-no" placeholder="e.g. AUD001"></div>
        <div class="field"><label>Name</label><input type="text" id="na2-name" placeholder="Full name"></div>
        <div class="field"><label>Email</label><input type="email" id="na2-email" placeholder="name@example.com"></div>
        <div class="modal-hint">A login password is generated automatically and shown once you confirm. Use this employee no as the <b>Supervisor</b> value in the Employee Master to assign them employees.</div>
      </div>
      <div class="modal-foot">
        <button class="btn" onclick="closeModal('modal-new-auditor')">Cancel</button>
        <button class="btn btn-primary" onclick="submitNewAuditor()">Confirm</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="modal-edit-auditor">
    <div class="modal-box">
      <div class="modal-head">Edit Auditor</div>
      <div class="modal-body">
        <input type="hidden" id="ea-id">
        <div class="field"><label>Employee No</label><input type="text" id="ea-emp-no"></div>
        <div class="field"><label>Name</label><input type="text" id="ea-name"></div>
        <div class="field"><label>Email</label><input type="email" id="ea-email"></div>
      </div>
      <div class="modal-foot">
        <button class="btn" onclick="closeModal('modal-edit-auditor')">Cancel</button>
        <button class="btn btn-primary" onclick="submitEditAuditor()">Save</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="modal-password-reveal">
    <div class="modal-box">
      <div class="modal-head">Login Credentials</div>
      <div class="modal-body">
        <p style="color:var(--muted); font-size:13px;">Share this password securely — it will not be shown again.</p>
        <div class="field"><label>Password</label><input type="text" id="pw-reveal" readonly></div>
      </div>
      <div class="modal-foot"><button class="btn btn-primary" onclick="closeModal('modal-password-reveal')">Done</button></div>
    </div>
  </div>

<?php else: ?>

  <div class="page-head">
    <div>
      <h1>Audit Checklist Master</h1>
      <p>The standardized checklist applied to every personal file</p>
    </div>
    <div class="page-actions">
      <a class="btn" href="api/export_excel.php?type=template_checklist">&#8595; Download Template</a>
      <a class="btn" href="api/export_excel.php?type=checklist_master">Export Excel</a>
      <button class="btn btn-primary" onclick="openModal('modal-new-checklist')">+ New Record</button>
    </div>
  </div>

  <div class="import-note"><b>Expected columns:</b> Code · Short Name · Audit Item · Category · Answer Type<br>Download the template to get the exact layout. Rows are matched on <b>Audit Item</b> — existing records are updated, new ones are created.</div>

  <form id="import-checklist-form" action="api/import_checklist.php" method="post" enctype="multipart/form-data">
    <div class="dropzone" id="checklist-dropzone">
      <strong>Drop the .xlsx file here, or click to browse</strong>
      <small>Nothing is written until you review the preview and confirm</small>
      <input type="file" id="checklist-file" name="file" accept=".xlsx" class="hidden">
    </div>
  </form>

  <div class="grid grid-4" style="margin-bottom:18px;">
    <div class="card"><div class="stat-label">Checklist Items</div><div class="stat-value"><?= $totalChecklist ?></div></div>
    <div class="card"><div class="stat-label">Documentation</div><div class="stat-value" style="color:var(--accent)"><?= $catCounts['Documentation'] ?></div></div>
    <div class="card"><div class="stat-label">HRIS Accuracy</div><div class="stat-value" style="color:var(--info)"><?= $catCounts['HRIS Accuracy'] ?></div></div>
    <div class="card"><div class="stat-label">Yes / No</div><div class="stat-value c-success"><?= $catCounts['Yes/No'] ?></div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>Checklist Items</h3><span class="panel-tag"><?= count($checklist) ?> items</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Code</th><th>Audit Item</th><th>Category</th><th>Answer Type</th><th>Remark Rule</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($checklist as $i => $c): ?>
            <tr>
              <td class="muted-cell"><?= $i + 1 ?></td>
              <td class="muted-cell"><?= h($c['code']) ?></td>
              <td class="emp-name"><?= h($c['audit_item']) ?></td>
              <td class="muted-cell"><?= h($c['category']) ?></td>
              <td class="muted-cell"><?= $c['answer_type'] === 'yes_no' ? 'Yes / No' : 'Yes / No / N/A' ?></td>
              <td class="muted-cell">Remark required when <span class="c-danger">No</span></td>
              <td><button class="btn btn-sm" onclick="openEditChecklist(<?= (int) $c['id'] ?>,<?= js_arg($c) ?>)">Edit</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-overlay" id="modal-new-checklist">
    <div class="modal-box">
      <div class="modal-head">New Checklist Item</div>
      <div class="modal-body">
        <input type="hidden" id="nc-id">
        <div class="field"><label>Code</label><input type="text" id="nc-code" placeholder="e.g. CHK-007"></div>
        <div class="field"><label>Short Name</label><input type="text" id="nc-short" placeholder="e.g. Contract"></div>
        <div class="field"><label>Audit Item</label><input type="text" id="nc-item" placeholder="Full description"></div>
        <div class="field"><label>Category</label><input type="text" id="nc-category" placeholder="e.g. Documentation"></div>
        <div class="field">
          <label>Answer Type</label>
          <select id="nc-answer-type">
            <option value="yes_no_na">Yes / No / N/A</option>
            <option value="yes_no">Yes / No</option>
          </select>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn" onclick="closeModal('modal-new-checklist')">Cancel</button>
        <button class="btn btn-primary" onclick="submitChecklist()">Confirm</button>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php $extraScript = <<<'JS'
/* ---------- Employee tab ---------- */
async function submitNewEmployee() {
  const payload = {
    action: 'create',
    emp_no: document.getElementById('ne-emp-no').value.trim(),
    name: document.getElementById('ne-name').value.trim(),
    designation: document.getElementById('ne-designation').value.trim(),
    supervisor_no: document.getElementById('ne-supervisor').value.trim(),
  };
  if (!payload.emp_no || !payload.name) { toast('Employee No and Name are required.', 'error'); return; }
  const res = await apiPost('api/employee_crud.php', payload);
  if (res.ok) { toast('Employee saved.', 'success'); setTimeout(() => location.reload(), 500); }
  else toast(res.message || 'Could not save employee.', 'error');
}
function openSupervisor(empNo, name) {
  document.getElementById('sv-emp-no').value = empNo;
  document.getElementById('sv-emp-label').value = name + ' (' + empNo + ')';
  document.getElementById('sv-auditor-no').value = '';
  openModal('modal-supervisor');
}
async function submitSupervisor() {
  const res = await apiPost('api/employee_crud.php', {
    action: 'set_supervisor',
    emp_no: document.getElementById('sv-emp-no').value,
    supervisor_no: document.getElementById('sv-auditor-no').value.trim(),
  });
  if (res.ok) { toast('Supervisor updated.', 'success'); closeModal('modal-supervisor'); setTimeout(() => location.reload(), 500); }
  else toast(res.message || 'Could not update supervisor.', 'error');
}
async function toggleEmployee(empNo, newStatus) {
  const res = await apiPost('api/employee_crud.php', { action: 'set_status', emp_no: empNo, status: newStatus });
  if (res.ok) { toast('Status updated.', 'success'); setTimeout(() => location.reload(), 400); }
  else toast(res.message || 'Could not update status.', 'error');
}

/* ---------- Auditor tab ---------- */
async function submitNewAuditor() {
  const payload = {
    action: 'create',
    emp_no: document.getElementById('na2-emp-no').value.trim(),
    name: document.getElementById('na2-name').value.trim(),
    email: document.getElementById('na2-email').value.trim(),
  };
  if (!payload.emp_no || !payload.name || !payload.email) { toast('All fields are required.', 'error'); return; }
  const res = await apiPost('api/auditor_crud.php', payload);
  if (res.ok) {
    closeModal('modal-new-auditor');
    document.getElementById('pw-reveal').value = res.password;
    openModal('modal-password-reveal');
    setTimeout(() => location.reload(), 4000);
  } else toast(res.message || 'Could not create auditor.', 'error');
}
function openEditAuditor(id, empNo, name, email) {
  document.getElementById('ea-id').value = id;
  document.getElementById('ea-emp-no').value = empNo;
  document.getElementById('ea-name').value = name;
  document.getElementById('ea-email').value = email;
  openModal('modal-edit-auditor');
}
async function submitEditAuditor() {
  const res = await apiPost('api/auditor_crud.php', {
    action: 'edit',
    id: document.getElementById('ea-id').value,
    emp_no: document.getElementById('ea-emp-no').value.trim(),
    name: document.getElementById('ea-name').value.trim(),
    email: document.getElementById('ea-email').value.trim(),
  });
  if (res.ok) { toast('Auditor updated.', 'success'); setTimeout(() => location.reload(), 400); }
  else toast(res.message || 'Could not update auditor.', 'error');
}
async function resetPassword(id) {
  const res = await apiPost('api/auditor_crud.php', { action: 'reset_password', id: id });
  if (res.ok) { document.getElementById('pw-reveal').value = res.password; openModal('modal-password-reveal'); }
  else toast(res.message || 'Could not reset password.', 'error');
}
async function removeAuditor(id) {
  if (!confirm('Remove this auditor? This is blocked if they still have allocations.')) return;
  const res = await apiPost('api/auditor_crud.php', { action: 'remove', id: id });
  if (res.ok) { toast('Auditor removed.', 'success'); setTimeout(() => location.reload(), 400); }
  else toast(res.message || 'Could not remove auditor.', 'error');
}

/* ---------- Checklist tab ---------- */
function openEditChecklist(id, item) {
  document.getElementById('nc-id').value = id;
  document.getElementById('nc-code').value = item.code || '';
  document.getElementById('nc-short').value = item.short_name || '';
  document.getElementById('nc-item').value = item.audit_item || '';
  document.getElementById('nc-category').value = item.category || '';
  document.getElementById('nc-answer-type').value = item.answer_type || 'yes_no_na';
  document.querySelector('#modal-new-checklist .modal-head').textContent = 'Edit Checklist Item';
  openModal('modal-new-checklist');
}
async function submitChecklist() {
  const payload = {
    action: document.getElementById('nc-id').value ? 'edit' : 'create',
    id: document.getElementById('nc-id').value,
    code: document.getElementById('nc-code').value.trim(),
    short_name: document.getElementById('nc-short').value.trim(),
    audit_item: document.getElementById('nc-item').value.trim(),
    category: document.getElementById('nc-category').value.trim(),
    answer_type: document.getElementById('nc-answer-type').value,
  };
  if (!payload.audit_item) { toast('Audit Item is required.', 'error'); return; }
  const res = await apiPost('api/checklist_crud.php', payload);
  if (res.ok) { toast('Checklist item saved.', 'success'); setTimeout(() => location.reload(), 400); }
  else toast(res.message || 'Could not save checklist item.', 'error');
}

/* ---------- Dropzones ---------- */
bindDropzone('employee-dropzone', 'employee-file', 'import-employee-form');
bindDropzone('checklist-dropzone', 'checklist-file', 'import-checklist-form');

/* open "new" modal automatically if ?new=1 */
const params = new URLSearchParams(location.search);
if (params.get('new') === '1') {
  if (params.get('tab') === 'auditor') openModal('modal-new-auditor');
  else openModal('modal-new-employee');
}
JS;
require __DIR__ . '/includes/footer.php'; ?>
