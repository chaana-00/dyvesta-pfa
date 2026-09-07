<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/XLSX.php';
require_admin();

if (empty($_FILES['file']['tmp_name'])) {
    redirect('../master_data.php?tab=checklist&import=nofile');
}

$rows = XLSXReader::read($_FILES['file']['tmp_name']);

if (count($rows) < 2) {
    redirect('../master_data.php?tab=checklist&import=empty');
}

$header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
$map = [];
foreach ($header as $i => $col) {
    if ($col === 'code') $map['code'] = $i;
    elseif (str_contains($col, 'short name')) $map['short_name'] = $i;
    elseif (str_contains($col, 'audit item')) $map['audit_item'] = $i;
    elseif (str_contains($col, 'category')) $map['category'] = $i;
    elseif (str_contains($col, 'answer type')) $map['answer_type'] = $i;
}

if (!isset($map['audit_item'])) {
    redirect('../master_data.php?tab=checklist&import=badcolumns');
}

$pdo = db();
$pdo->beginTransaction();

$max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0) m FROM checklist_items')->fetch()['m'];

$stmt = $pdo->prepare(
    'INSERT INTO checklist_items (code, short_name, audit_item, category, answer_type, sort_order)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE code = VALUES(code), short_name = VALUES(short_name),
                              category = VALUES(category), answer_type = VALUES(answer_type)'
);

$imported = 0;
for ($i = 1; $i < count($rows); $i++) {
    $r = $rows[$i];
    $auditItem = trim((string) ($r[$map['audit_item']] ?? ''));
    if ($auditItem === '') {
        continue;
    }
    $code       = trim((string) ($r[$map['code']] ?? ''));
    $shortName  = trim((string) ($r[$map['short_name']] ?? '')) ?: $auditItem;
    $category   = trim((string) ($r[$map['category']] ?? ''));
    $answerTypeRaw = strtolower(trim((string) ($r[$map['answer_type']] ?? '')));
    $answerType = (str_contains($answerTypeRaw, 'n/a') || $answerTypeRaw === '') ? 'yes_no_na' : 'yes_no';

    $max++;
    $stmt->execute([$code ?: null, $shortName, $auditItem, $category ?: null, $answerType, $max]);
    $imported++;
}

$pdo->commit();

redirect('../master_data.php?tab=checklist&import=ok&count=' . $imported);
