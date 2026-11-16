<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
require_once __DIR__ . '/../../../includes/memo_lang.php';
ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT r.*, uc.username as created_by_name, ua.username as approved_by_name,
            a.account_name as account_name, s.invoice_number AS sale_invoice
     FROM sale_returns_v2 r
     LEFT JOIN users uc ON r.created_by = uc.id
     LEFT JOIN users ua ON r.approved_by = ua.id
     LEFT JOIN accounts a ON r.account_id = a.id
     LEFT JOIN sales s ON r.sale_id = s.id
     WHERE r.id = ?"
);
if (!$stmt) {
    die('❌ রিটার্ন খোলা যায়নি। <a href="register.php">তালিকায় ফিরুন</a>');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$ret = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ret) {
    die('❌ রিটার্ন খুঁজে পাওয়া যায়নি। <a href="register.php">তালিকায় ফিরুন</a>');
}

$deducted_account_name = trim((string)($ret['account_name'] ?? ''));
$deducted_amount = 0.0;
if ((int)($ret['account_id'] ?? 0) > 0 && ($ret['refund_method'] ?? '') !== 'customer_balance') {
    $deducted_amount = round(abs((float)($ret['customer_adjustment'] ?? 0) < 0 ? (float)$ret['customer_adjustment'] : (float)($ret['return_amount'] ?? 0)), 2);
}
$al_stmt = $db->prepare(
    "SELECT al.amount, al.type, a.account_name as acc_name
     FROM account_ledger al
     LEFT JOIN accounts a ON al.account_id = a.id
     WHERE al.ref_type IN ('sales_return', 'sale_return_v2', 'sale_exchange_v2', 'sale_return') AND al.ref_id = ?
     ORDER BY al.id DESC LIMIT 1"
);
if ($al_stmt) {
    $al_stmt->bind_param('i', $id);
    $al_stmt->execute();
    $al_row = $al_stmt->get_result()->fetch_assoc();
    $al_stmt->close();
    if ($al_row) {
        if (!empty($al_row['acc_name'])) {
            $deducted_account_name = trim((string)$al_row['acc_name']);
        }
        if (($al_row['type'] ?? '') === 'debit') {
            $deducted_amount = round((float)$al_row['amount'], 2);
        }
    }
}

