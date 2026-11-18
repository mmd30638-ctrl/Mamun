<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';

require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$clear = (string)($_POST['clear'] ?? '') === '1';
ensure_category_exchange_schema($db);

if ($clear) {
    ame_set_exchange_category($db, 0);
} elseif ($id > 0) {
    $chk = $db->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('i', $id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($row) {
            ame_set_exchange_category($db, $id);
        }
    }
}

header('Location: categories.php');
exit;
