<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/invoice_functions.php';
admin_only();
ensure_invoice_designer_schema($db);

require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ../index.php');
    exit;
}

try {
    $db->begin_transaction();

    $db->query("UPDATE invoice_layout_templates SET is_active = 0");

    $stmt = $db->prepare("UPDATE invoice_layout_templates SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $db->commit();

    log_activity('Invoice Template Activated', 'Settings', 'Template ID: ' . $id);

    $_SESSION['success'] = 'টেমপ্লেট সক্রিয় করা হয়েছে — এখন থেকে নতুন ইনভয়েসে এই ডিজাইন ব্যবহার হবে।';
} catch (Throwable $e) {
    $db->rollback();
    error_log('Invoice template activate failed: ' . $e->getMessage());
    $_SESSION['error'] = 'সক্রিয় করতে সমস্যা হয়েছে।';
}

header('Location: ../index.php?id=' . $id);
exit;
