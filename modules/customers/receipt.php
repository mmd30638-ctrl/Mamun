<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/memo_paper.php';
require_once __DIR__ . '/../../includes/invoice_functions.php';
$ameCustomerSchema = __DIR__ . '/customer_schema.php';
if (is_file($ameCustomerSchema)) {
    require_once $ameCustomerSchema;
    if (function_exists('ensure_customer_tables')) {
        ensure_customer_tables($db);
    }
    if (function_exists('ame_ensure_due_watch_columns')) {
        ame_ensure_due_watch_columns($db);
    }
}

$payment_id = (int)($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    die('❌ পেমেন্ট আইডি পাওয়া যায়নি। <a href="index.php">ফিরে যান</a>');
}

$pStmt = $db->prepare("SELECT * FROM customer_payments WHERE id = ?");
$pStmt->bind_param("i", $payment_id);
$pStmt->execute();
$payment = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$payment) {
    die('❌ পেমেন্ট খুঁজে পাওয়া যায়নি। <a href="index.php">ফিরে যান</a>');
}

$cStmt = $db->prepare("SELECT id, customer_code, name, phone, address, receivable_balance, next_due_date, due_watch, due_watch_on FROM customers WHERE id = ?");
$cStmt->bind_param("i", $payment['customer_id']);
$cStmt->execute();
$customer = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

$account_name = '';
$pay_account_id = (int)($payment['account_id'] ?? 0);
if ($pay_account_id > 0) {
    $aStmt = $db->prepare('SELECT account_name FROM accounts WHERE id = ?');
    if ($aStmt) {
        $aStmt->bind_param('i', $pay_account_id);
        $aStmt->execute();
        $accRow = $aStmt->get_result()->fetch_assoc();
        $aStmt->close();
        $account_name = (string)($accRow['account_name'] ?? '');
    }
}

if (!$customer) {
    die('❌ কাস্টমার খুঁজে পাওয়া যায়নি। <a href="index.php">ফিরে যান</a>');
}

$method_labels = ['CASH' => 'নগদ', 'BKASH' => 'বিকাশ', 'NAGAD' => 'নগদ (মোবাইল)', 'BANK' => 'ব্যাংক', 'CHEQUE' => 'চেক'];
$method_text = $method_labels[$payment['method']] ?? (string)$payment['method'];
$ref = 'PAY-' . str_pad((string)$payment_id, 6, '0', STR_PAD_LEFT);

$pay_amount = (float)($payment['amount'] ?? 0);
$pay_discount = (float)($payment['discount'] ?? 0);
$pay_relief = $pay_amount + $pay_discount;

$current_balance = (float)$customer['receivable_balance'];
$previous_balance = $current_balance + $pay_relief;
$pay_ledger = ame_customer_pay_memo_ledger($previous_balance, $pay_relief, $current_balance);

$inv = get_invoice_settings($db);
$paper_width = $inv['paper_width'] ?? '80mm';
$max_width = ($paper_width === 'A4') ? '720px' : '480px';
$shop_name = (string)($shop['shop_name'] ?? shop_name());
$shop_address = trim((string)($shop['shop_address'] ?? ''));
$shop_phone = trim((string)($shop['shop_phone'] ?? ''));
$footer_note = trim((string)preg_replace('/\s+/u', ' ', (string)($inv['footer_note'] ?? '')));
if ($footer_note === '') {
    $footer_note = 'ধন্যবাদ, আবার আসবেন!';
}

$c_name = trim((string)($customer['name'] ?? ''));
if ($c_name === '') {
    $c_name = 'ক্রেতা';
}
$c_phone = trim((string)($customer['phone'] ?? ''));
$c_code = trim((string)($customer['customer_code'] ?? ''));
$reference_no = trim((string)($payment['reference_no'] ?? ''));
$pay_note = trim((string)($payment['note'] ?? ''));

$dateTs = strtotime((string)($payment['payment_date'] ?? ''));
$timeTs = strtotime((string)($payment['created_at'] ?? ''));
$clock = static function (int $ts): string {
    return date('h:i', $ts) . "\u{00A0}" . date('A', $ts);
};
if ($dateTs && $timeTs) {
    $formatted_when = date('d-m-Y', $dateTs) . ' ' . $clock($timeTs);
} elseif ($timeTs) {
    $formatted_when = date('d-m-Y', $timeTs) . ' ' . $clock($timeTs);
} elseif ($dateTs) {
    $formatted_when = date('d-m-Y', $dateTs);
} else {
    $formatted_when = (string)($payment['payment_date'] ?? '');
}

