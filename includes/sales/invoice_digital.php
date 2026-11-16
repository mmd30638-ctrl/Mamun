<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
/*
 * POS Digital Invoice — সম্পন্ন বিক্রয়ের রসিদ
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/sales/sale_invoice.php';

$is_english = false;
$words = ame_invoice_words($is_english);
$invoice = ame_invoice_load(
    $db,
    (int)($_GET['id'] ?? 0)
);

$shop_name = shop_name();
$shop_phone = (string)($shop['shop_phone'] ?? '');
$shop_address = (string)($shop['shop_address'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo $is_english ? 'en' : 'bn'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo ame_invoice_h($words['invoice']); ?>
        <?php echo $invoice ? ame_invoice_h($invoice['invoice_number']) : ''; ?>
    </title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --blue: #0D47A1;
            --green: #087F3D;
            --black: #111827;
            --red: #C62828;
            --muted: #6B7280;
            --line: #E5E7EB;
            --bg: #F3F4F6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 20px;
            background: var(--bg);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--black);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #memo-container {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(17, 24, 39, 0.12);
            border-top: 8px solid var(--blue);
        }
        .memo-header {
            padding: 22px 22px 12px;
            text-align: center;
        }
        .memo-header h1 {
            margin: 0;
            color: var(--blue);
            font-size: 20px;
        }
        .memo-header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #E8F5E9;
            color: var(--green);
        }
        .badge.retail {
            background: #E3F2FD;
            color: var(--blue);
        }
        .memo-body { padding: 8px 22px 22px; }
        .info-row, .sum-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #F3F4F6;
            font-size: 13px;
        }
        .label { color: var(--muted); }
        .value { font-weight: 700; text-align: right; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 12px;
        }
        th {
            text-align: left;
            color: var(--blue);
            border-bottom: 1px solid var(--line);
            padding: 6px 0;
        }
        td { padding: 6px 0; border-bottom: 1px solid #F3F4F6; }
        .right { text-align: right; }
        .center { text-align: center; }
        .snapshot {
            margin-top: 12px;
            padding: 12px;
            border-radius: 12px;
            background: #F8FAFC;
            border: 1px solid var(--line);
        }
        .snapshot h3 {
            margin: 0 0 8px;
            font-size: 12px;
            color: var(--blue);
        }
        .net { color: var(--green); font-weight: 800; }
        .due { color: var(--red); }
        .memo-footer {
            text-align: center;
            padding: 16px 22px 22px;
            color: var(--muted);
            font-size: 11px;
        }
        .btn-group {
            width: 100%;
            max-width: 400px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }
        .action-btn, .btn-back {
            padding: 13px;
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-download { background: var(--blue); }
        .btn-share { background: var(--green); }
        .btn-print { background: var(--black); }
        .btn-back {
            background: #4B5563;
            grid-column: span 2;
        }
        .missing {
            max-width: 400px;
            background: #fff;
            padding: 28px;
            border-radius: 16px;
            text-align: center;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            #memo-container { box-shadow: none; max-width: none; }
        }
    </style>

</head>
<body>
<?php if (!$invoice): ?>
    <div class="missing">
        <p><?php echo ame_invoice_h($words['missing']); ?></p>
        <a class="btn-back" href="pos.php"><?php echo ame_invoice_h($words['back_pos']); ?></a>
    </div>
<?php else: ?>
<div id="memo-container">
    <div class="memo-header">
        <h1><?php echo ame_invoice_h($shop_name); ?></h1>
        <?php if ($shop_address !== ''): ?>
            <p><?php echo ame_invoice_h($shop_address); ?></p>
        <?php endif; ?>
        <?php if ($shop_phone !== ''): ?>
            <p><?php echo ame_invoice_h($words['phone_label']); ?>: <?php echo ame_invoice_h($shop_phone); ?></p>
        <?php endif; ?>
        <div class="badge<?php echo $invoice['is_registered'] ? '' : ' retail'; ?>">
            <?php echo ame_invoice_h(
                $invoice['is_registered']
                    ? $words['registered']
                    : $words['retail']
            ); ?>
        </div>
    </div>

    <div class="memo-body">
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['invoice']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['invoice_number']); ?></span>
        </div>
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['date']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['sold_at']); ?></span>
        </div>
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['customer']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['customer_name']); ?></span>
        </div>
        <?php if ($invoice['customer_code'] !== ''): ?>
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['code']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['customer_code']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['customer_phone'] !== ''): ?>
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['phone']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['customer_phone']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['customer_note'] !== ''): ?>
        <div class="info-row">
            <span class="label"><?php echo ame_invoice_h($words['note']); ?></span>
            <span class="value"><?php echo ame_invoice_h($invoice['customer_note']); ?></span>
        </div>
        <?php endif; ?>

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

        <?php foreach ($invoice['services'] as $service): ?>
        <div class="info-row">
            <span class="label">
                <?php echo ame_invoice_h($words['service']); ?>
                <?php if ($service['engineer_name'] !== ''): ?>
                    — <?php echo ame_invoice_h($service['engineer_name']); ?>
                <?php endif; ?>
            </span>
            <span class="value">
                <?php echo ame_invoice_h($service['service_type']); ?>
                · ৳<?php echo ame_invoice_money($service['amount']); ?>
            </span>
        </div>
        <?php endforeach; ?>

        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['subtotal']); ?></span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['total_bill']); ?></span>
        </div>
        <?php if ($invoice['discount'] != 0): ?>
        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['discount']); ?></span>
            <span class="value due">- ৳<?php echo ame_invoice_money($invoice['discount']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['vat_amount'] != 0): ?>
        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['vat']); ?> (<?php echo ame_invoice_money($invoice['vat_percent']); ?>%)</span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['vat_amount']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['service_amount'] != 0): ?>
        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['service']); ?></span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['service_amount']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['rounding_amount'] != 0): ?>
        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['rounding']); ?></span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['rounding_amount']); ?></span>
        </div>
        <?php endif; ?>
        <div class="sum-row net">
            <span><?php echo ame_invoice_h($words['payable']); ?></span>
            <span>৳<?php echo ame_invoice_money($invoice['net_payable']); ?></span>
        </div>

        <?php if ($invoice['payments']): ?>
            <div class="snapshot">
                <h3><?php echo ame_invoice_h($words['payments']); ?></h3>
                <?php foreach ($invoice['payments'] as $payment): ?>
                <div class="sum-row">
                    <span class="label">
                        <?php echo ame_invoice_h($payment['account_name']); ?>
                        <?php if ($payment['reference_number'] !== ''): ?>
                            · <?php echo ame_invoice_h($words['reference']); ?>
                            <?php echo ame_invoice_h($payment['reference_number']); ?>
                        <?php endif; ?>
                    </span>
                    <span class="value">৳<?php echo ame_invoice_money($payment['amount']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['paid']); ?></span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['paid_amount']); ?></span>
        </div>
        <?php if ($invoice['due_amount'] > 0): ?>
        <div class="sum-row due">
            <span><?php echo ame_invoice_h($words['due']); ?></span>
            <span>৳<?php echo ame_invoice_money($invoice['due_amount']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($invoice['change_amount'] > 0): ?>
        <div class="sum-row">
            <span class="label"><?php echo ame_invoice_h($words['change']); ?></span>
            <span class="value">৳<?php echo ame_invoice_money($invoice['change_amount']); ?></span>
        </div>
        <?php endif; ?>

        <div class="snapshot">
            <h3>ক্রেতার হিসাব</h3>
            <div class="sum-row">
                <span class="label"><?php echo ame_invoice_h($words['previous_balance']); ?></span>
                <span class="value">৳<?php echo ame_invoice_money($invoice['previous_balance']); ?></span>
            </div>
            <div class="sum-row">
                <span class="label">বর্তমান এমাউন্ট</span>
                <span class="value">৳<?php echo ame_invoice_money($invoice['book']['current'] ?? $invoice['invoice_bill'] ?? $invoice['net_payable']); ?></span>
            </div>
            <div class="sum-row">
                <span class="label"><?php echo ame_invoice_h($words['paid']); ?> (−)</span>
                <span class="value">৳<?php echo ame_invoice_money($invoice['paid_amount']); ?></span>
            </div>
            <?php if ($invoice['due_adjustment'] > 0): ?>
            <div class="sum-row">
                <span class="label"><?php echo ame_invoice_h($words['due_adjustment']); ?></span>
                <span class="value">৳<?php echo ame_invoice_money($invoice['due_adjustment']); ?></span>
            </div>
            <?php endif; ?>
            <div class="sum-row net">
                <span><?php echo ame_invoice_h($words['new_balance']); ?></span>
                <span>৳<?php echo ame_invoice_money($invoice['customer_balance_after']); ?></span>
            </div>
        </div>
    </div>

    <div class="memo-footer">
        <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($invoice['created_by'] ?? 0)) : ''; ?>
    </div>
</div>

<div class="btn-group no-print">
    <button class="action-btn btn-download" type="button" onclick="captureMemo('download')">
        <?php echo ame_invoice_h($words['download']); ?>
    </button>
    <button class="action-btn btn-share" type="button" onclick="captureMemo('share')">
        <?php echo ame_invoice_h($words['share']); ?>
    </button>
    <button class="action-btn btn-print" type="button" onclick="window.print()">
        <?php echo ame_invoice_h($words['print']); ?>
    </button>
    <a class="btn-back" href="pos.php"><?php echo ame_invoice_h($words['back_pos']); ?></a>
</div>

<script>
function captureMemo(action) {
    const container = document.getElementById('memo-container');

    html2canvas(container, { scale: 3 }).then(function (canvas) {
        if (action === 'download') {
            const link = document.createElement('a');
            link.download = 'Receipt_<?php echo ame_invoice_h($invoice['invoice_number']); ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            return;
        }

        canvas.toBlob(function (blob) {
            const file = new File(
                [blob],
                'receipt.png',
                { type: 'image/png' }
            );

            if (navigator.share) {
                navigator.share({
                    files: [file],
                    title: '<?php echo ame_invoice_h($words['receipt']); ?>',
                    text: '<?php echo ame_invoice_h($invoice['invoice_number']); ?>'
                }).catch(function () {});
            } else {
                alert(<?php echo json_encode($words['share_fail'], JSON_UNESCAPED_UNICODE); ?>);
            }
        });
    });
}
</script>
<?php endif; ?>
</body>
</html>