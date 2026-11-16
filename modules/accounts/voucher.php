<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';

$ledger_id = (int)($_GET['ledger_id'] ?? 0);
if ($ledger_id <= 0) {
    die('❌ ভুল রিকোয়েস্ট। <a href="list.php">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT l.*, a.account_name FROM account_ledger l JOIN accounts a ON a.id = l.account_id WHERE l.id = ?");
$stmt->bind_param("i", $ledger_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) {
    die('❌ এন্ট্রি খুঁজে পাওয়া যায়নি। <a href="list.php">ফিরে যান</a>');
}

$type_label = $entry['type'] === 'credit' ? 'জমা (Credit)' : 'খরচ (Debit)';
$voucher_no = 'ACV-' . str_pad($ledger_id, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>অ্যাকাউন্ট ভাউচার</title>
<style>
    body { font-family: sans-serif; background:#f4f7f9; padding:15px; margin:0; color:#263238; }
    .receipt { background:#fff; border-radius:14px; max-width:420px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
    .top-bar-colors { display:flex; height:6px; }
    .top-bar-colors span { flex:1; }
    .c1{background:#0D47A1;} .c2{background:#2E7D32;} .c3{background:#E65100;}
    .shop-header { text-align:center; padding:18px 15px 10px; }
    .shop-header h1 { margin:0; font-size:19px; color:#0D47A1; }
    .shop-header small { color:#666; }
    .section { padding:14px 18px; border-top:1px solid #eee; }
    .row { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
    .row span:first-child { color:#666; }
    .row span:last-child { font-weight:bold; text-align:right; }
    .total-box { margin:15px 18px; padding:14px 16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; font-weight:bold; font-size:16px; color:#fff; }
    .total-credit { background:#2E7D32; }
    .total-debit { background:#B71C1C; }
    .footer-note { text-align:center; font-size:11px; color:#999; padding:10px 15px 18px; }
    .btn-row { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; padding:15px; }
    .btn { padding:10px 14px; border-radius:8px; border:none; font-weight:bold; cursor:pointer; text-decoration:none; font-size:12px; color:#fff; }
    .btn-thermal { background:#0D47A1; } .btn-a4 { background:#455A64; } .btn-share { background:#2E7D32; } .btn-back { background:#757575; }

    @media print {
        body { background:#fff; padding:0; }
        .no-print { display:none !important; }
        .receipt { box-shadow:none; max-width:100%; }
        body.printing-thermal .receipt { max-width:80mm; font-size:11px; }
        body.printing-a4 .receipt { max-width:100%; }
    }
</style>

</head>
<body>
<div class="receipt" id="voucherCard">
    <div class="top-bar-colors"><span class="c1"></span><span class="c2"></span><span class="c3"></span></div>
    <div class="shop-header">
        <h1><?php echo htmlspecialchars(shop_name()); ?></h1>
        <small>অ্যাকাউন্ট ভাউচার</small>
    </div>

    <div class="section">
        <div class="row"><span>ভাউচার নং</span><span><?php echo $voucher_no; ?></span></div>
        <div class="row"><span>তারিখ</span><span><?php echo date('d/m/Y', strtotime($entry['created_at'])); ?></span></div>
        <div class="row"><span>সময়</span><span><?php echo date('h:i A', strtotime($entry['created_at'])); ?></span></div>
        <div class="row"><span>অ্যাকাউন্ট</span><span><?php echo htmlspecialchars($entry['account_name']); ?></span></div>
        <div class="row"><span>ধরন</span><span><?php echo $type_label; ?></span></div>
        <div class="row"><span>রেফারেন্স</span><span><?php echo htmlspecialchars(strtoupper($entry['ref_type'])); ?></span></div>
        <div class="row"><span>বিবরণ</span><span><?php echo htmlspecialchars($entry['note']); ?></span></div>
    </div>

    <div class="total-box <?php echo $entry['type'] === 'credit' ? 'total-credit' : 'total-debit'; ?>">
        <span><?php echo $type_label; ?></span>
        <span>৳<?php echo number_format((float)$entry['amount'], 2); ?></span>
    </div>

    <div class="section" style="border-top:none; padding-top:0;">
        <div class="row"><span>লেনদেনের পরের ব্যালেন্স</span><span><?php echo ame_balance_label((float)$entry['balance_after']); ?></span></div>
    </div>

    <div class="footer-note"><?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($entry['created_by'] ?? 0)) : ''; ?></div>
</div>

<div class="btn-row no-print">
    <button class="btn btn-thermal" onclick="printAs('thermal')">🖨️ থার্মাল প্রিন্ট</button>
    <button class="btn btn-a4" onclick="printAs('a4')">🖨️ A4 প্রিন্ট</button>
    <button class="btn btn-share" onclick="shareVoucher()">📤 শেয়ার করুন</button>
    <a href="ledger.php?id=<?php echo (int)$entry['account_id']; ?>" class="btn btn-back">⬅️ লেজার</a>
</div>

<script src="/assets/js/ame-memo-file.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
function printAs(mode) {
    document.body.className = mode === 'thermal' ? 'printing-thermal' : 'printing-a4';
    window.print();
}
function shareVoucher() {
    var el = document.getElementById('voucherCard');
    if (el) el.setAttribute('data-ame-capture', 'clone');
    if (typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('share', 'voucher-<?php echo $voucher_no; ?>', 'voucherCard');
    }
}
</script>
</body>
</html>
