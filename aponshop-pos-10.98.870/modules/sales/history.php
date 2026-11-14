<?php
require_once '../../includes/config.php';
require_once '../../includes/sales/sale_common.php';
require_once '../../includes/sales/sale_finance.php';
ensure_sales_schema($db);

$saleWhenExpr = ame_sale_history_effective_sql();

$is_admin = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
$today = date('Y-m-d');
$start = trim((string) ($_GET['start_date'] ?? ''));
$end = trim((string) ($_GET['end_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = $today;
}
if (strtotime($start) > strtotime($end)) {
    [$start, $end] = [$end, $start];
}

$q = "SELECT COUNT(*) AS n, COALESCE(SUM(net_payable),0) AS bill
      FROM sales s
      WHERE (s.status IS NULL OR s.status='' OR s.status='completed')
        AND DATE($saleWhenExpr) >= ?
        AND DATE($saleWhenExpr) <= ?";
$sum = $db->prepare($q);
$rows = [];
$total_n = 0;
$total_bill = 0.0;
if ($sum) {
    $sum->bind_param('ss', $start, $end);
    $sum->execute();
    $t = $sum->get_result()->fetch_assoc() ?: [];
    $sum->close();
    $total_n = (int) ($t['n'] ?? 0);
    $total_bill = (float) ($t['bill'] ?? 0);
}

$list = $db->prepare(
    "SELECT s.id, s.invoice_number, s.sale_date, s.created_at, s.completed_at, s.net_payable, s.customer_id,
            s.guest_customer_name, c.customer_code, c.name AS customer_name
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE (s.status IS NULL OR s.status='' OR s.status='completed')
       AND DATE($saleWhenExpr) >= ?
       AND DATE($saleWhenExpr) <= ?
     ORDER BY $saleWhenExpr DESC, s.id DESC
     LIMIT 800"
);
if ($list) {
    $list->bind_param('ss', $start, $end);
    $list->execute();
    $res = $list->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $list->close();
}

$chip = static function (string $from, string $to, string $label, string $start, string $end): string {
    $on = ($start === $from && $end === $to);
    $href = '?' . http_build_query(['start_date' => $from, 'end_date' => $to]);
    $cls = $on ? ' shist-chip on' : ' shist-chip';
    return '<a class="' . trim($cls) . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};

$clean = static function (string $value): string {
    if (function_exists('ame_clean_display_text')) {
        return ame_clean_display_text($value);
    }
    return trim($value);
};

$monthStart = date('Y-m-01');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekStart = date('Y-m-d', strtotime('-6 days'));

include '../../partials/header.php';
?>
<style>
.shist-wrap {
    width: 100%;
    max-width: none;
    padding: 15px;
    box-sizing: border-box;
    background: #fff;
    color: #111;
}
.shist-wrap h3 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 800;
    color: #111;
}
.shist-lead { margin: 0 0 14px; font-size: 13px; color: #525252; }
.shist-filter.card {
    padding: 14px 16px !important;
    margin-bottom: 14px;
}
.shist-chips {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin: 0 0 12px;
}
.shist-chip {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 8px 6px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    color: #111;
    background: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 800;
    box-sizing: border-box;
}
.shist-chip.on { background: #fff; color: #003F8F; border-color: #003F8F; box-shadow: inset 0 0 0 1.5px currentColor; }
.shist-chips a:nth-child(1) { color: #003F8F; border-color: #003F8F; }
.shist-chips a:nth-child(2) { color: #0f766e; border-color: #0f766e; }
.shist-chips a:nth-child(3) { color: #7c3aed; border-color: #7c3aed; }
.shist-chips a:nth-child(4) { color: #c2410c; border-color: #c2410c; }
.shist-dates {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}
.shist-dates label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    color: #111;
    margin: 0 0 4px;
}
.shist-dates input[type="date"] {
    display: block;
    width: 100%;
    box-sizing: border-box;
    min-height: 44px;
    padding: 9px 10px;
    border: 1px solid #d4d4d4;
    border-radius: 8px;
    background: #fff;
    color: #111;
    font-size: 15px;
}
.shist-dates button {
    border: 1.5px solid #003F8F;
    border-radius: 8px;
    background: #fff;
    color: #003F8F;
    font-weight: 800;
    font-size: 15px;
    min-height: 44px;
    padding: 11px 22px;
    cursor: pointer;
    white-space: nowrap;
}
.shist-kpi {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 12px 0 0;
}
.shist-kpi div {
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 10px 12px;
    background: #fff;
    border-color: #003F8F;
}
.shist-kpi div:nth-child(2) { border-color: #04933A; }
.shist-kpi div:nth-child(1) b { color: #003F8F; }
.shist-kpi div:nth-child(2) b { color: #04933A; }
.shist-kpi small { display: block; font-size: 11px; font-weight: 700; color: #525252; }
.shist-kpi b { display: block; font-size: 18px; font-weight: 800; color: #111; margin-top: 2px; }
.shist-card.card {
    padding: 0 !important;
    overflow-x: auto;
    width: 100%;
    margin-bottom: 0;
}
.shist-empty {
    padding: 40px 16px;
    text-align: center;
    font-weight: 700;
    color: #525252;
}
.shist-table,
table.shist-table {
    display: table !important;
    width: 100% !important;
    min-width: 100%;
    border-collapse: collapse !important;
    text-align: left;
    font-size: 14px;
    color: #111;
    background: #fff;
}
.shist-table thead,
table.shist-table thead {
    display: table-header-group !important;
}
.shist-table tbody,
table.shist-table tbody {
    display: table-row-group !important;
}
.shist-table tr,
table.shist-table tr {
    display: table-row !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
}
.shist-table th,
.shist-table td,
table.shist-table th,
table.shist-table td {
    display: table-cell !important;
    width: auto !important;
    padding: 12px !important;
    border: 0 !important;
    border-bottom: 1px solid #eee !important;
    vertical-align: middle;
    white-space: normal !important;
    box-sizing: border-box !important;
}
.shist-table th::before,
.shist-table td::before { content: none !important; display: none !important; }
html body table.shist-table thead tr {
    background: #fff !important;
    color: #003F8F !important;
}
html body table.shist-table thead th {
    color: #003F8F !important;
    background: #fff !important;
    font-weight: 800;
    border-bottom: 0 !important;
}
html body table.shist-table tbody tr,
html body table.shist-table tbody tr:nth-child(even),
html body table.shist-table tbody tr:hover { background: #fff !important; }
.shist-table .num { font-weight: 800; color: #111; }
.shist-table .inv { display: block; font-size: 12px; font-weight: 600; color: #525252; margin-top: 2px; }
.shist-table .when { font-weight: 800; color: #111; font-size: 13px; line-height: 1.35; white-space: nowrap !important; }
.shist-table .who { font-weight: 800; color: #111; }
.shist-table .cid { display: block; font-size: 12px; font-weight: 700; color: #525252; margin-top: 2px; }
.shist-table .amt { text-align: right; font-weight: 800; white-space: nowrap !important; }
.shist-table .memo { text-align: center; }
.shist-table .memo a {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    margin: 2px;
    padding: 4px 8px;
    border-radius: 8px;
    background: #fff;
    color: #003F8F;
    border: 1.5px solid #003F8F;
    font-weight: 800;
    text-decoration: none;
    font-size: 12px;
}
.shist-table .memo a:nth-child(1) { color: #003F8F; border-color: #003F8F; }
.shist-table .memo a:nth-child(2) { color: #04933A; border-color: #04933A; }
.shist-table .memo a:nth-child(3) { color: #0f766e; border-color: #0f766e; }
.shist-table .memo a:nth-child(4) { color: #229ED9; border-color: #229ED9; }
.shist-table .memo a:nth-child(5) { color: #c2410c; border-color: #c2410c; }
.shist-table .memo a.shist-edit { color: #7c3aed; border-color: #7c3aed; }
@media (max-width: 720px) {
    .shist-wrap { padding: 12px 10px 20px; }
    .shist-dates { grid-template-columns: 1fr 1fr; }
    .shist-dates .go { grid-column: 1 / -1; }
    .shist-dates .go button { width: 100%; }
    .shist-chips { gap: 6px; }
    .shist-chip { min-height: 40px; font-size: 13px; }
    .shist-table,
    table.shist-table { font-size: 13px; }
    .shist-table th,
    .shist-table td,
    table.shist-table th,
    table.shist-table td { padding: 10px 8px !important; }
}
</style>
<div class="shist-wrap">
    <h3><?php echo ame_l('বিক্রয় ইতিহাস', 'Sales history'); ?></h3>
    <p class="shist-lead"><?php echo ame_l('তারিখ বেছে খুঁজুন। মেমো থেকে প্রিন্ট, শেয়ার ও WhatsApp।', 'Pick a date. Print, share, or send the memo on WhatsApp.'); ?></p>

    <form class="card shist-filter" method="get" action="">
        <div class="shist-chips">
            <?php
            echo $chip($today, $today, ame_l('আজ', 'Today'), $start, $end);
            echo $chip($yesterday, $yesterday, ame_l('গতকাল', 'Yesterday'), $start, $end);
            echo $chip($weekStart, $today, ame_l('৭ দিন', '7 days'), $start, $end);
            echo $chip($monthStart, $today, ame_l('এই মাস', 'This month'), $start, $end);
            ?>
        </div>
        <div class="shist-dates">
            <div>
                <label for="shist-from"><?php echo ame_l('শুরু', 'From'); ?></label>
                <input id="shist-from" type="date" name="start_date" value="<?php echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div>
                <label for="shist-to"><?php echo ame_l('শেষ', 'To'); ?></label>
                <input id="shist-to" type="date" name="end_date" value="<?php echo htmlspecialchars($end, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="go">
                <button type="submit" class="ame-calc-btn"><?php echo ame_l('তারিখ খুঁজুন', 'Search dates'); ?></button>
            </div>
        </div>
        <div class="shist-kpi">
            <div><small><?php echo ame_l('বিল', 'Bills'); ?></small><b><?php echo number_format($total_n); ?></b></div>
            <div><small><?php echo ame_l('মোট', 'Total'); ?></small><b>৳<?php echo number_format($total_bill, 2); ?></b></div>
        </div>
    </form>

    <div class="card shist-card">
        <?php if ($rows === []): ?>
            <div class="shist-empty"><?php echo ame_l('এই তারিখে কোনো বিক্রয় নেই।', 'No sales in this date range.'); ?></div>
        <?php else: ?>
        <table class="shist-table">
            <thead>
                <tr>
                    <th><?php echo ame_l('নম্বর', 'Number'); ?></th>
                    <th><?php echo ame_l('তারিখ · সময়', 'Date · time'); ?></th>
                    <th><?php echo ame_l('কাস্টমার', 'Customer'); ?></th>
                    <th class="amt"><?php echo ame_l('বিল', 'Bill'); ?></th>
                    <th class="memo"><?php echo ame_l('মেমো', 'Memo'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $sid = (int) $row['id'];
                $whenLabel = ame_sale_history_datetime_label($row);
                $inv = $clean((string) ($row['invoice_number'] ?? ''));
                $code = $clean((string) ($row['customer_code'] ?? ''));
                $cname = $clean((string) ($row['customer_name'] ?? ''));
                $guest = $clean((string) ($row['guest_customer_name'] ?? ''));
                $cid = (int) ($row['customer_id'] ?? 0);
                if ($cname === '' && $guest !== '') {
                    $cname = $guest;
                }
                if ($cname === '') {
                    $cname = ame_l('হাঁটা ক্রেতা', 'Walk-in customer');
                }
                if ($code === '' && $cid > 0) {
                    $code = '#' . $cid;
                }
                if ($code === '') {
                    $code = '—';
                }
                ?>
                <tr>
                    <td>
                        <span class="num">#<?php echo $sid; ?></span>
                        <?php if ($inv !== '' && $inv !== (string) $sid): ?>
                            <span class="inv"><?php echo htmlspecialchars($inv, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="when"><?php echo htmlspecialchars($whenLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td>
                        <span class="who"><?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="cid"><?php echo ame_l('আইডি', 'ID'); ?> <?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td class="amt">৳<?php echo number_format((float) ($row['net_payable'] ?? 0), 2); ?></td>
                    <td class="memo">
                        <a href="invoice.php?id=<?php echo $sid; ?>" title="<?php echo ame_l('ইনভয়েস দেখুন', 'View invoice'); ?>"><?php echo ame_l('দেখুন', 'View'); ?></a>
                        <a href="invoice.php?id=<?php echo $sid; ?>&amp;print=1" title="<?php echo ame_l('ইনভয়েস প্রিন্ট', 'Print invoice'); ?>"><?php echo ame_l('প্রিন্ট', 'Print'); ?></a>
                        <a href="invoice.php?id=<?php echo $sid; ?>&amp;share=1" title="<?php echo ame_l('ইনভয়েস শেয়ার', 'Share invoice'); ?>"><?php echo ame_l('শেয়ার', 'Share'); ?></a>
                        <a href="invoice.php?id=<?php echo $sid; ?>&amp;tg=1" target="_blank" rel="noopener" title="Telegram-এ ছবি সহ শেয়ার করুন">Telegram</a>
                        <a href="invoice.php?id=<?php echo $sid; ?>&amp;download=1" title="<?php echo ame_l('পিডিএফ ডাউনলোড', 'Download PDF'); ?>"><?php echo ame_l('পিডিএফ', 'PDF'); ?></a>
                        <?php if (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin'): ?>
                        <a class="shist-edit" href="/modules/reports/invoice_edit.php?id=<?php echo $sid; ?>" title="<?php echo ame_l('কাস্টমার বদলান — টাকা অপরিবর্তিত', 'Change customer — amounts stay the same'); ?>"><?php echo ame_l('এডিট', 'Edit'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
