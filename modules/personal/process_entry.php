<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';

require_post();
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cat_id = (int)($_POST['cat_id'] ?? 0);
    $acc_id = (int)($_POST['account_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $note = clean_input($_POST['note'] ?? '');

    // ক্যাটাগরি তথ্য নেওয়া (আয় না কি খরচ তা বোঝার জন্য)
    $cat_stmt = $db->prepare("SELECT * FROM personal_categories WHERE id = ?");
    $cat_stmt->bind_param("i", $cat_id);
    $cat_stmt->execute();
    $cat = $cat_stmt->get_result()->fetch_assoc();
    if (!$cat) {
        echo "<script>alert('ক্যাটাগরি খুঁজে পাওয়া যায়নি'); window.location='index.php';</script>";
        exit();
    }

    $db->begin_transaction();
    try {
        if ($cat['type'] == 'income') {
            account_credit($acc_id, $amount, 'personal_income', $cat_id, "ব্যক্তিগত আয়: " . $cat['name'] . " - " . $note);
        } else {
            account_debit($acc_id, $amount, 'personal_expense', $cat_id, "ব্যক্তিগত খরচ: " . $cat['name'] . " - " . $note);
        }

        $db->commit();
        echo "<script>alert('ব্যক্তিগত হিসাব সফলভাবে সেভ হয়েছে'); window.location='index.php';</script>";
    } catch (Exception $e) {
        $db->rollback();
        echo esc_html($e->getMessage());
    }
}
?>