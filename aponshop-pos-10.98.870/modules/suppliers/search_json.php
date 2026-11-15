<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
ame_supplier_schema_boot($db);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    echo '[]';
    exit;
}

$like = '%' . $q . '%';
$start = $q . '%';
$digits = preg_replace('/\D+/', '', $q);
$digitLike = $digits !== '' ? '%' . $digits . '%' : $like;

$live = ame_supplier_live_sql($db);
$sql = "SELECT id, name, phone, alt_phone, business_name, party_code
    FROM suppliers
    WHERE ($live) AND (
        name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR business_name LIKE ?
       OR REPLACE(REPLACE(REPLACE(IFNULL(phone,''),' ',''),'-',''),'+','') LIKE ?
       OR REPLACE(REPLACE(REPLACE(IFNULL(alt_phone,''),' ',''),'-',''),'+','') LIKE ?
    )
    ORDER BY
        (name LIKE ?) DESC,
        (phone LIKE ?) DESC,
        name ASC
    LIMIT 12";

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo '[]';
    exit;
}
$stmt->bind_param(
    'ssssssss',
    $like,
    $like,
    $like,
    $like,
    $digitLike,
    $digitLike,
    $start,
    $start
);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$out = [];
foreach ($rows as $row) {
    $out[] = [
        'id' => (int)$row['id'],
        'name' => (string)($row['name'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'alt' => (string)($row['alt_phone'] ?? ''),
        'biz' => (string)($row['business_name'] ?? ''),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
