<?php
/* 10.98.714 — পার্টনারশিপ খরচ CSV (Excel-এ বাংলা ঠিক থাকতে BOM সহ) */
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
pa_guard();
ensure_partner_accounting_schema($db);

$month = pa_month((string)($_GET['month'] ?? date('Y-m')));
$ym = $month['ym'];
$cat = (string)($_GET['ecat'] ?? '');
$cats = pa_expense_categories();
$methods = pa_payment_methods();
$rows = pa_month_expenses($db, $ym, $cat);
$voided = pa_month_voided_expenses($db, $ym);

$safe = static function ($v) {
    $v = (string)$v;
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
        return "'" . $v;
    }
    return $v;
};
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="partner-expenses-' . $ym . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Voucher', 'Date', 'Category', 'Payee', 'Method', 'Description', 'Reference', 'Note', 'Amount', 'Status', 'Void reason']);
$total = 0.0;
foreach ($rows as $r) {
    $total += pa_money($r['amount']);
    fputcsv($out, [
        $safe(pa_expense_voucher_label($r)), $r['expense_date'], $safe($cats[$r['category']] ?? $r['category']),
        $safe($r['payee'] ?? ''), $safe($methods[$r['payment_method'] ?? 'cash'] ?? ($r['payment_method'] ?? '')),
        $safe($r['description']), $safe($r['reference'] ?? ''), $safe($r['note'] ?? ''),
        number_format(pa_money($r['amount']), 2, '.', ''), 'active', '',
    ]);
}
fputcsv($out, ['', '', '', '', '', '', '', 'TOTAL (active)', number_format($total, 2, '.', ''), '', '']);
if ($cat === '') {
    foreach ($voided as $r) {
        fputcsv($out, [
            $safe(pa_expense_voucher_label($r)), $r['expense_date'], $safe($cats[$r['category']] ?? $r['category']),
            $safe($r['payee'] ?? ''), '', $safe($r['description']), $safe($r['reference'] ?? ''), '',
            number_format(pa_money($r['amount']), 2, '.', ''), 'void', $safe($r['void_reason'] ?? ''),
        ]);
    }
}
fclose($out);
