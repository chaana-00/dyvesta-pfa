<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$pdo    = db();
$action = $_POST['action'] ?? '';

$code       = trim($_POST['code'] ?? '');
$shortName  = trim($_POST['short_name'] ?? '');
$auditItem  = trim($_POST['audit_item'] ?? '');
$category   = trim($_POST['category'] ?? '');
$answerType = ($_POST['answer_type'] ?? 'yes_no_na') === 'yes_no' ? 'yes_no' : 'yes_no_na';

if ($auditItem === '') {
    json_out(['ok' => false, 'message' => 'Audit Item is required.']);
}

if ($action === 'create') {
    try {
        $max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0) m FROM checklist_items')->fetch()['m'];
        $stmt = $pdo->prepare(
            'INSERT INTO checklist_items (code, short_name, audit_item, category, answer_type, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code), short_name = VALUES(short_name),
                                      category = VALUES(category), answer_type = VALUES(answer_type)'
        );
        $stmt->execute([$code ?: null, $shortName ?: $auditItem, $auditItem, $category ?: null, $answerType, $max + 1]);
        json_out(['ok' => true, 'message' => 'Checklist item saved.']);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'Could not save that checklist item.']);
    }
}

if ($action === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        json_out(['ok' => false, 'message' => 'Missing checklist item id.']);
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE checklist_items SET code = ?, short_name = ?, audit_item = ?, category = ?, answer_type = ? WHERE id = ?'
        );
        $stmt->execute([$code ?: null, $shortName ?: $auditItem, $auditItem, $category ?: null, $answerType, $id]);
        json_out(['ok' => true, 'message' => 'Checklist item updated.']);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'Could not update — that Audit Item text may already be used.']);
    }
}

json_out(['ok' => false, 'message' => 'Unknown action.']);
