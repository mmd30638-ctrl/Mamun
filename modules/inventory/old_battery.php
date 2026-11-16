<?php
require_once '../../includes/config.php';
require_once '../../includes/sales/sale_common.php';
ensure_sales_schema($db);

$is_english = current_lang() === 'en';
$h = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$money = static function ($n) {
    return '৳' . number_format((float)$n, 2);
};

$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
$stock_sql = $store_id > 0
    ? 'COALESCE(ss.qty, p.stock) AS qty'
    : 'p.stock AS qty';
$stock_join = $store_id > 0
    ? ' LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ' . $store_id . ' '
    : '';

$stock_rows = [];
$stock_q = @$db->query(
    "SELECT p.id, p.name, p.sku, p.purchase_price, $stock_sql
     FROM products p
     $stock_join
     WHERE p.is_old_battery = 1
     ORDER BY p.name ASC"
);
if ($stock_q) {
    while ($row = $stock_q->fetch_assoc()) {
        $qty = (float)$row['qty'];
        $cost = (float)$row['purchase_price'];
        $stock_rows[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'sku' => (string)($row['sku'] ?? ''),
            'qty' => $qty,
            'cost' => $cost,
            'value' => $qty * $cost,
        ];
    }
}

$history = [];
$hist = @$db->query(
    "SELECT
        m.created_at,
        m.movement_type,
        m.qty_in,
        m.qty_out,
        m.unit_value,
        m.line_value,
        m.note,
        p.name AS product_name,
        s.invoice_number,
        c.name AS customer_name
     FROM stock_movements m
     INNER JOIN products p ON p.id = m.product_id
     LEFT JOIN sales s ON m.ref_type = 'sale' AND s.id = m.ref_id
     LEFT JOIN customers c ON c.id = m.customer_id
     WHERE p.is_old_battery = 1
        OR m.movement_type = 'exchange_in'
     ORDER BY m.id DESC
     LIMIT 80"
);
if ($hist) {
    while ($row = $hist->fetch_assoc()) {
        $history[] = $row;
    }
}

$exchanges = [];
$exq = @$db->query(
    "SELECT
        s.completed_at,
        s.invoice_number,
        COALESCE(NULLIF(c.name, ''), s.guest_customer_name, 'খুচরা') AS customer_name,
        e.item_name,
        e.qty,
        e.exchange_rate,
        e.line_total
     FROM sale_exchange_items e
     INNER JOIN sales s ON s.id = e.sale_id AND s.status = 'completed'
     LEFT JOIN customers c ON c.id = s.customer_id
     ORDER BY e.id DESC
     LIMIT 50"
);
if ($exq) {
    while ($row = $exq->fetch_assoc()) {
        $exchanges[] = $row;
    }
}

$resales = [];
$rq = @$db->query(
    "SELECT
        s.completed_at,
        s.invoice_number,
        COALESCE(NULLIF(c.name, ''), s.guest_customer_name, 'খুচরা') AS customer_name,
        COALESCE(NULLIF(si.item_name, ''), p.name) AS item_name,
        si.qty,
        si.purchase_price,
        si.sale_price,
        (si.sale_price - si.purchase_price) * si.qty AS profit
     FROM sale_items si
     INNER JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
     INNER JOIN products p ON p.id = si.product_id AND p.is_old_battery = 1
     LEFT JOIN customers c ON c.id = s.customer_id
     ORDER BY si.id DESC
     LIMIT 50"
);
if ($rq) {
    while ($row = $rq->fetch_assoc()) {
        $resales[] = $row;
    }
}

$stock_qty = 0.0;
$stock_value = 0.0;
foreach ($stock_rows as $row) {
    $stock_qty += (float)$row['qty'];
    $stock_value += (float)$row['value'];
}

