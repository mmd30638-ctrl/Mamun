<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_profit_allocation_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../index.php');
    exit;
}

try {
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // শুধু "proposed" অবস্থায় থাকা বণ্টনই অনুমোদন করা যাবে (একই বণ্টন দুইবার অনুমোদন হওয়া ঠেকাতে)
    $stmt = $db->prepare(
        "UPDATE profit_allocations SET status = 'approved', approved_by = ?, approved_at = NOW()
         WHERE id = ? AND status = 'proposed'"
    );
    $stmt->bind_param('ii', $user_id, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        log_activity('Profit Allocation Approved', 'Partnership', 'Allocation ID: ' . $id);
        $_SESSION['success'] = 'লাভ বণ্টন সফলভাবে অনুমোদিত হয়েছে।';
    } else {
        $_SESSION['error'] = 'এই বণ্টন ইতিমধ্যে অনুমোদিত অথবা খুঁজে পাওয়া যায়নি।';
    }
} catch (Throwable $e) {
    error_log('Profit allocation approve failed: ' . $e->getMessage());
    $_SESSION['error'] = 'অনুমোদনে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
}

header('Location: ../view.php?id=' . $id);
exit;
