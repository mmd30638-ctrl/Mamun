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
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // শুধু "pending_approval" অবস্থায় থাকা রিটার্নই অনুমোদন করা যাবে
    $stmt = $db->prepare(
        "UPDATE sale_returns_v2 SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW()
         WHERE id = ? AND status = 'pending_approval'"
    );
    $stmt->bind_param('ii', $user_id, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        log_activity('Return Approved', 'Returns', 'Return ID: ' . $id);
        $_SESSION['success'] = 'রিটার্ন অনুমোদিত হয়েছে — এখন "সম্পন্ন করুন" চাপলে স্টক ও কাস্টমার লেজার আপডেট হবে।';
    } else {
        $_SESSION['error'] = 'এই রিটার্ন ইতিমধ্যে অনুমোদিত অথবা খুঁজে পাওয়া যায়নি।';
    }
} catch (Throwable $e) {
    error_log('Return approve failed: ' . $e->getMessage());
    $_SESSION['error'] = 'অনুমোদনে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