$remain_due = (float)$pay_ledger['remaining_due'];
$advance = (float)$pay_ledger['advance'];
$after = (float)$pay_ledger['after'];
if ($after > 0.009) {
    $after_label = 'দোকান পাবে';
    $after_color = '#991B1B';
} elseif ($after < -0.009) {
    $after_label = 'কাস্টমার পাবে';
    $after_color = '#166534';
} else {
    $after_label = 'সর্বমোট';
    $after_color = '#111827';
}

$wa_phone = preg_replace('/\D+/', '', $c_phone);
if (str_starts_with($wa_phone, '0') && strlen($wa_phone) >= 11) {
    $wa_phone = '880' . substr($wa_phone, 1);
} elseif ($wa_phone !== '' && !str_starts_with($wa_phone, '880') && strlen($wa_phone) === 10) {
    $wa_phone = '880' . $wa_phone;
}
$wa_text = $shop_name . "\n"
    . "পেমেন্ট রসিদ: #" . $ref . "\n"
    . "তারিখ: " . $formatted_when . "\n"
    . "ক্রেতা: " . $c_name . "\n"
    . "প্রাপ্ত টাকা: ৳" . number_format($pay_amount, 2) . "\n"
    . $pay_ledger['prev_label'] . ": " . ame_memo_paper_money((float)$pay_ledger['previous'], true) . "\n"
    . $after_label . ": " . ame_memo_paper_money($after) . "\n"
    . "ধন্যবাদ!";
