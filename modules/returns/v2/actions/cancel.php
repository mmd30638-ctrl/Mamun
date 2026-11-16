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

try {
    $stmt = $db->prepare(
        "UPDATE sale_returns_v2 SET status = 'cancelled', updated_at = NOW()
         WHERE id = ? AND status IN ('draft', 'pending_approval')"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        log_activity('Return Cancelled', 'Returns', 'Return ID: ' . $id);
        $_SESSION['success'] = 'রিটার্ন বাতিল করা হয়েছে।';
    } else {
        $_SESSION['error'] = 'এই রিটার্ন এখন আর বাতিল করা যাবে না (হয়তো ইতিমধ্যে অনুমোদিত/সম্পন্ন হয়ে গেছে)।';
    }
} catch (Throwable $e) {
    error_log('Return cancel failed: ' . $e->getMessage());
    $_SESSION['error'] = 'বাতিল করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
