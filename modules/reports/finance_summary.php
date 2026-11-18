<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT al.type, COALESCE(ec.name, al.ref_type, 'অশ্রেণীবদ্ধ') as category, SUM(al.amount) as total
         FROM account_ledger al
         LEFT JOIN expense_categories ec ON al.category_id = ec.id
         WHERE DATE(al.created_at) BETWEEN ? AND ?
         GROUP BY al.type, category
         ORDER BY al.type ASC, total DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['type'] === 'credit' ? 'আয়' : 'ব্যয়', $row['category'], $row['total']];
    }
    report_export_csv('finance_summary', ['ধরন', 'ক্যাটাগরি', 'পরিমাণ'], $csv_rows);
}

include '../../partials/header.php';

$stmt = $db->prepare(
    "SELECT al.type, COALESCE(ec.name, al.ref_type, 'অশ্রেণীবদ্ধ') as category, SUM(al.amount) as total
     FROM account_ledger al
     LEFT JOIN expense_categories ec ON al.category_id = ec.id
     WHERE DATE(al.created_at) BETWEEN ? AND ?
     GROUP BY al.type, category
     ORDER BY al.type ASC, total DESC"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$income_rows = array_values(array_filter($rows, fn($r) => $r['type'] === 'credit'));
$expense_rows = array_values(array_filter($rows, fn($r) => $r['type'] === 'debit'));

$total_income = array_sum(array_column($income_rows, 'total'));
$total_expense = array_sum(array_column($expense_rows, 'total'));
$net = $total_income - $total_expense;

$csv_url = '?export=csv';

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'finance-summary-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('আয়-ব্যয় সারাংশ', 'Finance Summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('আয়-ব্যয় সারাংশ', 'Finance Summary'),
        $start_date . ' — ' . $end_date,
        ame_l('নিট', 'Net') . ': ৳' . number_format($net, 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('Finance Summary (আয়-ব্যয় বিশ্লেষণ)', '💰', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট আয়</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format($total_income, 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#C62828,#E53935); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট ব্যয়</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format($total_expense, 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0B1E4D,#0091EA); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">নিট (আয় − ব্যয়)</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format($net, 2); ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:14px;">
        <div class="card">
            <h4 style="margin-top:0; color:#0B8043; font-size:14px;">↗️ আয়ের উৎস</h4>
            <?php if (empty($income_rows)): report_empty_state(); else: foreach ($income_rows as $r): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:13px;">
                    <span><?php echo htmlspecialchars($r['category']); ?></span>
                    <strong style="color:#0B8043;">৳<?php echo number_format((float)$r['total'], 2); ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h4 style="margin-top:0; color:#C62828; font-size:14px;">↙️ ব্যয়ের খাত</h4>
            <?php if (empty($expense_rows)): report_empty_state(); else: foreach ($expense_rows as $r): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:13px;">
                    <span><?php echo htmlspecialchars($r['category']); ?></span>
                    <strong style="color:#C62828;">৳<?php echo number_format((float)$r['total'], 2); ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    </div>

    <?php report_footer_meta('RPT-FINANCE-SUMMARY'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
