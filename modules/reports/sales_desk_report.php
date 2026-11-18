<?php
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

function ame_sales_report_range(): array
{
    $start = trim((string) ($_GET['start_date'] ?? ''));
    $end = trim((string) ($_GET['end_date'] ?? ''));
    $today = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = $today;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = $today;
    }
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }
    return [$start, $end];
}

function ame_sales_report_rows(mysqli $db, string $start, string $end): array
{
    $sql = "SELECT s.id, s.invoice_number, s.sale_date, s.net_payable, s.paid_amount, s.due_amount,
                   COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            WHERE s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?";
    $types = 'ss';
    $params = [$start, $end];
    $store_id = function_exists('current_store_id') ? (int) current_store_id() : 0;
    $default_store = function_exists('ame_default_store_id') ? (int) ame_default_store_id($db) : 0;
    if ($store_id > 0) {
        if ($store_id === $default_store) {
            $sql .= ' AND (s.store_id = ? OR s.store_id IS NULL)';
        } else {
            $sql .= ' AND s.store_id = ?';
        }
        $types .= 'i';
        $params[] = $store_id;
    }
    $sql .= ' ORDER BY s.sale_date DESC, s.id DESC LIMIT 3000';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $sql = str_replace("NULLIF(s.guest_customer_name, ''), ", '', $sql);
        $stmt = $db->prepare($sql);
    }
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function ame_sales_report_money($value): string
{
    return number_format((float) $value, 2, '.', '');
}

[$start_date, $end_date] = ame_sales_report_range();
$rows = ame_sales_report_rows($db, $start_date, $end_date);
$net = 0.0;
$paid = 0.0;
$due = 0.0;
foreach ($rows as $row) {
    $net += (float) ($row['net_payable'] ?? 0);
    $paid += (float) ($row['paid_amount'] ?? 0);
    $due += (float) ($row['due_amount'] ?? 0);
}

$en = function_exists('current_lang') && current_lang() === 'en';
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

if (($_GET['export'] ?? '') === 'xls') {
    $filename = 'Aponshop_sales_' . $start_date . '_' . $end_date . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><thead><tr>';
    foreach (['তারিখ', 'ইনভয়েস', 'ক্রেতা', 'মোট বিল', 'জমা', 'বাকি'] as $col) {
        echo '<th>' . $h($col) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $name = trim((string) ($row['customer_name'] ?? ''));
        if ($name === '') {
            $name = 'খুচরা';
        }
        echo '<tr>';
        echo '<td>' . $h(date('d-m-Y', strtotime((string) $row['sale_date']))) . '</td>';
        echo '<td>' . $h($row['invoice_number'] ?: ('#' . $row['id'])) . '</td>';
        echo '<td>' . $h($name) . '</td>';
        echo '<td>' . $h(ame_sales_report_money($row['net_payable'] ?? 0)) . '</td>';
        echo '<td>' . $h(ame_sales_report_money($row['paid_amount'] ?? 0)) . '</td>';
        echo '<td>' . $h(ame_sales_report_money($row['due_amount'] ?? 0)) . '</td>';
        echo '</tr>';
    }
    echo '<tr><td></td><td></td><td>মোট</td><td>' . $h(ame_sales_report_money($net)) . '</td><td>' . $h(ame_sales_report_money($paid)) . '</td><td>' . $h(ame_sales_report_money($due)) . '</td></tr>';
    echo '</tbody></table>';
    exit;
}

$excel_url = '/modules/reports/sales_desk_report.php?' . http_build_query([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'export' => 'xls',
]);

