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

try {
    $stmt = $db->prepare(
        "UPDATE purchase_returns SET status = 'cancelled', updated_at = NOW()
         WHERE id = ? AND status IN ('draft', 'pending_approval')"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        log_activity('Purchase Return Cancelled', 'Purchase Returns', 'Return ID: ' . $id);
        log_return_action($db, $id, 'cancelled', null, 'cancelled', 'রিটার্ন বাতিল করা হয়েছে');
        $_SESSION['success'] = 'রিটার্ন বাতিল করা হয়েছে।';
    } else {
        $_SESSION['error'] = 'এই রিটার্ন এখন আর বাতিল করা যাবে না।';
    }
} catch (Throwable $e) {
    error_log('Purchase return cancel failed: ' . $e->getMessage());
    $_SESSION['error'] = 'বাতিল করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
