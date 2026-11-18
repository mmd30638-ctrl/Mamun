<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=120');

try {
    ensure_customer_tables($db);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'customers' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = $db->query("SELECT id, customer_code, name, phone, receivable_balance, next_due_date FROM customers WHERE is_customer = 1 ORDER BY name ASC LIMIT 500");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$customers = array_map(static function ($c) {
    return [
        'id' => (int)$c['id'],
        'name' => $c['name'],
        'code' => $c['customer_code'],
        'phone' => $c['phone'] ?? '',
        'balance' => (float)$c['receivable_balance'],
        'due' => $c['next_due_date'] ?? '',
    ];
}, $rows);

echo json_encode([
    'status' => 'success',
    'customers' => $customers,
], JSON_UNESCAPED_UNICODE);
