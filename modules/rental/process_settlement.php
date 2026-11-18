<?php
require_once __DIR__ . '/_boot.php';
require_post();
csrf_verify();

$owner_id = (int)($_POST['owner_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$method = strtoupper(trim((string)($_POST['method'] ?? 'CASH')));
$ref = trim((string)($_POST['ref'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$paid_at = trim((string)($_POST['paid_at'] ?? rental_today()));
if (strlen($paid_at) === 10) {
    $paid_at .= ' ' . date('H:i:s');
}
if (!in_array($method, ['CASH', 'BKASH', 'NAGAD', 'BANK'], true)) {
    $method = 'CASH';
}
$owner = rental_owner($db, $owner_id);
if (!$owner || $amount <= 0) {
    rental_flash('পরিশোধ গ্রহণযোগ্য নয়', 'err');
    redirect($owner_id ? '/modules/rental/statement.php?id=' . $owner_id : '/modules/rental/owners.php');
}
$uid = rental_user_id();
rental_exec(
    $db,
    'INSERT INTO rental_owner_payments (owner_id, amount, method, paid_at, ref, notes, user_id, status) VALUES (?,?,?,?,?,?,?, "posted")',
    'idssssi',
    [$owner_id, $amount, $method, $paid_at, $ref, $notes, $uid]
);
$pid = (int)$db->insert_id;
rental_audit($db, 'rental_owner_payments', $pid, 'create', null, ['amount' => $amount, 'owner_id' => $owner_id]);
rental_flash('স্টেটমেন্টে পরিশোধ যোগ হয়েছে');
redirect('/modules/rental/statement.php?id=' . $owner_id);
