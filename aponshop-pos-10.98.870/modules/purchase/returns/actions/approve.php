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
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $credit_note_no = generate_credit_note_no($db);

    $stmt = $db->prepare(
        "UPDATE purchase_returns
         SET status = 'approved', approved_by = ?, approved_at = NOW(), inspection_status = 'passed', credit_note_no = ?, updated_at = NOW()
         WHERE id = ? AND status = 'pending_approval'"
    );
    $stmt->bind_param('isi', $user_id, $credit_note_no, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        log_activity('Purchase Return Approved', 'Purchase Returns', 'Return ID: ' . $id);
        log_return_action($db, $id, 'approved', 'pending_approval', 'approved', 'রিটার্ন অনুমোদিত হয়েছে — ক্রেডিট নোট: ' . $credit_note_no);
        $_SESSION['success'] = 'রিটার্ন অনুমোদিত হয়েছে — ক্রেডিট নোট নং: ' . $credit_note_no;
    } else {
        $_SESSION['error'] = 'এই রিটার্ন ইতিমধ্যে অনুমোদিত অথবা খুঁজে পাওয়া যায়নি।';
    }
} catch (Throwable $e) {
    error_log('Purchase return approve failed: ' . $e->getMessage());
    $_SESSION['error'] = 'অনুমোদনে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
