<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
csrf_verify();
ensure_loan_tables($db);

function app_fail($msg) {
    header("Location: applications_add.php?error=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { app_fail('অবৈধ অনুরোধ'); }

$customer_id = (int)($_POST['customer_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$requested_amount = (float)($_POST['requested_amount'] ?? 0);
$requested_term_months = (int)($_POST['requested_term_months'] ?? 0);
$purpose = clean_input($_POST['purpose'] ?? '');
$guarantor_name = clean_input($_POST['guarantor_name'] ?? '');
$guarantor_phone = clean_input($_POST['guarantor_phone'] ?? '');

if ($customer_id <= 0 || $product_id <= 0 || $requested_amount <= 0 || $requested_term_months <= 0) {
    app_fail('সব আবশ্যক তথ্য সঠিকভাবে পূরণ করুন।');
}

// প্রোডাক্টের সীমার মধ্যে আছে কিনা যাচাই — শুধু ক্লায়েন্ট-সাইড না, সার্ভারেও
$stmt = $db->prepare("SELECT * FROM loan_products WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    app_fail('বাছাইকৃত লোন প্রোডাক্টটি খুঁজে পাওয়া যায়নি বা নিষ্ক্রিয়।');
}

if ($requested_amount < (float)$product['min_amount'] || ($product['max_amount'] > 0 && $requested_amount > (float)$product['max_amount'])) {
    app_fail('চাহিদাকৃত পরিমাণ এই প্রোডাক্টের অনুমোদিত সীমার মধ্যে নেই (' . number_format((float)$product['min_amount']) . ' – ' . number_format((float)$product['max_amount']) . ' ৳)।');
}

$cust_stmt = $db->prepare("SELECT id FROM customers WHERE id = ?");
$cust_stmt->bind_param("i", $customer_id);
$cust_stmt->execute();
if ($cust_stmt->get_result()->num_rows === 0) {
    app_fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
}

$applied_by = $_SESSION['user_id'] ?? null;

$ins = $db->prepare("INSERT INTO loan_applications
    (customer_id, product_id, requested_amount, requested_term_months, purpose, guarantor_name, guarantor_phone, applied_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param("iidisssi", $customer_id, $product_id, $requested_amount, $requested_term_months, $purpose, $guarantor_name, $guarantor_phone, $applied_by);
$ins->execute();

log_activity('loan_application_submit', 'loans', 'নতুন লোন আবেদন জমা (গ্রাহক আইডি: ' . $customer_id . ', পরিমাণ: ' . $requested_amount . ')');

header("Location: applications_list.php?submitted=1");
exit();
