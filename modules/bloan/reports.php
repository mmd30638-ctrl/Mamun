<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
ame_bloan_refresh_open($db);

$type = (string)($_GET['type'] ?? 'all');
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
$allowed = ['all','active','provider','installments','payments','outstanding','overdue','interest','closing','dates'];
if (!in_array($type, $allowed, true)) {
    $type = 'all';
}

$sql = [
    'all' => "SELECT a.loan_no, p.name, a.approved_amount, a.disbursed_amount, a.principal_outstanding, a.interest_paid, a.status, a.disbursed_on FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id ORDER BY a.id DESC",
    'active' => "SELECT a.loan_no, p.name, a.disbursed_amount, a.installment_amount, a.principal_outstanding, a.interest_rate FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.status='active'",
    'provider' => "SELECT p.name, p.provider_type, COUNT(a.id) loans, COALESCE(SUM(a.disbursed_amount),0) disbursed, COALESCE(SUM(a.principal_outstanding),0) outstanding FROM bloan_providers p LEFT JOIN bloan_accounts a ON a.provider_id=p.id GROUP BY p.id",
    'installments' => "SELECT a.loan_no, i.installment_no, i.due_date, i.principal_part, i.interest_part, i.total_due, i.paid_amount, i.remaining_amount, i.status FROM bloan_installments i JOIN bloan_accounts a ON a.id=i.loan_id ORDER BY i.due_date",
    'payments' => "SELECT t.txn_date, a.loan_no, t.kind, t.principal_part, t.interest_part, t.charge_part, t.total, t.balance_after FROM bloan_txns t JOIN bloan_accounts a ON a.id=t.loan_id WHERE t.kind IN ('payment','settlement') ORDER BY t.id DESC",
    'outstanding' => "SELECT a.loan_no, p.name, a.principal_outstanding, a.disbursed_amount, a.principal_paid FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.principal_outstanding>0",
    'overdue' => "SELECT a.loan_no, p.name, i.installment_no, i.due_date, i.remaining_amount FROM bloan_installments i JOIN bloan_accounts a ON a.id=i.loan_id JOIN bloan_providers p ON p.id=a.provider_id WHERE i.status='overdue'",
    'interest' => "SELECT a.loan_no, p.name, a.interest_rate, a.interest_type, a.interest_paid FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id",
    'closing' => "SELECT a.loan_no, p.name, a.disbursed_amount, a.principal_paid, a.interest_paid, a.charges_paid, a.status FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.status='closed'",
    'dates' => "SELECT t.txn_date, a.loan_no, t.kind, t.principal_part, t.interest_part, t.total, t.balance_after FROM bloan_txns t JOIN bloan_accounts a ON a.id=t.loan_id WHERE t.txn_date BETWEEN ? AND ? ORDER BY t.txn_date, t.id",
];
if ($type === 'dates') {
    $stmt = $db->prepare($sql['dates']);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query($sql[$type]);
}
$rows = [];
$headers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($headers === []) {
            $headers = array_keys($row);
        }
        $rows[] = $row;
    }
}
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="business-loan-' . $type . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
include '../../partials/header.php';
$titles = [
    'all' => ame_l('সব ঋণ', 'All loans'),
    'active' => ame_l('চালু ঋণ', 'Active'),
    'provider' => ame_l('ঋণদাতা', 'Provider'),
    'installments' => ame_l('কিস্তি', 'Installments'),
    'payments' => ame_l('পরিশোধ', 'Payments'),
    'outstanding' => ame_l('বাকি', 'Outstanding'),
    'overdue' => ame_l('বকেয়া', 'Overdue'),
    'interest' => ame_l('সুদ', 'Interest'),
    'closing' => ame_l('বন্ধ ঋণ', 'Closing'),
    'dates' => ame_l('তারিখ অনুযায়ী', 'Date range'),
];
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ঋণ রিপোর্ট', 'Loan reports'); ?></h1>
    <p class="en"><?php echo ame_l('প্রিন্ট বা এক্সেল CSV। পিডিএফ ব্রাউজারের প্রিন্ট থেকে নেবেন।', 'Print, or download an Excel CSV. Use the browser print dialog for PDF.'); ?></p>
    <p>
        <?php foreach ($titles as $key => $label): ?>
        <a href="?type=<?php echo $key; ?>"><?php echo ame_bloan_h($label); ?></a>
        <?php endforeach; ?>
    </p>
    <form class="bl-inline" method="get">
        <input type="hidden" name="type" value="dates">
        <label><?php echo ame_l('থেকে', 'From'); ?> <input type="date" name="from" value="<?php echo ame_bloan_h($from); ?>"></label>
        <label><?php echo ame_l('পর্যন্ত', 'To'); ?> <input type="date" name="to" value="<?php echo ame_bloan_h($to); ?>"></label>
        <button type="submit"><?php echo ame_l('তারিখের রিপোর্ট', 'Date report'); ?></button>
    </form>
    <p class="no-print">
        <button type="button" onclick="window.print()"><?php echo ame_l('প্রিন্ট / PDF', 'Print / PDF'); ?></button>
        <a class="btn" href="?type=<?php echo ame_bloan_h($type); ?>&from=<?php echo ame_bloan_h($from); ?>&to=<?php echo ame_bloan_h($to); ?>&export=csv"><?php echo ame_l('এক্সেল / CSV', 'Excel / CSV'); ?></a>
    </p>
    <h2><?php echo ame_bloan_h($titles[$type]); ?></h2>
    <table>
        <tr><?php foreach ($headers as $h): ?><th><?php echo ame_bloan_h((string)$h); ?></th><?php endforeach; ?></tr>
        <?php foreach ($rows as $row): ?>
        <tr><?php foreach ($row as $cell): ?><td><?php echo ame_bloan_h((string)$cell); ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
