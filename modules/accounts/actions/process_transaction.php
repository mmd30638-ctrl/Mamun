<?php
/* 10.98.687 */
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

function ame_txn_money($raw): float
{
    $text = trim((string)$raw);
    $text = strtr($text, [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ]);
    $text = str_replace([',', '৳', ' '], '', $text);
    return round((float)$text, 2);
}

require_post();
csrf_verify();
ensure_accounts_schema($db);

$back = '../add_transaction.php';

$fail = static function (string $message) use ($back): void {
    $_SESSION['user_flash'] = $message;
    $_SESSION['user_flash_ok'] = false;
    header('Location: ' . $back);
    exit;
};

$posted_account = trim((string)($_POST['posted_account_id'] ?? ''));
if ($posted_account === '') {
    $posted_account = trim((string)($_POST['account_id'] ?? ''));
}
$account_id  = ame_posted_shop_account_id($db, $posted_account);
$txn_type    = ame_manual_txn_direction($_POST);
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$amount      = ame_txn_money($_POST['amount'] ?? 0);
$note        = trim((string)($_POST['note'] ?? ''));
$member_id   = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
$merchant_name = trim((string)($_POST['merchant_name'] ?? ''));
$token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

if ($account_id <= 0 || !in_array($txn_type, ['credit', 'debit'], true)) {
    $fail('অ্যাকাউন্ট বাছুন এবং জমা বা খরচ ঠিক করুন।');
}
if ($amount <= 0) {
    $fail('পরিমাণ ০-এর বেশি দিন। জমা তখনই অ্যাকাউন্টে যোগ হবে।');
}
if ($note === '') {
    $note = $txn_type === 'credit' ? 'জমা' : 'খরচ';
}
if (function_exists('mb_substr')) {
    $note = mb_substr($note, 0, 255);
} else {
    $note = substr($note, 0, 255);
}

$db->begin_transaction();
try {
    $replay_id = 0;
    if ($token !== '' && function_exists('claim_idempotency_token')
        && !claim_idempotency_token($db, 'account_manual', $token, $replay_id)) {
        $db->rollback();
        $fail('এই হিসাবটি আগেই সংরক্ষিত হয়েছে — ডাবল এন্ট্রি আটকানো হয়েছে।');
    }

    $merchant_id = null;
    if ($merchant_name !== '') {
        $find = $db->prepare('SELECT id FROM merchants WHERE name = ? LIMIT 1');
        if ($find) {
            $find->bind_param('s', $merchant_name);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc();
            $find->close();
            if ($existing) {
                $merchant_id = (int)$existing['id'];
            } else {
                $ins = $db->prepare('INSERT INTO merchants (name, category_id) VALUES (?, ?)');
                if ($ins) {
                    $cat_bind = $category_id !== null ? $category_id : 0;
                    $ins->bind_param('si', $merchant_name, $cat_bind);
                    $ins->execute();
                    $merchant_id = (int)$ins->insert_id;
                    $ins->close();
                }
            }
        }
    }

    ame_require_selectable_account($db, $account_id);
    if ($txn_type === 'credit') {
        account_credit($account_id, $amount, 'manual_income', 0, $note, $category_id, $merchant_id, $member_id);
    } else {
        account_debit($account_id, $amount, 'manual_expense', 0, $note, false, $category_id, $merchant_id, $member_id);
    }

    if ($token !== '' && function_exists('finish_idempotency_token')) {
        finish_idempotency_token($db, 'account_manual', $token, $account_id);
    }
    $db->commit();
    if (function_exists('log_activity')) {
        log_activity(
            $txn_type === 'credit' ? 'Account Receive' : 'Account Withdraw',
            'Accounts',
            $note . ' ৳' . number_format($amount, 2),
            [
                'action_type' => 'payment',
                'entity_type' => 'account',
                'entity_id' => (string)$account_id,
                'amount_total' => (float)$amount,
                'amount_paid' => $txn_type === 'credit' ? (float)$amount : null,
            ]
        );
    }

    $_SESSION['user_flash'] = ($txn_type === 'credit' ? 'জমা' : 'খরচ')
        . ' সংরক্ষণ হয়েছে — ৳' . number_format($amount, 2);
    $_SESSION['user_flash_ok'] = true;
    header('Location: ' . $back);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    $fail('সংরক্ষণে সমস্যা: ' . $e->getMessage());
}
