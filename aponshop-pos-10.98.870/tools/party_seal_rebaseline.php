<?php
/**
 * একবার চালান (অ্যাডমিন): ame_ledger_seal যোগফল = বর্তমান DB যোগফল। individual ব্যালেন্স বদলায় না।
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/party_amount_shield.php';

header('Content-Type: text/plain; charset=UTF-8');

$admin = (function_exists('ame_is_admin_like') && ame_is_admin_like())
    || !empty($_SESSION['is_super_admin'])
    || in_array(strtolower((string)($_SESSION['role'] ?? '')), ['admin', 'super_admin', 'superadmin', 'owner'], true);

if (!$admin || !($db instanceof mysqli)) {
    http_response_code(403);
    echo "অ্যাডমিন লগইন প্রয়োজন।\n";
    exit;
}

if (!function_exists('ame_ledger_seal_rebaseline_totals')) {
    echo "party_amount_shield.php আপডেট করুন (10.98.923+)\n";
    exit;
}

$result = ame_ledger_seal_rebaseline_totals($db);
echo ($result['ok'] ? 'OK: ' : 'FAIL: ') . ($result['message'] ?? '') . "\n";