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
$as_of_date = partnership_valid_date($_POST['as_of_date'] ?? date('Y-m-d'));

if ($partner_id <= 0 || abs($amount) < 0.01) {
    header('Location: ../partnership.php?err=opening');
    exit();
}

$check = $db->prepare("SELECT id FROM partners WHERE id = ? LIMIT 1");
$check->bind_param("i", $partner_id);
$check->execute();
$found = $check->get_result()->fetch_assoc();
$check->close();

if (!$found) {
    header('Location: ../partnership.php?err=opening');
    exit();
}

$db->begin_transaction();
try {
    $ins = $db->prepare("INSERT INTO partnership_openings (partner_id, amount, note, as_of_date) VALUES (?, ?, ?, ?)");
    $ins->bind_param("idss", $partner_id, $amount, $note, $as_of_date);
    if (!$ins->execute()) {
        throw new RuntimeException('insert failed');
    }
    $ins->close();

    $upd = $db->prepare("UPDATE partners SET opening_balance = opening_balance + ? WHERE id = ?");
    $upd->bind_param("di", $amount, $partner_id);
    if (!$upd->execute()) {
        throw new RuntimeException('update failed');
    }
    $upd->close();

    $db->commit();
    log_activity("Partnership Opening", "Accounts", "সাবেক ব্যালেন্স: ৳$amount (পার্টনার #$partner_id)");
    header('Location: ../partnership.php?saved=opening');
    exit();
} catch (Throwable $e) {
    $db->rollback();
    error_log('Partnership opening save failed: ' . $e->getMessage());
    header('Location: ../partnership.php?err=save');
    exit();
}
