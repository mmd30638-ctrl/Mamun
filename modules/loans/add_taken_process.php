<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_taken.php");
    exit();
}

$person_name = clean_input($_POST['person_name'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);

if ($person_name === '' || $amount <= 0 || $account_id <= 0) {
    header("Location: add_taken.php?error=" . urlencode('সব আবশ্যক তথ্য সঠিকভাবে পূরণ করুন।'));
    exit();
}

$db->begin_transaction();
try {
    $ins = $db->prepare("INSERT INTO loans (person_name, type, amount, paid_amount, status) VALUES (?, 'taken', ?, 0, 'open')");
    $ins->bind_param("sd", $person_name, $amount);
    $ins->execute();
    $loan_id = $db->insert_id;

    // ধার নেওয়া টাকা অ্যাকাউন্টে জমা হচ্ছে (নগদ বাড়ছে)
    account_credit($account_id, $amount, 'loan_taken', $loan_id, 'ধার গ্রহণ: ' . $person_name);

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    header("Location: add_taken.php?error=" . urlencode('সেভ করতে ব্যর্থ: ' . $e->getMessage()));
    exit();
}

log_activity('loan_taken_add', 'loans', 'নতুন গৃহীত ঋণ যোগ: ' . $person_name . ' — ' . $amount . ' ৳');

header("Location: list.php");
exit();
