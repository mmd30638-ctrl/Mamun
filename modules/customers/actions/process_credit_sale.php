<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$customer_id     = (int)($_POST['customer_id'] ?? 0);
$product_details = trim($_POST['product_details'] ?? '');
$amount          = (float)($_POST['amount'] ?? 0);

if ($customer_id <= 0 || $product_details === '' || $amount <= 0) {
    die('❌ পণ্যের বিবরণ ও সঠিক পরিমাণ আবশ্যক। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$chk = $db->prepare("SELECT id FROM customers WHERE id = ?");
$chk->bind_param("i", $customer_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    die('❌ কাস্টমার খুঁজে পাওয়া যায়নি।');
}

try {
    // নিয়ম: কাস্টমার দোকান থেকে পণ্য নিচ্ছে (বাকিতে) → debit (+)
    $ref = 'SALE-' . time();
    add_ledger_entry($db, $customer_id, 'বাকি বিক্রি', $ref, $product_details, $amount, 0);
    header('Location: ../profile.php?id=' . $customer_id . '&charge=1');
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('process_credit_sale failed: ' . $e->getMessage()); die('❌ সংরক্ষণে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।');
}