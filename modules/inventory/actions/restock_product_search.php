<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/stock_alert_functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!ame_stock_alert_can_view($db)) {
    http_response_code(403);
    echo '[]';
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$q = str_replace(['%', '_'], '', $q);
if ($q === '' || mb_strlen($q) > 80) {
    echo '[]';
    exit;
}

$like = '%' . $q . '%';
$full = "SELECT p.id, p.name, IFNULL(p.sku,'') AS sku, IFNULL(p.oem_code,'') AS oem_code,
            IFNULL(p.supplier_code,'') AS supplier_code, p.stock, p.min_stock, p.purchase_price,
            IFNULL(u.unit_name,'') AS unit_name
         FROM products p
         LEFT JOIN product_units u ON p.unit_id = u.id
         WHERE " . ame_product_option_sql('p.status') . "
           AND (p.name LIKE ? OR IFNULL(p.sku,'') LIKE ? OR IFNULL(p.oem_code,'') LIKE ?
                OR IFNULL(p.supplier_code,'') LIKE ? OR CAST(p.id AS CHAR) = ?)
         ORDER BY p.name ASC
         LIMIT 8";
$stmt = $db->prepare($full);
$wide = $stmt instanceof mysqli_stmt;
if (!$wide) {
    $stmt = $db->prepare(
        "SELECT id, name, IFNULL(sku,'') AS sku, '' AS oem_code, '' AS supplier_code,
                stock, min_stock, purchase_price, '' AS unit_name
         FROM products
         WHERE " . ame_product_option_sql('status') . "
           AND (name LIKE ? OR IFNULL(sku,'') LIKE ? OR CAST(id AS CHAR) = ?)
         ORDER BY name ASC
         LIMIT 8"
    );
}
if (!$stmt) {
    echo '[]';
    exit;
}
if ($wide) {
    $stmt->bind_param('sssss', $like, $like, $like, $like, $q);
} else {
    $stmt->bind_param('sss', $like, $like, $q);
}
$stmt->execute();
$res = $stmt->get_result();
$out = [];
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $stock = (float)($row['stock'] ?? 0);
        $min = (float)($row['min_stock'] ?? 0);
        $qty = 1;
        if ($stock <= $min) {
            $qty = ame_stock_alert_suggest_qty($stock, $min);
        }
        $fixed = ame_stock_alert_fixed_order_qty(
            (string)($row['name'] ?? ''),
            (string)($row['sku'] ?? ''),
            (string)($row['oem_code'] ?? ''),
            (string)($row['supplier_code'] ?? '')
        );
        if ($fixed !== null) {
            $qty = $fixed;
        }
        $out[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'sku' => (string)($row['sku'] ?? ''),
            'unit' => (string)($row['unit_name'] ?? ''),
            'stock' => $stock,
            'price' => round((float)($row['purchase_price'] ?? 0), 2),
            'qty' => $qty,
        ];
    }
}
$stmt->close();

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
