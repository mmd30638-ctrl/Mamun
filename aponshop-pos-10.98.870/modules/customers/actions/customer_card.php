<?php
define('AME_READ_ONLY_REQUEST', true);
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'লগইন করতে হবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

// এই endpoint শুধু read করে। দীর্ঘ query চলার সময় session lock ধরে রাখলে একই
// ব্যবহারকারীর বাকি সব request অপেক্ষায় থাকে এবং মোবাইলে পুরো সাইট hang মনে হয়।
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ensure_customer_tables($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'ক্রেতার আইডি পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$c) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'এই ক্রেতা খুঁজে পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stats = ['qty' => 0, 'invoices' => 0, 'amount' => 0];
$statsStmt = @$db->prepare(
    "SELECT COUNT(*) AS invoices,
            COALESCE(SUM(s.net_payable), 0) AS amount,
            COALESCE((
                SELECT SUM(si.qty)
                FROM sale_items si
                INNER JOIN sales sx ON sx.id = si.sale_id
                WHERE sx.customer_id = ? AND sx.status = 'completed'
            ), 0) AS qty
     FROM sales s
     WHERE s.customer_id = ? AND s.status = 'completed'"
);
if ($statsStmt) {
    $statsStmt->bind_param('ii', $id, $id);
    if ($statsStmt->execute()) {
        $statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
        $stats = [
            'qty' => (float)($statsRow['qty'] ?? 0),
            'invoices' => (int)($statsRow['invoices'] ?? 0),
            'amount' => (float)($statsRow['amount'] ?? 0),
        ];
    }
    $statsStmt->close();
}

$photo = ame_customer_photo_url($c['photo'] ?? '');
$letter = mb_substr((string)($c['name'] ?? '?'), 0, 1, 'UTF-8');

$last_product = '';
$lp = @$db->prepare("SELECT COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name
    FROM sales s
    INNER JOIN sale_items si ON si.sale_id = s.id
    LEFT JOIN products p ON p.id = si.product_id
    WHERE s.customer_id = ?
    ORDER BY s.id DESC, si.id DESC
    LIMIT 1");
if ($lp) {
    $lp->bind_param('i', $id);
    if ($lp->execute()) {
        $lprow = $lp->get_result()->fetch_assoc();
        $last_product = trim((string)($lprow['item_name'] ?? ''));
    }
    $lp->close();
}

echo json_encode([
    'ok' => true,
    'customer' => [
        'id' => (int)$c['id'],
        'code' => (string)($c['customer_code'] ?? ''),
        'name' => (string)($c['name'] ?? ''),
        'photo' => $photo,
        'letter' => $letter,
        'phone' => (string)($c['phone'] ?? ''),
        'alt_phone' => (string)($c['alt_phone'] ?? ''),
        'guarantor_name' => (string)($c['guarantor_name'] ?? ''),
        'guarantor_phone' => (string)($c['guarantor_phone'] ?? ''),
        'nid' => (string)($c['nid'] ?? ''),
        'father_name' => (string)($c['father_name'] ?? ''),
        'address' => (string)($c['address'] ?? ''),
        'blood_group' => (string)($c['blood_group'] ?? ''),
        'group_name' => ame_customer_lookup_name($db, 'customer_groups', (int)($c['group_id'] ?? 0)),
        'area_name' => ame_customer_lookup_name($db, 'customer_areas', (int)($c['area_id'] ?? 0)),
        'notes' => (string)($c['additional_notes'] ?? $c['notes'] ?? ''),
        'due_note' => (string)($c['due_note'] ?? ''),
        'next_due_date' => (string)($c['next_due_date'] ?? ''),
        'due_watch' => !empty($c['due_watch']),
        'due_watch_on' => (string)($c['due_watch_on'] ?? ''),
        'due_watch_fire' => (string)(ame_due_watch_fire_date($c['due_watch_on'] ?? null) ?? ''),
        'next_due_amount' => (isset($c['next_due_amount']) && $c['next_due_amount'] !== null && $c['next_due_amount'] !== '') ? (float)$c['next_due_amount'] : null,
        'receivable' => (float)($c['receivable_balance'] ?? 0),
        'credit_limit' => (float)($c['credit_limit'] ?? 0),
        'is_customer' => !empty($c['is_customer']),
        'is_supplier' => !empty($c['is_supplier']),
        'is_installment' => !empty($c['is_installment']),
        'is_rental' => !empty($c['is_rental']),
        'is_star' => $stats['amount'] >= 100000,
        'qty' => $stats['qty'],
        'invoices' => $stats['invoices'],
        'amount' => $stats['amount'],
        'pdf_file' => (string)($c['pdf_file'] ?? ''),
        'pdf_url' => function_exists('ame_customer_photo_url') ? ame_customer_photo_url($c['pdf_file'] ?? '') : '',
        'shop' => ame_customer_lookup_name($db, 'customer_groups', (int)($c['group_id'] ?? 0)),
        'occupation' => (string)($c['profession'] ?? $c['occupation'] ?? ''),
        'last_product' => $last_product,
    ],
], JSON_UNESCAPED_UNICODE);