$wa_url = 'https://wa.me/' . ($wa_phone !== '' ? $wa_phone : '') . '?text=' . rawurlencode($wa_text);
$ame_ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.847';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>পেমেন্ট রসিদ #<?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="/assets/css/ame-front-colors.css">
<link rel="stylesheet" href="/assets/css/memo-clear.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: "Noto Sans Bengali", "Segoe UI", Arial, sans-serif;
        background: #E8EEF5;
        color: #111827;
        margin: 0;
        padding: 20px 10px 40px;
        font-size: 13px;
        line-height: 1.5;
    }
    .inv-action-bar {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin: 0 auto 16px;
        max-width: <?php echo $max_width; ?>;
        flex-wrap: wrap;
    }
    .inv-btn {
        min-height: 44px;
        padding: 8px 14px;
        border: 1px solid #D1D5DB;
        border-radius: 10px;
        background: #ffffff;
        color: #003F8F;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 700;
        font-family: inherit;
    }
    .inv-btn-print { background: #003F8F; color: #ffffff; border-color: #003F8F; }
    .inv-btn-pdf { background: #04933A; color: #ffffff; border-color: #04933A; }
    .inv-btn-img { background: #4F46E5; color: #ffffff; border-color: #4F46E5; }
    .inv-btn-share { background: #0284C7; color: #ffffff; border-color: #0284C7; }
    .inv-btn-wa { background: #25D366; color: #ffffff; border-color: #25D366; }
    .inv-btn-tg { background: #229ED9; color: #ffffff; border-color: #229ED9; }
    .inv-btn-back { background: #ffffff; color: #374151; border-color: #D1D5DB; }
    .pay-share-hint {
        max-width: <?php echo $max_width; ?>;
        margin: -6px auto 14px;
        text-align: center;
        color: #0B3D91;
        font-size: 12px;
        font-weight: 700;
    }
    .memo-wrapper {
        background: #ffffff;
        width: 100%;
        max-width: <?php echo $max_width; ?>;
        margin: 0 auto;
        padding: 24px 20px;
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        border: 1px solid #E5E7EB;
    }
    .memo-head { text-align: center; border-bottom: 2px dashed #003F8F; padding-bottom: 14px; margin-bottom: 14px; }
    .memo-shop-name { font-size: 20px; font-weight: 800; margin: 0 0 4px; line-height: 1.25; }
    .memo-shop-desc, .memo-shop-phone { margin: 0; font-weight: 800; }
    .memo-badge { display: inline-block; margin-top: 8px; padding: 3px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; }
    .memo-meta-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 8px 10px;
        margin-bottom: 10px;
    }
    .memo-meta-item { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
    .memo-meta-item .lbl { flex: 0 0 auto; }
    .memo-meta-item .val { text-align: right; min-width: 0; }
    #memo-container .memo-table,
    #ame-memo-capture-clone .memo-table,
    #memo-container .memo-table tbody,
    #ame-memo-capture-clone .memo-table tbody,
    #memo-container .memo-table tr,
    #ame-memo-capture-clone .memo-table tr { display: block; width: 100%; }
    #memo-container .memo-table thead,
    #ame-memo-capture-clone .memo-table thead { display: none; }
    #memo-container .memo-table tr,
    #ame-memo-capture-clone .memo-table tr { padding: 4px 0 8px; border-bottom: 1px solid #E2E8F0; }
    #memo-container .memo-table td,
    #ame-memo-capture-clone .memo-table td {
        display: flex !important;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        padding: 3px 0;
        text-align: right !important;
    }
    #memo-container .memo-table td::before,
    #ame-memo-capture-clone .memo-table td::before {
        content: attr(data-k);
        font-weight: 800;
        text-align: left;
        flex: 0 0 auto;
    }
    #memo-container .memo-table td:nth-child(1)::before,
    #ame-memo-capture-clone .memo-table td:nth-child(1)::before { color: #1D4ED8 !important; -webkit-text-fill-color: #1D4ED8 !important; }
    #memo-container .memo-table td:nth-child(2)::before,
    #ame-memo-capture-clone .memo-table td:nth-child(2)::before { color: #0F766E !important; -webkit-text-fill-color: #0F766E !important; }
    #memo-container .memo-table td:nth-child(3)::before,
    #ame-memo-capture-clone .memo-table td:nth-child(3)::before { color: #C2410C !important; -webkit-text-fill-color: #C2410C !important; }
    #memo-container .memo-table td:nth-child(4)::before,
    #ame-memo-capture-clone .memo-table td:nth-child(4)::before { color: #6D28D9 !important; -webkit-text-fill-color: #6D28D9 !important; }
    .cpay-val { text-align: right; min-width: 0; }
    .sku-text { display: block; font-size: 12px; font-weight: 700; color: #0369A1; }
    .memo-summary { border-top: 1px solid #CBD5E1; padding-top: 8px; }
    .memo-sum-row { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
    .memo-sum-row.sabek-box { flex-direction: column; align-items: stretch; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 8px 10px; margin-top: 6px; }
    .sabek-line, .total-due-line { display: flex; justify-content: space-between; gap: 12px; width: 100%; }
    .sabek-line span:last-child, .total-due-line span:last-child, .memo-sum-row > span:last-child { text-align: right; white-space: nowrap; }
    .memo-foot { text-align: center; margin-top: 14px; padding-top: 10px; border-top: 1px dashed #CBD5E1; }
    .cpay-sheet { width: 100%; max-width: <?php echo $max_width; ?>; margin: 0 auto; }
    .cpay-sheet .memo-wrapper,
    .cpay-sheet .notify-box {
        width: 100%;
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        box-sizing: border-box;
    }
    .notify-box { margin: 14px 0 0; padding: 16px 20px; background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; }
    #cpayReverse { display: flex; flex-direction: column; gap: 8px; align-items: stretch; width: 100%; }
    #cpayReverse > strong { color: #9F1239; font-size: 16px; }
    #cpayReverse > p { margin: 0; color: #1D4ED8; font-weight: 800; }
    .cpay-reason { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .cpay-reason label { margin: 0; color: #C2410C; font-weight: 800; flex: 0 0 auto; }
    .cpay-reason input { flex: 1; min-width: 0; text-align: right; color: #9A3412; font-weight: 800; border: 1.5px solid #C2410C !important; }
    #cpayReverse .ame-pay-sec {
        display: grid !important;
        grid-template-columns: minmax(0, 46%) minmax(0, 1fr) !important;
        gap: 8px 10px !important;
        align-items: center !important;
        margin: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    #cpayReverse .ame-pay-sec-kicker {
        margin: 0 !important;
        color: #7C3AED !important;
        -webkit-text-fill-color: #7C3AED !important;
    }
    #cpayReverse .ame-pay-sec-code {
        width: auto !important;
        margin: 0 !important;
        justify-self: stretch;
        text-align: right !important;
        font-size: 22px !important;
        letter-spacing: .08em !important;
        padding: 4px 8px !important;
    }
    #cpayReverse .ame-pay-sec-click-hint,
    #cpayReverse .ame-pay-sec-hint {
        grid-column: 1 / -1;
        margin: 0 !important;
    }
    #cpayReverse .ame-pay-sec-click-hint {
        color: #0F766E !important;
        -webkit-text-fill-color: #0F766E !important;
    }
    #cpayReverse .ame-pay-sec-hint {
        color: #0369A1 !important;
        -webkit-text-fill-color: #0369A1 !important;
    }
    #cpayReverse .ame-pay-sec-lbl {
        display: block !important;
        margin: 0 !important;
        color: #9F1239 !important;
        -webkit-text-fill-color: #9F1239 !important;
    }
    #cpayReverse .ame-pay-sec-input {
        width: 100% !important;
        min-height: 42px !important;
        margin: 0 !important;
        text-align: right !important;
    }
    #cpayReverse button[type="submit"] { align-self: stretch; width: 100%; box-sizing: border-box; }
    .no-memo-print { display: none !important; }
    @media print {
        .no-print, .no-memo-print { display: none !important; }
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .memo-wrapper { box-shadow: none !important; border: none !important; max-width: 100% !important; }
    }
</style>
</head>
<body>
<nav class="inv-action-bar no-print" aria-label="রসিদ অপশন">
    <button type="button" class="inv-btn inv-btn-print" data-pay-act="print-image">প্রিন্ট</button>
    <button type="button" class="inv-btn inv-btn-pdf" data-pay-act="download">পিডিএফ</button>
    <button type="button" class="inv-btn inv-btn-img" data-pay-act="download-image">ছবি</button>
    <button type="button" class="inv-btn inv-btn-share" data-pay-act="share">শেয়ার</button>
    <a class="inv-btn inv-btn-wa" href="<?php echo htmlspecialchars($wa_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">হোয়াটসঅ্যাপ</a>
    <button type="button" class="inv-btn inv-btn-tg" data-pay-act="share-telegram">টেলিগ্রাম</button>
    <a class="inv-btn inv-btn-back" href="profile.php?id=<?php echo (int)$customer['id']; ?>">প্রোফাইল</a>
</nav>
<p class="pay-share-hint no-print">শেয়ার চাপলে মেমোর ছবি যাবে।</p>

<div class="cpay-sheet">
<div id="memo-container" class="memo-wrapper" data-ame-capture="clone">
    <div class="memo-head">
        <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(72) : ''; ?>
        <h1 class="memo-shop-name"><?php echo htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($shop_address !== '' && !empty($inv['show_address'])): ?>
        <p class="memo-shop-desc"><?php echo htmlspecialchars($shop_address, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($shop_phone !== ''): ?>
        <p class="memo-shop-phone">📞 <?php echo htmlspecialchars($shop_phone, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="memo-badge">পেমেন্ট রসিদ</div>
    </div>

    <div class="memo-meta-grid">
        <div class="memo-meta-item">
            <span class="lbl">রসিদ নম্বর</span>
            <span class="val">#<?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl">তারিখ ও সময়</span>
            <span class="val"><?php echo htmlspecialchars($formatted_when, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl">ক্রেতার নাম</span>
            <span class="val"><span class="memo-customer-name"><?php
                echo htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8');
                echo $c_code !== '' ? ' (' . htmlspecialchars($c_code, ENT_QUOTES, 'UTF-8') . ')' : '';
            ?></span></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl">মোবাইল</span>
            <span class="val"><?php echo $c_phone !== '' ? htmlspecialchars($c_phone, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
        </div>
        <?php if ($reference_no !== ''): ?>
        <div class="memo-meta-item" style="grid-column: 1 / -1;">
            <span class="lbl">রেফারেন্স</span>
            <span class="val"><?php echo htmlspecialchars($reference_no, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <table class="memo-table">
        <thead>
            <tr>
                <th style="width:28px;" class="tc">নং</th>
                <th>বিবরণ</th>
                <th>মাধ্যম</th>
                <th style="width:90px;" class="tr">টাকা</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="tc" data-k="নং"><span class="cpay-val">1</span></td>
                <td data-k="বিবরণ"><span class="cpay-val">
                    <span style="font-weight:600;">পেমেন্ট গ্রহণ</span>
                    <?php if ($pay_note !== ''): ?>
                    <span class="sku-text"><?php echo htmlspecialchars($pay_note, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($account_name !== ''): ?>
                    <span class="sku-text">অ্যাকাউন্ট: <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span></td>
                <td data-k="মাধ্যম"><span class="cpay-val"><?php echo htmlspecialchars($method_text, ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td class="tr" data-k="টাকা"><span class="cpay-val" style="font-weight:700;"><?php echo number_format($pay_amount, 2); ?></span></td>
            </tr>
            <?php if ($pay_discount > 0.009): ?>
            <tr>
                <td class="tc" data-k="নং"><span class="cpay-val">2</span></td>
                <td data-k="বিবরণ"><span class="cpay-val"><span style="font-weight:600;">ডিসকাউন্ট</span></span></td>
                <td data-k="মাধ্যম"><span class="cpay-val">—</span></td>
                <td class="tr" data-k="টাকা"><span class="cpay-val" style="font-weight:700;"><?php echo number_format($pay_discount, 2); ?></span></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="memo-summary">
        <div class="memo-sum-row">
            <span>মোট পেমেন্ট:</span>
            <span>৳<?php echo number_format($pay_amount, 2); ?></span>
        </div>
        <?php if ($pay_discount > 0.009): ?>
        <div class="memo-sum-row" style="color:#DC2626;">
            <span>ডিসকাউন্ট:</span>
            <span>−৳<?php echo number_format($pay_discount, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="memo-sum-row bold">
            <span>নিট জমা:</span>
            <span>৳<?php echo number_format($pay_relief, 2); ?></span>
        </div>
        <div class="memo-sum-row highlight">
            <span>প্রাপ্ত টাকা:</span>
            <span>৳<?php echo number_format($pay_amount, 2); ?></span>
        </div>

        <div class="memo-sum-row sabek-box">
            <div class="sabek-line" style="<?php echo (float)$pay_ledger['previous'] < -0.009 ? 'color:#15803D;font-weight:700;' : ''; ?>">
                <span><?php echo htmlspecialchars((string)$pay_ledger['prev_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money((float)$pay_ledger['previous'], true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if ($pay_discount > 0.009): ?>
            <div class="sabek-line">
                <span>নগদ জমা (−)</span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money(-$pay_amount, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="sabek-line">
                <span>ডিসকাউন্ট (−)</span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money(-$pay_discount, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php else: ?>
            <div class="sabek-line">
                <span>পেমেন্ট (−)</span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money(-$pay_relief, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-due-line" style="color:<?php echo htmlspecialchars($after_color, ENT_QUOTES, 'UTF-8'); ?>;">
                <span><?php echo htmlspecialchars($after_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money($after), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="sabek-line" style="color:#991B1B;font-weight:700;">
                <span>মেমো শেষে অবশিষ্ট বকেয়া</span>
                <span>৳<?php echo number_format($remain_due, 2); ?></span>
            </div>
            <div class="sabek-line" style="color:#166534;font-weight:700;">
                <span>অগ্রিম জমা</span>
                <span>৳<?php echo number_format($advance, 2); ?></span>
            </div>
            <div class="pay-proof ame-paper-proof"><?php echo htmlspecialchars((string)$pay_ledger['formula'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>

    <div class="memo-foot">
        <?php if ($account_name !== '' && !empty($inv['show_payment_method'])): ?>
        <p style="margin:0 0 3px;">পেমেন্ট মাধ্যম: <b><?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?></b></p>
        <?php endif; ?>
        <p class="thanks"><?php echo htmlspecialchars($footer_note, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($payment['created_by'] ?? $payment['received_by'] ?? 0)) : ''; ?>
        <?php
        if (function_exists('ame_provenance_for')) {
            $prov = ame_provenance_for($db, 'payment', $payment_id);
            if (!$prov && function_exists('ame_provenance_record')) {
                ame_provenance_record($db, 'payment', $payment_id, [
                    'amount' => (float)$payment['amount'],
                    'label' => $ref,
                ]);
                $prov = ame_provenance_for($db, 'payment', $payment_id);
            }
            if ($prov && function_exists('ame_provenance_footer')) {
                echo '<div class="no-memo-print" style="margin-top:4px;font-size:9.5px;color:#9CA3AF;">' . ame_provenance_footer($prov) . '</div>';
            }
        }
        ?>
    </div>
</div>

<?php
$pay_err = (string)($_SESSION['cpay_error'] ?? '');
$pay_ok = (string)($_SESSION['cpay_ok'] ?? '');
unset($_SESSION['cpay_error'], $_SESSION['cpay_ok']);
$pay_reversed = function_exists('ame_customer_payment_is_reversed') && ame_customer_payment_is_reversed($payment);
?>
<?php if ($pay_err !== ''): ?>
<div class="notify-box no-print" id="cpayReverse" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;">
    <strong>বাতিল হয়নি</strong>
    <p><?php echo htmlspecialchars($pay_err, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php endif; ?>
<?php if ($pay_ok !== ''): ?>
<div class="notify-box no-print" id="cpayReverse" style="background:#ECFDF5;border-color:#A7F3D0;color:#065F46;">
    <strong>হয়ে গেছে</strong>
    <p><?php echo htmlspecialchars($pay_ok, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php endif; ?>
<?php if ($pay_reversed): ?>
<div class="notify-box no-print" id="cpayReverse">
    <strong>এই রসিদ বাতিল</strong>
    <p><?php echo htmlspecialchars(trim((string)($payment['reversal_reason'] ?? 'পেমেন্ট বাতিল হয়েছে।')), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php elseif (!$pay_reversed): ?>
<form class="notify-box no-print" id="cpayReverse" method="post" action="actions/process_payment_reverse.php">
    <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
    <input type="hidden" name="payment_id" value="<?php echo (int)$payment_id; ?>">
    <strong>ভুল রসিদ বাতিল</strong>
    <p>একই পেমেন্ট দুবার হয়ে গেলে এখানে বাতিল করুন। টাকা অ্যাকাউন্ট থেকে কাটা হবে, কাস্টমারের বাকি ফিরবে।</p>
    <div class="cpay-reason">
        <label for="cpayReason">বাতিলের কারণ</label>
        <input id="cpayReason" name="reason" required maxlength="255" style="box-sizing:border-box;min-height:42px;padding:8px 10px;border-radius:10px;" placeholder="যেমন: একই রসিদ দুবার হয়ে গেছে">
    </div>
    <?php if (function_exists('ame_pay_sec_box')) echo ame_pay_sec_box('customer_reverse', (int)$payment_id, 'বাতিল করতে পর্দার কোড লিখুন।', 'Type the on-screen code to reverse this receipt.'); ?>
    <button type="submit" style="margin-top:10px;min-height:44px;padding:8px 16px;border:0;border-radius:10px;background:#b91c1c;color:#fff;font-weight:800;cursor:pointer;">রসিদ বাতিল করুন</button>
</form>
<?php endif; ?>

<?php if ($current_balance > 0.009): ?>
<div class="notify-box no-print">
    <strong>বকেয়া নোটিফিকেশন</strong>
    <p>বাকি থাকলে এই পেমেন্টের ৮ দিন পর মূল ঘণ্টায় আবার আসবে। নোটিফিকেশন পেজ থেকে পরবর্তী তারিখ দিলে সেই তারিখে আসবে।</p>
</div>
<?php endif; ?>
</div>

<script src="/assets/js/double-submit-guard.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/enter-submit.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/pay-security-autofill.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/ame-memo-file.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    var fileBase = <?php echo json_encode('receipt-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $ref), JSON_UNESCAPED_UNICODE); ?>;
    document.querySelectorAll('[data-pay-act]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var act = btn.getAttribute('data-pay-act');
            if (typeof window.ameMemoCapture === 'function') {
                window.ameMemoCapture(act, fileBase, 'memo-container');
            } else if (act === 'print-image') {
                window.print();
            }
        });
    });
    window.addEventListener('DOMContentLoaded', function () {
        var params = new URLSearchParams(window.location.search);
        var act = '';
        if (params.get('print') === '1') act = 'print-image';
        else if (params.get('share') === '1') act = 'share';
        else if (params.get('download') === '1') act = 'download';
        else if (params.get('image') === '1') act = 'download-image';
        else if (params.get('tg') === '1' || params.get('telegram') === '1') act = 'share-telegram';
        if (!act) return;
        setTimeout(function () {
            if (typeof window.ameMemoCapture === 'function') {
                window.ameMemoCapture(act, fileBase, 'memo-container');
            } else if (act === 'print-image') {
                window.print();
            }
        }, 400);
    });
})();
</script>
<?php
$confirm = __DIR__ . '/../../partials/confirm_actions.php';
if (is_file($confirm)) {
    include $confirm;
}
?>
<?php include __DIR__ . '/../../partials/page_fit.php'; ?>
</body>
</html>
