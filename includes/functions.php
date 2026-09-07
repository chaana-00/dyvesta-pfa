<?php
/**
 * DYVESTA — Personal File Audit
 * Shared helper functions
 */

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------------------------------------------- */
/* Auth helpers                                                     */
/* --------------------------------------------------------------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}

/* --------------------------------------------------------------- */
/* Settings                                                          */
/* --------------------------------------------------------------- */

function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`,`value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([$key, $value]);
}

function active_cycle(): string
{
    return get_setting('active_cycle', 'Personal File Audit — 2026');
}

function app_name(): string
{
    return get_setting('app_name', 'DYVESTA');
}

/* --------------------------------------------------------------- */
/* Misc                                                              */
/* --------------------------------------------------------------- */

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Safely embed a PHP value as a JS argument inside an inline HTML event
 * attribute (e.g. onclick="fn(<?= js_arg($name) ?>)"). json_encode() gives
 * a valid JS literal (handles quotes/apostrophes/backslashes correctly),
 * and htmlspecialchars() on top protects the surrounding HTML attribute
 * quotes — safe no matter which quote style wraps the attribute.
 */
function js_arg($value): string
{
    return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function random_password(int $len = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function status_badge(string $status): string
{
    $map = [
        'not_started'  => ['Not Started', 'badge-warning'],
        'in_progress'  => ['In Progress', 'badge-info'],
        'completed'    => ['Completed',   'badge-success'],
        'issues_found' => ['Issues Found','badge-danger'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst($status), 'badge-muted'];
    return '<span class="badge ' . $class . '">' . h($label) . '</span>';
}

function answer_badge(?string $answer): string
{
    if ($answer === 'yes') return '<span class="ans ans-yes">&#10003;</span>';
    if ($answer === 'no')  return '<span class="ans ans-no">&#10007; No</span>';
    if ($answer === 'na')  return '<span class="ans ans-na">N/A</span>';
    return '<span class="ans ans-empty">—</span>';
}

/**
 * Recalculate an allocation's status/progress from its audit_answers rows.
 */
function recalc_allocation(int $allocationId): void
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT ci.id, aa.answer
         FROM checklist_items ci
         LEFT JOIN audit_answers aa
                ON aa.checklist_item_id = ci.id AND aa.allocation_id = ?'
    );
    $stmt->execute([$allocationId]);
    $rows = $stmt->fetchAll();

    $total    = count($rows);
    $answered = 0;
    $hasNo    = false;

    foreach ($rows as $r) {
        if ($r['answer'] !== null) {
            $answered++;
            if ($r['answer'] === 'no') {
                $hasNo = true;
            }
        }
    }

    $progress = $total > 0 ? (int) round(($answered / $total) * 100) : 0;

    if ($answered === 0) {
        $status = 'not_started';
    } elseif ($hasNo) {
        $status = 'issues_found';
    } elseif ($answered === $total) {
        $status = 'completed';
    } else {
        $status = 'in_progress';
    }

    $upd = $pdo->prepare('UPDATE allocations SET status = ?, progress = ? WHERE id = ?');
    $upd->execute([$status, $progress, $allocationId]);
}

/**
 * Make sure every checklist item has an (empty) audit_answers row for an
 * allocation, so "My Audits" always has a full set of dropdowns to show.
 */
function ensure_answer_rows(int $allocationId): void
{
    $pdo = db();
    $items = $pdo->query('SELECT id FROM checklist_items')->fetchAll();
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO audit_answers (allocation_id, checklist_item_id) VALUES (?, ?)'
    );
    foreach ($items as $it) {
        $ins->execute([$allocationId, $it['id']]);
    }
}

/* --------------------------------------------------------------- */
/* Scope helpers — admins see everything, auditors see only theirs   */
/* --------------------------------------------------------------- */

function scope_where(string $alias = 'a'): array
{
    $u = current_user();
    if ($u && $u['role'] === 'auditor') {
        return ["$alias.auditor_id = ?", [$u['id']]];
    }
    return ['1=1', []];
}

