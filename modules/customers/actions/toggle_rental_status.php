<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$rental_id   = (int)($_POST['rental_id'] ?? 0);
$customer_id = (int)($_POST['customer_id'] ?? 0);
$new_status  = trim($_POST['new_status'] ?? '');

if ($rental_id <= 0 || $customer_id <= 0 || !in_array($new_status, ['ACTIVE', 'COMPLETED'])) {
    die('❌ অবৈধ অনুরোধ। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

try {
    $stmt = $db->prepare("UPDATE rental_contracts SET status = ? WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("sii", $new_status, $rental_id, $customer_id);
    $stmt->execute();

    header('Location: ../profile.php?id=' . $customer_id . '&status_updated=1');
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('toggle_rental_status failed: ' . $e->getMessage());
    die('❌ সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন, সমস্যা থাকলে এডমিনের সাথে যোগাযোগ করুন।');
}