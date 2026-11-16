<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
/**
 * বিক্রয় ডিজিটাল মেমো — সম্পূর্ণ বাংলা, সাবেক ডিউ + বর্তমান এমাউন্ট + মোট
 */
require_once '../../includes/config.php';
require_once '../../includes/sales/sale_invoice.php';
require_once '../../includes/memo_functions.php';

$words = ame_bn_memo_words();
$sale_id = (int)($_GET['id'] ?? 0);
$invoice = ame_invoice_load($db, $sale_id);
$shop_name = function_exists('shop_name') ? shop_name() : (string)($shop['shop_name'] ?? '');

$due = [
    'previous' => 0.0,
    'current' => 0.0,
    'paid' => 0.0,
    'total' => 0.0,
    'extra' => 0.0,
];
if ($invoice) {
    $book = $invoice['book'] ?? ame_balance_book(
        'sale',
        $invoice['previous_balance'],
        $invoice['net_payable'],
        $invoice['paid_amount'],
        $invoice['customer_balance_after'],
        $invoice['due_adjustment']
    );
    $due = [
        'previous' => $book['previous'],
        'current' => $book['current'],
        'paid' => $book['paid'],
        'total' => $book['total'],
        'extra' => $book['extra'],
        'book' => $book,
    ];
}

$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.38', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo (function_exists('is_en') && is_en()) ? 'en' : 'bn'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ame_memo_h($words['sale_title']); ?><?php echo $invoice ? ' ' . ame_memo_h($invoice['invoice_number']) : ''; ?></title>
    <link rel="stylesheet" href="/assets/css/txn-memo.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/invoice-print-fix.css?v=<?php echo $ver; ?>">
    <script src="/assets/js/html2canvas.min.js?v=<?php echo $ver; ?>"></script>

</head>
<body class="tm-page ame-paper-80mm" data-paper="80mm">
<?php ame_memo_render_nav(['home' => '/index.php', 'back' => 'history.php']); ?>
<?php if (!$invoice): ?>
    <div class="tm-missing">
        <p><?php echo ame_memo_h($words['missing']); ?></p>
        <a class="tm-back" href="pos.php" style="display:inline-block;margin-top:12px;padding:12px 18px;border-radius:12px;"><?php echo ame_memo_h($words['new_sale']); ?></a>
    </div>