function count_allocations(?string $status = null): int
{
    [$where, $params] = scope_where('a');
    $sql = "SELECT COUNT(*) c FROM allocations a WHERE $where AND a.cycle = ?";
    $params[] = active_cycle();
    if ($status) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['c'];
}

function checklist_compliance(): array
{
    [$where, $params] = scope_where('a');
    $cycle = active_cycle();

    $items = db()->query('SELECT * FROM checklist_items ORDER BY sort_order, id')->fetchAll();
    $out = [];

    foreach ($items as $item) {
        $sql = "SELECT
                  SUM(CASE WHEN aa.answer IN ('yes','na') THEN 1 ELSE 0 END) AS ok_count,
                  SUM(CASE WHEN aa.answer = 'no' THEN 1 ELSE 0 END) AS issue_count,
                  SUM(CASE WHEN aa.answer IS NULL THEN 1 ELSE 0 END) AS pending_count,
                  COUNT(*) AS total
                FROM allocations a
                JOIN audit_answers aa ON aa.allocation_id = a.id AND aa.checklist_item_id = ?
                WHERE $where AND a.cycle = ?";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$item['id']], $params, [$cycle]));
        $row = $stmt->fetch();

        $total = (int) ($row['total'] ?? 0);
        $ok    = (int) ($row['ok_count'] ?? 0);
        $issue = (int) ($row['issue_count'] ?? 0);
        $pend  = (int) ($row['pending_count'] ?? 0);
        $pct   = $total > 0 ? (int) round(($ok / $total) * 100) : 0;

        $out[] = [
            'item'    => $item,
            'ok'      => $ok,
            'issues'  => $issue,
            'pending' => $pend,
            'total'   => $total,
            'pct'     => $pct,
        ];
    }

    return $out;
}

function status_distribution(): array
{
    return [
        'not_started'  => count_allocations('not_started'),
        'in_progress'  => count_allocations('in_progress'),
        'completed'    => count_allocations('completed'),
        'issues_found' => count_allocations('issues_found'),
    ];
}

function allocation_balance(): array
{
    $cycle = active_cycle();
    $sql = "SELECT u.id, u.name,
                   COUNT(a.id) AS allocated,
                   SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed
            FROM users u
            LEFT JOIN allocations a ON a.auditor_id = u.id AND a.cycle = ?
            WHERE u.role = 'auditor'
            GROUP BY u.id, u.name
            ORDER BY u.name";
    $stmt = db()->prepare($sql);
    $stmt->execute([$cycle]);
    return $stmt->fetchAll();
}

function hris_mismatch_count(): int
{
    [$where, $params] = scope_where('a');
    $cycle = active_cycle();
    $sql = "SELECT COUNT(*) c
            FROM audit_answers aa
            JOIN allocations a ON a.id = aa.allocation_id
            JOIN checklist_items ci ON ci.id = aa.checklist_item_id
            WHERE $where AND a.cycle = ? AND ci.category = 'HRIS Accuracy' AND aa.answer = 'no'";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($params, [$cycle]));
    return (int) $stmt->fetch()['c'];
}

function nav_counts(): array
{
    return [
        'my_audits' => count_allocations(),
        'issues'    => count_allocations('issues_found'),
        'pending'   => count_allocations('not_started') + count_allocations('in_progress'),
    ];
}

/**
 * Find-or-create the allocation row for an employee in the active cycle.
 */
function get_or_create_allocation(int $employeeId, int $auditorId): int
{
    $pdo   = db();
    $cycle = active_cycle();

    $stmt = $pdo->prepare('SELECT id FROM allocations WHERE employee_id = ? AND cycle = ?');
    $stmt->execute([$employeeId, $cycle]);
    $row = $stmt->fetch();

    if ($row) {
        $upd = $pdo->prepare('UPDATE allocations SET auditor_id = ? WHERE id = ?');
        $upd->execute([$auditorId, $row['id']]);
        $id = (int) $row['id'];
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO allocations (employee_id, auditor_id, cycle) VALUES (?, ?, ?)'
        );
        $ins->execute([$employeeId, $auditorId, $cycle]);
        $id = (int) $pdo->lastInsertId();
    }

    ensure_answer_rows($id);
    return $id;
}
