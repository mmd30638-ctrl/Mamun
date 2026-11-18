<?php 
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { 
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; 
}
require_once '../../includes/config.php';
require_once '../../includes/memo_lang.php';
require_once '../../includes/invoice_functions.php';
require_once '../../includes/sales/sale_invoice.php';

$sale_id = (int)($_GET['id'] ?? 0);
$is_admin = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';

// ডাটাবেজ থেকে বিক্রয় ও কাস্টমার বিস্তারিত লোড
$stmt = $db->prepare(
    "SELECT s.*, a.account_name, 
            c.name AS customer_name, c.phone AS customer_phone, c.customer_code, c.address AS customer_address,
            c.is_supplier, c.payable_balance
     FROM sales s 
     LEFT JOIN accounts a ON s.account_id = a.id 
     LEFT JOIN customers c ON s.customer_id = c.id 
     WHERE s.id = ?"
);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    die('❌ ' . ame_ui('এই ইনভয়েস খুঁজে পাওয়া যায়নি।', 'This invoice was not found.') . ' <a href="pos.php">' . ame_ui('বিক্রয়ে ফিরে যান', 'Back to sales') . '</a>');
}

$inv_no = trim((string)($sale['invoice_number'] ?? ''));
if ($inv_no === '') {
    $inv_no = 'INV-' . $sale_id;
}

if (function_exists('log_activity')) {
    try {
        $isPrint = (isset($_GET['print']) && $_GET['print'] === '1');
        log_activity($isPrint ? 'Invoice Print' : 'Invoice View', 'Sales', $inv_no, [
            'action_type' => $isPrint ? 'print' : 'view',
            'entity_type' => 'invoice',
            'entity_id' => (string)$sale_id,
            'reference' => $inv_no,
            'party_type' => 'customer',
            'party_id' => (int)($sale['customer_id'] ?? 0),
            'amount_total' => isset($sale['net_payable']) ? (float)$sale['net_payable'] : null,
            'amount_paid' => isset($sale['paid_amount']) ? (float)$sale['paid_amount'] : null,
            'amount_due' => isset($sale['due_amount']) ? (float)$sale['due_amount'] : null,
            'amount_discount' => isset($sale['discount']) ? (float)$sale['discount'] : null,
        ]);
        if ($isPrint && isset($_GET['beacon'])) {
            http_response_code(204);
            exit;
        }
    } catch (Throwable $e) {
        error_log('invoice audit skipped: ' . $e->getMessage());
    }
}

// বিক্রিত পণ্যসমূহ লোড
$items_stmt = $db->prepare(
    "SELECT si.*, COALESCE(NULLIF(si.item_name, ''), p.name) AS name, p.sku 
     FROM sale_items si 
     LEFT JOIN products p ON p.id = si.product_id 
     WHERE si.sale_id = ? 
     ORDER BY si.id ASC"
);
$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items_res = $items_stmt->get_result();
$items = [];
while ($it = $items_res->fetch_assoc()) {
    $items[] = $it;
}
$items_stmt->close();

$memo_items_per_page = 15;
if ($items === []) {
    $memo_item_chunks = [[]];
} elseif (count($items) > $memo_items_per_page) {
    $memo_item_chunks = array_chunk($items, $memo_items_per_page);
} else {
    $memo_item_chunks = [$items];
}
$memo_item_page_total = count($memo_item_chunks);

// পুরাতন ব্যাটারি এক্সচেঞ্জ আইটেম (যদি থাকে)
if (!function_exists('ame_sale_load_exchange_items')) {
    require_once '../../includes/sales/sale_exchange.php';
}
$ex_rows = ame_sale_load_exchange_items($db, $sale_id);
$ex_amount = (float)($sale['exchange_amount'] ?? 0);
$svc_rows = ame_sale_load_invoice_services(
    $db,
    $sale_id,
    (float)($sale['service_amount'] ?? 0)
);

// ইনভয়েস সেটিংস
$inv = get_invoice_settings($db);
$accent = get_invoice_accent_hex($inv['accent_color']);
$paper_width = $inv['paper_width'] ?? '80mm';
$max_width = ($paper_width === 'A4') ? '720px' : '480px';
$paper_class = ($paper_width === 'A4')
    ? 'ame-paper-a4'
    : (($paper_width === '58mm') ? 'ame-paper-58mm' : 'ame-paper-80mm');
$ame_ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.847';

