<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../add_transaction.php');
    exit;
}
csrf_verify();

$type = in_array($_POST['type'] ?? '', ['income', 'expense', 'transfer', 'refund'], true) ? $_POST['type'] : null;
$amount = round((float)($_POST['amount'] ?? 0), 2);
$txn_date = trim($_POST['txn_date'] ?? date('Y-m-d'));
$note = substr(trim($_POST['note'] ?? ''), 0, 255);

if (!$type) {
    $_SESSION['error'] = '⚠️ লেনদেনের ধরন সঠিক নয়।';
    header('Location: ../add_transaction.php');
    exit;
}
if ($amount <= 0) {
    $_SESSION['error'] = '⚠️ পরিমাণ সঠিকভাবে লিখুন।';
    header('Location: ../add_transaction.php');
    exit;
}

// ================= প্রতিটা ধরন অনুযায়ী প্রয়োজনীয় ফিল্ড বের করা ও যাচাই =================
$account_id = null;
$to_account_id = null;
$category_id = null;
$merchant_id = null;
$member_id = null;
$note_prefix = '';

if ($type === 'income') {
    $account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
    $category_id = !empty($_POST['category_id_income']) ? (int)$_POST['category_id_income'] : null;
    $source_label = substr(trim($_POST['source_label'] ?? ''), 0, 150);
    $note_prefix = $source_label ? ($source_label . ' — ') : '';

    if (!$account_id) {
        $_SESSION['error'] = '⚠️ কোন অ্যাকাউন্টে জমা হবে তা নির্বাচন করুন।';
        header('Location: ../add_transaction.php');
        exit;
    }
} elseif ($type === 'expense') {
    $account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
    $category_id = !empty($_POST['category_id_expense']) ? (int)$_POST['category_id_expense'] : null;
    $member_id = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
    $merchant_name = trim($_POST['merchant_name'] ?? '');

    if (!$account_id) {
        $_SESSION['error'] = '⚠️ কোন অ্যাকাউন্ট থেকে খরচ হয়েছে তা নির্বাচন করুন।';
        header('Location: ../add_transaction.php');
        exit;
    }

    // ================= মার্চেন্ট নাম দিয়ে খুঁজে বের করা, না পেলে নতুন তৈরি =================
    if ($merchant_name !== '') {
        $find = $db->prepare("SELECT id FROM pf_merchants WHERE name = ? LIMIT 1");
        $find->bind_param('s', $merchant_name);
        $find->execute();
        $existing = $find->get_result()->fetch_assoc();
        if ($existing) {
            $merchant_id = (int)$existing['id'];
        } else {
            $ins = $db->prepare("INSERT INTO pf_merchants (name, category_id) VALUES (?, ?)");
            $ins->bind_param('si', $merchant_name, $category_id);
            $ins->execute();
            $merchant_id = $ins->insert_id;
        }
    }
} elseif ($type === 'transfer') {
    $account_id = !empty($_POST['from_account_id']) ? (int)$_POST['from_account_id'] : null;
    $to_account_id = !empty($_POST['to_account_id']) ? (int)$_POST['to_account_id'] : null;

    if (!$account_id || !$to_account_id) {
        $_SESSION['error'] = '⚠️ উৎস ও গন্তব্য — দুটো অ্যাকাউন্টই নির্বাচন করুন।';
        header('Location: ../add_transaction.php');
        exit;
    }
    if ($account_id === $to_account_id) {
        $_SESSION['error'] = '⚠️ একই অ্যাকাউন্টে ট্রান্সফার করা যাবে না।';
        header('Location: ../add_transaction.php');
        exit;
    }
} elseif ($type === 'refund') {
    $account_id = !empty($_POST['refund_account_id']) ? (int)$_POST['refund_account_id'] : null;
    $refund_note = substr(trim($_POST['refund_note'] ?? ''), 0, 150);
    $note_prefix = $refund_note ? ($refund_note . ' — ') : '';

    if (!$account_id) {
        $_SESSION['error'] = '⚠️ কোন অ্যাকাউন্টে ফেরত এসেছে তা নির্বাচন করুন।';
        header('Location: ../add_transaction.php');
        exit;
    }
}

// ================= অ্যাকাউন্ট(গুলো) সত্যিই বিদ্যমান কিনা যাচাই =================
foreach (array_filter([$account_id, $to_account_id]) as $acc_id) {
    $chk = $db->prepare("SELECT id FROM accounts WHERE id = ?");
    $chk->bind_param('i', $acc_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $_SESSION['error'] = '⚠️ নির্বাচিত অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।';
        header('Location: ../add_transaction.php');
        exit;
    }
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $full_note = $note_prefix . $note;

    // ⭐ একীভূতকরণ: এখন account_credit/account_debit ব্যবহার হচ্ছে — একই ফাংশন যেটা
    // লোন, বিক্রয়, ক্রয়ের জন্যও ব্যবহৃত হয়। ফলে account_ledger-এই সব লেনদেন একসাথে জমা হয়।
    if ($type === 'income') {
        account_credit($account_id, $amount, 'personal_income', 0, $full_note, $category_id, null, null);
    } elseif ($type === 'expense') {
        account_debit($account_id, $amount, 'personal_expense', 0, $full_note, false, $category_id, $merchant_id, $member_id);
    } elseif ($type === 'refund') {
        account_credit($account_id, $amount, 'personal_refund', 0, $full_note, $category_id, null, null);
    } elseif ($type === 'transfer') {
        account_debit($account_id, $amount, 'personal_transfer', 0, $full_note ?: 'ট্রান্সফার', false, null, null, null);
        account_credit($to_account_id, $amount, 'personal_transfer', 0, $full_note ?: 'ট্রান্সফার', null, null, null);
    }

    log_activity('Personal Finance Transaction', 'Finance', ucfirst($type) . ' — ৳' . $amount);

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'লেনদেন সফলভাবে সংরক্ষণ করা হয়েছে।';
    header('Location: ../dashboard.php');
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Personal finance transaction save failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: '⚠️ লেনদেন সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../add_transaction.php');
    exit;
}
