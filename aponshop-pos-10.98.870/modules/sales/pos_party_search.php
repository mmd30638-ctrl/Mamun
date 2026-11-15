<?php
/**
 * বিক্রয় ডেস্কের ক্রেতা/সাপ্লায়ার সার্চ। পুরো তালিকা পেজে বসায় না।
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/supplier_functions.php';
require_once __DIR__ . '/../../includes/sales/sale_common.php';
if (is_file(__DIR__ . '/../customers/customer_schema.php')) {
    require_once __DIR__ . '/../customers/customer_schema.php';
    if (isset($db) && $db instanceof mysqli) {
        ensure_customer_tables($db);
    }
}
if (is_file(__DIR__ . '/../../includes/party_purge.php')) {
    require_once __DIR__ . '/../../includes/party_purge.php';
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!isset($db) || !($db instanceof mysqli)) {
    echo '[]';
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 80);
} else {
    $q = substr($q, 0, 80);
}
$filter = (string)($_GET['filter'] ?? '');
if (!in_array($filter, ['customer', 'supplier', ''], true)) {
    $filter = '';
}
$showAll = (string)($_GET['all'] ?? '') === '1';
if ($q === '' && !$showAll) {
    echo '[]';
    exit;
}

$live = '1=1';
if (function_exists('ame_supplier_live_sql')) {
    $live = ame_supplier_live_sql($db, 's');
}

$where = [
    '(c.is_customer = 1 OR c.is_customer IS NULL OR c.is_supplier = 1)',
    '(c.is_customer = 1 OR c.is_customer IS NULL OR c.linked_supplier_id IS NULL OR c.linked_supplier_id = 0 OR ' . $live . ')',
];
if (function_exists('ame_customer_live_sql')) {
    $where[] = ame_customer_live_sql($db, 'c');
}
if (function_exists('ame_customer_active_sql')) {
    $where[] = ame_customer_active_sql('c');
}
if ($filter === 'supplier') {
    $where[] = 'c.is_supplier = 1';
} elseif ($filter === 'customer') {
    $where[] = '(c.is_customer = 1 OR c.is_customer IS NULL OR c.is_supplier = 1)';
}

$types = '';
$params = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $digits = preg_replace('/\D+/', '', $q);
    $tail = is_string($digits) && strlen($digits) >= 6 ? ('%' . substr($digits, -10) . '%') : $like;
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone, \'\') LIKE ? OR IFNULL(c.customer_code, \'\') LIKE ? OR CAST(c.id AS CHAR) LIKE ? OR IFNULL(c.address, \'\') LIKE ? OR IFNULL(c.nid, \'\') LIKE ? OR REPLACE(REPLACE(REPLACE(IFNULL(c.phone, \'\'), \' \', \'\'), \'-\', \'\'), \'+\', \'\') LIKE ?)';
    $types = 'ssssssss';
    $params = [$like, $like, $like, $like, $like, $like, $like, $tail];
}

$sql = 'SELECT c.id, c.name, c.phone, c.alt_phone, c.customer_code, c.receivable_balance, c.payable_balance, c.is_supplier, c.is_customer, c.credit_limit, c.always_wholesale
        FROM customers c
        LEFT JOIN suppliers s ON s.id = c.linked_supplier_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY c.name ASC
        LIMIT 40';

$stmt = @$db->prepare($sql);
if (!$stmt && $q !== '') {
    $where[count($where) - 1] = '(c.name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR CAST(c.id AS CHAR) LIKE ?)';
    $types = 'ssss';
    $params = array_slice($params, 0, 4);
    $sql = 'SELECT c.id, c.name, c.phone, c.customer_code, c.receivable_balance, c.payable_balance, c.is_supplier, c.is_customer, c.credit_limit, c.always_wholesale
            FROM customers c
            LEFT JOIN suppliers s ON s.id = c.linked_supplier_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY c.name ASC
            LIMIT 40';
    $stmt = @$db->prepare($sql);
}
if (!$stmt) {
    $why = 'POS party search prepare failed: ' . (string)$db->error;
    error_log($why);
    if (function_exists('ame_error_write_event')) {
        ame_error_write_event('POS', $why, __FILE__, __LINE__);
    }
    echo '[]';
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    $why = 'POS party search execute failed: ' . (string)$stmt->error;
    error_log($why);
    if (function_exists('ame_error_write_event')) {
        ame_error_write_event('POS', $why, __FILE__, __LINE__);
    }
    $stmt->close();
    echo '[]';
    exit;
}
$res = $stmt->get_result();
$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $isSupplier = !empty($row['is_supplier']);
        $isCustomer = !isset($row['is_customer']) || (string)$row['is_customer'] === '' || (int)$row['is_customer'] === 1;
        $balance = function_exists('ame_resolve_party_sale_balance')
            ? ame_resolve_party_sale_balance($row, null)
            : round((float)($row['receivable_balance'] ?? 0) - max(0.0, (float)($row['payable_balance'] ?? 0)), 2);
        $name = (string)($row['name'] ?? '');
        $out[] = [
            'id' => (int)$row['id'],
            'name' => $isSupplier ? ($name . ' [সাপ্লায়ার]') : $name,
            'rawName' => $name,
            'phone' => (string)($row['phone'] ?? ''),
            'alt' => (string)($row['alt_phone'] ?? ''),
            'code' => (string)($row['customer_code'] ?? $row['id']),
            'partyCode' => '',
            'isSupplier' => $isSupplier,
            'isCustomer' => $isCustomer,
            'due' => number_format($balance, 2, '.', ''),
            'credit' => number_format((float)($row['credit_limit'] ?? 0), 2, '.', ''),
            'alwaysWholesale' => !empty($row['always_wholesale']),
        ];
    }
}
$stmt->close();

echo json_encode($out, JSON_UNESCAPED_UNICODE);
