<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/integrity_functions.php';
ensure_accounts_schema($db);
ensure_integrity_schema($db);
require_post();
csrf_verify();

$fail = static function (string $msg): void {
    $_SESSION['user_flash'] = $msg;
    $_SESSION['user_flash_ok'] = false;
    header('Location: ../receive.php');
    exit();
};

$source_name = trim((string) ($_POST['source_name'] ?? ''));
$source_phone = trim((string) ($_POST['source_phone'] ?? ''));
$amount = round((float) ($_POST['amount'] ?? 0), 2);
$receive_date = trim((string) ($_POST['receive_date'] ?? date('Y-m-d')));
$method = trim((string) ($_POST['method'] ?? 'CASH'));
$note = trim((string) ($_POST['note'] ?? ''));
$account_id = (int) ($_POST['account_id'] ?? 0);
$token = substr(trim((string) ($_POST['idempotency_token'] ?? '')), 0, 80);

if (!in_array($method, ['CASH', 'BKASH', 'NAGAD', 'BANK', 'CHEQUE'], true)) {
    $method = 'CASH';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $receive_date)) {
    $receive_date = date('Y-m-d');
}
if ($source_name === '') {
    $fail('কার কাছ থেকে টাকা পেয়েছেন তা লিখুন। / Enter who you received the money from.');
}
if ($amount <= 0) {
    $fail('সঠিক পরিমাণ দিন। / Enter a valid amount.');
}
if ($account_id <= 0) {
    $fail('যে অ্যাকাউন্টে টাকা জমা হবে সেটি বাছুন। / Choose the account the money goes into.');
}

try {
    ame_require_selectable_account($db, $account_id);
} catch (InvalidArgumentException $e) {
    $fail('সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট বাছুন। / Pick an active cash, bank, or mobile account.');
}

if (function_exists('ame_lock')) {
    ame_lock($db, 'acct_recv_' . $account_id);
}

$who = $source_name . ($source_phone !== '' ? ' (' . $source_phone . ')' : '');
$desc = 'বাহির থেকে টাকা রিসিভ — ' . $who . ' (' . $method . ')' . ($note !== '' ? ' — ' . $note : '');

$db->begin_transaction();
try {
    $replay_id = 0;
    if ($token !== '' && function_exists('claim_idempotency_token') && !claim_idempotency_token($db, 'external_receive', $token, $replay_id)) {
        $db->rollback();
        $_SESSION['user_flash'] = 'এই রিসিভ আগেই হয়েছে। / This receipt was already recorded.';
        $_SESSION['user_flash_ok'] = true;
        header('Location: ../receive.php');
        exit();
    }

    ame_post_account($db, 'receive', $account_id, $amount, 'external_receive', 0, $desc, true);

    if ($token !== '' && function_exists('finish_idempotency_token')) {
        finish_idempotency_token($db, 'external_receive', $token, 0);
    }
    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('External Receive', 'Accounts', $who . ' ৳' . number_format($amount, 2) . ' (' . $receive_date . ')');
    }
    $_SESSION['user_flash'] = 'টাকা রিসিভ হয়েছে — ৳' . number_format($amount, 2) . ' জমা হলো। / Money received — ৳' . number_format($amount, 2) . ' recorded.';
    $_SESSION['user_flash_ok'] = true;
    header('Location: ../receive.php');
    exit();
} catch (Throwable $e) {
    $db->rollback();
    error_log('External receive failed: ' . $e->getMessage());
    $reason = trim((string) $e->getMessage());
    $msg = $reason !== ''
        ? ('রিসিভ হয়নি — ' . $reason . ' / Receive failed — ' . $reason)
        : 'রিসিভ হয়নি — অ্যাকাউন্টে সমস্যা হয়েছে। / Receive failed — something went wrong with the account.';
    $fail($msg);
}
