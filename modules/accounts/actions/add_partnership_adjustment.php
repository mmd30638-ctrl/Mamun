<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';
admin_only();
require_post();
csrf_verify();
ensure_partnership_schema($db);

$amount = (float)($_POST['amount'] ?? 0);
$reason = clean_input($_POST['reason'] ?? '');
$adjustment_date = partnership_valid_date($_POST['adjustment_date'] ?? date('Y-m-d'));

if ($amount <= 0 || $reason === '') {
    header('Location: ../partnership.php?err=expense');
    exit();
}

$stmt = $db->prepare("INSERT INTO partnership_adjustments (amount, reason, adjustment_date) VALUES (?, ?, ?)");
$stmt->bind_param("dss", $amount, $reason, $adjustment_date);

if ($stmt->execute()) {
    $stmt->close();
    log_activity("Partnership Unit Expense", "Accounts", "ইউনিট খরচ যোগ: ৳$amount ($reason)");
    header('Location: ../partnership.php?saved=expense');
    exit();
}

$stmt->close();
header('Location: ../partnership.php?err=save');
exit();
