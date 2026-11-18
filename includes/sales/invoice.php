<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
/*
 * Thermal Invoice — ইতিহাস ও প্রিন্ট
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/invoice_functions.php';
require_once '../../includes/sales/sale_invoice.php';

$is_english = false;
$words = ame_invoice_words($is_english);
$invoice = ame_invoice_load(
    $db,
    (int)($_GET['id'] ?? 0)
);

if ($invoice && function_exists('log_activity')) {
    try {
        $isPrint = (isset($_GET['print']) && $_GET['print'] === '1');
        $invNo = (string)($invoice['invoice_number'] ?? '');
        $saleId = (int)($_GET['id'] ?? 0);
        if ($invNo === '') {
            $invNo = 'INV-' . $saleId;
        }
        log_activity($isPrint ? 'Invoice Print' : 'Invoice View', 'Sales', $invNo, [
            'action_type' => $isPrint ? 'print' : 'view',
            'entity_type' => 'invoice',
            'entity_id' => (string)$saleId,
            'reference' => $invNo,
            'party_type' => 'customer',
            'party_name' => (string)($invoice['customer_name'] ?? ''),
            'amount_total' => isset($invoice['net_payable']) ? (float)$invoice['net_payable'] : null,
            'amount_paid' => isset($invoice['paid_amount']) ? (float)$invoice['paid_amount'] : null,
            'amount_due' => isset($invoice['due_amount']) ? (float)$invoice['due_amount'] : null,
        ]);
        if ($isPrint && isset($_GET['beacon'])) {
            http_response_code(204);
            exit;
        }
    } catch (Throwable $e) {
        error_log('thermal invoice audit skipped: ' . $e->getMessage());
    }
}

$inv = get_invoice_settings($db);
$accent = get_invoice_accent_hex($inv['accent_color']);
$body_width = ($inv['paper_width'] === 'A4')
    ? '100%'
    : $inv['paper_width'];
$max_width = ($inv['paper_width'] === 'A4')
    ? '700px'
    : $inv['paper_width'];
$paper_class = ($inv['paper_width'] === 'A4')
    ? 'ame-paper-a4'
    : (($inv['paper_width'] === '58mm') ? 'ame-paper-58mm' : 'ame-paper-80mm');

$shop_name = shop_name();
$shop_phone = (string)($shop['shop_phone'] ?? '');
$shop_address = (string)($shop['shop_address'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo $is_english ? 'en' : 'bn'; ?>">
<head>
    <meta charset="UTF-8">
    <title>
        <?php echo ame_invoice_h($words['invoice']); ?>
        <?php echo $invoice ? ame_invoice_h($invoice['invoice_number']) : ''; ?>
    </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            width: <?php echo ame_invoice_h($body_width); ?>;
            max-width: <?php echo ame_invoice_h($max_width); ?>;
            margin: auto;
            padding: 10px;
            font-size: 13px;
            color: #111827;
        }
        .text-center { text-align: center; }
        .header {
            border-bottom: 2px dashed <?php echo ame_invoice_h($accent); ?>;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .header h2 {
            color: <?php echo ame_invoice_h($accent); ?>;
            margin: 0;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            border-bottom: 1px solid #333;
            color: <?php echo ame_invoice_h($accent); ?>;
        }
        td { padding: 4px 0; }
        .right { text-align: right; }
        .center { text-align: center; }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 3px 0;
        }
        .net-row {
            font-weight: bold;
            color: <?php echo ame_invoice_h($accent); ?>;
        }
        .due { color: #C62828; font-weight: bold; }
        .footer {
            margin-top: 16px;
            border-top: 1px dashed <?php echo ame_invoice_h($accent); ?>;
            padding-top: 10px;
            font-size: 11px;
        }
        @media print {
            .no-print { display: none; }
            @page { size: 80mm auto; margin: 2mm; }
            body.ame-paper-80mm, body.ame-paper-58mm {
                width: 72mm !important;
                max-width: 72mm !important;
                padding: 1.5mm !important;
                font-size: 10px !important;
            }
            body.ame-paper-58mm { width: 52mm !important; max-width: 52mm !important; }
            h2 { font-size: 13px !important; }
            table, td, th { font-size: 9px !important; word-break: break-word; }
        }
    </style>
    <link rel="stylesheet" href="/assets/css/invoice-print-fix.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.574', ENT_QUOTES, 'UTF-8'); ?>">

</head>
<body class="<?php echo ame_invoice_h($paper_class); ?>" data-paper="<?php echo ame_invoice_h((string)($inv['paper_width'] ?? '80mm')); ?>">
<?php if (!$invoice): ?>
    <p><?php echo ame_invoice_h($words['missing']); ?></p>
    <p class="no-print"><a href="pos.php"><?php echo ame_invoice_h($words['back_pos']); ?></a></p>
<?php else: ?>
    <div class="text-center header">
        <h2><?php echo ame_invoice_h($shop_name); ?></h2>
        <?php if (!empty($inv['show_address']) && $shop_address !== ''): ?>
            <p style="margin:5px 0;"><?php echo ame_invoice_h($shop_address); ?></p>
        <?php endif; ?>
        <?php if ($shop_phone !== ''): ?>
            <p style="margin:0;">
                <?php echo ame_invoice_h($words['phone_label']); ?>:
                <?php echo ame_invoice_h($shop_phone); ?>
            </p>
        <?php endif; ?>
        <hr>
        <strong><?php echo ame_invoice_h($words['receipt']); ?></strong>
    </div>

    <p>
        <?php echo ame_invoice_h($words['invoice']); ?>:
        <?php echo ame_invoice_h($invoice['invoice_number']); ?><br>
        <?php echo ame_invoice_h($words['date']); ?>:
        <?php echo ame_invoice_h($invoice['sold_at']); ?>
    </p>

    <p>
        <?php echo ame_invoice_h($words['customer']); ?>:
        <strong><?php echo ame_invoice_h($invoice['customer_name']); ?></strong>
        <?php if ($invoice['customer_code'] !== ''): ?>
            <br><?php echo ame_invoice_h($words['code']); ?>:
            <?php echo ame_invoice_h($invoice['customer_code']); ?>
        <?php endif; ?>
        <?php if ($invoice['customer_phone'] !== ''): ?>
            <br><?php echo ame_invoice_h($words['phone']); ?>:
            <?php echo ame_invoice_h($invoice['customer_phone']); ?>
        <?php endif; ?>
        <?php if ($invoice['customer_note'] !== ''): ?>
            <br><?php echo ame_invoice_h($words['note']); ?>:
            <?php echo ame_invoice_h($invoice['customer_note']); ?>
        <?php endif; ?>
    </p>

    <?php if ($invoice['items']): ?>
    <table>
        <thead>
            <tr>
                <th><?php echo ame_invoice_h($words['item']); ?></th>
                <th class="center"><?php echo ame_invoice_h($words['qty']); ?></th>
                <th class="right"><?php echo ame_invoice_h($words['total']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['items'] as $item): ?>
            <tr>
                <td><?php echo ame_invoice_h($item['name']); ?></td>
                <td class="center"><?php echo ame_invoice_h($item['qty']); ?></td>
                <td class="right"><?php echo ame_invoice_money($item['line_total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($invoice['services'])): ?>
    <p style="margin:10px 0 4px; font-weight:bold;"><?php echo ame_invoice_h($words['work_bill']); ?></p>
    <table>
        <thead>
            <tr>
                <th><?php echo ame_invoice_h($words['engineer']); ?></th>
                <th><?php echo ame_invoice_h($words['work_desc']); ?></th>
                <th class="right"><?php echo ame_invoice_h($words['total']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['services'] as $service): ?>
            <tr>
                <td><?php echo ame_invoice_h(($service['engineer_name'] ?? '') !== '' ? $service['engineer_name'] : '—'); ?></td>
                <td><?php echo ame_invoice_h(($service['service_type'] ?? '') !== '' ? $service['service_type'] : '—'); ?></td>
                <td class="right"><?php echo ame_invoice_money($service['amount']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="margin-top:10px; border-top: 1px solid #333; padding-top:5px;">
        <div class="row">
            <span><?php echo ame_invoice_h($words['subtotal']); ?></span>
            <span><?php echo ame_invoice_money($invoice['total_bill']); ?> ৳</span>
        </div>
        <?php if (!empty($inv['show_discount']) && $invoice['discount'] != 0): ?>
        <div class="row">
            <span><?php echo ame_invoice_h($words['discount']); ?></span>
            <span><?php echo ame_invoice_money($invoice['discount']); ?> ৳</span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['vat_amount'] != 0): ?>
        <div class="row">
            <span><?php echo ame_invoice_h($words['vat']); ?></span>
            <span><?php echo ame_invoice_money($invoice['vat_amount']); ?> ৳</span>
        </div>
        <?php endif; ?>
        <?php if (empty($invoice['services']) && $invoice['service_amount'] != 0): ?>
        <div class="row">
            <span><?php echo ame_invoice_h($words['service']); ?></span>
            <span><?php echo ame_invoice_money($invoice['service_amount']); ?> ৳</span>
        </div>
        <?php endif; ?>
        <div class="row net-row">
            <span><?php echo ame_invoice_h($words['payable']); ?></span>
            <span><?php echo ame_invoice_money($invoice['net_payable']); ?> ৳</span>
        </div>
        <div class="row">
            <span><?php echo ame_invoice_h($words['paid']); ?></span>
            <span><?php echo ame_invoice_money($invoice['paid_amount']); ?> ৳</span>
        </div>
        <?php if ($invoice['due_amount'] > 0): ?>
        <div class="row due">
            <span><?php echo ame_invoice_h($words['due']); ?></span>
            <span><?php echo ame_invoice_money($invoice['due_amount']); ?> ৳</span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['change_amount'] > 0): ?>
        <div class="row">
            <span><?php echo ame_invoice_h($words['change']); ?></span>
            <span><?php echo ame_invoice_money($invoice['change_amount']); ?> ৳</span>
        </div>
        <?php endif; ?>

        <?php
        $memo_prev = (float)($invoice['book']['previous'] ?? $invoice['previous_balance']);
        $memo_cur = (float)($invoice['book']['current'] ?? $invoice['invoice_bill'] ?? $invoice['net_payable']);
        $memo_paid = (float)($invoice['book']['paid'] ?? $invoice['paid_amount']);
        $memo_extra = (float)($invoice['book']['extra'] ?? $invoice['due_adjustment'] ?? 0);
        $memo_total = (float)($invoice['book']['total'] ?? round($memo_prev + $memo_cur - $memo_paid - $memo_extra, 2));
        ?>
        <div style="margin-top:8px;">
            <?php
            if (function_exists('ame_memo_paper_rows_html') && !empty($invoice['book'])) {
                echo ame_memo_paper_rows_html($invoice['book']);
            }
            ?>
        </div>
    </div>

    <div class="text-center footer">
        <?php if (!empty($inv['show_payment_method']) && $invoice['payments']): ?>
            <?php foreach ($invoice['payments'] as $payment): ?>
                <p style="margin:2px 0;">
                    <?php echo ame_invoice_h($payment['account_name']); ?>:
                    <?php echo ame_invoice_money($payment['amount']); ?> ৳
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (trim((string)($inv['footer_note'] ?? '')) !== ''): ?><p><?php echo ame_invoice_h($inv['footer_note']); ?></p><?php endif; ?>
        <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($invoice['created_by'] ?? 0)) : ''; ?>
        <div class="no-print">
            <button type="button" onclick="try{fetch('invoice.php?id=<?php echo (int)($_GET['id'] ?? 0); ?>&amp;print=1&amp;beacon=1',{credentials:'same-origin',keepalive:true,cache:'no-store'});}catch(e){} window.print();" style="padding:10px; cursor:pointer;">
                <?php echo ame_invoice_h($words['print']); ?>
            </button>
            <a href="pos.php"><?php echo ame_invoice_h($words['back_pos']); ?></a>
        </div>
    </div>
<?php endif; ?>
</body>
</html>