<?php 
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/sales/sale_invoice.php';
$sale_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT s.*, a.account_name, 
            c.name AS customer_name, c.phone AS customer_phone, c.customer_code, c.address AS customer_address,
            c.is_supplier, c.linked_supplier_id, c.payable_balance, c.receivable_balance
     FROM sales s 
     LEFT JOIN accounts a ON s.account_id = a.id 
     LEFT JOIN customers c ON s.customer_id = c.id 
     WHERE s.id = ?"
);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die('❌ বিক্রয় তথ্য পাওয়া যায়নি।');
}

$items_stmt = $db->prepare("SELECT si.*, COALESCE(NULLIF(si.item_name, ''), p.name) AS name FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?");
$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>ইনভয়েস #<?php echo $sale_id; ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 40px; color: #333; line-height: 1.6; }
        .invoice-box { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 30px; }
        .header-table { width: 100%; border-bottom: 2px solid var(--dark-blue); padding-bottom: 20px; }
        .title { font-size: 28px; color: #0D47A1; font-weight: bold; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .main-table th { background: #f8f9fa; padding: 12px; border: 1px solid #dee2e6; text-align: left; }
        .main-table td { padding: 12px; border: 1px solid #dee2e6; }
        .summary-table { width: 300px; margin-left: auto; margin-top: 20px; }
        .summary-table td { padding: 8px; font-weight: bold; }
        .no-memo-print { display: none !important; }
        @media print { .no-print, .no-memo-print { display: none !important; } }
    </style>

</head>
<body class="ame-paper-a4" data-paper="A4">
    <div class="invoice-box" id="memo-container" data-ame-capture="clone">
        <table class="header-table">
            <tr>
                <td>
                    <?php echo shop_print_logo_tag(60); ?>
                    <span class="title"><?php echo htmlspecialchars($shop['shop_name'] ?? ''); ?></span><br>
                    <?php echo htmlspecialchars($shop['shop_address'] ?? ''); ?><br>
                    ফোন: <?php echo htmlspecialchars($shop['shop_phone'] ?? ''); ?>
                    <?php
                    $c_name_a4 = trim((string)($sale['customer_name'] ?? $sale['guest_customer_name'] ?? ''));
                    $c_phone_a4 = trim((string)($sale['customer_phone'] ?? $sale['guest_customer_phone'] ?? ''));
                    $c_code_a4 = trim((string)($sale['customer_code'] ?? ''));
                    $c_addr_a4 = trim((string)($sale['customer_address'] ?? ''));
                    $is_sup_a4 = !empty($sale['is_supplier']);
                    ?>
                    <?php if ($c_name_a4 !== ''): ?>
                    <div style="margin-top:10px; padding:6px 10px; background:#f8fafc; border-left:3px solid #0D47A1; border-radius:4px; font-size:13px;">
                        <strong><?php echo $is_sup_a4 ? 'সরবরাহকারী / পার্টি:' : 'ক্রেতার নাম:'; ?></strong> <?php echo htmlspecialchars($c_name_a4); ?>
                        <?php if ($is_sup_a4): ?><span style="background:#E0F2FE; color:#0369A1; padding:1px 6px; border-radius:4px; font-size:11px; margin-left:4px; font-weight:700;">সরবরাহকারী</span><?php endif; ?>
                        <?php if ($c_code_a4 !== ''): ?> (<?php echo htmlspecialchars($c_code_a4); ?>)<?php endif; ?>
                        <?php if ($c_phone_a4 !== ''): ?><br>মোবাইল: <?php echo htmlspecialchars($c_phone_a4); ?><?php endif; ?>
                        <?php if ($c_addr_a4 !== ''): ?><br>ঠিকানা: <?php echo htmlspecialchars($c_addr_a4); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    <h2 style="margin:0; color:#666;">ইনভয়েস</h2>
                    নম্বর: #<?php echo $sale_id; ?><br>
                    তারিখ: <?php echo date('d M, Y', strtotime($sale['sale_date'])); ?>
                    <?php if (trim((string)($sale['vehicle_no'] ?? '')) !== ''): ?>
                    <br>গাড়ির নম্বর: <?php echo htmlspecialchars((string)$sale['vehicle_no'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table class="main-table">
            <thead>
                <tr>
                    <th>পণ্য বিবরণ</th>
                    <th style="text-align:center;">পরিমাণ</th>
                    <th style="text-align:right;">একক মূল্য</th>
                    <th style="text-align:right;">মোট</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td style="text-align:center;"><?php echo htmlspecialchars($row['qty']); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float)($row['sale_price'] ?? 0), 2); ?></td>
                    <td style="text-align:right;"><?php echo number_format($row['sale_price'] * $row['qty'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php
        if (!function_exists('ame_sale_load_exchange_items')) {
            require_once '../../includes/sales/sale_exchange.php';
        }
        $ex_rows = ame_sale_load_exchange_items($db, $sale_id);
        $ex_amount = (float)($sale['exchange_amount'] ?? 0);
        if ($ex_rows):
        ?>
        <p style="margin:18px 0 6px; font-weight:700;">পুরাতন ব্যাটারি গ্রহণ / এক্সচেঞ্জ</p>
        <table class="main-table">
            <thead>
                <tr>
                    <th>পণ্য বিবরণ</th>
                    <th style="text-align:center;">পরিমাণ</th>
                    <th style="text-align:right;">এক্সচেঞ্জ রেট</th>
                    <th style="text-align:right;">মোট</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ex_rows as $ex_row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ex_row['name']); ?></td>
                    <td style="text-align:center;"><?php echo htmlspecialchars($ex_row['qty']); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float)$ex_row['exchange_rate'], 2); ?></td>
                    <td style="text-align:right;">−<?php echo number_format((float)$ex_row['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:13px;">ক্রেতা <?php echo htmlspecialchars((string)array_sum(array_map(static function ($r) { return (float)$r['qty']; }, $ex_rows))); ?>টি পুরাতন ব্যাটারি ফেরত দিয়েছেন, মূল্য ৳<?php echo number_format($ex_amount, 2); ?>।</p>
        <?php endif; ?>
        <?php
        if (!function_exists('ame_sale_load_invoice_services')) {
            require_once '../../includes/sales/sale_invoice.php';
        }
        $svc_rows = ame_sale_load_invoice_services(
            $db,
            $sale_id,
            (float)($sale['service_amount'] ?? 0)
        );
        if ($svc_rows):
        ?>
        <p style="margin:18px 0 6px; font-weight:700;">ইঞ্জিনিয়ার ও কাজের বিল</p>
        <table class="main-table">
            <thead>
                <tr>
                    <th>ইঞ্জিনিয়ার</th>
                    <th>কাজের বিবরণ</th>
                    <th style="text-align:right;">টাকা</th>
                </tr>
            </thead>
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
                    <td style="text-align:right; font-weight:700;"><?php echo number_format((float)($svc['amount'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <table class="summary-table">
            <tr><td>মোট বিল:</td><td style="text-align:right;"><?php echo number_format((float)($sale['total_bill'] ?? 0), 2); ?> ৳</td></tr>
            <tr><td>ডিসকাউন্ট:</td><td style="text-align:right; color:red;">- <?php echo number_format((float)($sale['discount'] ?? 0), 2); ?> ৳</td></tr>
            <?php
            if (function_exists('ame_sale_align_net_with_ledger')) {
                ame_sale_align_net_with_ledger($db);
            }
            if (function_exists('ame_sale_balance_apply')) {
                $sale = ame_sale_balance_apply($db, $sale);
            }
            $a4_bill = (float)($sale['invoice_bill'] ?? $sale['balance_book']['current'] ?? (function_exists('ame_sale_this_bill') ? ame_sale_this_bill($sale) : ($sale['net_payable'] ?? 0)));
            $a4_book = $sale['balance_book'] ?? ame_balance_book(
                'sale',
                $sale['previous_customer_balance'] ?? 0,
                $a4_bill,
                $sale['paid_amount'] ?? 0,
                $sale['customer_balance_after'] ?? null,
                $sale['due_adjustment_amount'] ?? 0
            );
            ?>
            <tr><td>মোট বিক্রয়:</td><td style="text-align:right;"><?php echo number_format($a4_bill, 2); ?> ৳</td></tr>
            <?php if ((float)($sale['exchange_amount'] ?? 0) > 0.009): ?>
            <tr><td>পুরাতন ব্যাটারি এক্সচেঞ্জ:</td><td style="text-align:right;">−<?php echo number_format((float)$sale['exchange_amount'], 2); ?> ৳</td></tr>
            <tr><td>নগদ/অ্যাকাউন্ট পরিশোধ:</td><td style="text-align:right;"><?php echo number_format((float)($sale['cash_paid_amount'] ?? 0), 2); ?> ৳</td></tr>
            <?php endif; ?>
            <tr><td>আজকের পরিশোধ (−):</td><td style="text-align:right;"><?php echo number_format((float)($sale['paid_amount'] ?? 0), 2); ?> ৳</td></tr>
            <?php
            $a4_prev = (float)$a4_book['previous'];
            $a4_cur = (float)$a4_book['current'];
            $a4_paid = (float)$a4_book['paid'];
            $a4_extra = (float)$a4_book['extra'];
            $a4_total = (float)$a4_book['total'];
            ?>
            <?php
            $a4_dep = function_exists('ame_memo_deposit_slice') ? ame_memo_deposit_slice($a4_book) : ['show' => false];
            if (!empty($a4_dep['show'])):
            ?>
            <tr><td style="color:#166534; font-weight:700;">জমা ছিল (+):</td><td style="text-align:right; color:#166534; font-weight:700;">+<?php echo number_format((float)$a4_dep['before'], 2); ?> ৳</td></tr>
            <tr><td style="color:#b91c1c; font-weight:700;">এই বিল থেকে কাটা (−):</td><td style="text-align:right; color:#b91c1c; font-weight:700;">−<?php echo number_format((float)$a4_dep['used'], 2); ?> ৳</td></tr>
            <tr><td style="color:#166534; font-weight:800;">রয়ে গেল (+):</td><td style="text-align:right; color:#166534; font-weight:800;">+<?php echo number_format((float)$a4_dep['after'], 2); ?> ৳</td></tr>
            <tr><td colspan="2" style="text-align:center;font-weight:800;color:#0B1E4D;background:#ffffff;">+<?php echo number_format((float)$a4_dep['before'], 2); ?> − <?php echo number_format((float)$a4_dep['used'], 2); ?> = +<?php echo number_format((float)$a4_dep['after'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if ($a4_prev < -0.009): ?>
            <tr><td style="color:#166534; font-weight:600;"><?php echo htmlspecialchars(function_exists('ame_sale_memo_prev_label') ? ame_sale_memo_prev_label($a4_prev) : 'সাবেক পাবে', ENT_QUOTES, 'UTF-8'); ?>:</td><td style="text-align:right; color:#166534; font-weight:700;"><?php echo number_format(abs($a4_prev), 2); ?> ৳</td></tr>
            <?php elseif ($a4_prev > 0.009): ?>
            <tr><td><?php echo htmlspecialchars(function_exists('ame_sale_memo_prev_label') ? ame_sale_memo_prev_label($a4_prev) : 'সাবেক বকেয়া (+)', ENT_QUOTES, 'UTF-8'); ?>:</td><td style="text-align:right;"><?php echo number_format($a4_prev, 2); ?> ৳</td></tr>
            <?php else: ?>
            <tr><td>সাবেক বকেয়া:</td><td style="text-align:right;">0.00 ৳</td></tr>
            <?php endif; ?>
            <tr><td>বর্তমান বিল (+):</td><td style="text-align:right;"><?php echo number_format($a4_cur, 2); ?> ৳</td></tr>
            <?php if ($a4_extra > 0.009): ?>
            <tr><td>সাবেক ডিউ সমন্বয় (−):</td><td style="text-align:right;"><?php echo number_format($a4_extra, 2); ?> ৳</td></tr>
            <?php endif; ?>
            <?php if ($a4_total < -0.009): ?>
            <tr style="font-size:18px; color:#166534; font-weight:bold; background:#F0FDF4;"><td><?php echo htmlspecialchars(function_exists('ame_sale_memo_total_label') ? ame_sale_memo_total_label($a4_total) : 'কাস্টমার পাবে', ENT_QUOTES, 'UTF-8'); ?>:</td><td style="text-align:right;"><?php echo number_format(abs($a4_total), 2); ?> ৳</td></tr>
            <?php elseif ($a4_total > 0.009): ?>
            <tr style="font-size:18px; color:#991B1B; font-weight:bold; background:#FEF2F2;"><td><?php echo htmlspecialchars(function_exists('ame_sale_memo_total_label') ? ame_sale_memo_total_label($a4_total) : 'দোকান পাবে', ENT_QUOTES, 'UTF-8'); ?>:</td><td style="text-align:right;"><?php echo number_format($a4_total, 2); ?> ৳</td></tr>
            <?php else: ?>
            <tr style="font-size:18px; color:#1B5E20; font-weight:bold;"><td>সর্বমোট হিসাব:</td><td style="text-align:right;">0.00 ৳ (পরিশোধিত)</td></tr>
            <?php endif; ?>
            <?php if (function_exists('ame_balance_book_proof')): ?>
            <tr><td colspan="2" style="text-align:center;font-weight:800;color:#0B1E4D;padding-top:8px;"><?php echo htmlspecialchars(ame_balance_book_proof($a4_book), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
        </table>

        <div class="no-memo-print" style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; font-size: 12px; text-align: center;">
            <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($sale['created_by'] ?? 0)) : ''; ?>
        </div>
    </div>
    
    <div class="text-center no-print" style="margin-top:20px; text-align:center;">
        <button type="button" onclick="window.print()" style="padding:10px 30px; background:#1B5E20; color:white; border:none; cursor:pointer; border-radius:5px;">এ৪ ইনভয়েস প্রিন্ট</button>
        <button type="button" onclick="ameMemoCapture('share','sale-a4-<?php echo (int)$sale_id; ?>')" style="padding:10px 18px; margin-left:8px;">শেয়ার</button>
        <button type="button" onclick="ameMemoCapture('download','sale-a4-<?php echo (int)$sale_id; ?>')" style="padding:10px 18px; margin-left:8px;">পিডিএফ</button>
        <a href="pos.php" style="margin-left:10px; color:#0D47A1;">ফিরে যান</a>
        <script src="/assets/js/ame-memo-file.js"></script>
    </div>
<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body>
</html>