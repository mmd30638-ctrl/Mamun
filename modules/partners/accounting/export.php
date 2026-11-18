<?php
require_once '../../../includes/config.php';
require_once '../../../includes/partner_accounting_v2.php';
require_permission($db, 'm-acc');

$ym = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
$report = pav2_report($db, $ym);
$filename = 'partner-accounting-' . $ym . '.csv';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['পার্টনার হিসাব', $ym, 'কার্যকর তারিখ', $report['period']['cutoff']]);
fputcsv($out, ['যোগ্য বিক্রয়', $report['sales'], 'পণ্য খরচ', $report['cost'], 'সরাসরি খরচ', $report['direct_cost'], 'পণ্য লাভ', $report['gross_profit'], 'যৌথ খরচ', $report['joint_expense'], 'নিট লাভ', $report['net_profit']]);
fputcsv($out, []);
fputcsv($out, ['পণ্যভিত্তিক লাভ']);
fputcsv($out, ['তারিখ','ইনভয়েস','Sale ID','পণ্য','বিক্রি','ক্রয়মূল্য','সরাসরি খরচ','লাভ','প্রথম পার্টনার','প্রথম অংশ','দ্বিতীয় পার্টনার','দ্বিতীয় অংশ']);
foreach ($report['rows'] as $row) {
    fputcsv($out, [$row['date'],$row['invoice_no'],$row['sale_id'],$row['product_name'],$row['sales'],$row['cost'],$row['direct_cost'],$row['profit'],$row['partner_one_name'],$row['partner_one_share'],$row['partner_two_name'],$row['partner_two_share']]);
}
fputcsv($out, []);
fputcsv($out, ['পার্টনারভিত্তিক সারাংশ']);
fputcsv($out, ['পার্টনার','পণ্য লাভ','যৌথ খরচের অংশ','নিট লাভ','ফেরতযোগ্য খরচ','লাভ পরিশোধ','খরচ ফেরত','মোট পাওনা','মূলধন/বিনিয়োগ','উত্তোলন']);
foreach ($report['partners'] as $row) {
    fputcsv($out, [$row['name'] ?? '',$row['gross_share'] ?? 0,$row['expense_share'] ?? 0,$row['net_profit_share'] ?? 0,$row['reimbursable'] ?? 0,$row['profit_payment'] ?? 0,$row['expense_reimbursement'] ?? 0,$row['receivable'] ?? 0,$row['capital'] ?? 0,$row['withdrawal'] ?? 0]);
}
fputcsv($out, []);
fputcsv($out, ['যৌথ খরচ']);
fputcsv($out, ['তারিখ','ভাউচার','খাত','বিবরণ','প্রদানকারী','প্রকৃত অ্যাকাউন্ট','পরিমাণ','পেমেন্ট মাধ্যম']);
foreach ($report['expenses'] as $row) {
    $accountName = ($row['payer_type'] ?? '') === 'partner'
        ? 'প্রযোজ্য নয় · ব্যক্তিগত টাকা'
        : ($row['account_name'] ?? '');
    fputcsv($out, [$row['expense_date'],$row['voucher_no'],$row['category'],$row['description'],($row['payer_type'] ?? '') === 'partner' ? ($row['payer_partner_name'] ?? '') : 'ব্যবসা',$accountName,$row['amount'],$row['payment_method']]);
}
fputcsv($out, []);
fputcsv($out, ['জমা, উত্তোলন ও পরিশোধ']);
fputcsv($out, ['তারিখ','পার্টনার','ধরন','প্রকৃত অ্যাকাউন্ট','রেফারেন্স','পরিমাণ','নোট']);
foreach ($report['movements'] as $row) {
    $accountName = trim((string)($row['account_name'] ?? ''));
    if ($accountName === '') {
        $accountName = (int)($row['account_id'] ?? 0) > 0 ? ('Account #' . (int)$row['account_id']) : 'প্রযোজ্য নয়';
    } elseif (!empty($row['account_type'])) {
        $accountName .= ' · ' . $row['account_type'];
    }
    fputcsv($out, [$row['movement_date'],$row['partner_name'],$row['movement_type'],$accountName,$row['reference'],$row['amount'],$row['note']]);
}
fclose($out);
exit;
