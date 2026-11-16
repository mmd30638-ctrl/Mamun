<?php
/**
 * জমা উত্তোলন রসিদ — কাস্টমার পেমেন্ট রিসিভ মেমোর (receipt.php) একই লেআউট ও রঙ।
 */
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
require_once __DIR__ . '/../../includes/memo_functions.php';
require_once __DIR__ . '/../../includes/memo_paper.php';
require_once __DIR__ . '/../../includes/invoice_functions.php';
ensure_customer_tables($db);

$wid = (int) ($_GET['id'] ?? 0);
$st = $db->prepare(
    'SELECT w.*, c.name, c.customer_code, c.phone, c.receivable_balance, a.account_name
     FROM customer_withdrawals w
     LEFT JOIN customers c ON c.id = w.customer_id
     LEFT JOIN accounts a ON a.id = w.account_id
     WHERE w.id = ? LIMIT 1'
);
$row = null;
if ($st) {
    $st->bind_param('i', $wid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
}

$inv = get_invoice_settings($db);
$paper_width = $inv['paper_width'] ?? '80mm';
$body_paper_class = ($paper_width === 'A4') ? ' ame-cpay-memo--a4' : '';
$footer_note = trim((string) preg_replace('/\s+/u', ' ', (string) ($inv['footer_note'] ?? '')));
if ($footer_note === '') {
    $footer_note = ame_l('ধন্যবাদ, আবার আসবেন!', 'Thank you, visit again!');
}

$shop_name = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_address = function_exists('shop_address') ? trim(shop_address()) : '';
$shop_phone = function_exists('shop_phone') ? trim(shop_phone()) : '';
$ame_ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.941';

if ($row) {
    $amount = round((float) $row['amount'], 2);
    $post_balance = round((float) ($row['receivable_balance'] ?? 0), 2);
    $prev_balance = round($post_balance - $amount, 2);

    $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $row['name']) : (string) $row['name'];
    $ref = (string) ($row['reference_no'] ?: ('WD-' . str_pad((string) $wid, 6, '0', STR_PAD_LEFT)));
    $phone = trim((string) ($row['phone'] ?? ''));
    $code = trim((string) ($row['customer_code'] ?? ''));
    $method = strtoupper(trim((string) ($row['method'] ?? 'CASH')));
    $method_map = [
        'CASH' => ['নগদ', 'Cash'],
        'BKASH' => ['বিকাশ', 'bKash'],
        'NAGAD' => ['নগদ (মোবাইল)', 'Nagad'],
        'BANK' => ['ব্যাংক', 'Bank'],
        'CHEQUE' => ['চেক', 'Cheque'],
    ];
    $method_pair = $method_map[$method] ?? [$method, $method];
    $method_label = is_en() ? $method_pair[1] : $method_pair[0];
    $account = trim((string) ($row['account_name'] ?? ''));
    $note = trim((string) ($row['note'] ?? ''));

    $dateTs = strtotime((string) ($row['withdraw_date'] ?? ''));
    $timeTs = strtotime((string) ($row['created_at'] ?? ''));
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
        $formatted_when = (string) ($row['withdraw_date'] ?? '');
    }

    $balance_line = static function (float $balance): array {
        if ($balance < -0.009) {
            return [ame_l('জমা', 'Held'), round(abs($balance), 2), '#166534'];
        }
        if ($balance > 0.009) {
            return [ame_l('বকেয়া', 'Due'), round($balance, 2), '#991B1B'];
        }
        return [ame_l('সমান', 'Settled'), 0.0, '#111827'];
    };
    [$prev_label, $prev_amt, $prev_color] = $balance_line($prev_balance);
    [$now_label, $now_amt, $now_color] = $balance_line($post_balance);

    $wa_phone = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($wa_phone, '0') && strlen($wa_phone) >= 11) {
        $wa_phone = '880' . substr($wa_phone, 1);
    } elseif ($wa_phone !== '' && !str_starts_with($wa_phone, '880') && strlen($wa_phone) === 10) {
        $wa_phone = '880' . $wa_phone;
    }
    $wa_text = $shop_name . "\n"
        . ame_l('উত্তোলন রসিদ', 'Payout receipt') . ': #' . $ref . "\n"
        . ame_l('তারিখ', 'Date') . ': ' . $formatted_when . "\n"
        . ame_l('কাস্টমার', 'Customer') . ': ' . $name . "\n"
        . ame_l('তোলা হয়েছে', 'Withdrawn') . ': ৳' . number_format($amount, 2) . "\n"
        . ame_l('ধন্যবাদ!', 'Thank you!');
    $wa_url = 'https://wa.me/' . ($wa_phone !== '' ? $wa_phone : '') . '?text=' . rawurlencode($wa_text);
}
$file_base = $row ? 'withdraw-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $ref) : 'withdraw';
?>
<!DOCTYPE html>
<html lang="<?php echo is_en() ? 'en' : 'bn'; ?>">
<head>
<meta charset="UTF-8">
<?php echo defined('AME_VIEWPORT_META') ? AME_VIEWPORT_META : '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">'; ?>
<title><?php echo htmlspecialchars(ame_l('উত্তোলন রসিদ', 'Payout receipt'), ENT_QUOTES, 'UTF-8'); ?><?php echo $row ? ' #' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') : ''; ?></title>
<link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/memo-clear.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/customer-pay-receipt-memo.css?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>">
<script src="/assets/js/ame-desktop-site.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
if (function_exists('ame_global_responsive_tier_script_echo')) {
    ame_global_responsive_tier_script_echo();
}
?>
<script src="/assets/js/html2canvas.min.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body class="ame-customer-pay-receipt ame-withdraw-receipt<?php echo htmlspecialchars($body_paper_class, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!$row): ?>
<div class="memo-missing no-print">
    <p><?php echo htmlspecialchars(ame_l('উত্তোলন রসিদ পাওয়া যায়নি।', 'Payout receipt not found.'), ENT_QUOTES, 'UTF-8'); ?></p>
    <a class="inv-btn inv-btn-back" href="withdraw.php"><?php echo htmlspecialchars(ame_l('ফিরে যান', 'Go back'), ENT_QUOTES, 'UTF-8'); ?></a>
