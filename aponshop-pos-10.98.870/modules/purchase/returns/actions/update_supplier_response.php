<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$response = $_POST['supplier_response'] ?? 'pending';
$note = trim($_POST['note'] ?? '');

$allowed = ['pending', 'accepted', 'rejected', 'disputed'];
if (!in_array($response, $allowed, true)) {
    $response = 'pending';
}

if ($id <= 0) {
    header('Location: ../register.php');
    exit;
}

try {
    $stmt = $db->prepare(
        "UPDATE purchase_returns SET supplier_response = ?, supplier_response_note = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param('ssi', $response, $note, $id);
    $stmt->execute();

    log_activity('Purchase Return Supplier Response', 'Purchase Returns', 'Return ID: ' . $id . ' — ' . $response);
    log_return_action($db, $id, 'supplier_response', null, null, 'সাপ্লায়ারের প্রতিক্রিয়া: ' . $response . ($note ? ' — ' . $note : ''));
    $_SESSION['success'] = 'সাপ্লায়ারের প্রতিক্রিয়া আপডেট করা হয়েছে।';
} catch (Throwable $e) {
    error_log('Supplier response update failed: ' . $e->getMessage());
    $_SESSION['error'] = 'আপডেট করতে সমস্যা হয়েছে।';
}

header('Location: ../view.php?id=' . $id);
exit;
