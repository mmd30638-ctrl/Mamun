<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partner_accounting_v2.php';
require_permission($db, 'm-acc');

header('Content-Type: application/json; charset=UTF-8');

$type = preg_replace('/[^a-z]/', '', (string)($_GET['type'] ?? 'product'));
$q = trim((string)($_GET['q'] ?? ''));
$productId = max(0, (int)($_GET['product_id'] ?? 0));

if ($q === '' || mb_strlen($q, 'UTF-8') > 80) {
    echo '[]';
    exit;
}

try {
    if ($type === 'invoice') {
        $like = '%' . $q . '%';
        $saleId = ctype_digit($q) ? (int)$q : 0;
        $dateExpr = pav2_sale_date_expr($db);
        $sql = "SELECT s.id sale_id,
                       COALESCE(NULLIF(s.invoice_number,''), CONCAT('INV-', s.id)) invoice_no,
                       MAX({$dateExpr}) sale_date,
                       si.product_id,
                       MAX(p.name) product_name
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN products p ON p.id = si.product_id
                WHERE (s.status='completed' OR s.status IS NULL OR s.status='')
                  AND (s.invoice_number LIKE ? OR s.id = ?)
                  AND (? = 0 OR si.product_id = ?)
                GROUP BY s.id, si.product_id
                ORDER BY sale_date DESC, s.id DESC
                LIMIT 20";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($db->error);
        }
        $stmt->bind_param('siii', $like, $saleId, $productId, $productId);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $rows[] = [
                'id' => (int)$row['sale_id'],
                'product_id' => (int)$row['product_id'],
                'product_label' => (string)$row['product_name'],
                'label' => $row['invoice_no'] . ' · ' . $row['product_name'] . ' · ' . date('d-m-Y', strtotime((string)$row['sale_date'])),
            ];
        }
        $stmt->close();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $like = '%' . $q . '%';
    $idMatch = ctype_digit($q) ? (int)$q : 0;
    $stmt = $db->prepare(
        "SELECT id, name, sku
         FROM products
         WHERE " . ame_product_option_sql('status') . "
           AND (name LIKE ? OR sku LIKE ? OR id = ?)
         ORDER BY name ASC
         LIMIT 20"
    );
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    $stmt->bind_param('ssi', $like, $like, $idMatch);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $sku = trim((string)($row['sku'] ?? ''));
        $rows[] = [
            'id' => (int)$row['id'],
            'label' => (string)$row['name'] . ($sku !== '' ? ' · ' . $sku : ''),
        ];
    }
    $stmt->close();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('partner v2 search: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
