<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/integrity_functions.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
ensure_integrity_schema($db);
require_post();
csrf_verify();

$fail = static function (string $msg, array $ticket = []): void {
    $_SESSION['user_flash'] = $msg;
    $_SESSION['user_flash_ok'] = false;
    if ($ticket !== []) {
        $_SESSION['wdw_msg'] = $ticket;
    }
    $back = (int) ($_POST['customer_id'] ?? 0);
    header('Location: ../withdraw.php' . ($back > 0 ? ('?id=' . $back) : ''));
    exit();
};

$ticket = static function (string $kind, string $titleBn, string $titleEn, string $bn, string $en): array {
    return [
        'kind' => $kind,
        'title_bn' => $titleBn,
        'title_en' => $titleEn,
        'bn' => $bn,
        'en' => $en,
    ];
};

$ensure_wd_cols = static function (mysqli $db): void {
    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM customer_withdrawals');
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        $have[strtolower((string) ($row['Field'] ?? ''))] = true;
    }
    if (empty($have['account_id'])) {
        @$db->query('ALTER TABLE customer_withdrawals ADD COLUMN account_id INT NULL DEFAULT NULL AFTER method');
    }
    if (empty($have['idempotency_token'])) {
        @$db->query('ALTER TABLE customer_withdrawals ADD COLUMN idempotency_token VARCHAR(80) NULL DEFAULT NULL');
    }
    if (empty($have['reference_no'])) {
        @$db->query('ALTER TABLE customer_withdrawals ADD COLUMN reference_no VARCHAR(50) DEFAULT NULL');
    }
};
$ensure_wd_cols($db);

$customer_id = (int) ($_POST['customer_id'] ?? 0);
$amount = round((float) ($_POST['amount'] ?? 0), 2);
$withdraw_date = trim((string) ($_POST['withdraw_date'] ?? date('Y-m-d')));
$method = trim((string) ($_POST['method'] ?? 'CASH'));
$note = trim((string) ($_POST['note'] ?? ''));
$account_id = (int) ($_POST['account_id'] ?? 0);
$token = substr(trim((string) ($_POST['idempotency_token'] ?? '')), 0, 80);

if (!in_array($method, ['CASH', 'BKASH', 'NAGAD', 'BANK', 'CHEQUE'], true)) {
    $method = 'CASH';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $withdraw_date)) {
    $withdraw_date = date('Y-m-d');
}
if ($customer_id <= 0 || $amount <= 0) {
    $fail(
        'কাস্টমার ও সঠিক পরিমাণ দিন। / Enter a customer and a valid amount.',
        $ticket('need', 'পরিমাণ দিন', 'Amount needed', 'কাস্টমার বেছে সঠিক টাকার পরিমাণ লিখুন।', 'Pick a customer and enter a valid amount.')
    );
}
if ($account_id <= 0) {
    $fail(
        'যে অ্যাকাউন্ট থেকে টাকা যাবে সেটি বাছুন। / Choose the cash/bank account the money leaves.',
        $ticket('need', 'অ্যাকাউন্ট বাছুন', 'Choose an account', 'যে ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট থেকে টাকা যাবে সেটি বাছুন।', 'Choose the cash, bank, or mobile account the money leaves.')
    );
}

try {
    ame_require_selectable_account($db, $account_id);
} catch (InvalidArgumentException $e) {
    $fail(
        'সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট বাছুন। / Pick an active cash, bank, or mobile account.',
        $ticket('need', 'সক্রিয় অ্যাকাউন্ট লাগবে', 'Active account needed', 'সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট বাছুন।', 'Pick an active cash, bank, or mobile account.')
    );
}

$chk = $db->prepare('SELECT id, name, receivable_balance, status FROM customers WHERE id = ? LIMIT 1');
if (!$chk) {
    $fail(
        'কাস্টমার যাচাই হয়নি। / Could not check the customer.',
        $ticket('fail', 'উত্তোলন হয়নি', 'Payout not recorded', 'কাস্টমার যাচাই হয়নি। আবার চেষ্টা করুন।', 'Could not check the customer. Try again.')
    );
}
$chk->bind_param('i', $customer_id);
$chk->execute();
$cust = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$cust) {
    $fail(
        'কাস্টমার পাওয়া যায়নি। / Customer not found.',
        $ticket('fail', 'কাস্টমার মেলেনি', 'Customer not found', 'এই কাস্টমার খুঁজে পাওয়া যায়নি।', 'This customer was not found.')
    );
}
if (function_exists('ame_customer_row_operable') && !ame_customer_row_operable($cust)) {
    $fail(
        'বন্ধ কাস্টমারের উত্তোলন যায় না। / Inactive customer cannot withdraw.',
        $ticket('fail', 'কাস্টমার বন্ধ', 'Customer inactive', 'বন্ধ কাস্টমারের উত্তোলন যায় না। তালিকা থেকে চালু করুন।', 'Inactive customers cannot withdraw. Enable them from the list.')
    );
}

// ⭐ আগে এখানে জমা না থাকলে বা জমার চেয়ে বেশি চাইলে আটকে দেওয়া হতো। এখন
// ইচ্ছাকৃতভাবে জমা না থাকলেও/জমার চেয়ে বেশি চাইলেও টাকা দেওয়া যাবে —
// নিচের add_ledger_entry()-এর debit এমনিতেই receivable_balance বাড়িয়ে
// দেয়, তাই জমার চেয়ে বেশি অংশটা স্বয়ংক্রিয়ভাবে কাস্টমারের বকেয়া
// (due) হিসেবে যোগ হয়ে যাবে — এখানে আলাদা কোনো নতুন হিসাব যোগ করা হয়নি।

