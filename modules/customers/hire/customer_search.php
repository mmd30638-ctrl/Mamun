<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
header('Content-Type: application/json; charset=UTF-8');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    echo '[]';
    exit;
}
$like = '%' . $q . '%';
$pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
$stmt = $db->prepare(
    "SELECT id, name, customer_code, phone FROM customers
     WHERE is_customer = 1 AND {$pick} AND (name LIKE ? OR phone LIKE ? OR customer_code LIKE ?)
     ORDER BY name ASC LIMIT 20"
);
if (!$stmt) {
    echo '[]';
    exit;
}
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$out = [];
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) {
    $out[] = [
        'id' => (int)$row['id'],
        'label' => $row['name'] . ' · ' . $row['customer_code'] . ' · ' . ($row['phone'] ?? ''),
    ];
}
$stmt->close();
echo json_encode($out, JSON_UNESCAPED_UNICODE);
