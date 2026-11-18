<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/integrity_functions.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_module_guard.php';

require_post();
csrf_verify();
ensure_integrity_schema($db);

$sid = (int)($_POST['supplier_id'] ?? 0);
ame_supplier_module_write_boot($db);
try {
    if (function_exists('ame_pay_sec_verify')) {
        ame_pay_sec_verify('supplier_pay', $sid, ame_pay_sec_posted());
    }
    $id = ame_spay_save($db, $_POST, $_FILES['attachment'] ?? null);
    header('Location: receipt.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['spay_error'] = $e->getMessage();
    $back = 'pay.php' . ($sid > 0 ? '?id=' . $sid : '');
    $edit = (int)($_POST['settlement_id'] ?? 0);
    if ($edit > 0 && $sid > 0) {
        $back .= '&edit=' . $edit;
    }
    header('Location: ' . $back);
    exit;
}
