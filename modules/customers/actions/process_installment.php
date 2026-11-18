<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$customer_id        = (int)($_POST['customer_id'] ?? 0);
$product_details    = trim($_POST['product_details'] ?? '');
$product_condition  = trim($_POST['product_condition'] ?? 'NEW');
$total_amount        = (float)($_POST['total_amount'] ?? 0);
$down_payment        = (float)($_POST['down_payment'] ?? 0);
$total_installments  = (int)($_POST['total_installments'] ?? 1);
$installment_type    = trim($_POST['installment_type'] ?? 'MONTHLY');

if ($customer_id <= 0 || $product_details === '' || $total_amount <= 0 || $total_installments < 1) {
    die('❌ প্রয়োজনীয় তথ্য দিন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}
if ($down_payment > $total_amount) {
    die('❌ ডাউন পেমেন্ট মোট মূল্যের চেয়ে বেশি হতে পারে না। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$chk = $db->prepare("SELECT id FROM customers WHERE id = ?");
$chk->bind_param("i", $customer_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    die('❌ কাস্টমার খুঁজে পাওয়া যায়নি।');
}

$remaining_balance = $total_amount - $down_payment;
$contract_no = generate_contract_no($db, 'installment_contracts', 'INST');

try {
    $stmt = $db->prepare("INSERT INTO installment_contracts 
        (contract_no, customer_id, product_details, product_condition, total_amount, down_payment, 
         total_installments, installment_type, remaining_balance, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')");
    $stmt->bind_param(
        "sissddisd",
        $contract_no, $customer_id, $product_details, $product_condition, $total_amount, $down_payment,
        $total_installments, $installment_type, $remaining_balance
    );
    $stmt->execute();

    // নিয়ম: কাস্টমার দোকান থেকে কিস্তিতে পণ্য নিচ্ছে (অবশিষ্ট বকেয়া) → debit (+)
    $desc = 'কিস্তি চুক্তি: ' . $product_details . ' (' . $contract_no . ')';
    add_ledger_entry($db, $customer_id, 'কিস্তি', $contract_no, $desc, $remaining_balance, 0);

    $db->query("UPDATE customers SET is_installment = 1 WHERE id = " . $customer_id);

    header('Location: ../profile.php?id=' . $customer_id . '&installment=1');
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('process_installment failed: ' . $e->getMessage()); die('❌ সংরক্ষণে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।');
}