</div>
<?php else: ?>
<nav class="inv-action-bar no-print" aria-label="<?php echo htmlspecialchars(ame_l('রসিদ অপশন', 'Receipt options'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="inv-btn inv-btn-print" data-pay-act="print-image"><?php echo htmlspecialchars(ame_l('প্রিন্ট', 'Print'), ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="inv-btn inv-btn-pdf" data-pay-act="download"><?php echo htmlspecialchars(ame_l('পিডিএফ', 'PDF'), ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="inv-btn inv-btn-img" data-pay-act="download-image"><?php echo htmlspecialchars(ame_l('ছবি', 'Image'), ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="inv-btn inv-btn-share" data-pay-act="share"><?php echo htmlspecialchars(ame_l('শেয়ার', 'Share'), ENT_QUOTES, 'UTF-8'); ?></button>
    <a class="inv-btn inv-btn-wa" href="<?php echo htmlspecialchars($wa_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(ame_l('হোয়াটসঅ্যাপ', 'WhatsApp'), ENT_QUOTES, 'UTF-8'); ?></a>
    <button type="button" class="inv-btn inv-btn-tg" data-pay-act="share-telegram"><?php echo htmlspecialchars(ame_l('টেলিগ্রাম', 'Telegram'), ENT_QUOTES, 'UTF-8'); ?></button>
    <a class="inv-btn inv-btn-back" href="withdraw.php"><?php echo htmlspecialchars(ame_l('আরেকজন কাস্টমার', 'Another customer'), ENT_QUOTES, 'UTF-8'); ?></a>
