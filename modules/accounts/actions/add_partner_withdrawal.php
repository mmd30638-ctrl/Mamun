<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partnership_functions.php';
admin_only();
require_post();
csrf_verify();
ensure_partnership_schema($db);

$partner_id = (int)($_POST['partner_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$note = clean_input($_POST['note'] ?? '');
$withdrawal_date = partnership_valid_date($_POST['withdrawal_date'] ?? date('Y-m-d'));

if ($partner_id <= 0 || $amount <= 0) {
    header('Location: ../partnership.php?err=withdraw');
    exit();
}

$stmt = $db->prepare("INSERT INTO partner_withdrawals (partner_id, amount, note, withdrawal_date) VALUES (?, ?, ?, ?)");
$stmt->bind_param("idss", $partner_id, $amount, $note, $withdrawal_date);

if ($stmt->execute()) {
    $stmt->close();
    log_activity("Partner Withdrawal", "Accounts", "পার্টনার উত্তোলন: ৳$amount");
    header('Location: ../partnership.php?saved=withdraw');
    exit();
}

$stmt->close();
header('Location: ../partnership.php?err=save');
exit();