// কাস্টমার তথ্য
$c_name = trim((string)($sale['customer_name'] ?? ''));
$g_name = trim((string)($sale['guest_customer_name'] ?? ''));
if ($c_name === '' && $g_name !== '') {
    $c_name = $g_name;
}
if ($c_name === '') {
    $c_name = 'খুচরা ক্রেতা';
}
$c_phone = trim((string)($sale['customer_phone'] ?? $sale['guest_customer_phone'] ?? ''));
$c_code = trim((string)($sale['customer_code'] ?? ''));
if ($c_code === '' && !empty($sale['customer_id'])) {
    $c_code = '#' . (int)$sale['customer_id'];
}
$vehicle_no = trim((string)($sale['vehicle_no'] ?? ''));

// হিসাব
$total_bill = (float)($sale['total_bill'] ?? 0);
$discount = (float)($sale['discount'] ?? 0);
$net_payable = (float)($sale['net_payable'] ?? 0);
$paid_amount = (float)($sale['paid_amount'] ?? 0);
if (function_exists('ame_sale_balance_apply')) {
    $sale = ame_sale_balance_apply($db, $sale);
}
if (!function_exists('ame_memo_paper_rows_html')) {
    require_once __DIR__ . '/../../includes/memo_paper.php';
}
$sale_book = $sale['balance_book'] ?? ame_balance_book(
    'sale',
    $sale['previous_customer_balance'] ?? 0,
    $net_payable,
    $paid_amount,
    $sale['customer_balance_after'] ?? null,
    $sale['due_adjustment_amount'] ?? 0
);
$memo_prev = (float)$sale_book['previous'];
$memo_cur = (float)$sale_book['current'];
$memo_paid = (float)$sale_book['paid'];
$memo_extra = (float)$sale_book['extra'];
$memo_total = (float)$sale_book['total'];

$sale_ts = strtotime((string)($sale['completed_at'] ?: $sale['sale_date'] ?: $sale['created_at'] ?: ''));
$formatted_date = ame_ui_date($sale_ts ?: time());
$formatted_time = ame_ui_time($sale_ts ?: time());

// WhatsApp লিংক
$wa_phone = preg_replace('/\D+/', '', $c_phone);
if (str_starts_with($wa_phone, '0') && strlen($wa_phone) >= 11) {
    $wa_phone = '880' . substr($wa_phone, 1);
} elseif ($wa_phone !== '' && !str_starts_with($wa_phone, '880') && strlen($wa_phone) === 10) {
    $wa_phone = '880' . $wa_phone;
}
$wa_dep = '';
if (function_exists('ame_memo_deposit_slice')) {
    $wa_dep_row = ame_memo_deposit_slice($sale_book);
    if (!empty($wa_dep_row['show'])) {
        $wa_dep = ame_ui('জমা ছিল', 'Deposit was') . ': +৳' . number_format((float) $wa_dep_row['before'], 2) . "\n"
            . ame_ui('এই বিল থেকে কাটা', 'Cut from this bill') . ': −৳' . number_format((float) $wa_dep_row['used'], 2) . "\n"
            . ame_ui('রয়ে গেল', 'Left') . ': +৳' . number_format((float) $wa_dep_row['after'], 2) . "\n";
    }
}
if ($memo_total < -0.009) {
    $wa_due_line = ame_ui('দোকানের অবশিষ্ট দেনা', 'Shop still owes') . ': ৳' . number_format(abs($memo_total), 2) . "\n";
} elseif ($memo_total > 0.009) {
    $wa_due_line = ame_ui('মোট বাকি', 'Total due') . ': ৳' . number_format($memo_total, 2) . "\n";
} else {
    $wa_due_line = ame_ui('মোট বাকি', 'Total due') . ': ৳0.00 (' . ame_ui('পরিশোধিত', 'Paid') . ")\n";
}
$party_role_label = !empty($sale['is_supplier']) ? ' (' . ame_ui('সরবরাহকারী', 'Supplier') . ')' : '';
$wa_svc = '';
if (!empty($svc_rows)) {
    $wa_svc = ame_ui('ইঞ্জিনিয়ার ও কাজের বিল', 'Engineer and job bill') . ":\n";
    foreach ($svc_rows as $svc) {
        $eng = trim((string)($svc['engineer_name'] ?? ''));
        $typ = trim((string)($svc['service_type'] ?? ''));
        $line = $eng !== '' ? $eng : ame_ui('ইঞ্জিনিয়ার', 'Engineer');
        if ($typ !== '') {
            $line .= ' — ' . $typ;
        }
        $wa_svc .= $line . ': ৳' . number_format((float)($svc['amount'] ?? 0), 2) . "\n";
    }
}
$wa_text = ($shop['shop_name'] ?? 'Aponshop') . "\n"
    . ame_ui('বিক্রয় ইনভয়েস', 'Sales invoice') . ': #' . $inv_no . "\n"
    . ame_ui('তারিখ', 'Date') . ': ' . $formatted_date . ' ' . $formatted_time . "\n"
    . ame_ui('ক্রেতা/পার্টি', 'Customer/party') . ': ' . $c_name . $party_role_label . "\n"
    . ame_ui('মোট বিল', 'Total bill') . ': ৳' . number_format($total_bill, 2) . "\n"
    . $wa_svc
    . ame_ui('পরিশোধ', 'Paid') . ': ৳' . number_format($paid_amount, 2) . "\n"
    . $wa_dep
    . $wa_due_line
    . ame_ui('ধন্যবাদ!', 'Thank you!');