$items = [];
$items_stmt = $db->prepare(
    "SELECT sri.*, p.name as product_name
     FROM sale_return_items sri
     JOIN products p ON sri.product_id = p.id
     WHERE sri.return_id = ?
     ORDER BY sri.is_replacement ASC, sri.id ASC"
);
if ($items_stmt) {
    $items_stmt->bind_param('i', $id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $items_stmt->close();
}

$ame_ret_prev = null;
$ame_ret_cut = round((float)($ret['return_amount'] ?? 0), 2);
$ame_ret_cut_minus = true;
$ame_ret_after = null;
$ame_ret_cid = (int)($ret['customer_id'] ?? 0);
$ame_ret_stored = array_key_exists('previous_due', $ret) && $ret['previous_due'] !== null
    && array_key_exists('balance_after', $ret) && $ret['balance_after'] !== null
    && ($ret['refund_method'] ?? '') === 'customer_balance';
if ($ame_ret_stored) {
    $ame_ret_prev = round((float) $ret['previous_due'], 2);
    $ame_ret_after = round((float) $ret['balance_after'], 2);
    $ame_ret_cut = round((float) ($ret['return_amount'] ?? 0), 2);
    $ame_ret_cut_minus = true;
} elseif ($ame_ret_cid > 0) {
    $led = $db->prepare('SELECT ref_no, debit, credit FROM customer_ledger WHERE customer_id = ? ORDER BY txn_date ASC, id ASC');
    if ($led) {
        $led->bind_param('i', $ame_ret_cid);
        $led->execute();
        $led_res = $led->get_result();
        $run = 0.0;
        $ref = (string)($ret['return_no'] ?? '');
        while ($row = $led_res->fetch_assoc()) {
            $before = $run;
            $run = round($run + (float)$row['debit'] - (float)$row['credit'], 2);
            if ($ref !== '' && (string)$row['ref_no'] === $ref) {
                $ame_ret_prev = $before;
                $ame_ret_after = $run;
                if ((float)$row['debit'] > 0.009) {
                    $ame_ret_cut = round((float)$row['debit'], 2);
                    $ame_ret_cut_minus = false;
                } else {
                    $ame_ret_cut = round((float)$row['credit'], 2);
                    $ame_ret_cut_minus = true;
                }
            }
        }
        $led->close();
    }
}
$ame_ret_phone = '';
$ame_ret_code = '';
if ($ame_ret_cid > 0) {
    $cp = $db->prepare('SELECT phone, customer_code FROM customers WHERE id = ? LIMIT 1');
    if ($cp) {
        $cp->bind_param('i', $ame_ret_cid);
        $cp->execute();
        $cp_row = $cp->get_result()->fetch_assoc();
        $cp->close();
        if ($cp_row) {
            $ame_ret_phone = trim((string)($cp_row['phone'] ?? ''));
            $ame_ret_code = trim((string)($cp_row['customer_code'] ?? ''));
        }
    }
}
$ame_shop_name = trim((string)($shop['shop_name'] ?? ''));
if ($ame_shop_name === '') {
    $ame_shop_name = 'Aponshop';
}
$ame_shop_address = trim((string)($shop['shop_address'] ?? ''));
$ame_shop_phone = trim((string)($shop['shop_phone'] ?? ''));
$ame_ret_sign = static function (float $n): string {
    if (abs($n) < 0.009) {
        return '=';
    }
    $body = number_format(abs($n), 2);
    return ($n < 0 ? '−' : '+') . $body;
};
$ame_ret_pct = ($ame_ret_prev !== null && $ame_ret_prev > 0.009 && $ame_ret_cut > 0.009)
    ? round(($ame_ret_cut / $ame_ret_prev) * 100, 2)
    : null;

include __DIR__ . '/../../../partials/header.php';

$status_meta = [
    'draft' => ['label' => ame_ui('খসড়া', 'Draft'), 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'pending_approval' => ['label' => ame_ui('⏳ অনুমোদনের অপেক্ষায়', '⏳ Waiting for approval'), 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => ame_ui('✅ অনুমোদিত (সম্পন্ন করা বাকি)', '✅ Approved (not finished)'), 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'completed' => ['label' => ame_ui('🎉 সম্পন্ন', '🎉 Completed'), 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'cancelled' => ['label' => ame_ui('❌ বাতিল', '❌ Cancelled'), 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
$meta = $status_meta[$ret['status']] ?? $status_meta['draft'];
?>
<style>
    .rv-wrap { padding: 12px; max-width: 480px; margin: 0 auto; }
    .inv-action-bar { display:flex; justify-content:center; align-items:center; gap:8px; margin:0 auto 16px; flex-wrap:wrap; }
    .inv-btn { min-height:44px; padding:8px 14px; border:1px solid #D1D5DB; border-radius:10px; background:#fff; color:#003F8F; display:inline-flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; text-decoration:none; font-size:13px; font-weight:700; font-family:inherit; box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .inv-btn-print { background:#003F8F; color:#fff; border-color:#003F8F; }
    .inv-btn-pdf { background:#04933A; color:#fff; border-color:#04933A; }
    .inv-btn-img { background:#4F46E5; color:#fff; border-color:#4F46E5; }
    .inv-btn-share { background:#0284C7; color:#fff; border-color:#0284C7; }
    .inv-btn-wa { background:#25D366; color:#fff; border-color:#25D366; }
    .inv-btn-tg { background:#229ED9; color:#fff; border-color:#229ED9; }
    .memo-wrapper { background:#fff; width:100%; max-width:480px; margin:0 auto; padding:24px 20px; border-radius:14px; box-shadow:0 8px 24px rgba(0,0,0,.08); border:1px solid #E5E7EB; overflow:visible; }
    .memo-head { text-align:center; border-bottom:2px dashed #003F8F; padding-bottom:12px; margin-bottom:12px; }
    .memo-shop-name { font-size:20px; font-weight:800; color:#003F8F; margin:0 0 4px; line-height:1.25; }
    .memo-shop-desc, .memo-shop-phone { font-size:15px; color:#111827; margin:0; font-weight:800; line-height:1.45; text-shadow:none; }
    .memo-shop-desc { color:#111827; }
    .memo-badge { display:inline-block; margin-top:8px; padding:3px 14px; background:#003F8F; color:#fff; font-size:12px; font-weight:800; border-radius:20px; }
    .memo-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:10px 12px; margin-bottom:12px; font-size:12px; }
    .memo-meta-item { display:flex; flex-direction:column; line-height:1.35; min-width:0; }
    .memo-meta-item .lbl { font-size:13px; color:#0B1E4D; font-weight:800; }
    .memo-meta-item .val { font-weight:800; color:#0F172A; word-break:break-word; }
    .memo-table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
    .memo-table th { background:#F1F5F9; color:#003F8F; font-weight:800; padding:8px 6px; border-top:1px solid #CBD5E1; border-bottom:1px solid #CBD5E1; text-align:left; }
    .memo-table td { padding:7px 6px; border-bottom:1px solid #E2E8F0; color:#1E293B; vertical-align:top; }
    .memo-table .tc { text-align:center; }
    .memo-table .tr { text-align:right; white-space:nowrap; }
    .memo-table .sku-text { display:block; font-size:13px; color:#0B1E4D; font-weight:700; }
    .memo-summary { border-top:1px solid #CBD5E1; padding-top:8px; }
    .memo-sum-row { display:flex; justify-content:space-between; gap:10px; padding:3px 0; font-size:13px; font-weight:700; }
    .rv-settle { margin:8px 0 0; padding:10px; border:1px solid #FDE68A; border-radius:8px; background:#FFFBEB; color:#111; overflow:visible; }
    .rv-settle .sabek-line { display:flex; justify-content:space-between; align-items:baseline; gap:12px; width:100%; padding:4px 0; font-size:14px; font-weight:800; }
    .rv-settle .sabek-line span { min-width:0; }
    .rv-settle .sabek-line b { font-weight:900; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rv-settle .rv-settle-now { border-top:1px dashed #F59E0B; margin-top:4px; padding-top:6px; color:#991B1B; font-size:16px; }
    .rv-settle .rv-settle-proof { margin-top:8px; text-align:center; font-size:13px; font-weight:900; color:#0B1E4D; line-height:1.45; word-break:break-word; }
    .memo-foot { text-align:center; margin-top:14px; padding-top:10px; border-top:1px dashed #CBD5E1; font-size:14px; color:#0B1E4D; font-weight:700; }
    .rv-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .rv-btn { flex:1; min-width:120px; padding:12px; border:none; border-radius:10px; font-weight:800; font-size:13px; cursor:pointer; }
    .rv-btn-approve { background:linear-gradient(135deg,#0091EA,#4FC3F7); color:#fff; }
    .rv-btn-complete { background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; }
    .rv-btn-cancel { background:#FFEBEE; color:#C62828; }
    .rv-btn-print { background:#EAF4FF; color:#0B1E4D; }
    @media print { .no-print { display:none !important; } .memo-wrapper { box-shadow:none !important; border:none !important; } }
</style>

<?php
$ame_sale_invoice = trim((string) ($ret['sale_invoice'] ?? ''));
$ame_wa_phone = preg_replace('/\D+/', '', $ame_ret_phone);
if (str_starts_with($ame_wa_phone, '0') && strlen($ame_wa_phone) >= 11) {
    $ame_wa_phone = '880' . substr($ame_wa_phone, 1);
} elseif ($ame_wa_phone !== '' && !str_starts_with($ame_wa_phone, '880') && strlen($ame_wa_phone) === 10) {
    $ame_wa_phone = '880' . $ame_wa_phone;
}
$ame_wa_prev = $ame_ret_prev === null ? '' : ame_ui('সাবেক বকেয়া', 'Previous due') . ': ৳' . number_format((float) $ame_ret_prev, 2) . "\n";
$ame_wa_after = $ame_ret_after === null ? '' : ame_ui('সর্বমোট ব্যালেন্স', 'Total balance') . ': ৳' . number_format((float) $ame_ret_after, 2) . "\n";
$ame_wa_text = $ame_shop_name . "\n" . ame_ui('বিক্রয় ফেরত', 'Sales return') . ' ' . (string) $ret['return_no']
    . ($ame_sale_invoice !== '' ? "\n" . ame_ui('ইনভয়েস', 'Invoice') . ': ' . $ame_sale_invoice : '')
    . "\n" . $ame_wa_prev
    . ame_ui('ফেরত', 'Return') . ': −৳' . number_format((float) $ret['return_amount'], 2) . "\n"
    . $ame_wa_after;
$ame_wa_url = 'https://wa.me/' . $ame_wa_phone . '?text=' . rawurlencode($ame_wa_text);
$ame_file_base = 'return-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $ret['return_no']);
?>
<div class="rv-wrap">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="no-print" style="background:#E8F5E9; color:#0B8043; padding:12px 16px; border-radius:10px; margin-bottom:12px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="no-print" style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:12px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <nav class="inv-action-bar no-print" aria-label="<?php echo ame_ui('ফেরত মেমো অপশন', 'Return memo options'); ?>">
        <button type="button" class="inv-btn inv-btn-print" data-ret-act="print-image"><?php echo ame_ui('প্রিন্ট', 'Print'); ?></button>
        <button type="button" class="inv-btn inv-btn-pdf" data-ret-act="download"><?php echo ame_ui('পিডিএফ', 'PDF'); ?></button>
        <button type="button" class="inv-btn inv-btn-img" data-ret-act="download-image"><?php echo ame_ui('ছবি', 'Image'); ?></button>
        <button type="button" class="inv-btn inv-btn-share" data-ret-act="share"><?php echo ame_ui('শেয়ার', 'Share'); ?></button>
        <a class="inv-btn inv-btn-wa" href="<?php echo htmlspecialchars($ame_wa_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo ame_ui('হোয়াটসঅ্যাপ', 'WhatsApp'); ?></a>
        <button type="button" class="inv-btn inv-btn-tg" data-ret-act="share-telegram"><?php echo ame_ui('টেলিগ্রাম', 'Telegram'); ?></button>
    </nav>
    <div class="memo-wrapper" id="memo-container" data-ame-capture="clone">
        <div class="memo-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(56) : ''; ?>
            <h1 class="memo-shop-name"><?php echo htmlspecialchars($ame_shop_name, ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($ame_shop_address !== ''): ?>
            <p class="memo-shop-desc"><?php echo htmlspecialchars($ame_shop_address, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($ame_shop_phone !== ''): ?>
            <p class="memo-shop-phone">📞 <?php echo htmlspecialchars($ame_shop_phone, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="memo-badge"><?php echo ame_ui('বিক্রয় ফেরত মেমো', 'Sales return memo'); ?></div>
        </div>
        <div class="memo-meta-grid">
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('ফেরত নম্বর', 'Return no.'); ?></span>
                <span class="val"><?php echo htmlspecialchars((string)$ret['return_no'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if ($ame_sale_invoice !== ''): ?>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('ইনভয়েস', 'Invoice'); ?></span>
                <span class="val">#<?php echo htmlspecialchars($ame_sale_invoice, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('তারিখ', 'Date'); ?></span>
                <span class="val" id="dateDisplay"><?php echo ame_ui_date((int) strtotime($ret['return_date'] ?? $ret['created_at'])); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('ক্রেতার নাম', 'Customer'); ?></span>
                <span class="val"><span class="memo-customer-name"><?php echo htmlspecialchars($ret['customer_name'] ?: ame_ui('নগদ ক্রেতা', 'Cash customer'), ENT_QUOTES, 'UTF-8'); ?><?php echo $ame_ret_code !== '' ? ' (' . htmlspecialchars($ame_ret_code, ENT_QUOTES, 'UTF-8') . ')' : ''; ?></span></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('মোবাইল', 'Mobile'); ?></span>
                <span class="val"><?php echo $ame_ret_phone !== '' ? htmlspecialchars($ame_ret_phone, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('বিক্রয়ের ধরন', 'Sale type'); ?></span>
                <span class="val"><?php echo htmlspecialchars(ame_ui_sale_type((string)$ret['sales_type']), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('অবস্থা', 'Status'); ?></span>
                <span class="val"><?php echo $meta['label']; ?></span>
            </div>
        </div>
        <?php if (trim((string)($ret['reason'] ?? '')) !== ''): ?>
        <div class="memo-sum-row" style="margin-bottom:8px;"><span><?php echo ame_ui('কারণ', 'Reason'); ?></span><span><?php echo htmlspecialchars((string)$ret['reason'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>
        <table class="memo-table">
            <thead>
                <tr>
                    <th style="width:28px;" class="tc"><?php echo ame_ui('নং', 'No.'); ?></th>
                    <th><?php echo ame_ui('বিবরণ', 'Description'); ?></th>
                    <th style="width:48px;" class="tc"><?php echo ame_ui('পরিমাণ', 'Qty'); ?></th>
                    <th style="width:70px;" class="tr"><?php echo ame_ui('দর', 'Rate'); ?></th>
                    <th style="width:80px;" class="tr"><?php echo ame_ui('মোট', 'Total'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $sl = 1; foreach ($items as $it): ?>
                <tr>
                    <td class="tc"><?php echo $sl++; ?></td>
                    <td>
                        <span style="font-weight:700;"><?php echo htmlspecialchars((string)$it['product_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="sku-text"><?php echo !empty($it['is_replacement']) ? ame_ui('বদলি', 'Replacement') : ame_ui('ফেরত', 'Return'); ?><?php
                            $disc = (float)($it['discount_pct'] ?? 0);
                            if ($disc > 0.009) {
                                echo ' · ' . ame_ui('ছাড়', 'Discount') . ' ' . htmlspecialchars(rtrim(rtrim(number_format($disc, 1), '0'), '.'), ENT_QUOTES, 'UTF-8') . '%';
                            }
                            if (!empty($it['line_reason'])) {
                                echo ' · ' . htmlspecialchars((string)$it['line_reason'], ENT_QUOTES, 'UTF-8');
                            }
                        ?></span>
                    </td>
                    <td class="tc"><?php echo number_format((float)$it['returned_qty'], 2); ?></td>
                    <td class="tr"><?php echo number_format((float)$it['unit_price'], 2); ?></td>
                    <td class="tr" style="font-weight:800;"><?php echo number_format((float)$it['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="memo-summary">
            <div class="memo-sum-row">
                <span><?php echo ame_ui('ফেরতের বিল', 'Return bill'); ?></span>
                <span>৳<?php echo number_format((float)$ret['return_amount'], 2); ?></span>
            </div>
            <?php if ((float)$ret['customer_adjustment'] != 0): ?>
            <div class="memo-sum-row">
                <span><?php echo $ret['customer_adjustment'] > 0 ? ame_ui('বাড়তি প্রদেয়', 'Extra payable') : ame_ui('ক্রেডিট', 'Credit'); ?></span>
                <span>৳<?php echo number_format(abs((float)$ret['customer_adjustment']), 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($deducted_account_name !== '' && ($ret['refund_method'] ?? '') !== 'customer_balance'): ?>
            <div class="memo-sum-row" style="background:#FFF8E1; border:1px solid #FDE68A; border-radius:8px; padding:7px 9px; margin-top:6px; color:#B45309;">
                <span>🏦 <?php echo ame_ui('মাইনাসকৃত একাউন্ট', 'Deducted account'); ?>: <b><?php echo htmlspecialchars($deducted_account_name, ENT_QUOTES, 'UTF-8'); ?></b></span>
                <span style="color:#C62828; font-weight:900;">−৳<?php echo number_format($deducted_amount > 0 ? $deducted_amount : (float)$ret['return_amount'], 2); ?></span>
            </div>
            <?php elseif (($ret['refund_method'] ?? '') === 'customer_balance'): ?>
            <div class="memo-sum-row" style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; padding:7px 9px; margin-top:6px; color:#1E40AF;">
                <span>👤 <?php echo ame_ui('সমন্বয় পদ্ধতি', 'Adjustment method'); ?></span>
                <span style="font-weight:900;"><?php echo ame_ui('গ্রাহক খতিয়ানে সমন্বয়', 'Adjusted on the customer ledger'); ?> (−৳<?php echo number_format((float)$ret['return_amount'], 2); ?>)</span>
            </div>
            <?php endif; ?>
            <?php if ($ame_ret_prev !== null && $ame_ret_after !== null):
                $cut_sign = $ame_ret_cut_minus ? '−' : '+';
                $pct_txt = $ame_ret_pct === null ? '' : rtrim(rtrim(number_format($ame_ret_pct, 2), '0'), '.') . '%';
                $prev_label = $ame_ret_prev > 0.009 ? ame_ui('সাবেক বকেয়া', 'Previous due') : ($ame_ret_prev < -0.009 ? ame_ui('সাবেক জমা', 'Previous deposit') : ame_ui('সাবেক বকেয়া', 'Previous due'));
                $after_label = ame_ui('সর্বমোট ব্যালেন্স', 'Total balance');
                $cut_label = $ame_ret_cut_minus ? ame_ui('ফেরত (−)', 'Return (−)') : ame_ui('ফেরত (+)', 'Return (+)');
                $after_txt = abs($ame_ret_after) < 0.009 ? '=' : $ame_ret_sign($ame_ret_after);
                $formula = $ame_ret_sign($ame_ret_prev) . ' ' . $cut_sign . number_format(abs($ame_ret_cut), 2) . ' = ' . $after_txt;
            ?>
            <div class="rv-settle">
                <div class="sabek-line"><span><?php echo $prev_label; ?></span><b><?php echo $ame_ret_sign($ame_ret_prev); ?></b></div>
                <div class="sabek-line"><span><?php echo $cut_label; ?></span><b><?php echo $cut_sign . number_format(abs($ame_ret_cut), 2); ?></b></div>
                <div class="sabek-line rv-settle-now"><span><?php echo $after_label; ?></span><b><?php echo $after_txt; ?></b></div>
                <div class="rv-settle-proof"><?php echo $formula; ?><?php echo $pct_txt !== '' ? '<br>' . $pct_txt : ''; ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="memo-foot">
            <?php
            $rm_labels = ['customer_balance' => ame_ui('গ্রাহক ব্যালেন্স সমন্বয়', 'Customer balance adjustment'), 'cash_refund' => ame_ui('নগদ ফেরত', 'Cash refund'), 'bank_transfer' => ame_ui('ব্যাংক ট্রান্সফার', 'Bank transfer'), 'mobile_banking' => ame_ui('মোবাইল ব্যাংকিং', 'Mobile banking')];
            $rm = $rm_labels[$ret['refund_method'] ?? 'customer_balance'] ?? (string)($ret['refund_method'] ?? '');
            ?>
            <p style="margin:0 0 3px;"><?php echo ame_ui('ফেরত পদ্ধতি', 'Return method'); ?>: <b><?php echo htmlspecialchars($rm, ENT_QUOTES, 'UTF-8'); ?></b><?php if ($deducted_account_name !== '' && ($ret['refund_method'] ?? '') !== 'customer_balance'): ?> · <?php echo ame_ui('একাউন্ট', 'Account'); ?>: <b><?php echo htmlspecialchars($deducted_account_name, ENT_QUOTES, 'UTF-8'); ?></b><?php endif; ?></p>
            <?php if (!empty($ret['notes'])): ?>
            <p style="margin:0 0 3px;"><?php echo nl2br(htmlspecialchars((string)$ret['notes'], ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
            <p style="margin:0; font-weight:800; color:#0B1E4D;"><?php echo ame_ui('ধন্যবাদ, আবার আসবেন!', 'Thank you. Please visit again.'); ?></p>
        </div>
    </div>
    <div class="no-print" style="margin-top:8px; text-align:right;">
        <button type="button" onclick="toggleDateEdit()" style="background:#fff; border:1px solid #CBD5E1; border-radius:8px; padding:6px 10px; font-weight:700; cursor:pointer;"><?php echo ame_ui('তারিখ বদলান', 'Change date'); ?></button>
        <form action="actions/update_return_date.php" method="POST" id="dateEditForm" style="display:none; margin-top:6px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
            <input type="date" name="return_date" value="<?php echo date('Y-m-d', strtotime($ret['return_date'] ?? $ret['created_at'])); ?>" max="<?php echo date('Y-m-d'); ?>" style="padding:6px 8px; border:1.5px solid #CFE3F7; border-radius:6px; font-size:12px;">
            <button type="submit" style="background:#0091EA; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer;"><?php echo ame_ui('সংরক্ষণ', 'Save'); ?></button>
        </form>
    </div>
    <div class="rv-actions no-print">
                <?php if ($ret['status'] === 'pending_approval'): ?>
                    <form action="actions/approve.php" method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="rv-btn rv-btn-approve" style="width:100%;">✅ <?php echo ame_ui('অনুমোদন করুন', 'Approve'); ?></button>
                    </form>
                    <form action="actions/cancel.php" method="POST" style="flex:1;" onsubmit="return confirm(<?php echo json_encode(ame_ui('নিশ্চিত বাতিল করতে চান?', 'Cancel this return?')); ?>);">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="rv-btn rv-btn-cancel" style="width:100%;">❌ <?php echo ame_ui('বাতিল করুন', 'Cancel'); ?></button>
                    </form>
                <?php elseif ($ret['status'] === 'approved'): ?>
                    <form action="actions/complete.php" method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="rv-btn rv-btn-complete" style="width:100%;">🎉 <?php echo ame_ui('সম্পন্ন করুন (স্টক ও লেজার আপডেট)', 'Finish (update stock and ledger)'); ?></button>
                    </form>
                <?php endif; ?>
                <button type="button" class="rv-btn rv-btn-print" onclick="shareVoucher()"><?php echo ame_ui('শেয়ার', 'Share'); ?></button>
            </div>

    <div style="margin-top:16px; text-align:center;" class="no-print">
        <a href="register.php" style="color:#0091EA; text-decoration:none; font-size:14px; font-weight:700;">← <?php echo ame_ui('রেজিস্টারে ফিরে যান', 'Back to the register'); ?></a>
    </div>
</div>

<script>
function toggleDateEdit() {
    var form = document.getElementById('dateEditForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<script src="/assets/js/ame-memo-file.js?v=10.98.847"></script>
<script>
function shareVoucher() {
    if (typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('share', <?php echo json_encode($ame_file_base); ?>, 'memo-container');
    }
}

function downloadVoucherPdf() {
    if (typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('download', <?php echo json_encode($ame_file_base); ?>, 'memo-container');
    }
}

document.querySelectorAll('[data-ret-act]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var act = btn.getAttribute('data-ret-act');
        if (act === 'share') {
            shareVoucher();
            return;
        }
        if (act === 'download') {
            downloadVoucherPdf();
            return;
        }
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture(act, <?php echo json_encode($ame_file_base); ?>, 'memo-container');
        }
    });
});
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
<script>
(function () {
    var root = document.getElementById('ameConfirmActs');
    if (!root) return;
    root.addEventListener('click', function (ev) {
        var hit = ev.target.closest('[data-ame-c]');
        if (!hit) return;
        var act = hit.getAttribute('data-ame-c');
        if (act === 'share') {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            shareVoucher();
        } else if (act === 'down') {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            downloadVoucherPdf();
        }
    }, true);
})();
</script>
