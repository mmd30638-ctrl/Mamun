<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/supplier_settlement.php';

require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
try {
    if (in_array($action, ['reverse', 'cancel'], true) && function_exists('ame_pay_sec_verify')) {
        ame_pay_sec_verify('supplier_' . $action, $id, ame_pay_sec_posted());
    }
    if ($action === 'approve') {
        ame_spay_approve($db, $id);
    } elseif ($action === 'reverse') {
        ame_spay_reverse($db, $id, (string)($_POST['reason'] ?? ''));
    } elseif ($action === 'reverse_day') {
        $sid = (int) ($_POST['supplier_id'] ?? 0);
        $day = trim((string) ($_POST['pay_day'] ?? ''));
        $amount = round((float) ($_POST['pay_amount'] ?? 0), 2);
        if (function_exists('ame_pay_sec_verify')) {
            ame_pay_sec_verify('supplier_day_reverse', $sid, ame_pay_sec_posted());
        }
        $n = ame_spay_reverse_day_amount($db, $sid, $day, $amount, (string) ($_POST['reason'] ?? ''));
        $_SESSION['spay_ok'] = $n > 0
            ? ($n . 'টি পেমেন্ট বাতিল হয়েছে। টাকা অ্যাকাউন্টে ফিরেছে, বকেয়া বেড়েছে।')
            : 'বাতিল করার মতো পেমেন্ট পাওয়া যায়নি।';
        header('Location: statement.php?id=' . $sid);
        exit;
    } elseif ($action === 'reverse_ledger') {
        $ledgerId = (int) ($_POST['ledger_id'] ?? 0);
        $sid = (int) ($_POST['supplier_id'] ?? 0);
        if (function_exists('ame_pay_sec_verify')) {
            ame_pay_sec_verify('supplier_ledger_reverse', $ledgerId, ame_pay_sec_posted());
        }
        ame_spay_reverse_ledger_row($db, $ledgerId, (string) ($_POST['reason'] ?? ''));
        header('Location: ' . ($sid > 0 ? 'statement.php?id=' . $sid : 'payments-list.php'));
        exit;
    } elseif ($action === 'cancel') {
        ame_spay_cancel($db, $id);
    } elseif ($action === 'threshold') {
        $is_admin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
        if (!$is_admin) {
            throw new RuntimeException(ame_spay_t('এই সীমা শুধু অ্যাডমিন বদলাতে পারবেন।', 'Only an admin can change this limit.'));
        }
        $value = (string)round(max(0, (float)($_POST['limit'] ?? 0)), 2);
        set_app_setting($db, 'supplier_pay_approval_min', $value);
        header('Location: dashboard.php');
        exit;
    } else {
        throw new RuntimeException(ame_spay_t('কাজটি চেনা যায়নি।', 'Unknown action.'));
    }
    header('Location: receipt.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['spay_error'] = $e->getMessage();
    $sid = (int) ($_POST['supplier_id'] ?? 0);
    if (in_array($action, ['reverse_day', 'reverse_ledger'], true) && $sid > 0) {
        header('Location: statement.php?id=' . $sid);
        exit;
    }
    header('Location: ' . ($id > 0 ? 'receipt.php?id=' . $id : 'pay.php'));
    exit;
}
