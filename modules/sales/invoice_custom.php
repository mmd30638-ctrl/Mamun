<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/invoice_functions.php';

$sale_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT s.*, a.account_name, c.name as cus_name, c.phone as cus_phone, c.address as cus_address
     FROM sales s
     LEFT JOIN accounts a ON s.account_id = a.id
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE s.id = ?"
);
$stmt->bind_param('i', $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die('❌ এই ইনভয়েস খুঁজে পাওয়া যায়নি। <a href="pos.php">ফিরে যান</a>');
}

$items_stmt = $db->prepare(
    "SELECT si.*, COALESCE(NULLIF(si.item_name, ''), p.name) AS name
     FROM sale_items si
     LEFT JOIN products p ON p.id = si.product_id
     WHERE si.sale_id = ?"
);
$items_stmt->bind_param('i', $sale_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!function_exists('ame_sale_load_invoice_services')) {
    require_once '../../includes/sales/sale_invoice.php';
}
$svc_rows = ame_sale_load_invoice_services(
    $db,
    $sale_id,
    (float)($sale['service_amount'] ?? 0)
);

$template = get_active_invoice_template($db);
$shop_name = $shop['shop_name'] ?? 'Aponshop';

if (!$template) {
    // কোনো কাস্টম ডিজাইন সক্রিয় না থাকলে, স্বাভাবিক ইনভয়েসে পাঠিয়ে দেওয়া
    header('Location: invoice.php?id=' . $sale_id);
    exit;
}

$layout = json_decode($template['layout_json'], true) ?: [];

