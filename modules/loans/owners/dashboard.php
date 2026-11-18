<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

ame_owner_loan_recalc_installment_statuses($db);

$today = date('Y-m-d');

function oln_scalar($db, $sql)
{
    $r = @$db->query($sql);
    if (!$r) { return 0.0; }
    $row = $r->fetch_row();
    return $row ? (float)$row[0] : 0.0;
}
function oln_count($db, $sql)
{
    $r = @$db->query($sql);
    if (!$r) { return 0; }
    $row = $r->fetch_row();
    return $row ? (int)$row[0] : 0;
}

$kpi_owners        = oln_count($db, "SELECT COUNT(*) FROM shop_owners WHERE status = 1");
$kpi_active_loans  = oln_count($db, "SELECT COUNT(*) FROM owner_loans WHERE status IN ('approved','active','overdue')");
$kpi_disbursed     = oln_scalar($db, "SELECT COALESCE(SUM(disbursed_amount),0) FROM owner_loan_disbursements");
$kpi_collected     = oln_scalar($db, "SELECT COALESCE(SUM(total_payment),0) FROM owner_loan_payments");
$kpi_outstanding   = oln_scalar($db, "SELECT COALESCE(SUM(outstanding_amount),0) FROM owner_loans");
$kpi_savings       = oln_scalar($db, "SELECT COALESCE(SUM(current_balance),0) FROM owner_savings_accounts");
$kpi_savings_wd    = oln_scalar($db, "SELECT COALESCE(SUM(amount),0) FROM owner_savings_withdrawals");
$kpi_today_coll    = oln_scalar($db, "SELECT COALESCE(SUM(total_payment),0) FROM owner_loan_payments WHERE payment_date = '$today'");
$kpi_today_savings = oln_scalar($db, "SELECT COALESCE(SUM(amount),0) FROM owner_savings_transactions WHERE txn_type = 'deposit' AND txn_date = '$today'");
$kpi_today_due     = oln_count($db, "SELECT COUNT(*) FROM owner_loan_installments WHERE due_date = '$today' AND status IN ('upcoming','partial','overdue')");