if (function_exists('ame_lock')) {
    ame_lock($db, 'cust_wd_' . $customer_id);
}

$db->begin_transaction();
try {
    $replay_id = 0;
    if ($token !== '' && function_exists('claim_idempotency_token') && !claim_idempotency_token($db, 'customer_withdraw', $token, $replay_id)) {
        $db->rollback();
        if ($replay_id > 0) {
            header('Location: ../withdraw_receipt.php?id=' . $replay_id);
            exit();
        }
        $fail(
            'এই উত্তোলন আগেই হয়েছে। / This payout was already recorded.',
            $ticket('already', 'আগেই উত্তোলন হয়েছে', 'Already paid out', 'এই উত্তোলন আগেই রেকর্ড হয়েছে। নতুন করে আবার চাপবেন না।', 'This payout was already recorded. Do not tap again.')
        );
    }

    $stmt = $db->prepare(
        'INSERT INTO customer_withdrawals (customer_id, amount, withdraw_date, method, account_id, note, idempotency_token)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('withdraw insert prepare failed');
    }
    $tok = $token !== '' ? $token : null;
    $stmt->bind_param('idssiss', $customer_id, $amount, $withdraw_date, $method, $account_id, $note, $tok);
    $stmt->execute();
    $wid = (int) $db->insert_id;
    $stmt->close();

    $ref = 'WD-' . str_pad((string) $wid, 6, '0', STR_PAD_LEFT);
    $desc = 'দোকান থেকে জমা টাকা তোলা (' . $method . ')' . ($note !== '' ? ' — ' . $note : '');
    add_ledger_entry($db, $customer_id, 'উত্তোলন', $ref, $desc, $amount, 0);
    $fix = $db->prepare('UPDATE customer_ledger SET txn_date = ? WHERE customer_id = ? AND ref_no = ? LIMIT 1');
    if ($fix) {
        $fix->bind_param('sis', $withdraw_date, $customer_id, $ref);
        $fix->execute();
        $fix->close();
    }

    $upref = $db->prepare('UPDATE customer_withdrawals SET reference_no = ? WHERE id = ? LIMIT 1');
    if ($upref) {
        $upref->bind_param('si', $ref, $wid);
        $upref->execute();
        $upref->close();
    }

    account_debit(
        $account_id,
        $amount,
        'customer_withdraw',
        $wid,
        'কাস্টমার জমা তোলা (' . $method . ') — ' . $ref
    );

    if ($token !== '' && function_exists('finish_idempotency_token')) {
        finish_idempotency_token($db, 'customer_withdraw', $token, $wid);
    }
    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('Customer Withdraw', 'Customers', ($cust['name'] ?? '') . ' ৳' . number_format($amount, 2) . ' ' . $ref);
    }
    $_SESSION['user_flash'] = 'জমা টাকা তোলা হয়েছে। / Deposit payout recorded.';
    $_SESSION['user_flash_ok'] = true;
    $_SESSION['wdw_msg'] = $ticket(
        'ok',
        'জমা টাকা তোলা হয়েছে',
        'Deposit paid out',
        'ক্যাশ/ব্যাংক থেকে টাকা গেছে, স্টেটমেন্টে উত্তোলন বসেছে।',
        'Money left cash/bank and the statement now has a withdrawal.'
    );
    header('Location: ../withdraw_receipt.php?id=' . $wid);
    exit();
} catch (Throwable $e) {
    $db->rollback();
    error_log('Customer withdraw failed: ' . $e->getMessage());
    $reason = trim((string) $e->getMessage());
    $low = function_exists('mb_strtolower') ? mb_strtolower($reason) : strtolower($reason);
    if (str_contains($reason, 'অপর্যাপ্ত') || str_contains($reason, 'পর্যাপ্ত টাকা নেই') || str_contains($low, 'insufficient')) {
        $fail(
            'ক্যাশ/ব্যাংকে টাকা কম। অন্য অ্যাকাউন্ট বাছুন। / This account does not have enough cash for the payout.',
            $ticket(
                'cash',
                'ক্যাশে টাকা কম',
                'Not enough cash',
                'এই অ্যাকাউন্টে তোলার মতো পর্যাপ্ত ব্যালেন্স নেই। অন্য ক্যাশ/ব্যাংক বাছুন, অথবা আগে ক্যাশ জমা দিন। হিসাবের নিয়ম বদলানো হয়নি।',
                'This account does not have enough cash for the payout. Pick another account, or add cash first. Ledger rules are unchanged.'
            )
        );
    }
    $fail(
        'উত্তোলন হয়নি — ক্যাশ কম হতে পারে বা হিসাব আটকেছে। / Payout failed — cash may be short or the ledger blocked it.',
        $ticket(
            'fail',
            'উত্তোলন হয়নি',
            'Payout not recorded',
            'জমা টাকা এখনো কাস্টমারের খাতায় আছে। আবার চেষ্টা করুন — ক্যাশ কম থাকলে অন্য অ্যাকাউন্ট বাছুন।',
            'The deposit is still on the customer book. Try again, or pick another account if cash is short.'
        )
    );
}
