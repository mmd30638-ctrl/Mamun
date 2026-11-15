<?php
/**
 * ক্রয় সেভের পর বিক্রয় মেমোর মতোই ডিজিটাল মেমো দেখায়।
 */
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
ame_purchase_require_access($db);

$memo_no = trim((string) ($_GET['memo'] ?? ''));
$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$qs = $memo_no !== ''
    ? 'memo=' . rawurlencode($memo_no) . '&supplier_id=' . $supplier_id . '&ok=1'
    : 'ok=0';
header('Location: /modules/purchase/memo_digital.php?' . $qs, true, 302);
exit;
