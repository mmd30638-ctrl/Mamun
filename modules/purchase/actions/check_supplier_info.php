<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/purchase_functions.php';
admin_only();
ensure_purchase_schema($db);

header('Content-Type: application/json; charset=UTF-8');

$supplier_id = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
$customer_id = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
$memo_no = trim($_GET['memo_no'] ?? '');

$response = ['is_duplicate' => false, 'recent_purchases' => []];

// ================= লাইভ ডুপ্লিকেট চেক =================
if ($memo_no !== '') {
    $response['is_duplicate'] = purchase_memo_exists($db, $supplier_id, $memo_no);
}

// ================= এই সাপ্লায়ারের সাম্প্রতিক ক্রয় (মেমো-ভিত্তিক গ্রুপ করে) =================
if ($supplier_id) {
    $stmt = $db->prepare(
        "SELECT memo_no, MIN(created_at) as created_at, SUM(total_cost) as total
         FROM purchases WHERE supplier_id = ? AND memo_no IS NOT NULL
         GROUP BY memo_no ORDER BY MIN(created_at) DESC LIMIT 5"
    );
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
        $response['recent_purchases'][] = [
            'memo_no' => $r['memo_no'],
            'date' => date('d-m-Y', strtotime($r['created_at'])),
            'amount' => (float)$r['total'],
        ];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
