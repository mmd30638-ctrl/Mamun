<?php
/**
 * Extra invoice-editor desk removed — edit happens on the purchase page.
 */
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';

$memo_no = ame_purchase_normalize_memo($_GET['memo'] ?? $_GET['edit_memo'] ?? '');
if ($memo_no !== '') {
    header('Location: add.php?edit_memo=' . rawurlencode($memo_no));
    exit;
}
$id = (int) ($_GET['id'] ?? $_GET['invoice_id'] ?? $_GET['purchase_id'] ?? 0);
if ($id > 0) {
    header('Location: add.php?edit_id=' . $id);
    exit;
}
header('Location: history.php');
exit;