// স্পেক সেকশন ২১ — "নজর দরকার" প্যানেল: আজ/মেয়াদোত্তীর্ণ কিস্তি + সাম্প্রতিক পেমেন্ট
$due_today_rows = $db->query("
    SELECT oi.id, oi.due_date, oi.total_installment, oi.paid_amount, oi.remaining_amount, oi.status,
           ol.id loan_id, so.owner_name, so.mobile
    FROM owner_loan_installments oi
    JOIN owner_loans ol ON ol.id = oi.loan_id
    JOIN shop_owners so ON so.id = ol.owner_id
    WHERE oi.status IN ('overdue','upcoming') AND oi.due_date <= CURDATE()
    ORDER BY oi.due_date ASC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$recent_ledger = $db->query("
    SELECT otl.*, so.owner_name
    FROM owner_transaction_ledger otl
    JOIN shop_owners so ON so.id = otl.owner_id
    ORDER BY otl.id DESC LIMIT 15
")->fetch_all(MYSQLI_ASSOC);

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-wrap { padding: 15px; max-width: 1180px; margin: 0 auto; }
.oln-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-head h3 { margin:0; color: var(--brand-navy); }
.oln-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin-bottom:20px; }
.oln-kpi { border-radius: var(--radius-md, 12px); padding:16px; border:1px solid var(--brand-border); background: var(--brand-white); }
.oln-kpi .l { font-size:12px; font-weight:700; color: var(--brand-muted); margin-bottom:6px; }
.oln-kpi .v { font-size:19px; font-weight:800; color: var(--brand-navy); }
.oln-kpi.blue { background: var(--oln-kpi-blue-bg); } .oln-kpi.blue .v { color: var(--oln-kpi-blue-fg); }
.oln-kpi.purple { background: var(--oln-kpi-purple-bg); } .oln-kpi.purple .v { color: var(--oln-kpi-purple-fg); }
.oln-kpi.teal { background: var(--oln-kpi-teal-bg); } .oln-kpi.teal .v { color: var(--oln-kpi-teal-fg); }
.oln-kpi.orange { background: var(--oln-kpi-orange-bg); } .oln-kpi.orange .v { color: var(--oln-kpi-orange-fg); }
.oln-kpi.success { background: var(--brand-success-soft); } .oln-kpi.success .v { color: var(--brand-success); }
.oln-kpi.danger { background: var(--brand-danger-soft); } .oln-kpi.danger .v { color: var(--brand-danger); }

.oln-section { background: var(--brand-white); border:1px solid var(--brand-border); border-radius: var(--radius-md, 12px); padding:16px; margin-bottom:18px; }
.oln-section h4 { margin:0 0 12px; color: var(--brand-navy); }
.oln-table { width:100%; border-collapse: collapse; font-size:13px; }
.oln-table th { text-align:left; padding:8px; background: var(--brand-navy); color: var(--brand-white); }
.oln-table td { padding:8px; border-bottom:1px solid var(--brand-border); }
.oln-empty { text-align:center; padding:20px; color: var(--brand-muted); }
.oln-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.oln-badge.overdue { background: var(--oln-status-overdue-bg); color: var(--oln-status-overdue-fg); }
.oln-badge.upcoming { background: var(--oln-status-upcoming-bg); color: var(--oln-status-upcoming-fg); }
.oln-quicklinks { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
.oln-quicklinks a {
    background: var(--brand-gradient); color: var(--brand-white) !important; text-decoration:none;
    padding:10px 16px; border-radius:8px; font-weight:700; font-size:13px;
}
.oln-credit { color: var(--oln-ledger-credit); font-weight:700; }
.oln-debit { color: var(--oln-ledger-debit); font-weight:700; }

/* স্মরণিকা নীতি ২: লোগো স্পিনার */
.oln-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-wrap">
    <div class="oln-head">
        <h3>🏦 <?php echo te('nav_oloans'); ?> — <?php echo te('nav_oloan_dash'); ?></h3>
    </div>

    <div class="oln-quicklinks">
        <a href="add.php">+ <?php echo ame_l('নতুন দোকান মালিক', 'New shop owner'); ?></a>
        <a href="loan_add.php">+ <?php echo ame_l('নতুন ঋণ', 'New loan'); ?></a>
        <a href="payment_entry.php">💰 <?php echo ame_l('কিস্তি/জমা আদায়', 'Collect payment'); ?></a>
        <a href="savings_withdraw.php">🏧 <?php echo ame_l('সঞ্চয় উত্তোলন', 'Savings withdrawal'); ?></a>
    </div>

    <div class="oln-kpi-grid">
        <div class="oln-kpi blue"><div class="l"><?php echo ame_l('মোট দোকান মালিক', 'Total shop owners'); ?></div><div class="v"><?php echo number_format($kpi_owners); ?></div></div>
        <div class="oln-kpi purple"><div class="l"><?php echo ame_l('সক্রিয় ঋণ সংখ্যা', 'Active loans'); ?></div><div class="v"><?php echo number_format($kpi_active_loans); ?></div></div>
        <div class="oln-kpi teal"><div class="l"><?php echo ame_l('মোট বিতরণ', 'Total disbursed'); ?></div><div class="v">৳<?php echo number_format($kpi_disbursed, 2); ?></div></div>
        <div class="oln-kpi success"><div class="l"><?php echo ame_l('মোট আদায়', 'Total collected'); ?></div><div class="v">৳<?php echo number_format($kpi_collected, 2); ?></div></div>
        <div class="oln-kpi danger"><div class="l"><?php echo ame_l('মোট বকেয়া', 'Total outstanding'); ?></div><div class="v">৳<?php echo number_format($kpi_outstanding, 2); ?></div></div>
        <div class="oln-kpi orange"><div class="l"><?php echo ame_l('মোট সঞ্চয় জমা', 'Total savings balance'); ?></div><div class="v">৳<?php echo number_format($kpi_savings, 2); ?></div></div>
        <div class="oln-kpi blue"><div class="l"><?php echo ame_l('মোট সঞ্চয় উত্তোলন', 'Total savings withdrawn'); ?></div><div class="v">৳<?php echo number_format($kpi_savings_wd, 2); ?></div></div>
        <div class="oln-kpi success"><div class="l"><?php echo ame_l('আজকের আদায়', "Today's collection"); ?></div><div class="v">৳<?php echo number_format($kpi_today_coll, 2); ?></div></div>
        <div class="oln-kpi teal"><div class="l"><?php echo ame_l('আজকের সঞ্চয় জমা', "Today's savings"); ?></div><div class="v">৳<?php echo number_format($kpi_today_savings, 2); ?></div></div>
        <div class="oln-kpi purple"><div class="l"><?php echo ame_l('আজ কিস্তি বকেয়া সংখ্যা', "Installments due today"); ?></div><div class="v"><?php echo number_format($kpi_today_due); ?></div></div>
    </div>

    <div class="oln-section">
        <h4>⚠️ <?php echo ame_l('নজর দরকার — মেয়াদোত্তীর্ণ/আজকের কিস্তি', 'Attention needed — overdue / due-today installments'); ?></h4>
        <?php if (!$due_today_rows): ?>
            <div class="oln-empty"><?php echo ame_l('কোনো বকেয়া কিস্তি নেই।', 'No pending installments.'); ?></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('মালিক', 'Owner'); ?></th>
                <th><?php echo ame_l('মেয়াদ', 'Due date'); ?></th>
                <th><?php echo ame_l('বকেয়া', 'Due amount'); ?></th>
                <th><?php echo ame_l('অবস্থা', 'Status'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($due_today_rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['owner_name']); ?><br><small><?php echo esc_html($r['mobile']); ?></small></td>
                    <td><?php echo esc_html($r['due_date']); ?></td>
                    <td>৳<?php echo number_format((float)$r['remaining_amount'], 2); ?></td>
                    <td><span class="oln-badge <?php echo $r['status'] === 'overdue' ? 'overdue' : 'upcoming'; ?>"><?php echo esc_html($r['status']); ?></span></td>
                    <td><a href="loan_view.php?id=<?php echo (int)$r['loan_id']; ?>"><?php echo ame_l('দেখুন', 'View'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="oln-section">
        <h4>🧾 <?php echo ame_l('সাম্প্রতিক লেনদেন', 'Recent transactions'); ?></h4>
        <?php if (!$recent_ledger): ?>
            <div class="oln-empty"><?php echo ame_l('এখনো কোনো লেনদেন হয়নি।', 'No transactions yet.'); ?></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('তারিখ', 'Date'); ?></th>
                <th><?php echo ame_l('মালিক', 'Owner'); ?></th>
                <th><?php echo ame_l('ধরন', 'Type'); ?></th>
                <th><?php echo ame_l('বিবরণ', 'Description'); ?></th>
                <th><?php echo ame_l('ডেবিট', 'Debit'); ?></th>
                <th><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent_ledger as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['txn_date']); ?></td>
                    <td><?php echo esc_html($r['owner_name']); ?></td>
                    <td><?php echo esc_html($r['txn_type']); ?></td>
                    <td><?php echo esc_html($r['description']); ?></td>
                    <td class="oln-debit"><?php echo $r['debit'] > 0 ? number_format((float)$r['debit'], 2) : '—'; ?></td>
                    <td class="oln-credit"><?php echo $r['credit'] > 0 ? number_format((float)$r['credit'], 2) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