$ame_body_class = 'rstk-body';
include '../../partials/header.php';
?>
<style>
.obb-page { max-width: 980px; margin: 0 auto; padding: 12px 12px 40px; }
.obb-lead { color:#64748b; font-weight:600; font-size:13px; line-height:1.5; }
.obb-kpis { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:12px 0 18px; }
.obb-kpi { background:#fff; border:1px solid #cfe3f7; border-radius:14px; padding:14px; }
.obb-kpi b { display:block; font-size:20px; color:#0B1E4D; }
.obb-kpi span { color:#64748b; font-size:12px; font-weight:700; }
.obb-card { background:#fff; border:1px solid #cfe3f7; border-radius:16px; overflow:hidden; margin-bottom:16px; }
.obb-card h2 { margin:0; padding:12px 14px; background:#F8FBFF; font-size:15px; }
.obb-card table { width:100%; border-collapse:collapse; }
.obb-card th, .obb-card td { padding:10px 12px; border-top:1px solid #eef3f8; text-align:left; font-size:13px; }
.obb-card th { color:#64748b; font-size:11px; }
.obb-empty { padding:20px; color:#0E8F52; font-weight:700; }
@media (max-width: 720px) {
    .obb-kpis { grid-template-columns:1fr; }
    .obb-card { overflow-x:auto; }
}
</style>
<div class="obb-page">
    <h1><?php echo $h($is_english ? 'Old battery stock' : 'পুরাতন ব্যাটারি স্টক'); ?></h1>
    <p class="obb-lead"><?php echo $h($is_english
        ? 'Exchange-in, current stock value, later resale and profit — all in one place.'
        : 'এক্সচেঞ্জে আসা স্টক, বর্তমান মূল্য, পরে বিক্রি ও লাভ — এখানে একসাথে।'); ?></p>

    <div class="obb-kpis">
        <div class="obb-kpi">
            <span><?php echo $h($is_english ? 'Total quantity' : 'মোট পরিমাণ'); ?></span>
            <b><?php echo $h(rtrim(rtrim(number_format($stock_qty, 3, '.', ''), '0'), '.') ?: '0'); ?></b>
        </div>
        <div class="obb-kpi">
            <span><?php echo $h($is_english ? 'Inventory value' : 'স্টকের মূল্য'); ?></span>
            <b><?php echo $h($money($stock_value)); ?></b>
        </div>
    </div>

    <section class="obb-card">
        <h2><?php echo $h($is_english ? 'Current old battery stock' : 'এখনকার পুরাতন ব্যাটারি স্টক'); ?></h2>
        <?php if (!$stock_rows): ?>
            <p class="obb-empty"><?php echo $h($is_english
                ? 'No old-battery products yet. Mark a product as old battery, or take one in on a sale.'
                : 'এখনো পুরাতন ব্যাটারি নেই। পণ্য খাতে একটি ক্যাটাগরি এক্সচেঞ্জ হিসেবে নির্ধারণ করুন।'); ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo $h($is_english ? 'Product' : 'পণ্য'); ?></th>
                    <th><?php echo $h($is_english ? 'Qty' : 'পরিমাণ'); ?></th>
                    <th><?php echo $h($is_english ? 'Unit cost' : 'একক মূল্য'); ?></th>
                    <th><?php echo $h($is_english ? 'Value' : 'মূল্য'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock_rows as $row): ?>
                <tr>
                    <td><?php echo $h($row['name']); ?><?php if ($row['sku'] !== ''): ?><br><small><?php echo $h($row['sku']); ?></small><?php endif; ?></td>
                    <td><?php echo $h($row['qty']); ?></td>
                    <td><?php echo $h($money($row['cost'])); ?></td>
                    <td><?php echo $h($money($row['value'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="obb-card">
        <h2><?php echo $h($is_english ? 'Exchange received' : 'এক্সচেঞ্জে গ্রহণ'); ?></h2>
        <?php if (!$exchanges): ?>
            <p class="obb-empty"><?php echo $h($is_english ? 'No exchange yet.' : 'এখনো এক্সচেঞ্জ হয়নি।'); ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo $h($is_english ? 'Date' : 'তারিখ'); ?></th>
                    <th><?php echo $h($is_english ? 'Invoice' : 'ইনভয়েস'); ?></th>
                    <th><?php echo $h($is_english ? 'Customer' : 'ক্রেতা'); ?></th>
                    <th><?php echo $h($is_english ? 'Product' : 'পণ্য'); ?></th>
                    <th><?php echo $h($is_english ? 'IN' : 'ইন'); ?></th>
                    <th><?php echo $h($is_english ? 'Value' : 'মূল্য'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exchanges as $row): ?>
                <tr>
                    <td><?php echo $h($row['completed_at'] ? date('d/m/Y', strtotime((string)$row['completed_at'])) : '—'); ?></td>
                    <td><?php echo $h($row['invoice_number']); ?></td>
                    <td><?php echo $h($row['customer_name']); ?></td>
                    <td><?php echo $h($row['item_name']); ?></td>
                    <td><?php echo $h($row['qty']); ?></td>
                    <td><?php echo $h($money($row['line_total'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="obb-card">
        <h2><?php echo $h($is_english ? 'Resale & profit' : 'পুনরায় বিক্রি ও লাভ'); ?></h2>
        <?php if (!$resales): ?>
            <p class="obb-empty"><?php echo $h($is_english ? 'No old-battery resale yet.' : 'এখনো পুরাতন ব্যাটারি বিক্রি হয়নি।'); ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo $h($is_english ? 'Date' : 'তারিখ'); ?></th>
                    <th><?php echo $h($is_english ? 'Invoice' : 'ইনভয়েস'); ?></th>
                    <th><?php echo $h($is_english ? 'Customer' : 'ক্রেতা'); ?></th>
                    <th><?php echo $h($is_english ? 'OUT' : 'আউট'); ?></th>
                    <th><?php echo $h($is_english ? 'Cost' : 'কস্ট'); ?></th>
                    <th><?php echo $h($is_english ? 'Sale' : 'বিক্রি'); ?></th>
                    <th><?php echo $h($is_english ? 'Profit' : 'লাভ'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resales as $row): ?>
                <tr>
                    <td><?php echo $h($row['completed_at'] ? date('d/m/Y', strtotime((string)$row['completed_at'])) : '—'); ?></td>
                    <td><?php echo $h($row['invoice_number']); ?></td>
                    <td><?php echo $h($row['customer_name']); ?></td>
                    <td><?php echo $h($row['qty']); ?></td>
                    <td><?php echo $h($money((float)$row['purchase_price'] * (float)$row['qty'])); ?></td>
                    <td><?php echo $h($money((float)$row['sale_price'] * (float)$row['qty'])); ?></td>
                    <td><?php echo $h($money($row['profit'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="obb-card">
        <h2><?php echo $h($is_english ? 'Stock movement' : 'স্টক চলাচল'); ?></h2>
        <?php if (!$history): ?>
            <p class="obb-empty"><?php echo $h($is_english ? 'No movement recorded yet.' : 'এখনো কোনো চলাচল নেই।'); ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?php echo $h($is_english ? 'Date' : 'তারিখ'); ?></th>
                    <th><?php echo $h($is_english ? 'Transaction' : 'লেনদেন'); ?></th>
                    <th><?php echo $h($is_english ? 'IN' : 'ইন'); ?></th>
                    <th><?php echo $h($is_english ? 'OUT' : 'আউট'); ?></th>
                    <th><?php echo $h($is_english ? 'Value' : 'মূল্য'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                <?php
                $label = (string)$row['movement_type'] === 'exchange_in'
                    ? ($is_english ? 'Customer exchange' : 'ক্রেতা এক্সচেঞ্জ')
                    : ($is_english ? 'Resale' : 'পুনরায় বিক্রি');
                if (!empty($row['customer_name'])) {
                    $label .= ' · ' . $row['customer_name'];
                }
                if (!empty($row['invoice_number'])) {
                    $label .= ' · ' . $row['invoice_number'];
                }
                ?>
                <tr>
                    <td><?php echo $h($row['created_at'] ? date('d/m/Y H:i', strtotime((string)$row['created_at'])) : '—'); ?></td>
                    <td><?php echo $h($label); ?><br><small><?php echo $h($row['product_name']); ?></small></td>
                    <td><?php echo $h((float)$row['qty_in'] ?: '0'); ?></td>
                    <td><?php echo $h((float)$row['qty_out'] ?: '0'); ?></td>
                    <td><?php echo $h($money($row['line_value'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</div>
<?php include '../../partials/footer.php'; ?>
