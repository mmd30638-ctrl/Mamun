<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

$id = (int)($_POST['account_id'] ?? 0);
$opening_raw = trim((string)($_POST['opening_balance'] ?? ''));
$wanted = $opening_raw !== '' ? round((float)$opening_raw, 2) : 0.0;
$back = (string)($_POST['back'] ?? '../list.php');
if ($back === '' || !str_starts_with($back, '../')) {
    $back = '../list.php';
}

$fail = static function (string $message) use ($back): void {
    $_SESSION['user_flash'] = $message;
    $_SESSION['user_flash_ok'] = false;
    header('Location: ' . $back);
    exit;
};

if ($id <= 0) {
    $fail('অ্যাকাউন্ট বেছে নিন।');
}

$chk = $db->prepare('SELECT id, account_name FROM accounts WHERE id = ? LIMIT 1');
$chk->bind_param('i', $id);
$chk->execute();
$acc = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$acc) {
    $fail('অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।');
}

$db->begin_transaction();
try {
    $have = ame_account_opening_sum($db, $id);
    $delta = round($wanted - $have, 2);
    if (abs($delta) >= 0.01) {
        if ($delta > 0) {
            account_credit($id, $delta, 'opening_balance', $id, 'ওপেনিং ব্যালেন্স');
        } else {
            account_debit($id, abs($delta), 'opening_balance', $id, 'ওপেনিং ব্যালেন্স সমন্বয়', true);
        }
    }
    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('Opening Balance', 'Accounts', (string)$acc['account_name'] . ' #' . $id, [
            'action_type' => 'edit',
            'entity_type' => 'account',
            'entity_id' => (string)$id,
            'amount_total' => $wanted,
        ]);
    }
} catch (Throwable $e) {
    $db->rollback();
    $fail($e->getMessage());
}

$_SESSION['user_flash'] = 'ওপেনিং ব্যালেন্স হালনাগাদ হয়েছে।';
$_SESSION['user_flash_ok'] = true;
header('Location: ' . $back);
exit;
