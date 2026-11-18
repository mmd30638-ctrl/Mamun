<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
header('Content-Type: application/json; charset=UTF-8');
$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    echo '[]';
    exit;
}
$like = '%' . $q . '%';
$stmt = $db->prepare(
    'SELECT id, name, phone, vehicle_no, vehicle_name, pay_amount, pay_cycle, entry_date
     FROM rental_owners
     WHERE status = 1 AND (name LIKE ? OR phone LIKE ? OR vehicle_no LIKE ?)
     ORDER BY name ASC LIMIT 20'
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
    $name = (string) ($row['name'] ?? '');
    $phone = (string) ($row['phone'] ?? '');
    $plate = (string) ($row['vehicle_no'] ?? '');
    $out[] = [
        'id' => (int) $row['id'],
        'name' => $name,
        'phone' => $phone,
        'vehicle_no' => $plate,
        'entry_date' => (string) ($row['entry_date'] ?? ''),
        'label' => trim($name . ' · ' . $phone . ($plate !== '' ? ' · ' . $plate : ''), ' ·'),
    ];
}
$stmt->close();
echo json_encode($out, JSON_UNESCAPED_UNICODE);
