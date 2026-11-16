<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);
require_post();
csrf_verify();

$go = static function (array $q): void {
    header('Location: transfer.php?' . http_build_query($q));
    exit;
};

$from = ame_posted_shop_account_id($db, (string)($_POST['from_account'] ?? ''));
$to = ame_posted_shop_account_id($db, (string)($_POST['to_account'] ?? ''));
$amount = round((float)($_POST['amount'] ?? 0), 2);
$note = trim((string)($_POST['note'] ?? ''));
$approval_pin = trim((string)($_POST['approval_pin'] ?? ''));
$idempotency_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

try {
    $res = ame_post_account_transfer($db, [
        'from_account' => $from,
        'to_account' => $to,
        'amount' => $amount,
        'note' => $note,
        'approval_pin' => $approval_pin,
        'idempotency_token' => $idempotency_token,
    ]);
    log_activity(
        'Account transfer',
        'Accounts',
        $res['from_name'] . ' → ' . $res['to_name'] . ' ৳' . number_format($res['amount'], 2)
    );
    $go([
        'ok' => '1',
        'amt' => number_format($res['amount'], 2),
        'from' => $res['from_name'],
        'to' => $res['to_name'],
        'from_now' => number_format($res['from_now'], 2),
        'to_now' => number_format($res['to_now'], 2),
    ]);
} catch (Throwable $e) {
    $go(['err' => $e->getMessage()]);
}
