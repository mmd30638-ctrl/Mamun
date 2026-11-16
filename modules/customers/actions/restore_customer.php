<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/party_purge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$all = (string) ($_POST['all'] ?? '') === '1';
$id = (int) ($_POST['id'] ?? 0);
$back = (string) ($_POST['back'] ?? '');

try {
    if ($all) {
        $count = ame_customer_restore_purged($db);
        $note = $count > 0
            ? 'মুছে ফেলা কাস্টমার আবার তালিকায় ফিরে এসেছে।'
            : 'ফেরত আনার মতো কাস্টমার নেই।';
    } else {
        if ($id <= 0) {
            header('Location: ../index.php?role=deleted');
            exit;
        }
        ame_customer_restore($db, $id);
        $note = 'এই কাস্টমার আবার তালিকায় ফিরে এসেছে।';
    }
} catch (Throwable $e) {
    $fail = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    die('❌ ' . $fail . ' <a href="../index.php?role=deleted">ফিরে যান</a>');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['ame_cus_status_note'] = $note;
}

if (!$all && $back === 'profile' && $id > 0) {
    header('Location: ../profile.php?id=' . $id);
    exit;
}

header('Location: ../index.php');
exit;
