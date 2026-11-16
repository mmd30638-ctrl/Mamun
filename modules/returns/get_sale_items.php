<?php
require_once '../../includes/config.php';
require_once '../../includes/return_functions.php';

header('Content-Type: application/json; charset=UTF-8');

ensure_return_schema($db);

$sale_id = (int)($_GET['sale_id'] ?? 0);

if ($sale_id <= 0) {
    echo json_encode([
        'success' => false,
        'items' => []
    ], JSON_UNESCAPED_UNICODE);

    exit();
}

try {
    $stmt = $db->prepare(
        "SELECT
            si.product_id,
            p.name,
            SUM(si.qty) AS sold_quantity,
            COALESCE(sr.returned_quantity, 0) AS returned_quantity,
            SUM(si.qty) - COALESCE(sr.returned_quantity, 0)
                AS remaining_quantity
         FROM sale_items si
         INNER JOIN products p
            ON p.id = si.product_id
         LEFT JOIN (
            SELECT
                sale_id,
                product_id,
                SUM(qty) AS returned_quantity
            FROM sale_returns
            WHERE return_type = 'with_invoice'
            GROUP BY sale_id, product_id
         ) sr
            ON sr.sale_id = si.sale_id
           AND sr.product_id = si.product_id
         WHERE si.sale_id = ?
         GROUP BY
            si.product_id,
            p.name,
            sr.returned_quantity
         HAVING remaining_quantity > 0
         ORDER BY p.name ASC"
    );

    $stmt->bind_param('i', $sale_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $remaining = (float)$row['remaining_quantity'];

        $items[] = [
            'product_id' => (int)$row['product_id'],
            'name' => $row['name'],
            'sold_quantity' => (float)$row['sold_quantity'],
            'returned_quantity' => (float)$row['returned_quantity'],
            'remaining_quantity' => $remaining,

            /* পুরোনো JavaScript-এর সামঞ্জস্য */
            'qty' => $remaining
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log(
        'Sale return items load failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'items' => []
    ], JSON_UNESCAPED_UNICODE);
}