<?php else: ?>
<div id="memo-container" class="tm-card sale" data-ame-capture="clone">
    <div class="tm-head">
        <?php echo shop_print_logo_tag(52); ?>
        <h1><?php echo ame_memo_h($shop_name); ?></h1>
        <p><?php echo ame_memo_h($words['receipt']); ?></p>
    </div>
    <div class="tm-body">
        <div class="tm-title"><?php echo ame_memo_h($words['sale_title']); ?></div>

        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['invoice_id']); ?></span><span class="tm-value"><?php echo ame_memo_h($invoice['invoice_number']); ?></span></div>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['datetime']); ?></span><span class="tm-value"><?php echo ame_memo_h($invoice['sold_at']); ?></span></div>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['customer']); ?></span><span class="tm-value"><?php echo ame_memo_h($invoice['customer_name'] !== '' ? $invoice['customer_name'] : $words['walkin']); ?></span></div>
        <?php if ($invoice['customer_phone'] !== ''): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['phone']); ?></span><span class="tm-value"><?php echo ame_memo_h($invoice['customer_phone']); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($invoice['vehicle_no'])): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['vehicle']); ?></span><span class="tm-value"><?php echo ame_memo_h($invoice['vehicle_no']); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($invoice['payments'])): ?>
            <?php foreach ($invoice['payments'] as $payment): ?>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h($words['payment']); ?></span>
                <span class="tm-value"><?php echo ame_memo_h($payment['account_name']); ?> · ৳<?php echo ame_memo_money($payment['amount']); ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($invoice['items']): ?>
        <table class="tm-items">
            <thead>
                <tr>
                    <th><?php echo ame_memo_h($words['item']); ?></th>
                    <th class="c"><?php echo ame_memo_h($words['qty']); ?></th>
                    <th class="r"><?php echo ame_memo_h($words['sale_rate']); ?></th>
                    <th class="r"><?php echo ame_memo_h($words['line_total']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                <tr>
                    <td><?php echo ame_memo_h($item['name']); ?></td>
                    <td class="c"><?php echo ame_memo_h($item['qty']); ?></td>
                    <td class="r">৳<?php echo ame_memo_money($item['sale_price'] ?? 0); ?></td>
                    <td class="r">৳<?php echo ame_memo_money($item['line_total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($invoice['exchange_items'])): ?>
        <table class="tm-items">
            <thead>
                <tr>
                    <th><?php echo ame_memo_h($words['exchange']); ?></th>
                    <th class="c"><?php echo ame_memo_h($words['qty']); ?></th>
                    <th class="r"><?php echo ame_memo_h($words['line_total']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['exchange_items'] as $ex_item): ?>
                <tr>
                    <td><?php echo ame_memo_h($ex_item['name']); ?></td>
                    <td class="c"><?php echo ame_memo_h($ex_item['qty']); ?></td>
                    <td class="r">−৳<?php echo ame_memo_money($ex_item['line_total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="tm-note"><?php echo ame_memo_h($words['exchange_note']); ?> ৳<?php echo ame_memo_money($invoice['exchange_amount']); ?></p>
        <?php endif; ?>

        <?php if ($invoice['services']): ?>
        <table class="tm-items">
            <thead>
                <tr>
                    <th><?php echo ame_memo_h($words['engineer']); ?></th>
                    <th><?php echo ame_memo_h($words['work_desc'] ?? $words['service']); ?></th>
                    <th class="r"><?php echo ame_memo_h($words['line_total'] ?? 'টাকা'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['services'] as $service): ?>
                <tr>
                    <td><?php echo ame_memo_h(($service['engineer_name'] ?? '') !== '' ? $service['engineer_name'] : '—'); ?></td>
                    <td><?php echo ame_memo_h(($service['service_type'] ?? '') !== '' ? $service['service_type'] : '—'); ?></td>
                    <td class="r">৳<?php echo ame_memo_money($service['amount']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ((float)($invoice['discount'] ?? 0) > 0.009): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['discount']); ?></span><span class="tm-value">৳<?php echo ame_memo_money($invoice['discount']); ?></span></div>
        <?php endif; ?>
        <?php if ((float)($invoice['vat_amount'] ?? 0) > 0.009): ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['vat']); ?></span><span class="tm-value">৳<?php echo ame_memo_money($invoice['vat_amount']); ?></span></div>
        <?php endif; ?>
        <div class="tm-row"><span class="tm-label"><?php echo ame_memo_h($words['invoice_total']); ?></span><span class="tm-value">৳<?php echo ame_memo_money($invoice['net_payable']); ?></span></div>

        <div class="tm-paid">
            <small><?php echo ame_memo_h($words['paid']); ?></small>
            <b>৳ <?php echo ame_memo_money($due['paid']); ?></b>
        </div>

        <?php if ((float)$invoice['exchange_amount'] > 0.009): ?>
        <div class="tm-due">
            <h3><?php echo ame_memo_h($words['exchange']); ?></h3>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h($words['total_sale']); ?></span>
                <span class="tm-value">৳<?php echo ame_memo_money($invoice['net_payable']); ?></span>
            </div>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h($words['exchange']); ?></span>
                <span class="tm-value">−৳<?php echo ame_memo_money($invoice['exchange_amount']); ?></span>
            </div>
            <div class="tm-row">
                <span class="tm-label"><?php echo ame_memo_h($words['cash_paid']); ?></span>
                <span class="tm-value">৳<?php echo ame_memo_money($invoice['cash_paid_amount']); ?></span>
            </div>
            <div class="tm-row total">
                <span><?php echo ame_memo_h($words['total_paid']); ?></span>
                <span>৳<?php echo ame_memo_money($invoice['paid_amount']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="tm-due">
            <h3><?php echo ame_memo_h('সাবেক + পণ্য ক্রয় − পরিশোধ = দোকান পাবে'); ?></h3>
            <?php
            if (function_exists('ame_memo_paper_rows_html') && !empty($due['book'])) {
                echo ame_memo_paper_rows_html($due['book']);
            }
            ?>
        </div>
    </div>
    <div class="tm-foot"><?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($invoice['created_by'] ?? 0)) : ''; ?></div>
</div>
<?php
$shareText = $shop_name . "\n" . $words['sale_title'] . ' ' . $invoice['invoice_number']
    . "\n" . $invoice['sold_at']
    . "\n" . $words['customer'] . ': ' . ($invoice['customer_name'] !== '' ? $invoice['customer_name'] : $words['walkin'])
    . "\n" . $words['invoice_total'] . ': ' . ame_memo_money($invoice['net_payable'])
    . ($due['paid'] > 0.009 ? ("\n" . $words['paid'] . ': ' . ame_memo_money($due['paid'])) : '')
    . "\n" . ((float)$due['total'] < -0.009 ? ('সর্বমোট পাওনা: ৳' . ame_memo_money(abs((float)$due['total']))) : ($words['due'] . ': ৳' . ame_memo_money(max(0, (float)$due['total']))))
    . (trim((string)($invoice['vehicle_no'] ?? '')) !== ''
        ? "\n" . $words['vehicle'] . ': ' . $invoice['vehicle_no']
        : '');
ame_memo_render_actions([
    'file' => 'sale-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$invoice['invoice_number']) . '.png',
    'title' => $shop_name . ' · ' . $words['sale_title'] . ' · ' . $invoice['invoice_number'],
    'text' => $shareText,
    'image_caption' => false,
    'phone' => (string)($invoice['customer_phone'] ?? ''),
    'home' => '/index.php',
    'back' => 'history.php',
    'links' => [
        ['href' => 'history.php', 'label' => function_exists('ame_l') ? ame_l('ইতিহাস', 'History') : 'ইতিহাস'],
        ['href' => 'pos.php', 'label' => $words['new_sale']],
    ],
]);
?>
<?php endif; ?>
</body>
</html>
