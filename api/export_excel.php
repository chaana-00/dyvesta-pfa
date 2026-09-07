<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/XLSX.php';
require_login();

$type = $_GET['type'] ?? '';
$pdo  = db();
$cycle = active_cycle();
$user = current_user();

switch ($type) {

    case 'template_employee':
        require_admin();
        XLSXWriter::download('employee_master_template.xlsx', [
            ['Employee No', 'Employee Name', 'Designation', 'Supervisor'],
            ['100999', 'Jane Doe', 'Software Engineer', 'AUD001'],
        ]);
        break;

    case 'template_checklist':
        require_admin();
        XLSXWriter::download('checklist_master_template.xlsx', [
            ['Code', 'Short Name', 'Audit Item', 'Category', 'Answer Type'],
            ['CHK-007', 'Contract', 'Availability of the signed contract', 'Documentation', 'Yes/No/N/A'],
        ]);
        break;

    case 'employee_master':
        require_admin();
        $rows = [['Employee No', 'Employee Name', 'Designation', 'Supervisor', 'Status']];
        $stmt = $pdo->query(
            "SELECT e.*, u.name AS supervisor_name FROM employees e
             LEFT JOIN users u ON u.id = e.supervisor_id ORDER BY e.name"
        );
        foreach ($stmt as $r) {
            $rows[] = [$r['emp_no'], $r['name'], $r['designation'], $r['supervisor_name'], ucfirst($r['status'])];
        }
        XLSXWriter::download('employee_master.xlsx', $rows);
        break;

    case 'auditor_master':
        require_admin();
        $rows = [['Employee No', 'Name', 'Email', 'Allocated', 'Completed', 'Status']];
        $stmt = $pdo->query(
            "SELECT u.*,
               (SELECT COUNT(*) FROM allocations al WHERE al.auditor_id=u.id AND al.cycle=" . $pdo->quote($cycle) . ") allocated,
               (SELECT COUNT(*) FROM allocations al WHERE al.auditor_id=u.id AND al.cycle=" . $pdo->quote($cycle) . " AND al.status='completed') completed
             FROM users u WHERE role='auditor' ORDER BY name"
        );
        foreach ($stmt as $r) {
            $rows[] = [$r['emp_no'], $r['name'], $r['email'], $r['allocated'], $r['completed'], ucfirst($r['status'])];
        }
        XLSXWriter::download('auditor_master.xlsx', $rows);
        break;

    case 'checklist_master':
        require_admin();
        $rows = [['Code', 'Short Name', 'Audit Item', 'Category', 'Answer Type']];
        foreach ($pdo->query('SELECT * FROM checklist_items ORDER BY sort_order') as $r) {
            $rows[] = [$r['code'], $r['short_name'], $r['audit_item'], $r['category'], $r['answer_type'] === 'yes_no' ? 'Yes/No' : 'Yes/No/N/A'];
        }
        XLSXWriter::download('checklist_master.xlsx', $rows);
        break;

    case 'allocations':
        require_admin();
        $rows = [['Employee No', 'Employee', 'Designation', 'Auditor', 'Progress %', 'Status']];
        $stmt = $pdo->prepare(
            "SELECT e.emp_no, e.name, e.designation, u.name AS auditor_name, a.progress, a.status
             FROM employees e
             LEFT JOIN allocations a ON a.employee_id = e.id AND a.cycle = ?
             LEFT JOIN users u ON u.id = a.auditor_id
             WHERE e.status = 'active' ORDER BY e.name"
        );
        $stmt->execute([$cycle]);
        foreach ($stmt as $r) {
            $rows[] = [$r['emp_no'], $r['name'], $r['designation'], $r['auditor_name'] ?? 'Unallocated', $r['progress'] ?? 0, $r['status'] ? ucfirst(str_replace('_', ' ', $r['status'])) : 'Unallocated'];
        }
        XLSXWriter::download('employee_allocation.xlsx', $rows);
        break;

    case 'my_audits':
    case 'issues_found':
    case 'pending_audits':
        [$scopeSql, $scopeParams] = scope_where('a');
        $extra = '';
        if ($type === 'issues_found') $extra = "AND a.status = 'issues_found'";
        if ($type === 'pending_audits') $extra = "AND a.status IN ('not_started','in_progress')";
        if ($type === 'my_audits' && ($_GET['scope'] ?? '') === 'all') {
            $scopeSql = '1=1';
            $scopeParams = [];
        }

        $rows = [['Employee No', 'Employee', 'Designation', 'Auditor', 'Progress %', 'Status']];
        $sql = "SELECT e.emp_no, e.name, e.designation, u.name AS auditor_name, a.progress, a.status
                FROM allocations a
                JOIN employees e ON e.id = a.employee_id
                JOIN users u ON u.id = a.auditor_id
                WHERE $scopeSql AND a.cycle = ? $extra
                ORDER BY e.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($scopeParams, [$cycle]));
        foreach ($stmt as $r) {
            $rows[] = [$r['emp_no'], $r['name'], $r['designation'], $r['auditor_name'], $r['progress'], ucfirst(str_replace('_', ' ', $r['status']))];
        }
        XLSXWriter::download($type . '.xlsx', $rows);
        break;

    case 'dashboard':
        $compliance = checklist_compliance();
        $rows = [['Metric', 'Value']];
        $rows[] = ['Total Employees', (int) $pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c']];
        $rows[] = ['Completed', count_allocations('completed')];
        $rows[] = ['Pending', count_allocations('not_started') + count_allocations('in_progress')];
        $rows[] = ['Issues Found', count_allocations('issues_found')];
        $rows[] = [''];
        $rows[] = ['Audit Item', 'OK', 'Issues', 'Pending', 'Compliance %'];
        foreach ($compliance as $c) {
            $rows[] = [$c['item']['audit_item'], $c['ok'], $c['issues'], $c['pending'], $c['pct']];
        }
        XLSXWriter::download('dashboard_export.xlsx', $rows);
        break;

    default:
        http_response_code(400);
        echo 'Unknown export type.';
}
