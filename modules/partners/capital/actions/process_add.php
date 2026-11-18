<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$partner_id = (int)($_POST['partner_id'] ?? 0);
$type = trim($_POST['type'] ?? 'investment');
$amount = (float)($_POST['amount'] ?? 0);
$transaction_date = trim($_POST['transaction_date'] ?? '');
$note = trim($_POST['note'] ?? '');
$idempotency_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

$allowed_types = ['investment', 'capital_withdrawal', 'loan_to_business'];

// ================= ভ্যালিডেশন =================
if ($partner_id <= 0) {
    $_SESSION['error'] = 'একজন পার্টনার নির্বাচন করুন।';
    header('Location: ../add.php');
    exit;
}
if (!in_array($type, $allowed_types, true)) {
    $_SESSION['error'] = 'লেনদেনের ধরন সঠিক নয়।';
    header('Location: ../add.php');
    exit;
}
if ($amount <= 0) {
    $_SESSION['error'] = 'সঠিক পরিমাণ দিন।';
    header('Location: ../add.php');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
    $transaction_date = date('Y-m-d');
}

// নিশ্চিত করা হচ্ছে যে পার্টনার আসলেই বিদ্যমান (foreign-key ভরসা না করে, যেহেতু টেবিলে explicit FK constraint নেই)
$check = $db->prepare("SELECT id FROM partners WHERE id = ?");
$check->bind_param('i', $partner_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $_SESSION['error'] = 'নির্বাচিত পার্টনারকে খুঁজে পাওয়া যায়নি।';
    header('Location: ../add.php');
    exit;
}

try {
    $db->begin_transaction();
    $existing_ref = 0;
    if ($idempotency_token !== ''
        && !claim_idempotency_token($db, 'partner_capital', $idempotency_token, $existing_ref)) {
        $db->rollback();
        $_SESSION['success'] = 'এই মূলধন লেনদেন আগেই সংরক্ষিত হয়েছে।';
        header('Location: ../index.php');
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO partner_capital_transactions (partner_id, type, amount, transaction_date, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $stmt->bind_param('isdssi', $partner_id, $type, $amount, $transaction_date, $note, $user_id);
    $stmt->execute();
    $capital_id = (int)$db->insert_id;
    if ($idempotency_token !== '') {
        finish_idempotency_token($db, 'partner_capital', $idempotency_token, $capital_id);
    }
    $db->commit();

    log_activity('Capital Transaction Added', 'Partnership', $type . ': ৳' . $amount . ' (Partner ID: ' . $partner_id . ')');

    $_SESSION['success'] = 'মূলধন লেনদেন সফলভাবে সংরক্ষণ করা হয়েছে।';
    header('Location: ../index.php');
    exit;
} catch (Throwable $e) {
    $db->rollback();
    error_log('Capital transaction add failed: ' . $e->getMessage());
    $_SESSION['error'] = 'সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../add.php');
    exit;
}
