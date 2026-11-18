<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$installment_id     = (int)($_POST['installment_id'] ?? 0);
$customer_id         = (int)($_POST['customer_id'] ?? 0);
$product_details     = trim((string)($_POST['product_details'] ?? ''));
$product_condition   = trim((string)($_POST['product_condition'] ?? 'NEW'));
$total_amount        = round((float)($_POST['total_amount'] ?? 0), 2);
$down_payment        = round((float)($_POST['down_payment'] ?? 0), 2);
$total_installments  = (int)($_POST['total_installments'] ?? 1);
$installment_type    = trim((string)($_POST['installment_type'] ?? 'MONTHLY'));

if (!in_array($product_condition, ['NEW', 'OLD_REFURBISHED'], true)) {
    $product_condition = 'NEW';
}
if (!in_array($installment_type, ['MONTHLY', 'WEEKLY', 'DAILY'], true)) {
    $installment_type = 'MONTHLY';
}

if ($installment_id <= 0 || $customer_id <= 0 || $product_details === '' || $total_amount <= 0 || $total_installments < 1) {
    die('❌ প্রয়োজনীয় তথ্য দিন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}
if ($down_payment < 0 || $down_payment > $total_amount) {
    die('❌ ডাউন পেমেন্ট মোট মূল্যের চেয়ে বেশি বা ঋণাত্মক হতে পারে না। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT id, customer_id, contract_no, remaining_balance FROM installment_contracts WHERE id = ? LIMIT 1");
if (!$stmt) {
    die('❌ চুক্তি যাচাই করা যায়নি।');
}
$stmt->bind_param('i', $installment_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract || (int)$contract['customer_id'] !== $customer_id) {
    die('❌ কিস্তি চুক্তি খুঁজে পাওয়া যায়নি। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

$old_remaining = round((float)$contract['remaining_balance'], 2);
$new_remaining = round($total_amount - $down_payment, 2);
$delta = round($new_remaining - $old_remaining, 2);

$db->begin_transaction();
try {
    $upd = $db->prepare(
        "UPDATE installment_contracts
         SET product_details = ?, product_condition = ?, total_amount = ?, down_payment = ?,
             total_installments = ?, installment_type = ?, remaining_balance = ?
         WHERE id = ?"
    );
    if (!$upd) {
        throw new RuntimeException('কিস্তি চুক্তি আপডেট করা যায়নি।');
    }
    $upd->bind_param(
        "ssddissi",
        $product_details, $product_condition, $total_amount, $down_payment,
        $total_installments, $installment_type, $new_remaining, $installment_id
    );
    $upd->execute();
    $upd->close();

    // ⭐ পুরনো নিয়ম মেনেই (process_installment.php দেখুন): "অবশিষ্ট বকেয়া"-ই
    // customer ledger-এ debit হিসেবে বসে। এডিটে টোটাল/ডাউন পেমেন্ট বদলালে নতুন
    // অবশিষ্ট বকেয়া বের করে পুরনো-নতুনের পার্থক্যটাই (delta) লেজারে সমন্বয় হয়
    // — কোনো ক্যাশ/ব্যাংক অ্যাকাউন্ট এখানে ছোঁয়া হয় না (মূল এন্ট্রিও ছোঁয়নি)।
    if (abs($delta) >= 0.005) {
        $ref = (string)($contract['contract_no'] ?? ('INST-' . $installment_id));
        $desc = 'কিস্তি চুক্তি সংশোধন: ' . $product_details . ' (' . $ref . ') — অবশিষ্ট বকেয়া ৳'
            . number_format($old_remaining, 2) . ' থেকে ৳' . number_format($new_remaining, 2);

        if ($delta > 0) {
            add_ledger_entry($db, $customer_id, 'কিস্তি সংশোধন', $ref, $desc, $delta, 0);
        } else {
            add_ledger_entry($db, $customer_id, 'কিস্তি সংশোধন', $ref, $desc, 0, abs($delta));
        }
    }

    $db->commit();
    header('Location: ../profile.php?id=' . $customer_id . '&installment=1');
    exit;
} catch (Throwable $e) {
    $db->rollback();
    error_log('process_installment_edit failed: ' . $e->getMessage());
    die('❌ সংরক্ষণে সমস্যা হয়েছে, কোনো তথ্য বদলায়নি। পরে আবার চেষ্টা করুন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}
