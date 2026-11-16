<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/partner_accounting_v2.php';

require_post();
csrf_verify();
ame_ensure_category_archive($db);
pav2_ensure_schema($db);

$id = (int)($_POST['id'] ?? 0);
$stop = (string)($_POST['stop'] ?? '') === '1';
if ($id < 1) {
    header('Location: categories.php');
    exit;
}

try {
    if ($stop) {
        $off = 0;
        $flag = $db->prepare('UPDATE categories SET is_partnership_shared=? WHERE id=?');
        $flag->bind_param('ii', $off, $id);
        $flag->execute();
        $flag->close();
        pav2_stop_category_half($db, $id, date('Y-m-d', strtotime('-1 day')));
        $_SESSION['cat_flash'] = 'এই ক্যাটাগরির ৫০% লাভ নিষ্ক্রিয়। আগের তারিখের রিপোর্ট আছে।';
    } else {
        $on = 1;
        $flag = $db->prepare('UPDATE categories SET is_partnership_shared=? WHERE id=?');
        $flag->bind_param('ii', $on, $id);
        $flag->execute();
        $flag->close();
        pav2_enable_category_half($db, $id, date('Y-m-d'));
        $_SESSION['cat_flash'] = 'এই ক্যাটাগরি অ্যাক্টিভ। আজ থেকে লাভ ৫০/৫০।';
    }
} catch (Throwable $e) {
    $_SESSION['cat_error'] = $e->getMessage();
}

header('Location: categories.php');
exit;
