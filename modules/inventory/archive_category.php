<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/partner_accounting_v2.php';

require_post();
csrf_verify();
ame_ensure_category_archive($db);
pav2_ensure_schema($db);

$id = (int)($_POST['id'] ?? 0);
$restore = (string)($_POST['restore'] ?? '') === '1';
if ($id < 1) {
    header('Location: categories.php');
    exit;
}

$rowStmt = $db->prepare('SELECT id, name, is_exchange, archived FROM categories WHERE id=? LIMIT 1');
$rowStmt->bind_param('i', $id);
$rowStmt->execute();
$row = $rowStmt->get_result()->fetch_assoc();
$rowStmt->close();
if (!$row) {
    $_SESSION['cat_error'] = 'ক্যাটাগরি পাওয়া যায়নি।';
    header('Location: categories.php');
    exit;
}

if ($restore) {
    $upd = $db->prepare('UPDATE categories SET archived=0, archived_at=NULL, status=1 WHERE id=?');
    $upd->bind_param('i', $id);
    $upd->execute();
    $upd->close();
    $_SESSION['cat_flash'] = 'ক্যাটাগরি আবার তালিকায়। কোনো রিপোর্ট মুছা হয়নি।';
    header('Location: categories.php');
    exit;
}

if ((int)($row['is_exchange'] ?? 0) === 1) {
    $_SESSION['cat_error'] = 'এই খাত এক্সচেঞ্জ। আগে এক্সচেঞ্জ সরান, তারপর লুকান।';
    header('Location: categories.php');
    exit;
}

$upd = $db->prepare('UPDATE categories SET archived=1, archived_at=NOW(), status=0 WHERE id=?');
$upd->bind_param('i', $id);
$upd->execute();
$upd->close();

try {
    pav2_stop_category_half($db, $id, date('Y-m-d'));
} catch (Throwable $e) {
    error_log('category archive split stop: ' . $e->getMessage());
}

$_SESSION['cat_flash'] = 'ক্যাটাগরি তালিকা থেকে সরানো হয়েছে। পণ্য, বিক্রয় ও আগের পার্টনার রিপোর্ট মুছা হয়নি।';
header('Location: categories.php');
exit;
