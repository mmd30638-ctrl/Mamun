<?php
/**
 * SALES REPORT — ইনভয়েস বিস্তারিত (View/Print/Share-এর জন্য)।
 * শুধু পড়া যায়, বিক্রয় তৈরি/বদল এখানে হয় না।
 */
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require_permission($db, 'm-sales-report');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Not found');
}

$en = function_exists('current_lang') && current_lang() === 'en';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $bn, string $eng) use ($en): string {
    return $en ? $eng : $bn;
};
$money = static function ($value): string {
    return number_format((float)$value, 2);
};
$qtyf = static function ($value): string {
    $s = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
};

$stmt = $db->prepare("SELECT s.*, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name, c.phone AS customer_phone FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ? AND (s.status IS NULL OR s.status = '' OR s.status = 'completed') LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Error');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) {
    http_response_code(404);
    exit('Not found');
}

/* পেমেন্ট মাধ্যম (accounts) + ইউনিট কলাম — না থাকলে বাদ। */
$pay_via = '';
try {
    $chk = @$db->query("SHOW TABLES LIKE 'accounts'");
    $has_acc = $chk && $chk->num_rows > 0;
    if ($chk) {
        $chk->close();
    }
    if ($has_acc) {
        $acc_id = (int)($sale['account_id'] ?? 0);
        if ($acc_id > 0) {
            $astmt = @$db->prepare("SELECT account_name FROM accounts WHERE id = ? LIMIT 1");
            if ($astmt) {
                $astmt->bind_param('i', $acc_id);
                $astmt->execute();
                if ($arow = $astmt->get_result()->fetch_assoc()) {
                    $pay_via = (string)($arow['account_name'] ?? '');
                }
                $astmt->close();
            }
        }
    }
} catch (Throwable $e) {
}
$unit_col = '';
try {
    foreach (['unit', 'unit_name'] as $cand) {
        $uchk = @$db->query("SHOW COLUMNS FROM `sale_items` LIKE '" . $db->real_escape_string($cand) . "'");
        $found = $uchk && $uchk->num_rows > 0;
        if ($uchk) {
            $uchk->close();
        }
        if ($found) {
            $unit_col = $cand;
            break;
        }
    }
} catch (Throwable $e) {
}

$items = [];
$unit_sel = $unit_col !== '' ? ", si.`{$unit_col}` AS unit_name" : '';
$istmt = $db->prepare("SELECT si.qty, si.sale_price, COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name{$unit_sel} FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC");
if ($istmt) {
    $istmt->bind_param('i', $id);
    $istmt->execute();
    $items = $istmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $istmt->close();
}

$ret_total = 0.0;
$rstmt = @$db->prepare("SELECT COALESCE(SUM(total_return_amount), 0) AS rt FROM sale_returns WHERE sale_id = ?");
if ($rstmt) {
    $rstmt->bind_param('i', $id);
    $rstmt->execute();
    if ($row = $rstmt->get_result()->fetch_assoc()) {
        $ret_total = (float)($row['rt'] ?? 0);
    }
    $rstmt->close();
}

$name = trim((string)($sale['customer_name'] ?? ''));
if ($name === '') {
    $name = $t('খুচরা', 'Retail');
}
$due = (float)($sale['due_amount'] ?? 0);
$paid = (float)($sale['paid_amount'] ?? 0);
if ($due <= 0.009) {
    $status = $t('পরিশোধিত', 'Paid');
} elseif ($paid > 0.009) {
    $status = $t('আংশিক', 'Partial');
} else {
    $status = $t('বাকি', 'Due');
}
$shop = function_exists('shop_name') ? (string)shop_name() : 'APONSHOP';
?>
<div style="text-align:center; border-bottom:2px solid #003F8F; padding-bottom:8px; margin-bottom:10px;">
    <p style="margin:0; font-size:20px; font-weight:800;"><?php echo $h($shop); ?></p>
    <p style="margin:4px 0 0; font-size:14px; font-weight:700;"><?php echo $h($t('বিক্রয় ইনভয়েস', 'Sales invoice')); ?> · <?php echo $h($sale['invoice_number'] ?: ('#' . $sale['id'])); ?></p>
    <p style="margin:4px 0 0; font-size:13px;"><?php
        $view_when = (string)($sale['sale_date'] ?: $sale['completed_at'] ?: $sale['created_at'] ?: '');
        echo $h($view_when !== '' ? date('d-m-Y h:i A', strtotime($view_when)) : '—');
    ?> · <?php echo $h($name); ?><?php if (!empty($sale['customer_phone'])): ?> · <?php echo $h($sale['customer_phone']); ?><?php elseif (!empty($sale['guest_customer_phone'])): ?> · <?php echo $h($sale['guest_customer_phone']); ?><?php endif; ?></p>
</div>
<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            <th style="text-align:left;"><?php echo $h($t('পণ্য', 'Item')); ?></th>
            <th><?php echo $h($t('পরিমাণ', 'Qty')); ?></th>
            <?php if ($unit_col !== ''): ?><th><?php echo $h($t('ইউনিট', 'Unit')); ?></th><?php endif; ?>
            <th style="text-align:right;"><?php echo $h($t('দাম', 'Price')); ?></th>
            <th style="text-align:right;"><?php echo $h($t('মোট', 'Total')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it):
            $qty = (float)($it['qty'] ?? 0);
            $price = (float)($it['sale_price'] ?? 0);
        ?>
        <tr>
            <td><?php echo $h($it['item_name'] ?: $t('পণ্য', 'Item')); ?></td>
            <td style="text-align:center;"><?php echo $h($qtyf($qty)); ?></td>
            <?php if ($unit_col !== ''): ?><td style="text-align:center;"><?php echo $h($it['unit_name'] ?? '—'); ?></td><?php endif; ?>
            <td style="text-align:right;"><?php echo $h($money($price)); ?></td>
            <td style="text-align:right;"><?php echo $h($money($qty * $price)); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div style="margin-top:10px; font-size:15px; font-weight:700;">
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('মোট বিল', 'Subtotal')); ?></span><span><?php echo $h($money($sale['total_bill'] ?? 0)); ?></span></div>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('ছাড়', 'Discount')); ?></span><span><?php echo $h($money($sale['discount'] ?? 0)); ?></span></div>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('মোট বিক্রয়', 'Total')); ?></span><span><?php echo $h($money($sale['net_payable'] ?? 0)); ?></span></div>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('পরিশোধিত', 'Paid')); ?></span><span><?php echo $h($money($paid)); ?></span></div>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('বাকি', 'Due')); ?></span><span><?php echo $h($money($due)); ?></span></div>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('স্ট্যাটাস', 'Status')); ?></span><span><?php echo $h($status); ?></span></div>
    <?php if ($pay_via !== ''): ?>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('পেমেন্ট মাধ্যম', 'Payment via')); ?></span><span><?php echo $h($pay_via); ?></span></div>
    <?php endif; ?>
    <?php if ($ret_total > 0.009): ?>
    <div style="display:flex; justify-content:space-between; padding:3px 0;"><span><?php echo $h($t('ফেরত', 'Returned')); ?></span><span><?php echo $h($money($ret_total)); ?></span></div>
    <?php endif; ?>
</div>
