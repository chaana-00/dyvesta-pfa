<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$user         = current_user();
$allocationId = (int) ($_POST['allocation_id'] ?? 0);
$itemId       = (int) ($_POST['item_id'] ?? 0);
$remarkOnly   = !empty($_POST['remark_only']);
$answer       = $_POST['answer'] ?? null;
$remark       = $_POST['remark'] ?? null;

if (!$allocationId || !$itemId) {
    json_out(['ok' => false, 'message' => 'Missing allocation or checklist item.']);
}

$pdo = db();

// Auditors may only edit their own allocations; admins may edit any.
$check = $pdo->prepare('SELECT auditor_id FROM allocations WHERE id = ?');
$check->execute([$allocationId]);
$alloc = $check->fetch();
if (!$alloc) {
    json_out(['ok' => false, 'message' => 'Allocation not found.']);
}
if (!is_admin() && (int) $alloc['auditor_id'] !== (int) $user['id']) {
    json_out(['ok' => false, 'message' => 'You are not the auditor assigned to this employee.'], 403);
}

if ($remarkOnly) {
    $stmt = $pdo->prepare(
        'UPDATE audit_answers SET remark = ? WHERE allocation_id = ? AND checklist_item_id = ?'
    );
    $stmt->execute([$remark !== '' ? $remark : null, $allocationId, $itemId]);
    json_out(['ok' => true]);
}

if ($answer !== null && $answer !== '' && !in_array($answer, ['yes', 'no', 'na'], true)) {
    json_out(['ok' => false, 'message' => 'Invalid answer value.']);
}
$answerValue = ($answer === '' || $answer === null) ? null : $answer;

$stmt = $pdo->prepare(
    'UPDATE audit_answers
     SET answer = ?, answered_at = ?, remark = IF(? = "no", remark, NULL)
     WHERE allocation_id = ? AND checklist_item_id = ?'
);
$stmt->execute([
    $answerValue,
    $answerValue !== null ? date('Y-m-d H:i:s') : null,
    $answerValue,
    $allocationId,
    $itemId,
]);

recalc_allocation($allocationId);

$stmt = $pdo->prepare('SELECT status FROM allocations WHERE id = ?');
$stmt->execute([$allocationId]);
$status = $stmt->fetch()['status'];

json_out(['ok' => true, 'status' => $status, 'status_badge' => status_badge($status)]);
