<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
if (is_file(__DIR__ . '/../../../includes/rental_schema.php')) {
    require_once __DIR__ . '/../../../includes/rental_schema.php';
    require_once __DIR__ . '/../../../includes/rental_functions.php';
    ensure_rental_schema($db);
}

if (is_file(__DIR__ . '/../../../includes/hire_functions.php')) {
    require_once __DIR__ . '/../../../includes/hire_functions.php';
    hire_boot($db);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$rental_id    = (int)($_POST['rental_id'] ?? 0);
$customer_id  = (int)($_POST['customer_id'] ?? 0);
$new_deposit  = round((float)($_POST['new_deposit'] ?? -1), 2);

if ($rental_id <= 0 || $customer_id <= 0 || $new_deposit < 0) {
    die('❌ সঠিক তথ্য দিন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT id, customer_id, item_name, rental_no, security_deposit FROM rental_contracts WHERE id = ? LIMIT 1");
if (!$stmt) {
    die('❌ চুক্তি যাচাই করা যায়নি।');
}
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract || (int)$contract['customer_id'] !== $customer_id) {
    die('❌ ভাড়া চুক্তি খুঁজে পাওয়া যায়নি। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$old_deposit = round((float)$contract['security_deposit'], 2);
$delta = round($new_deposit - $old_deposit, 2);

if (abs($delta) < 0.005) {
    // কোনো পরিবর্তন নেই, চুপচাপ ফিরে যাও
    header('Location: ../profile.php?id=' . $customer_id . '&rental=1');
    exit;
}

$db->begin_transaction();
try {
    $upd = $db->prepare("UPDATE rental_contracts SET security_deposit = ? WHERE id = ?");
    if (!$upd) {
        throw new RuntimeException('জামানত আপডেট করা যায়নি।');
    }
    $upd->bind_param('di', $new_deposit, $rental_id);
    $upd->execute();
    $upd->close();

    $desc = 'জামানত সংশোধন ৳' . number_format($old_deposit, 2) . ' → ৳' . number_format($new_deposit, 2);
    if (function_exists('hire_deposit_move')) {
        hire_deposit_move($db, $rental_id, $customer_id, 'ADJ', $delta, null, 'ADJUST', $desc, null);
        if (function_exists('hire_history')) {
            hire_history($db, $rental_id, 'security_deposit', $old_deposit, $new_deposit, $desc);
        }
    }

    $db->commit();
    header('Location: ../profile.php?id=' . $customer_id . '&rental=1');
    exit;
} catch (Throwable $e) {
    $db->rollback();
    error_log('process_rental_deposit_edit failed: ' . $e->getMessage());
    die('❌ সংরক্ষণে সমস্যা হয়েছে, কোনো তথ্য বদলায়নি। পরে আবার চেষ্টা করুন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}