include __DIR__ . '/../../partials/header.php';
?>
<style>
.srep-page { padding: 16px; }
.srep-tools { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; }
.srep-tools a, .srep-tools button { text-decoration: none; }
#salesReportSheet { background: #fff; padding: 12px; }
.srep-shop { margin: 0; font-size: 22px; font-weight: 800; }
.srep-sub { margin: 4px 0 12px; font-weight: 800; }
.srep-kpis { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.srep-kpis div { flex: 1 1 140px; border: 1px solid #D7DEE8; border-radius: 12px; padding: 10px; }
.srep-kpis span { display: block; font-weight: 800; }
.srep-kpis strong { font-size: 22px; }
#salesReportSheet table { width: 100%; border-collapse: collapse; }
@media print {
    .master-header-fixed, .custom-sidebar, .no-print, .srep-tools, .rpt-filter-bar { display: none !important; }
    #salesReportSheet, #salesReportSheet * { visibility: visible !important; }
}
</style>
<div class="srep-page">
    <div class="srep-tools no-print">
        <button type="button" class="btn" onclick="window.print()"><?php echo $en ? 'Print' : 'প্রিন্ট'; ?></button>
        <button type="button" class="btn" onclick="ameSalesReportShare()"><?php echo $en ? 'Share' : 'শেয়ার'; ?></button>
        <a class="btn" href="<?php echo $h($excel_url); ?>"><?php echo $en ? 'Excel' : 'এক্সেল'; ?></a>
    </div>
    <form method="get" class="rpt-filter-bar no-print">
        <div>
            <label><?php echo $en ? 'From' : 'শুরু তারিখ'; ?></label>
            <input type="date" name="start_date" value="<?php echo $h($start_date); ?>">
        </div>
        <div>
            <label><?php echo $en ? 'To' : 'শেষ তারিখ'; ?></label>
            <input type="date" name="end_date" value="<?php echo $h($end_date); ?>">
        </div>
        <button type="submit" class="btn"><?php echo $en ? 'Show' : 'দেখুন'; ?></button>
    </form>
    <div id="salesReportSheet">
        <h1 class="srep-shop"><?php echo $h($shop); ?></h1>
        <p class="srep-sub"><?php echo $en ? 'Sales report' : 'বিক্রয় রিপোর্ট'; ?> · <?php echo $h($start_date); ?> — <?php echo $h($end_date); ?></p>
        <div class="srep-kpis">
            <div><span><?php echo $en ? 'Bills' : 'বিল'; ?></span><strong><?php echo count($rows); ?></strong></div>
            <div><span><?php echo $en ? 'Total' : 'মোট বিল'; ?></span><strong><?php echo $h(ame_sales_report_money($net)); ?></strong></div>
            <div><span><?php echo $en ? 'Paid' : 'জমা'; ?></span><strong><?php echo $h(ame_sales_report_money($paid)); ?></strong></div>
            <div><span><?php echo $en ? 'Due' : 'বাকি'; ?></span><strong><?php echo $h(ame_sales_report_money($due)); ?></strong></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?php echo $en ? 'Date' : 'তারিখ'; ?></th>
                    <th><?php echo $en ? 'Invoice' : 'ইনভয়েস'; ?></th>
                    <th><?php echo $en ? 'Customer' : 'ক্রেতা'; ?></th>
                    <th><?php echo $en ? 'Total' : 'মোট বিল'; ?></th>
                    <th><?php echo $en ? 'Paid' : 'জমা'; ?></th>
                    <th><?php echo $en ? 'Due' : 'বাকি'; ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6"><?php echo $en ? 'No sales in these dates.' : 'এই তারিখে কোনো বিক্রয় নেই।'; ?></td></tr>
            <?php else: foreach ($rows as $row):
                $name = trim((string) ($row['customer_name'] ?? ''));
                if ($name === '') {
                    $name = $en ? 'Retail' : 'খুচরা';
                }
            ?>
                <tr>
                    <td><?php echo $h(date('d-m-Y', strtotime((string) $row['sale_date']))); ?></td>
                    <td><?php echo $h($row['invoice_number'] ?: ('#' . $row['id'])); ?></td>
                    <td><?php echo $h($name); ?></td>
                    <td><?php echo $h(ame_sales_report_money($row['net_payable'] ?? 0)); ?></td>
                    <td><?php echo $h(ame_sales_report_money($row['paid_amount'] ?? 0)); ?></td>
                    <td><?php echo $h(ame_sales_report_money($row['due_amount'] ?? 0)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function ameSalesReportShare() {
    if (window.ameMemoCapture) {
        window.ameMemoCapture('share', 'aponshop-sales-report', 'salesReportSheet');
        return;
    }
    window.print();
}
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
