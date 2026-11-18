<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$owner_id = (int)($_GET['owner_id'] ?? 0);
$owner = ame_oloan_owner_fetch($db, $owner_id);
if (!$owner) {
    die('❌ ' . ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
$summary = ame_owner_financial_summary($db, $owner_id);
$ledger_stmt = $db->prepare("SELECT * FROM owner_transaction_ledger WHERE owner_id = ? ORDER BY id ASC");
$ledger_stmt->bind_param('i', $owner_id);
$ledger_stmt->execute();
$ledger_rows = $ledger_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ledger_stmt->close();

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'owner-statement-' . $owner_id,
    'title' => $shop . ' · ' . ame_l('মালিকের সম্পূর্ণ স্টেটমেন্ট', 'Owner full statement'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('মালিকের স্টেটমেন্ট', 'Owner statement'),
        (string)$owner['owner_name'],
        ame_l('মোট বকেয়া', 'Total outstanding') . ': ৳' . number_format($summary['total_outstanding'], 2),
        ame_l('সঞ্চয় ব্যালেন্স', 'Savings balance') . ': ৳' . number_format($summary['savings_balance'], 2),
    ])),
    'shop' => $shop,
];

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-stmt-wrap { padding: 15px; max-width: 900px; margin: 0 auto; }
.oln-stmt-card { background: var(--brand-white); border:1px solid var(--brand-border); border-radius:12px; padding:20px; }
.oln-stmt-card h3 { text-align:center; color: var(--brand-navy); margin-top:0; }
.oln-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin:18px 0; }
.oln-summary-item { background: var(--brand-bg, var(--oln-kpi-blue-bg)); border-radius:10px; padding:12px; }
.oln-summary-item .l { font-size:11.5px; color: var(--brand-muted); font-weight:700; }
.oln-summary-item .v { font-size:16px; font-weight:800; color: var(--brand-navy); }
.oln-table { width:100%; border-collapse: collapse; font-size:12.5px; }
.oln-table th { text-align:left; padding:8px; background: var(--brand-navy); color: var(--brand-white); }
.oln-table td { padding:8px; border-bottom:1px solid var(--brand-border); }
.oln-credit { color: var(--oln-ledger-credit); font-weight:700; }
.oln-debit { color: var(--oln-ledger-debit); font-weight:700; }
.oln-stmt-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-stmt-wrap">
    <?php include '../../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="oln-stmt-card">
        <h3><?php echo esc_html($shop); ?><br><small><?php echo ame_l('মালিকের সম্পূর্ণ স্টেটমেন্ট', 'Owner Full Statement'); ?></small></h3>
        <p style="text-align:center;"><b><?php echo esc_html($owner['owner_name']); ?></b> — <?php echo esc_html($owner['shop_name']); ?> — <?php echo esc_html($owner['mobile']); ?></p>

        <div class="oln-summary-grid">
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট ঋণ অনুমোদিত', 'Total loan approved'); ?></div><div class="v">৳<?php echo number_format($summary['total_loan_approved'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট বিতরণ', 'Total disbursed'); ?></div><div class="v">৳<?php echo number_format($summary['total_loan_disbursed'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('আসল পরিশোধিত', 'Principal paid'); ?></div><div class="v">৳<?php echo number_format($summary['total_principal_paid'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('সুদ পরিশোধিত', 'Interest paid'); ?></div><div class="v">৳<?php echo number_format($summary['total_interest_paid'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট আদায়', 'Total collected'); ?></div><div class="v">৳<?php echo number_format($summary['total_collected'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট বকেয়া', 'Total outstanding'); ?></div><div class="v" style="color:var(--brand-danger);">৳<?php echo number_format($summary['total_outstanding'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('সঞ্চয় জমা', 'Savings deposited'); ?></div><div class="v">৳<?php echo number_format($summary['savings_deposited'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('সঞ্চয় উত্তোলন', 'Savings withdrawn'); ?></div><div class="v">৳<?php echo number_format($summary['savings_withdrawn'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('সঞ্চয় ব্যালেন্স', 'Savings balance'); ?></div><div class="v" style="color:var(--brand-success);">৳<?php echo number_format($summary['savings_balance'], 2); ?></div></div>
            <div class="oln-summary-item"><div class="l"><?php echo ame_l('মেয়াদোত্তীর্ণ পরিমাণ', 'Overdue amount'); ?></div><div class="v" style="color:var(--brand-danger);">৳<?php echo number_format($summary['overdue_amount'], 2); ?></div></div>
        </div>

        <h4 style="color:var(--brand-navy);">📒 <?php echo ame_l('সম্পূর্ণ লেনদেন ইতিহাস', 'Complete transaction history'); ?></h4>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ধরন', 'Type'); ?></th><th><?php echo ame_l('বিবরণ', 'Description'); ?></th>
                <th><?php echo ame_l('ডেবিট', 'Debit'); ?></th><th><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th><th><?php echo ame_l('ব্যালেন্স', 'Balance'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($ledger_rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['txn_date']); ?></td>
                    <td><?php echo esc_html($r['txn_type']); ?></td>
                    <td><?php echo esc_html($r['description']); ?></td>
                    <td class="oln-debit"><?php echo (float)$r['debit'] > 0 ? number_format((float)$r['debit'], 2) : '—'; ?></td>
                    <td class="oln-credit"><?php echo (float)$r['credit'] > 0 ? number_format((float)$r['credit'], 2) : '—'; ?></td>
                    <td><?php echo number_format((float)$r['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ledger_rows): ?>
                <tr><td colspan="6" style="text-align:center;padding:16px;color:var(--brand-muted);"><?php echo ame_l('কোনো লেনদেন নেই।', 'No transactions.'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
