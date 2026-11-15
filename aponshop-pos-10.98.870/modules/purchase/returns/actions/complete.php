<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../register.php');
    exit;
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $is_english = (($_SESSION['app_lang'] ?? 'bn') === 'en');
    complete_purchase_return_transaction($db, $id, $is_english);
    log_return_action($db, $id, 'completed', 'approved', 'completed', 'স্টক ও সাপ্লায়ার হিসাব আপডেট হয়েছে');

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'রিটার্ন সফলভাবে সম্পন্ন হয়েছে — স্টক ও সাপ্লায়ার হিসাব আপডেট হয়ে গেছে।';
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Purchase return complete failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: 'সম্পন্ন করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
