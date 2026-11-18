<?php
/* 10.98.690 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/txn_types.php';

require_post();
csrf_verify();
admin_only();
ensure_accounts_schema($db);

$id = (int)($_POST['id'] ?? 0);
$name = '';
$find = $db->prepare('SELECT name FROM expense_categories WHERE id = ? LIMIT 1');
if ($find) {
    $find->bind_param('i', $id);
    $find->execute();
    $row = $find->get_result()->fetch_assoc();
    $find->close();
    $name = (string)($row['name'] ?? '');
}

if ($name === '') {
    $_SESSION['user_flash'] = 'এই ধরন পাওয়া যায়নি।';
    $_SESSION['user_flash_ok'] = false;
} else {
    $cleared = ame_txn_type_delete($db, $id);
    $_SESSION['user_flash'] = '«' . $name . '» মুছে গেছে।' . ($cleared > 0 ? ' ' . $cleared . 'টি লেনদেন এখন «ধরন নেই»; টাকার হিসাব বদলায়নি।' : '');
    $_SESSION['user_flash_ok'] = true;
}
header('Location: ../categories.php');
exit;