// ================= ক্যানভাসের সর্বোচ্চ প্রস্থ/উচ্চতা হিসাব (সব ব্লক ধরে) =================
$canvas_w = 0;
$canvas_h = 0;
foreach ($layout as $b) {
    $canvas_w = max($canvas_w, $b['x'] + $b['w']);
    $canvas_h = max($canvas_h, $b['y'] + $b['h']);
}
$canvas_w += 20;
$canvas_h += 20;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইনভয়েস #<?php echo (int)$sale_id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #EAF4FF; margin: 0; padding: 16px; }
        .inv-canvas {
            position: relative;
            width: <?php echo (int)$canvas_w; ?>px;
            height: <?php echo (int)$canvas_h; ?>px;
            background: #fff;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(11,30,77,0.1);
            max-width: 100%;
        }
        .inv-block {
            position: absolute;
            box-sizing: border-box;
            padding: 8px 10px;
            font-size: 12px;
            overflow: hidden;
        }
        .inv-block-title { font-weight: 800; color: #0B1E4D; font-size: 12.5px; margin-bottom: 5px; }
        .inv-block-row { display: flex; justify-content: space-between; font-size: 11.5px; padding: 2px 0; color: #334155; }
        table.inv-items { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.inv-items th { text-align: left; border-bottom: 1.5px solid #0B1E4D; color: #0B1E4D; padding: 4px 3px; }
        table.inv-items td { padding: 4px 3px; border-bottom: 1px solid #F1F5F9; }
        .inv-print-btn {
            display: block; max-width: <?php echo (int)$canvas_w; ?>px; margin: 12px auto 0; text-align: center;
        }
        .inv-print-btn button {
            background: linear-gradient(135deg,#0B1E4D,#0091EA); color: #fff; border: none; padding: 10px 22px;
            border-radius: 10px; font-weight: 700; cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .inv-canvas { box-shadow: none; }
            .no-print { display: none; }
        }
    </style>

</head>
<body>

<div class="inv-canvas">
    <?php foreach ($layout as $b):
        $style = sprintf(
            'left:%dpx;top:%dpx;width:%dpx;height:%dpx;background:%s;border:1.5px solid %s;z-index:%d;',
            $b['x'], $b['y'], $b['w'], $b['h'],
            htmlspecialchars($b['bg']), htmlspecialchars($b['border']), $b['z']
        );
    ?>
    <div class="inv-block" style="<?php echo $style; ?>">
        <?php switch ($b['type']):
            case 'invoice_info': ?>
                <div class="inv-block-title">🧾 <?php echo htmlspecialchars($shop_name); ?></div>
                <div class="inv-block-row"><span>ইনভয়েস নং</span><span><?php echo htmlspecialchars($sale['invoice_number'] ?? ('#' . $sale_id)); ?></span></div>
                <div class="inv-block-row"><span>তারিখ</span><span><?php echo date('d-m-Y', strtotime($sale['sale_date'] ?? $sale['created_at'])); ?></span></div>
                <?php break;

            case 'customer_info': ?>
                <div class="inv-block-title">👤 ক্রেতার তথ্য</div>
                <div class="inv-block-row"><span>নাম</span><span><?php echo htmlspecialchars($sale['cus_name'] ?: 'Retail Customer'); ?></span></div>
                <?php if (!empty($sale['cus_phone'])): ?>
                <div class="inv-block-row"><span>ফোন</span><span><?php echo htmlspecialchars($sale['cus_phone']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($sale['cus_address'])): ?>
                <div class="inv-block-row"><span>ঠিকানা</span><span><?php echo htmlspecialchars($sale['cus_address']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($sale['vehicle_no'])): ?>
                <div class="inv-block-row"><span>গাড়ির নম্বর</span><span><?php echo htmlspecialchars($sale['vehicle_no']); ?></span></div>
                <?php endif; ?>
                <?php break;

            case 'product_table': ?>
                <div class="inv-block-title">📦 পণ্যের তালিকা</div>
                <div style="overflow:auto; max-height:calc(100% - 24px);">
                <table class="inv-items">
                    <thead><tr><th>#</th><th>পণ্য</th><th style="text-align:right;">পরিমাণ</th><th style="text-align:right;">মূল্য</th><th style="text-align:right;">মোট</th></tr></thead>
                    <tbody>
                        <?php $sl = 1; foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo $sl++; ?></td>
                            <td><?php echo htmlspecialchars($it['name']); ?></td>
                            <td style="text-align:right;"><?php echo htmlspecialchars($it['qty']); ?></td>
                            <td style="text-align:right;"><?php echo number_format((float)$it['sale_price'], 2); ?></td>
                            <td style="text-align:right;"><?php echo number_format((float)$it['sale_price'] * (float)$it['qty'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($svc_rows)): ?>
                <div class="inv-block-title" style="margin-top:8px;">ইঞ্জিনিয়ার ও কাজের বিল</div>
                <table class="inv-items">
                    <thead><tr><th>ইঞ্জিনিয়ার</th><th>কাজের বিবরণ</th><th style="text-align:right;">টাকা</th></tr></thead>
                    <tbody>
                        <?php foreach ($svc_rows as $svc): ?>
                        <tr>
                            <td><?php
                                $eng = trim((string)($svc['engineer_name'] ?? ''));
                                echo $eng !== '' ? htmlspecialchars($eng) : '—';
                            ?></td>
                            <td><?php
                                $typ = trim((string)($svc['service_type'] ?? ''));
                                echo $typ !== '' ? htmlspecialchars($typ) : '—';
                            ?></td>
                            <td style="text-align:right;"><?php echo number_format((float)($svc['amount'] ?? 0), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                </div>
                <?php break;

            case 'payment_info': ?>
                <div class="inv-block-title">💳 পেমেন্ট</div>
                <div class="inv-block-row"><span>মাধ্যম</span><span><?php echo htmlspecialchars($sale['account_name'] ?? '—'); ?></span></div>
                <?php break;

            case 'summary_box': ?>
                <div class="inv-block-title">🧮 সারাংশ</div>
                <div class="inv-block-row"><span>সাব-টোটাল</span><span><?php echo number_format((float)($sale['total_bill'] ?? 0), 2); ?></span></div>
                <div class="inv-block-row"><span>ডিসকাউন্ট</span><span><?php echo number_format((float)($sale['discount'] ?? 0), 2); ?></span></div>
                <div class="inv-block-row" style="font-weight:800; color:#0B8043;"><span>সর্বমোট</span><span><?php echo number_format((float)(function_exists('ame_sale_this_bill') ? ame_sale_this_bill($sale) : ($sale['net_payable'] ?? 0)), 2); ?></span></div>
                <?php if ((float)($sale['exchange_amount'] ?? 0) > 0.009): ?>
                <div class="inv-block-row"><span>পুরাতন ব্যাটারি এক্সচেঞ্জ</span><span>−<?php echo number_format((float)$sale['exchange_amount'], 2); ?></span></div>
                <div class="inv-block-row"><span>নগদ/অ্যাকাউন্ট</span><span><?php echo number_format((float)($sale['cash_paid_amount'] ?? 0), 2); ?></span></div>
                <div class="inv-block-row"><span>মোট পরিশোধ</span><span><?php echo number_format((float)($sale['paid_amount'] ?? 0), 2); ?></span></div>
                <?php endif; ?>
                <?php break;

            case 'discount': ?>
                <div class="inv-block-title">🏷️ ডিসকাউন্ট</div>
                <div class="inv-block-row"><span>পরিমাণ</span><span><?php echo number_format((float)($sale['discount'] ?? 0), 2); ?></span></div>
                <?php break;

            case 'shipping': ?>
                <div class="inv-block-title">🚚 শিপিং</div>
                <div class="inv-block-row"><span>খরচ</span><span>0.00</span></div>
                <?php break;

            case 'notes': ?>
                <div class="inv-block-title">📝 নোট</div>
                <div style="font-size:11px; color:#64748B;"><?php echo htmlspecialchars($sale['draft_note'] ?? ''); ?></div>
                <?php break;

            case 'terms': ?>
                <div class="inv-block-title">📋 শর্তাবলী</div>
                <div style="font-size:10.5px; color:#64748B;">পণ্য একবার বিক্রি হলে ফেরত নেওয়া হয় না, তবে বিনিময়যোগ্য।</div>
                <?php break;

            case 'button_save': ?>
                <button class="no-print" style="width:100%;height:100%;background:#0B8043;color:#fff;border:none;border-radius:6px;font-weight:700;">💾 Save</button>
                <?php break;

            case 'button_print': ?>
                <button class="no-print" onclick="window.print()" style="width:100%;height:100%;background:#0091EA;color:#fff;border:none;border-radius:6px;font-weight:700;">🖨️ Print</button>
                <?php break;
        endswitch; ?>
    </div>
    <?php endforeach; ?>
    <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($sale['created_by'] ?? 0)) : ''; ?>
</div>

<div class="inv-print-btn no-print">
    <button onclick="window.print()">🖨️ প্রিন্ট করুন</button>
</div>

<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body>
</html>
