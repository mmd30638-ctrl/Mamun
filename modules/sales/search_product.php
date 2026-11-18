<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/workshop_functions.php';

header('Content-Type: application/json; charset=UTF-8');

$search = trim((string)($_GET['q'] ?? ''));
$is_code = $search !== '' && ctype_digit($search);

if ($search === '' || (strlen($search) < 2 && !$is_code)) {
    echo json_encode(
        [],
        JSON_UNESCAPED_UNICODE
    );
    exit();
}

try {
    $like = '%' . $search . '%';
    $flagCache = $_SESSION['ame_pos_search_flags'] ?? null;
    if (!is_array($flagCache) || (int)($flagCache['at'] ?? 0) < time() - 3600) {
        $flagCache = [
            'at' => time(),
            'barcode' => false,
            'oem' => false,
            'supplier' => false,
            'unit' => false,
            'old' => false,
            'exchange' => false,
        ];
        $flagSql = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND (
                    (TABLE_NAME = 'products' AND COLUMN_NAME IN ('barcode','oem_code','supplier_code','unit_id','is_old_battery'))
                 OR (TABLE_NAME = 'product_units' AND COLUMN_NAME = 'unit_name')
                 OR (TABLE_NAME = 'categories' AND COLUMN_NAME = 'is_exchange')
              )";
        $flagRows = @$db->query($flagSql);
        if ($flagRows instanceof mysqli_result && $flagRows->num_rows > 0) {
            while ($flagRow = $flagRows->fetch_assoc()) {
                $table = (string)($flagRow['TABLE_NAME'] ?? '');
                $column = (string)($flagRow['COLUMN_NAME'] ?? '');
                if ($table === 'products' && $column === 'barcode') {
                    $flagCache['barcode'] = true;
                } elseif ($table === 'products' && $column === 'oem_code') {
                    $flagCache['oem'] = true;
                } elseif ($table === 'products' && $column === 'supplier_code') {
                    $flagCache['supplier'] = true;
                } elseif ($table === 'products' && $column === 'unit_id') {
                    $flagCache['unit_col'] = true;
                } elseif ($table === 'products' && $column === 'is_old_battery') {
                    $flagCache['old'] = true;
                } elseif ($table === 'product_units' && $column === 'unit_name') {
                    $flagCache['unit_table'] = true;
                } elseif ($table === 'categories' && $column === 'is_exchange') {
                    $flagCache['exchange'] = true;
                }
            }
            $flagRows->free();
        } else {
            $showMap = [
                'barcode' => "SHOW COLUMNS FROM products LIKE 'barcode'",
                'oem' => "SHOW COLUMNS FROM products LIKE 'oem_code'",
                'supplier' => "SHOW COLUMNS FROM products LIKE 'supplier_code'",
                'unit_col' => "SHOW COLUMNS FROM products LIKE 'unit_id'",
                'old' => "SHOW COLUMNS FROM products LIKE 'is_old_battery'",
                'unit_table' => "SHOW TABLES LIKE 'product_units'",
                'exchange' => "SHOW COLUMNS FROM categories LIKE 'is_exchange'",
            ];
            foreach ($showMap as $flagKey => $showSql) {
                $show = @$db->query($showSql);
                $flagCache[$flagKey] = $show instanceof mysqli_result && $show->num_rows > 0;
            }
        }
        $flagCache['unit'] = !empty($flagCache['unit_col']) && !empty($flagCache['unit_table']);
        $_SESSION['ame_pos_search_flags'] = $flagCache;
    }
    $has_barcode = !empty($flagCache['barcode']);

    $where = ['p.name LIKE ?', 'p.sku LIKE ?'];
    $types = 'ss';
    $params = [$like, $like];

    if (!empty($flagCache['oem'])) {
        $where[] = 'p.oem_code LIKE ?';
        $where[] = 'p.oem_code = ?';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $search;
    }
    if (!empty($flagCache['supplier'])) {
        $where[] = 'p.supplier_code LIKE ?';
        $where[] = 'p.supplier_code = ?';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $search;
    }

    if ($has_barcode) {
        $where[] = 'p.barcode LIKE ?';
        $where[] = 'p.barcode = ?';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $search;
    }

    if ($is_code) {
        $where[] = 'p.id = ?';
        $types .= 'i';
        $params[] = (int)$search;
    }

    $unit_select = "'' AS unit_name";
    $unit_join = '';
    if (!empty($flagCache['unit'])) {
        $unit_select = 'u.unit_name';
        $unit_join = ' LEFT JOIN product_units u ON u.id = p.unit_id ';
    }

    $store_id = function_exists('current_store_id') ? current_store_id() : 0;
    $stock_select = $store_id > 0
        ? 'COALESCE(ss.qty, p.stock) AS stock'
        : 'p.stock';
    $stock_join = $store_id > 0
        ? ' LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ? '
        : '';

    $old_select = ', 0 AS is_old_battery';
    $ex_join = '';
    $use_ex_cat = !empty($flagCache['exchange']);
    if ($use_ex_cat) {
        $ex_join = ' LEFT JOIN categories cat ON cat.id = p.category_id ';
        if (!empty($flagCache['old'])) {
            $old_select = ', IF(p.is_old_battery = 1 OR cat.is_exchange = 1, 1, 0) AS is_old_battery';
        } else {
            $old_select = ', IF(cat.is_exchange = 1, 1, 0) AS is_old_battery';
        }
    } elseif (!empty($flagCache['old'])) {
        $old_select = ', p.is_old_battery';
    }
    $want_exchange = (string)($_GET['exchange'] ?? '') === '1';

    $sql = "SELECT
            p.id,
            p.sku,
            p.name,
            p.sale_price,
            p.wholesale_price,
            p.dealer_price,
            p.purchase_price,
            $stock_select,
            $unit_select
            $old_select
         FROM products p
         $unit_join
         $stock_join
         $ex_join
         WHERE (
            " . implode("\n            OR ", $where) . "
         )
         AND " . ame_product_option_sql('p.status') . "
         ORDER BY
            CASE
                WHEN p.sku = ? THEN 0
                WHEN CAST(p.id AS CHAR) = ? THEN 1
                ELSE 2
            END,
            p.name ASC
         LIMIT 40";
    if ($want_exchange && !empty($flagCache['old'])) {
        $sql = str_replace(
            'ORDER BY',
            'ORDER BY CASE WHEN p.is_old_battery = 1 THEN 0 ELSE 1 END,',
            $sql
        );
    }

    if ($store_id > 0) {
        $types = 'i' . $types;
        array_unshift($params, $store_id);
    }

    $types .= 'ss';
    $params[] = $search;
    $params[] = $search;

    $stmt = $db->prepare($sql);
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $i => $value) {
        $bind[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    $store_for_reserve = function_exists('current_store_id') ? (int)current_store_id() : 0;
    $reserved_map = [];
    try {
        if (function_exists('ame_workshop_reserved_map')) {
            $reserved_map = ame_workshop_reserved_map($db, $store_for_reserve);
        }
    } catch (Throwable $reserveError) {
        error_log('POS reserved map skipped: ' . $reserveError->getMessage());
        $reserved_map = [];
    }

    while ($product = $result->fetch_assoc()) {
        $on_hand = (float)$product['stock'];
        $reserved = (float)($reserved_map[(int)$product['id']] ?? 0);
        $products[] = [
            'id' => (int)$product['id'],
            'sku' => (string)($product['sku'] ?? ''),
            'name' => (string)$product['name'],
            'sale_price' => (float)$product['sale_price'],
            'wholesale_price' => (float)$product['wholesale_price'],
            'dealer_price' => (float)$product['dealer_price'],
            'purchase_price' => (float)($product['purchase_price'] ?? 0),
            'is_old_battery' => (int)($product['is_old_battery'] ?? 0) === 1,
            'stock' => ame_stock_sellable($on_hand, $reserved),
            'unit' => (string)($product['unit_name'] ?? '')
        ];
    }

    $stmt->close();

    echo json_encode(
        $products,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $error) {
    error_log(
        'POS product search failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Product search failed.'
    ]);
}