</nav>
<p class="pay-share-hint no-print"><?php echo htmlspecialchars(ame_l('শেয়ার চাপলে মেমোর ছবি যাবে।', 'Share sends a memo image.'), ENT_QUOTES, 'UTF-8'); ?></p>

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
        <div class="memo-badge"><?php echo htmlspecialchars(ame_l('জমা টাকা তোলা', 'Deposit payout'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="memo-meta-grid">
        <div class="memo-meta-item">
            <span class="lbl"><?php echo htmlspecialchars(ame_l('রসিদ নম্বর', 'Receipt no.'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="val">#<?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl"><?php echo htmlspecialchars(ame_l('তারিখ ও সময়', 'Date & time'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="val"><?php echo htmlspecialchars($formatted_when, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl"><?php echo htmlspecialchars(ame_l('কাস্টমারের নাম', 'Customer name'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="val"><span class="memo-customer-name"><?php
                echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                echo $code !== '' ? ' (' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . ')' : '';
            ?></span></span>
        </div>
        <div class="memo-meta-item">
            <span class="lbl"><?php echo htmlspecialchars(ame_l('মোবাইল', 'Mobile'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="val"><?php echo $phone !== '' ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
        </div>
        <?php if ($account !== ''): ?>
        <div class="memo-meta-item" style="grid-column: 1 / -1;">
            <span class="lbl"><?php echo htmlspecialchars(ame_l('অ্যাকাউন্ট', 'Account'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="val"><?php echo htmlspecialchars($account, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <table class="memo-table">
        <thead>
            <tr>
                <th style="width:28px;" class="tc"><?php echo htmlspecialchars(ame_l('নং', 'No.'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(ame_l('বিবরণ', 'Description'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(ame_l('মাধ্যম', 'Method'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th style="width:90px;" class="tr"><?php echo htmlspecialchars(ame_l('টাকা', 'Amount'), ENT_QUOTES, 'UTF-8'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="tc" data-k="<?php echo htmlspecialchars(ame_l('নং', 'No.'), ENT_QUOTES, 'UTF-8'); ?>"><span class="cpay-val">1</span></td>
                <td data-k="<?php echo htmlspecialchars(ame_l('বিবরণ', 'Description'), ENT_QUOTES, 'UTF-8'); ?>"><span class="cpay-val">
                    <span style="font-weight:600;"><?php echo htmlspecialchars(ame_l('জমা উত্তোলন', 'Deposit payout'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($note !== ''): ?>
                    <span class="sku-text"><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span></td>
                <td data-k="<?php echo htmlspecialchars(ame_l('মাধ্যম', 'Method'), ENT_QUOTES, 'UTF-8'); ?>"><span class="cpay-val"><?php echo htmlspecialchars($method_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td class="tr" data-k="<?php echo htmlspecialchars(ame_l('টাকা', 'Amount'), ENT_QUOTES, 'UTF-8'); ?>"><span class="cpay-val" style="font-weight:700;"><?php echo number_format($amount, 2); ?></span></td>
            </tr>
        </tbody>
    </table>

    <div class="memo-summary">
        <div class="memo-sum-row highlight">
            <span><?php echo htmlspecialchars(ame_l('এই রসিদে তোলা হয়েছে', 'Withdrawn on this receipt'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span>৳<?php echo number_format($amount, 2); ?></span>
        </div>

        <div class="memo-sum-row sabek-box">
            <div class="sabek-line" style="color:<?php echo htmlspecialchars($prev_color, ENT_QUOTES, 'UTF-8'); ?>;font-weight:700;">
                <span><?php echo htmlspecialchars(ame_l('সাবেক (উত্তোলনের আগে)', 'Previous (before payout)'), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($prev_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money($prev_balance, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="sabek-line">
                <span><?php echo htmlspecialchars(ame_l('এই উত্তোলন (−)', 'This payout (−)'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money(-$amount, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="total-due-line" style="color:<?php echo htmlspecialchars($now_color, ENT_QUOTES, 'UTF-8'); ?>;">
                <span><?php echo htmlspecialchars(ame_l('এখন', 'Now'), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($now_label, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars(ame_memo_paper_money($post_balance, true), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="pay-proof ame-paper-proof"><?php echo htmlspecialchars(ame_l('সাবেক − এই উত্তোলন = বর্তমান', 'Previous − this payout = now'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>

    <div class="memo-foot">
        <?php if ($account !== '' && !empty($inv['show_payment_method'])): ?>
        <p style="margin:0 0 3px;"><?php echo htmlspecialchars(ame_l('মাধ্যম', 'Method'), ENT_QUOTES, 'UTF-8'); ?>: <b><?php echo htmlspecialchars($account, ENT_QUOTES, 'UTF-8'); ?></b></p>
        <?php endif; ?>
        <p class="thanks"><?php echo htmlspecialchars($footer_note, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int) ($row['created_by'] ?? 0)) : ''; ?>
    </div>
</div>
</div>

<script src="/assets/js/ame-memo-file.js?v=<?php echo htmlspecialchars($ame_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    var fileBase = <?php echo json_encode($file_base, JSON_UNESCAPED_UNICODE); ?>;
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
<?php endif; ?>
<?php
$confirm = __DIR__ . '/../../partials/confirm_actions.php';
if (is_file($confirm)) {
    include $confirm;
}
if (function_exists('ame_global_responsive_assets_echo')) {
    echo "\n";
    ame_global_responsive_assets_echo();
}
?>
<?php include __DIR__ . '/../../partials/page_fit.php'; ?>
</body>
</html>