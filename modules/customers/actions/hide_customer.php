<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/party_purge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../index.php');
    exit;
}

try {
    ame_customer_purge($db, $id);
} catch (Throwable $e) {
    die('❌ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' <a href="../edit_customer.php?id=' . $id . '">ফিরে যান</a>');
}

header('Location: ../index.php');
exit;
