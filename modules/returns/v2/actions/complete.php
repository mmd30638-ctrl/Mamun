<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

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

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_english = (($_SESSION['app_lang'] ?? 'bn') === 'en');

    complete_return_transaction($db, $id, $user_id, $is_english);

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'রিটার্ন সফলভাবে সম্পন্ন হয়েছে — স্টক ও কাস্টমার হিসাব আপডেট হয়ে গেছে।';
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Return complete failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: 'সম্পন্ন করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
