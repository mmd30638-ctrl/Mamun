<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php'); exit; }
csrf_verify();

$customer_id = intval($_POST['customer_id'] ?? 0);
$amount = (float)($_POST['adjust_amount'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');

if ($customer_id <= 0 || $amount <= 0) {
    die('❌ অবৈধ ডাটা। <a href="../index.php">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT receivable_balance, payable_balance FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
if (!$c) { die('❌ কাস্টমার পাওয়া যায়নি।'); }

$max_netting = min((float)$c['receivable_balance'], (float)$c['payable_balance']);
if ($amount > $max_netting) { $amount = $max_netting; }

// পাওনা কমানো (ক্রেডিট) ও দেনা কমানো (ডেবিট) — দুটোই লেজারে রেকর্ড হবে
add_ledger_entry($db, $customer_id, 'NETTING', 'NET-' . time(), 'নেটিং সমন্বয়: ' . $remarks, 0, $amount, 'receivable_balance');
add_ledger_entry($db, $customer_id, 'NETTING', 'NET-' . time(), 'নেটিং সমন্বয়: ' . $remarks, $amount, 0, 'payable_balance');

header('Location: ../profile.php?id=' . $customer_id . '&netting=1');
exit;