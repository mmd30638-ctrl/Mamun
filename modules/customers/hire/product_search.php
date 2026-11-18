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
$status = function_exists('ame_product_option_sql') ? ame_product_option_sql('p.status') : '1=1';
$sql = "SELECT p.id, p.name, p.sku, p.barcode, p.stock, p.rental_qty,
               cat.name AS cat_name
        FROM products p
        LEFT JOIN categories cat ON cat.id = p.category_id
        WHERE " . $status . " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
        ORDER BY p.name ASC LIMIT 20";
$stmt = $db->prepare($sql);
if (!$stmt) {
    $sql = "SELECT p.id, p.name, p.sku, p.barcode, p.stock, 0 AS rental_qty,
                   cat.name AS cat_name
            FROM products p
            LEFT JOIN categories cat ON cat.id = p.category_id
            WHERE " . $status . " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
            ORDER BY p.name ASC LIMIT 20";
    $stmt = $db->prepare($sql);
}
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
        'name' => $row['name'],
        'sku' => $row['sku'] ?? '',
        'barcode' => $row['barcode'] ?? '',
        'serial' => '',
        'category' => $row['cat_name'] ?? '',
        'label' => $row['name'] . ' · ' . ($row['sku'] ?: '') . ' · stock ' . $row['stock'] . ' · rental ' . $row['rental_qty'],
    ];
}
$stmt->close();
echo json_encode($out, JSON_UNESCAPED_UNICODE);
