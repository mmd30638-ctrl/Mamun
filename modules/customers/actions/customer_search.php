<?php
/* 10.98.691 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'customers' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ensure_customer_tables($db);
    ame_ensure_due_watch_columns($db);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'customers' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$limit = 40;

$photo_fn = function_exists('ame_customer_photo_url') ? 'ame_customer_photo_url' : null;

$map_rows = static function (array $rows) use ($db, $photo_fn): array {
    if (!$rows) {
        return [];
    }
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['id'];
    }
    $paid_map = [];
    $id_list = implode(',', $ids);
    $paid_res = @$db->query(
        "SELECT customer_id, COALESCE(SUM(amount), 0) AS paid
         FROM customer_payments
         WHERE customer_id IN ($id_list)
         GROUP BY customer_id"
    );
    if ($paid_res) {
        while ($paid = $paid_res->fetch_assoc()) {
            $paid_map[(int)$paid['customer_id']] = (float)$paid['paid'];
        }
    }
    $out = [];
    foreach ($rows as $c) {
        $cid = (int)$c['id'];
        $out[] = [
            'id' => $cid,
            'name' => (string)($c['name'] ?? ''),
            'code' => (string)($c['customer_code'] ?? ''),
            'phone' => (string)($c['phone'] ?? ''),
            'address' => (string)($c['address'] ?? ''),
            'group' => (string)($c['group_name'] ?? ''),
            'area' => (string)($c['area_name'] ?? ''),
            'photo' => $photo_fn ? $photo_fn($c['photo'] ?? '') : '',
            'balance' => (float)($c['receivable_balance'] ?? 0),
            'paid' => $paid_map[$cid] ?? 0.0,
            'due' => (string)($c['next_due_date'] ?? ''),
            'due_watch' => !empty($c['due_watch']),
            'due_watch_on' => (string)($c['due_watch_on'] ?? ''),
            'notify_on' => function_exists('ame_customer_due_notify_on')
                ? ame_customer_due_notify_on($c)
                : (!empty($c['due_watch']) || trim((string)($c['next_due_date'] ?? '')) !== ''),
        ];
    }
    return $out;
};

$pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';

if ($id > 0) {
    $stmt = $db->prepare(
        "SELECT id, customer_code, name, phone, address, photo, receivable_balance, next_due_date, due_watch, due_watch_on
         FROM customers
         WHERE id = ? AND is_customer = 1 AND {$pick}
         LIMIT 1"
    );
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'customers' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode([
        'status' => 'success',
        'customers' => $row ? $map_rows([$row]) : [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($q === '' || mb_strlen($q) < 1) {
    echo json_encode(['status' => 'success', 'customers' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$ascii = strtr($q, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
$like = '%' . $q . '%';
$like_a = '%' . $ascii . '%';
$pref = $q . '%';
$pref_a = $ascii . '%';

$cols = [];
$cr = @$db->query('SHOW COLUMNS FROM customers');
if ($cr) {
    while ($x = $cr->fetch_assoc()) {
        $cols[(string)$x['Field']] = true;
    }
}
$parts = ['name LIKE ?', "IFNULL(customer_code,'') LIKE ?", "IFNULL(phone,'') LIKE ?"];
$types = 'sss';
$bind = [$like, $like_a, $like_a];
if (isset($cols['alt_phone'])) {
    $parts[] = "IFNULL(alt_phone,'') LIKE ?";
    $types .= 's';
    $bind[] = $like_a;
}
if (isset($cols['address'])) {
    $parts[] = "IFNULL(address,'') LIKE ?";
    $types .= 's';
    $bind[] = $like;
}
if (isset($cols['group_id'])) {
    $parts[] = 'EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = customers.group_id AND cg.name LIKE ?)';
    $types .= 's';
    $bind[] = $like;
}
if (isset($cols['area_id'])) {
    $parts[] = 'EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = customers.area_id AND ca.name LIKE ?)';
    $types .= 's';
    $bind[] = $like;
}
if (ctype_digit($ascii)) {
    $parts[] = 'id = ?';
    $types .= 'i';
    $bind[] = (int)$ascii;
}
$types .= 'ssssss';
array_push($bind, $q, $ascii, $ascii, $pref, $pref_a, $pref_a);

$stmt = $db->prepare(
    "SELECT id, customer_code, name, phone, address, photo, receivable_balance, next_due_date, due_watch, due_watch_on"
    . (isset($cols['group_id']) ? ", (SELECT cg2.name FROM customer_groups cg2 WHERE cg2.id = customers.group_id LIMIT 1) AS group_name" : ", '' AS group_name")
    . (isset($cols['area_id']) ? ", (SELECT ca2.name FROM customer_areas ca2 WHERE ca2.id = customers.area_id LIMIT 1) AS area_name" : ", '' AS area_name")
    . " FROM customers
     WHERE is_customer = 1 AND {$pick} AND (" . implode(' OR ', $parts) . ")
     ORDER BY CASE
         WHEN name = ? OR IFNULL(customer_code,'') = ? OR IFNULL(phone,'') = ? THEN 0
         WHEN name LIKE ? OR IFNULL(customer_code,'') LIKE ? OR IFNULL(phone,'') LIKE ? THEN 1
         ELSE 2 END ASC, name ASC
     LIMIT {$limit}"
);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'customers' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param($types, ...$bind);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'status' => 'success',
    'customers' => $map_rows($rows),
], JSON_UNESCAPED_UNICODE);
