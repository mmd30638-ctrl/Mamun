<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/supplier_functions.php';
require_once __DIR__ . '/../../../includes/purchase_functions.php';
require_once __DIR__ . '/../../../includes/purchase_draft.php';
require_once __DIR__ . '/../../../includes/running_ledger.php';

header('Content-Type: application/json; charset=UTF-8');
ame_purchase_require_access($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'অনুমোদিত অনুরোধ নয়।'], JSON_UNESCAPED_UNICODE);
    exit;
}
csrf_verify();

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$party_type = trim((string)($_POST['party_type'] ?? 'supplier'));
$amount = ame_purchase_parse_money($_POST['previous_balance'] ?? '0');
$kind = strtolower(trim((string)($_POST['settle_type'] ?? 'adjustment')));
$reason = trim((string)($_POST['settle_reason'] ?? ''));
if ($party_type === 'customer') {
    $amount = round(-$amount, 2);
}
if ($supplier_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'সাপ্লায়ার নির্বাচন করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ?');
if (!$lock) {
    echo json_encode(['status' => 'error', 'message' => 'সাপ্লায়ার খুঁজে পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$lock->bind_param('i', $supplier_id);
$lock->execute();
$row = $lock->get_result()->fetch_assoc();
$lock->close();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'সাপ্লায়ার খুঁজে পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$actual = round((float)($row['total_due'] ?? 0), 2);

$db->begin_transaction();
try {
    $settled = ame_running_settle_previous(
        $db,
        'supplier',
        $supplier_id,
        $actual,
        $amount,
        $kind,
        $reason,
        trim((string)($_POST['memo_no'] ?? ''))
    );
    if (!$settled['ok']) {
        $db->rollback();
        echo json_encode(['status' => 'error', 'message' => $settled['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ame_running_rebuild_supplier($db, $supplier_id);
    $db->commit();
    echo json_encode([
        'status' => 'success',
        'previous' => (float)$settled['previous'],
        'customer_balance' => round(-((float)$settled['previous']), 2),
        'changed' => abs($actual - (float)$settled['previous']) > 0.02,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $db->rollback();
    error_log('Set supplier previous failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'পূর্বের ব্যালেন্স সেভ হয়নি। আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
}