if (!function_exists('ame_memo_public_url')) {
    require_once '../../includes/memo_access.php';
}
$ame_public_memo = ame_memo_public_url((int) $sale_id);
$wa_text .= "\n" . ame_ui('মেমো', 'Memo') . ': ' . $ame_public_memo;
$wa_url = 'https://wa.me/' . ($wa_phone !== '' ? $wa_phone : '') . '?text=' . rawurlencode($wa_text);
$tg_url = 'https://t.me/share/url?url=' . rawurlencode($ame_public_memo) . '&text=' . rawurlencode($wa_text);
?>
<!DOCTYPE html>
<html lang="<?php echo ame_ui_bn() ? 'bn' : 'en'; ?>" data-ame-memo-standalone="1" class="ame-memo-standalone-fit">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <script>
    (function () {
        var root = document.documentElement;
        root.setAttribute('data-ame-memo-standalone', '1');
        root.classList.add('ame-memo-standalone-fit');
        var meta = document.querySelector('meta[name="viewport"]');
        if (meta) {
            meta.setAttribute('content', 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover');
        }
    })();
    </script>
    <script src="/assets/js/ame-desktop-site.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
    if (typeof window.ameApplySaleDesktopViewport === 'function') { window.ameApplySaleDesktopViewport(); }
    </script>
    <title><?php echo ame_ui('ইনভয়েস', 'Invoice'); ?> #<?php echo htmlspecialchars($inv_no, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($shop['shop_name'] ?? 'Aponshop', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css">
    <link rel="stylesheet" href="/assets/css/memo-clear.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/invoice-print-fix.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
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
            width: 100%;
            max-width: 100%;
            overflow-x: clip;
        }

        /* প্রিন্ট ও একশন বার */
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
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            transition: all 0.2s ease;
        }
        .inv-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .inv-btn svg { width: 18px; height: 18px; display: block; flex-shrink: 0; }
        .inv-btn-print { background: #003F8F; color: #ffffff; border-color: #003F8F; }
        .inv-btn-print:hover { background: #002e6b; color: #ffffff; }
        .inv-btn-pdf { background: #04933A; color: #ffffff; border-color: #04933A; }
        .inv-btn-pdf:hover { background: #03772f; color: #ffffff; }
        .inv-btn-img { background: #4F46E5; color: #ffffff; border-color: #4F46E5; }
        .inv-btn-img:hover { background: #4338CA; color: #ffffff; }
        .inv-btn-share { background: #0284C7; color: #ffffff; border-color: #0284C7; }
        .inv-btn-share:hover { background: #0369A1; color: #ffffff; }
        .inv-btn-wa { background: #25D366; color: #ffffff; border-color: #25D366; }
        .inv-btn-wa:hover { background: #1EBE5D; color: #ffffff; }
        .inv-btn-tg { background: #229ED9; color: #ffffff; border-color: #229ED9; }
        .inv-btn-tg:hover { background: #1b85b8; color: #ffffff; }
        .inv-btn-edit { background: #D97706; color: #ffffff; border-color: #D97706; }
        .inv-btn-edit:hover { background: #B45309; color: #ffffff; }
        .inv-btn-back { background: #ffffff; color: #374151; border-color: #D1D5DB; }

        /* প্রফেশনাল মেমো কন্টেইনার */
        .memo-wrapper {
            background: #ffffff;
            width: 100%;
            max-width: <?php echo $max_width; ?>;
            margin: 0 auto;
            padding: 24px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
            position: relative;
        }

        /* মেমো হেডার */
        .memo-head {
            text-align: center;
            border-bottom: 2px dashed #003F8F;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }
        .memo-logo {
            max-height: 58px;
            margin-bottom: 6px;
        }
        .memo-shop-name {
            font-size: 20px;
            font-weight: 800;
            color: #003F8F;
            margin: 0 0 4px;
            line-height: 1.2;
        }
        .memo-shop-desc {
            font-size: 15px;
            color: #111827;
            font-weight: 800;
            line-height: 1.45;
            text-shadow: none;
            margin: 0 0 4px;
        }
        .memo-shop-phone {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            line-height: 1.45;
            margin: 0;
        }
        .memo-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 14px;
            background: #003F8F;
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 800;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        /* ইনভয়েস ও কাস্টমার মেটা — বামে লেবেল, ডানে মান, দুই কলাম, ছোট */
        .memo-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 10px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 5px 8px;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .memo-meta-item {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: baseline;
            gap: 6px;
            line-height: 1.25;
            min-width: 0;
        }
        .memo-meta-item .lbl {
            font-size: 13px;
            color: #0B1E4D;
            font-weight: 800;
            flex-shrink: 0;
        }
        .memo-meta-item .val {
            font-weight: 800;
            font-size: 14px;
            color: #0F172A;
            text-align: right;
            word-break: break-word;
            min-width: 0;
        }
        .memo-meta-wide { grid-column: 1 / -1; }
        .memo-party-tag {
            background: #E0F2FE;
            color: #0369A1;
            padding: 0 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            margin-left: 3px;
        }

        /* টেবিল ডিজাইন */
        .memo-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 12px;
        }
        .memo-table th {
            background: #F1F5F9;
            color: #003F8F;
            font-weight: 800;
            padding: 8px 6px;
            border-top: 1px solid #CBD5E1;
            border-bottom: 1px solid #CBD5E1;
            text-align: left;
        }
        .memo-table td {
            padding: 7px 6px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: middle;
        }
        .memo-table tr:nth-child(even) td {
            background: #FAFBFD;
        }
        .memo-table .tc { text-align: center; }
        .memo-table .tr { text-align: right; }
        .memo-table .sku-text { display: none; }

        /* পণ্য ১৫ লাইনের পর পরবর্তী পৃষ্ঠা (প্রিন্ট / স্ক্রিন) */
        .memo-items-sheet-break {
            break-after: page;
            page-break-after: always;
        }
        .memo-sheet-cont {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin: 0 0 10px;
            padding: 7px 10px;
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            color: #003F8F;
        }
        @media screen {
            .memo-items-sheet-break {
                margin-bottom: 22px;
                padding-bottom: 16px;
                border-bottom: 2px dashed #94A3B8;
            }
        }
        @media print {
            .memo-wrapper,
            #memo-container.memo-wrapper {
                overflow: visible !important;
                max-height: none !important;
            }
            .memo-items-sheet-break {
                page-break-after: always;
                break-after: page;
            }
        }

        /* সামারি ও মোট হিসাব ব্লক */
        .memo-summary {
            border-top: 1px solid #CBD5E1;
            padding-top: 10px;
            margin-top: 8px;
        }
        .memo-sum-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 3px 0;
            font-size: 12.5px;
        }
        .memo-sum-row.bold {
            font-weight: 800;
        }
        .memo-sum-row.highlight {
            background: #F0FDF4;
            color: #166534;
            font-weight: 800;
            font-size: 13px;
            padding: 6px 10px;
            border-radius: 6px;
            margin: 4px 0;
            border: 1px solid #BBF7D0;
        }
        .memo-sum-row.sabek-box {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 8px;
            padding: 8px 10px;
            margin-top: 6px;
            flex-direction: column;
            gap: 4px;
        }
        .memo-sum-row.sabek-box .sabek-line {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 12px;
        }
        .memo-sum-row.sabek-box .total-due-line {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 13.5px;
            font-weight: 800;
            color: #991B1B;
            border-top: 1px dashed #F59E0B;
            padding-top: 4px;
            margin-top: 3px;
        }

        /* ফুটার */
        .memo-foot {
            text-align: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #CBD5E1;
            font-size: 14px;
            color: #0B1E4D;
            font-weight: 700;
            line-height: 1.4;
        }

        /* প্রিন্ট বা মেমো ডাউনলোডে অপ্রয়োজনীয় টেক্সট লুকানোর ক্লাস */
        .no-memo-print {
            display: none !important;
        }

        /* প্রিন্ট মিডিয়া */
        @media print {
            .no-print, .no-memo-print { display: none !important; }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .memo-wrapper {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }

        /* কম্পিউটার / হোয়াটসঅ্যাপ ওয়েবভিউ: অর্ধেক ফাঁকা ঠিক — পূর্ণ প্রস্থ, মেমো মাঝখানে */
        html.ame-memo-standalone-fit,
        html[data-ame-memo-standalone="1"] {
            width: 100% !important;
            max-width: 100% !important;
            min-height: 100%;
            background: #E8EEF5 !important;
        }
        .ame-invoice-viewport-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            background: #E8EEF5;
            z-index: -2;
            pointer-events: none;
        }
        html.ame-memo-standalone-fit,
        html.ame-memo-standalone-fit body.ame-sales-invoice-page,
        html.ame-phone-desktop.ame-memo-standalone-fit body.ame-sales-invoice-page {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            transform: none !important;
            zoom: 1 !important;
            overflow-x: hidden !important;
        }
        html.ame-memo-standalone-fit body.ame-sales-invoice-page {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            min-height: 100vh !important;
            background: #E8EEF5 !important;
        }
        .ame-invoice-memo-shell {
            width: 100%;
            max-width: <?php echo $max_width; ?>;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        html body.ame-sales-invoice-page .ame-invoice-memo-shell,
        html body.ame-sales-invoice-page .inv-action-bar,
        html body.ame-sales-invoice-page #memo-container.memo-wrapper {
            width: min(100%, <?php echo $max_width; ?>) !important;
            max-width: <?php echo $max_width; ?> !important;
            margin-left: auto !important;
            margin-right: auto !important;
            transform: none !important;
            position: relative !important;
            left: auto !important;
            right: auto !important;
            z-index: 1 !important;
        }
        #ame-memo-capture-host {
            position: absolute !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
        }
    </style>
</head>
<body class="ame-sales-invoice-page <?php echo htmlspecialchars($paper_class, ENT_QUOTES, 'UTF-8'); ?>" data-paper="<?php echo htmlspecialchars((string)$paper_width, ENT_QUOTES, 'UTF-8'); ?>" data-ame-memo-standalone="1">
    <div class="ame-invoice-viewport-bg" aria-hidden="true"></div>

    <div class="ame-invoice-memo-shell">
    <!-- শীর্ষ একশন বার (প্রিন্ট, শেয়ার, ডাউনলোড) -->
    <nav class="inv-action-bar no-print" aria-label="<?php echo ame_ui('ইনভয়েস অপশন', 'Invoice options'); ?>">
        <button type="button" class="inv-btn inv-btn-print" data-inv-act="print-image" title="প্রিন্ট করুন">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V4h10v4" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="5" y="8" width="14" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 14h10v6H7z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span><?php echo ame_ui('প্রিন্ট', 'Print'); ?></span>
        </button>

        <button type="button" class="inv-btn inv-btn-pdf" data-inv-act="download" title="PDF ডাউনলোড">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m8 11 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 19h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span><?php echo ame_ui('পিডিএফ', 'PDF'); ?></span>
        </button>

        <button type="button" class="inv-btn inv-btn-img" data-inv-act="download-image" title="ছবি (PNG) ডাউনলোড">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><path d="m21 15-5-5L5 21" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span><?php echo ame_ui('ছবি', 'Image'); ?></span>
        </button>

        <button type="button" class="inv-btn inv-btn-share" data-inv-act="share" title="শেয়ার করুন">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="17" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m8 11 7-3M8 13l7 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span><?php echo ame_ui('শেয়ার', 'Share'); ?></span>
        </button>

        <a class="inv-btn inv-btn-wa" href="<?php echo htmlspecialchars($wa_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" title="<?php echo ame_ui('হোয়াটসঅ্যাপে পাঠান', 'Send on WhatsApp'); ?>">
            <span><?php echo ame_ui('হোয়াটসঅ্যাপ', 'WhatsApp'); ?></span>
        </a>

        <button type="button" class="inv-btn inv-btn-tg" data-inv-act="share-telegram" title="<?php echo ame_ui('টেলিগ্রামে মেমো শেয়ার করুন', 'Share the memo on Telegram'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .37z"/></svg>
            <span><?php echo ame_ui('টেলিগ্রাম', 'Telegram'); ?></span>
        </button>

        <?php /* সরাসরি edit নতুন sale বানাত; stock ও কমিশন ডাবল হওয়া ঠেকাতে লিংকটি অবসর দেওয়া হয়েছে। */ ?>

        <a class="inv-btn inv-btn-back" href="pos.php" title="<?php echo ame_ui('বিক্রয় কাউন্টারে ফিরে যান', 'Back to the sales counter'); ?>">
            <span><?php echo ame_ui('ডেস্ক', 'Desk'); ?></span>
        </a>
    </nav>

    <!-- মূল মেমো কন্টেইনার (এটি ছবি বা PDF হবে) -->
    <div id="memo-container" class="memo-wrapper" data-ame-capture="clone" data-ame-file-base="<?php echo htmlspecialchars(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$inv_no), ENT_QUOTES, 'UTF-8'); ?>">
        <!-- শপ ব্র্যান্ডিং ও হেডার -->
        <div class="memo-head">
            <?php echo shop_print_logo_tag(56); ?>
            <h1 class="memo-shop-name"><?php echo htmlspecialchars($shop['shop_name'] ?? 'Aponshop', ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if (!empty($shop['shop_address']) && !empty($inv['show_address'])): ?>
            <p class="memo-shop-desc"><?php echo htmlspecialchars($shop['shop_address'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($shop['shop_phone'])): ?>
            <p class="memo-shop-phone">📞 <?php echo htmlspecialchars($shop['shop_phone'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="memo-badge"><?php echo ame_ui('বিক্রয় ইনভয়েস', 'Sales invoice'); ?></div>
        </div>

        <!-- মেটা তথ্য: বামে লেবেল, ডানে মান — ছোট কাস্টমার ব্লক -->
        <div class="memo-meta-grid">
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('ইনভয়েস', 'Invoice'); ?></span>
                <span class="val">#<?php echo htmlspecialchars($inv_no, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('তারিখ', 'Date'); ?></span>
                <span class="val"><?php echo htmlspecialchars($formatted_date . ' ' . $formatted_time, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo !empty($sale['is_supplier']) ? ame_ui('পার্টি', 'Party') : ame_ui('ক্রেতা', 'Customer'); ?></span>
                    <span class="val">
                    <span class="memo-customer-name"><?php echo htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($sale['is_supplier'])): ?>
                    <span class="memo-party-tag"><?php echo ame_ui('সরবরাহকারী', 'Supplier'); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo ame_ui('মোবাইল', 'Mobile'); ?></span>
                <span class="val"><?php echo $c_phone !== '' ? htmlspecialchars($c_phone, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
            </div>
            <?php if ($vehicle_no !== ''): ?>
            <div class="memo-meta-item memo-meta-wide">
                <span class="lbl"><?php echo ame_ui('গাড়ি', 'Vehicle'); ?></span>
                <span class="val"><?php echo htmlspecialchars($vehicle_no, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- পণ্যের তালিকা — ১৫ লাইনের বেশি হলে পরবর্তী পৃষ্ঠায় -->
        <?php
        $memo_sl = 1;
        foreach ($memo_item_chunks as $chunk_idx => $chunk_rows):
            $chunk_page = (int)$chunk_idx + 1;
            $chunk_break = $chunk_page < $memo_item_page_total;
            ?>
        <div class="memo-items-sheet<?php echo $chunk_break ? ' memo-items-sheet-break' : ''; ?>">
            <?php if ($chunk_idx > 0): ?>
            <div class="memo-sheet-cont">
                <span>#<?php echo htmlspecialchars($inv_no, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo ame_ui(
                    'পৃষ্ঠা ' . $chunk_page . ' / ' . $memo_item_page_total . ' — অব্যাহত',
                    'Page ' . $chunk_page . ' / ' . $memo_item_page_total . ' — continued'
                ); ?></span>
            </div>
            <?php endif; ?>
            <table class="memo-table memo-table-items">
                <thead>
                    <tr>
                        <th style="width: 28px;" class="tc"><?php echo ame_ui('নং', 'No.'); ?></th>
                        <th><?php echo ame_ui('বিবরণ', 'Description'); ?></th>
                        <th style="width: 48px;" class="tc"><?php echo ame_ui('পরিমাণ', 'Qty'); ?></th>
                        <th style="width: 70px;" class="tr"><?php echo ame_ui('দর', 'Rate'); ?></th>
                        <th style="width: 80px;" class="tr"><?php echo ame_ui('মোট', 'Total'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($chunk_rows === []): ?>
                    <tr>
                        <td colspan="5" class="tc" style="padding: 12px; color: #0B1E4D; font-weight: 800;"><?php echo ame_ui('কোনো পণ্য তালিকাভুক্ত নেই', 'No product is listed'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($chunk_rows as $row): ?>
                    <tr>
                        <td class="tc"><?php echo $memo_sl++; ?></td>
                        <td>
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td class="tc"><?php echo htmlspecialchars((string)($row['qty'] ?? 1), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="tr"><?php echo number_format((float)($row['sale_price'] ?? 0), 2); ?></td>
                        <td class="tr" style="font-weight: 700;"><?php echo number_format((float)($row['sale_price'] ?? 0) * (float)($row['qty'] ?? 1), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- পুরাতন ব্যাটারি এক্সচেঞ্জ (যদি থাকে) -->
        <?php if (!empty($ex_rows)): ?>
        <div style="margin: 10px 0 6px; font-weight: 800; color: #003F8F; font-size: 15px;">🔄 <?php echo ame_ui('পুরাতন ব্যাটারি গ্রহণ / এক্সচেঞ্জ', 'Old battery intake / exchange'); ?>:</div>
        <table class="memo-table">
            <thead>
                <tr>
                    <th><?php echo ame_ui('বিবরণ', 'Description'); ?></th>
                    <th style="width: 50px;" class="tc"><?php echo ame_ui('পরিমাণ', 'Qty'); ?></th>
                    <th style="width: 80px;" class="tr"><?php echo ame_ui('মোট সমন্বয়', 'Adjustment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ex_rows as $ex): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ex['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="tc"><?php echo htmlspecialchars((string)$ex['qty'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="tr" style="color: #DC2626; font-weight: 700;">−৳<?php echo number_format((float)$ex['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($svc_rows)): ?>
        <div style="margin: 10px 0 6px; font-weight: 800; color: #003F8F; font-size: 15px;"><?php echo ame_ui('ইঞ্জিনিয়ার ও কাজের বিল', 'Engineer and job bill'); ?></div>
        <table class="memo-table">
            <thead>
                <tr>
                    <th style="width: 28px;" class="tc"><?php echo ame_ui('নং', 'No.'); ?></th>
                    <th><?php echo ame_ui('ইঞ্জিনিয়ার', 'Engineer'); ?></th>
                    <th><?php echo ame_ui('কাজের বিবরণ', 'Job details'); ?></th>
                    <th style="width: 80px;" class="tr"><?php echo ame_ui('টাকা', 'Amount'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $svc_sl = 1; foreach ($svc_rows as $svc): ?>
                <tr>
                    <td class="tc"><?php echo $svc_sl++; ?></td>
                    <td><?php
                        $eng = trim((string)($svc['engineer_name'] ?? ''));
                        echo $eng !== '' ? htmlspecialchars($eng, ENT_QUOTES, 'UTF-8') : '—';
                    ?></td>
                    <td><?php
                        $typ = trim((string)($svc['service_type'] ?? ''));
                        echo $typ !== '' ? htmlspecialchars($typ, ENT_QUOTES, 'UTF-8') : '—';
                    ?></td>
                    <td class="tr" style="font-weight: 700;"><?php echo number_format((float)($svc['amount'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- সামারি ও পেমেন্ট হিসাব -->
        <div class="memo-summary">
            <div class="memo-sum-row">
                <span><?php echo ame_ui('মোট বিল', 'Total bill'); ?>:</span>
                <span>৳<?php echo number_format($total_bill, 2); ?></span>
            </div>

            <?php if ($discount > 0.009): ?>
            <div class="memo-sum-row" style="color: #DC2626;">
                <span><?php echo ame_ui('ডিসকাউন্ট', 'Discount'); ?>:</span>
                <span>−৳<?php echo number_format($discount, 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="memo-sum-row bold" style="font-size: 13px; color: #003F8F; border-top: 1px solid #E2E8F0; padding-top: 4px;">
                <span><?php echo ame_ui('নিট প্রদেয়', 'Net payable'); ?>:</span>
                <span>৳<?php echo number_format($net_payable, 2); ?></span>
            </div>

            <?php if ($ex_amount > 0.009): ?>
            <div class="memo-sum-row" style="color: #059669;">
                <span><?php echo ame_ui('পুরাতন ব্যাটারি এক্সচেঞ্জ', 'Old battery exchange'); ?>:</span>
                <span>−৳<?php echo number_format($ex_amount, 2); ?></span>
            </div>
            <div class="memo-sum-row">
                <span><?php echo ame_ui('নগদ/অ্যাকাউন্ট পরিশোধ', 'Cash/account payment'); ?>:</span>
                <span>৳<?php echo number_format((float)($sale['cash_paid_amount'] ?? 0), 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="memo-sum-row highlight">
                <span><?php echo ame_ui('পরিশোধিত টাকা', 'Amount paid'); ?>:</span>
                <span>৳<?php echo number_format($paid_amount, 2); ?></span>
            </div>

            <?php echo ame_memo_paper_rows_html($sale_book); ?>
        </div>

        <!-- ফুটার বার্তা -->
        <div class="memo-foot">
            <?php if (!empty($sale['account_name']) && !empty($inv['show_payment_method'])): ?>
            <p style="margin: 0 0 3px;"><?php echo ame_ui('পেমেন্ট মাধ্যম', 'Payment method'); ?>: <b><?php echo htmlspecialchars($sale['account_name'], ENT_QUOTES, 'UTF-8'); ?></b></p>
            <?php endif; ?>
            <p style="margin: 0 0 3px; font-weight: 800; color: #0B1E4D;"><?php echo htmlspecialchars($inv['footer_note'] ?? ame_ui('ধন্যবাদ, আবার আসবেন!', 'Thank you. Please visit again.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($sale['created_by'] ?? 0)) : ''; ?>
            <?php
            $prov = ame_provenance_for($db, 'sale', $sale_id);
            if (!$prov) {
                $prov_stamp = ame_provenance_record($db, 'sale', $sale_id, [
                    'amount' => $net_payable,
                    'label' => $inv_no,
                ]);
                $prov = $prov_stamp ? ame_provenance_for($db, 'sale', $sale_id) : null;
            }
            if ($prov) {
                echo '<div class="no-memo-print" style="margin-top:4px;font-size:9.5px;color:#9CA3AF;">' . ame_provenance_footer($prov) . '</div>';
            }
            ?>
        </div>
    </div>
    </div>

    <!-- স্ক্রিপ্ট লোডার -->
    <script src="/assets/js/ame-memo-file.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
    (function () {
        var saleId = <?php echo (int)$sale_id; ?>;
        var fileBase = 'invoice-' + encodeURIComponent(String('<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '-', $inv_no); ?>'));

        document.querySelectorAll('[data-inv-act]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-inv-act');
                if (act === 'print-image') {
                    try {
                        fetch('invoice.php?id=' + encodeURIComponent(String(saleId)) + '&print=1&beacon=1', {
                            credentials: 'same-origin',
                            keepalive: true,
                            cache: 'no-store'
                        });
                    } catch (e) {}
                }
                if (typeof window.ameMemoCapture === 'function') {
                    window.ameMemoCapture(act, fileBase, 'memo-container');
                }
            });
        });

        // স্বয়ংক্রিয় ট্রিগার (শুধু ইচ্ছাকৃত — বিক্রয় কমপ্লিটের পর শেয়ার শিট খোলে না)
        window.addEventListener('DOMContentLoaded', function () {
            var urlParams = new URLSearchParams(window.location.search);
            try {
                sessionStorage.removeItem('amePosCashShare');
            } catch (err) {}
            var fromComplete = urlParams.get('from') === 'complete';
            var wantShare = urlParams.get('share') === '1' && !fromComplete;
            if (urlParams.get('print') === '1' && !urlParams.get('beacon')) {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('print-image', fileBase, 'memo-container');
                    } else {
                        window.print();
                    }
                }, 400);
            } else if (wantShare) {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('share', fileBase, 'memo-container');
                    }
                }, 400);
            } else if (urlParams.get('download') === '1') {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('download', fileBase, 'memo-container');
                    }
                }, 400);
            } else if (urlParams.get('tg') === '1' || urlParams.get('telegram') === '1') {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('share-telegram', fileBase, 'memo-container');
                    }
                }, 400);
            }
        });
    })();
    </script>
    <script>
    (function () {
        function ameInvoiceDesktopFill() {
            var body = document.body;
            if (!body || !body.classList.contains('ame-sales-invoice-page')) {
                return;
            }
            var root = document.documentElement;
            root.setAttribute('data-ame-memo-standalone', '1');
            root.classList.add('ame-memo-standalone-fit');
            root.classList.remove('ame-phone-desktop');
            try {
                window.AME_SALE_DESKTOP_SITE = false;
                delete window.AME_SALE_DESKTOP_FIT;
            } catch (e) {}
            if (typeof window.ameMemoStandaloneViewportReset === 'function') {
                window.ameMemoStandaloneViewportReset();
            } else if (typeof window.ameApplySaleDesktopViewport === 'function') {
                window.ameApplySaleDesktopViewport();
            }
            var meta = document.querySelector('meta[name="viewport"]');
            if (meta) {
                meta.setAttribute('content', 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover');
            }
            root.style.width = '100%';
            body.style.width = '100%';
            body.style.maxWidth = '100%';
        }
        ameInvoiceDesktopFill();
        document.addEventListener('DOMContentLoaded', ameInvoiceDesktopFill);
        window.addEventListener('resize', ameInvoiceDesktopFill);
        window.addEventListener('orientationchange', function () {
            setTimeout(ameInvoiceDesktopFill, 50);
        });
        setTimeout(ameInvoiceDesktopFill, 120);
        setTimeout(ameInvoiceDesktopFill, 600);
    })();
    </script>
<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body>
</html>
