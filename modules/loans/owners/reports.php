<?php
/**
 * স্পেক সেকশন ১৬ — কনসোলিডেটেড রিপোর্ট পেজ (১২টি রিপোর্ট টাইপ, ট্যাব আকারে,
 * প্রতিটা একটা সাধারণ ফিল্টারযোগ্য টেবিল — বাস্তবসম্মত একক-ফাইল কনসলিডেশন)।
 */
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);
ame_owner_loan_recalc_installment_statuses($db);

$from = clean_input($_GET['from'] ?? date('Y-m-01'));
$to = clean_input($_GET['to'] ?? date('Y-m-d'));
$tab = clean_input($_GET['tab'] ?? 'daily');

function oln_rep_rows($db, $sql, $types = '', $params = [])
{
    if ($types === '') {
        $r = @$db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) { return []; }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ১. দৈনিক আদায় রিপোর্ট
$daily = oln_rep_rows($db, "SELECT payment_date d, COUNT(*) cnt, SUM(principal_paid) p, SUM(interest_paid) i, SUM(penalty_charge) pen, SUM(total_payment) t
    FROM owner_loan_payments WHERE payment_date BETWEEN ? AND ? GROUP BY payment_date ORDER BY payment_date DESC", 'ss', [$from, $to]);

// ২. মাসিক আদায় রিপোর্ট
$monthly = oln_rep_rows($db, "SELECT DATE_FORMAT(payment_date, '%Y-%m') m, COUNT(*) cnt, SUM(total_payment) t
    FROM owner_loan_payments GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY m DESC LIMIT 24");

// ৩. বিতরণ রিপোর্ট
$disbursement = oln_rep_rows($db, "SELECT d.*, so.owner_name FROM owner_loan_disbursements d JOIN shop_owners so ON so.id = d.owner_id
    WHERE d.disbursement_date BETWEEN ? AND ? ORDER BY d.disbursement_date DESC", 'ss', [$from, $to]);

// ৪. বকেয়া ঋণ রিপোর্ট
$outstanding = oln_rep_rows($db, "SELECT ol.*, so.owner_name FROM owner_loans ol JOIN shop_owners so ON so.id = ol.owner_id
    WHERE ol.outstanding_amount > 0.005 ORDER BY ol.outstanding_amount DESC LIMIT 200");

// ৫. মেয়াদোত্তীর্ণ ঋণ রিপোর্ট
$overdue = oln_rep_rows($db, "SELECT oi.*, so.owner_name, ol.id loan_id FROM owner_loan_installments oi
    JOIN owner_loans ol ON ol.id = oi.loan_id JOIN shop_owners so ON so.id = ol.owner_id
    WHERE oi.status = 'overdue' ORDER BY oi.due_date ASC LIMIT 200");

// ৬. কিস্তি রিপোর্ট
$installment_report = oln_rep_rows($db, "SELECT oi.*, so.owner_name, ol.id loan_id FROM owner_loan_installments oi
    JOIN owner_loans ol ON ol.id = oi.loan_id JOIN shop_owners so ON so.id = ol.owner_id
    WHERE oi.due_date BETWEEN ? AND ? ORDER BY oi.due_date ASC LIMIT 300", 'ss', [$from, $to]);

// ৭. সঞ্চয় রিপোর্ট
$savings_report = oln_rep_rows($db, "SELECT t.*, so.owner_name FROM owner_savings_transactions t JOIN shop_owners so ON so.id = t.owner_id
    WHERE t.txn_type = 'deposit' AND t.txn_date BETWEEN ? AND ? ORDER BY t.txn_date DESC LIMIT 300", 'ss', [$from, $to]);

// ৮. সঞ্চয় উত্তোলন রিপোর্ট
$savings_wd_report = oln_rep_rows($db, "SELECT w.*, so.owner_name FROM owner_savings_withdrawals w JOIN shop_owners so ON so.id = w.owner_id
    WHERE w.withdrawal_date BETWEEN ? AND ? ORDER BY w.withdrawal_date DESC LIMIT 300", 'ss', [$from, $to]);

// ১০. ব্যাংক-ভিত্তিক ঋণ রিপোর্ট
$bank_wise = oln_rep_rows($db, "SELECT b.bank_name, COUNT(ol.id) cnt, COALESCE(SUM(ol.approved_amount),0) approved, COALESCE(SUM(ol.outstanding_amount),0) outstanding
    FROM banks b LEFT JOIN owner_loans ol ON ol.bank_id = b.id GROUP BY b.id ORDER BY approved DESC");

// ১১. আদায়কারী-ভিত্তিক আদায় রিপোর্ট
$collector_wise = oln_rep_rows($db, "SELECT u.username, COUNT(p.id) cnt, COALESCE(SUM(p.total_payment),0) t
    FROM owner_loan_payments p LEFT JOIN users u ON u.id = p.collected_by
    WHERE p.payment_date BETWEEN ? AND ? GROUP BY p.collected_by ORDER BY t DESC", 'ss', [$from, $to]);

// ১২. সম্পূর্ণ লেনদেন রিপোর্ট
$complete_txn = oln_rep_rows($db, "SELECT otl.*, so.owner_name FROM owner_transaction_ledger otl JOIN shop_owners so ON so.id = otl.owner_id
    WHERE otl.txn_date BETWEEN ? AND ? ORDER BY otl.id DESC LIMIT 400", 'ss', [$from, $to]);

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-page-wrap { padding: 15px; }
.oln-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; }
.oln-toolbar input, .oln-toolbar select {
    padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px; background: var(--brand-white);
}
.oln-add-btn { background: var(--brand-navy); color: var(--brand-white) !important; padding:10px 18px; border-radius:8px; border:none; font-weight:700; font-size:13.5px; cursor:pointer; }
.oln-tabbar { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; border-bottom:2px solid var(--brand-border); }
.oln-tab-btn { background:transparent; border:none; padding:9px 14px; cursor:pointer; font-weight:700; font-size:12.5px; color: var(--brand-muted); border-bottom:3px solid transparent; margin-bottom:-2px; }
.oln-tab-btn.is-active { color: var(--brand-navy); border-bottom-color: var(--brand-accent); background: var(--oln-tab-active-bg); }
.oln-tab-panel { display:none; }
.oln-tab-panel.is-active { display:block; }
.oln-table-wrap { overflow-x:auto; }
.oln-table { width:100%; border-collapse: collapse; text-align:left; font-size:12.5px; }
.oln-table thead tr { background: var(--brand-navy); color: var(--brand-white); }
.oln-table th { padding:9px; }
.oln-table td { padding:9px; border-bottom:1px solid var(--brand-border); }
.oln-empty { text-align:center; padding:20px 0; color: var(--brand-muted); }
.oln-page-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-page-wrap">
    <div class="card" style="padding:18px;">
        <h3 style="color:var(--brand-navy); margin-top:0;">📊 <?php echo te('nav_oloan_report'); ?></h3>
        <form method="GET" class="oln-toolbar">
            <input type="hidden" name="tab" id="tabField" value="<?php echo esc_html($tab); ?>">
            <input type="date" name="from" value="<?php echo esc_html($from); ?>">
            <input type="date" name="to" value="<?php echo esc_html($to); ?>">
            <button type="submit" class="oln-add-btn"><?php echo ame_l('ফিল্টার প্রয়োগ', 'Apply filter'); ?></button>
            <a href="statement.php" class="oln-add-btn" style="text-decoration:none; display:inline-flex; align-items:center;"><?php echo ame_l('মালিক স্টেটমেন্ট →', 'Owner statement →'); ?></a>
        </form>

        <div class="oln-tabbar" role="tablist">
            <?php
            $tabs = [
                'daily' => ame_l('দৈনিক আদায়', 'Daily collection'),
                'monthly' => ame_l('মাসিক আদায়', 'Monthly collection'),
                'disbursement' => ame_l('বিতরণ', 'Disbursement'),
                'outstanding' => ame_l('বকেয়া ঋণ', 'Outstanding loans'),
                'overdue' => ame_l('মেয়াদোত্তীর্ণ', 'Overdue'),
                'installments' => ame_l('কিস্তি', 'Installments'),
                'savings' => ame_l('সঞ্চয় জমা', 'Savings deposits'),
                'savings_wd' => ame_l('সঞ্চয় উত্তোলন', 'Savings withdrawals'),
                'bank_wise' => ame_l('ব্যাংক-ভিত্তিক', 'Bank-wise'),
                'collector_wise' => ame_l('আদায়কারী-ভিত্তিক', 'Collector-wise'),
                'complete_txn' => ame_l('সম্পূর্ণ লেনদেন', 'Complete transactions'),
            ];
            foreach ($tabs as $key => $label):
            ?>
            <button type="button" class="oln-tab-btn <?php echo $tab === $key ? 'is-active' : ''; ?>" data-oln-tab="<?php echo $key; ?>"><?php echo esc_html($label); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'daily' ? 'is-active' : ''; ?>" data-oln-panel="daily">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr>
                <th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('সংখ্যা', 'Count'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th><th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('জরিমানা', 'Penalty'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th>
            </tr></thead><tbody>
            <?php foreach ($daily as $r): ?>
            <tr><td><?php echo esc_html($r['d']); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><?php echo number_format((float)$r['p'],2); ?></td><td><?php echo number_format((float)$r['i'],2); ?></td><td><?php echo number_format((float)$r['pen'],2); ?></td><td><b><?php echo number_format((float)$r['t'],2); ?></b></td></tr>
            <?php endforeach; if (!$daily): ?><tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'monthly' ? 'is-active' : ''; ?>" data-oln-panel="monthly">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('মাস', 'Month'); ?></th><th><?php echo ame_l('সংখ্যা', 'Count'); ?></th><th><?php echo ame_l('মোট আদায়', 'Total collected'); ?></th></tr></thead><tbody>
            <?php foreach ($monthly as $r): ?><tr><td><?php echo esc_html($r['m']); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><b><?php echo number_format((float)$r['t'],2); ?></b></td></tr>
            <?php endforeach; if (!$monthly): ?><tr><td colspan="3" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'disbursement' ? 'is-active' : ''; ?>" data-oln-panel="disbursement">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('বিতরণ', 'Disbursed'); ?></th><th><?php echo ame_l('নিট প্রাপ্ত', 'Net'); ?></th></tr></thead><tbody>
            <?php foreach ($disbursement as $r): ?><tr><td><?php echo esc_html($r['disbursement_date']); ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo number_format((float)$r['disbursed_amount'],2); ?></td><td><?php echo number_format((float)$r['net_amount_received'],2); ?></td></tr>
            <?php endforeach; if (!$disbursement): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'outstanding' ? 'is-active' : ''; ?>" data-oln-panel="outstanding">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('অনুমোদিত', 'Approved'); ?></th><th><?php echo ame_l('বকেয়া', 'Outstanding'); ?></th><th><?php echo ame_l('স্ট্যাটাস', 'Status'); ?></th></tr></thead><tbody>
            <?php foreach ($outstanding as $r): ?><tr><td>#<?php echo (int)$r['id']; ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo number_format((float)$r['approved_amount'],2); ?></td><td><b><?php echo number_format((float)$r['outstanding_amount'],2); ?></b></td><td><?php echo esc_html($r['status']); ?></td></tr>
            <?php endforeach; if (!$outstanding): ?><tr><td colspan="5" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'overdue' ? 'is-active' : ''; ?>" data-oln-panel="overdue">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('মেয়াদ', 'Due'); ?></th><th><?php echo ame_l('বাকি', 'Remaining'); ?></th></tr></thead><tbody>
            <?php foreach ($overdue as $r): ?><tr><td>#<?php echo (int)$r['loan_id']; ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo esc_html($r['due_date']); ?></td><td><b><?php echo number_format((float)$r['remaining_amount'],2); ?></b></td></tr>
            <?php endforeach; if (!$overdue): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'installments' ? 'is-active' : ''; ?>" data-oln-panel="installments">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th>#</th><th><?php echo ame_l('মেয়াদ', 'Due'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr></thead><tbody>
            <?php foreach ($installment_report as $r): ?><tr><td>#<?php echo (int)$r['loan_id']; ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo (int)$r['installment_no']; ?></td><td><?php echo esc_html($r['due_date']); ?></td><td><?php echo number_format((float)$r['total_installment'],2); ?></td><td><?php echo esc_html($r['status']); ?></td></tr>
            <?php endforeach; if (!$installment_report): ?><tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'savings' ? 'is-active' : ''; ?>" data-oln-panel="savings">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('পরিমাণ', 'Amount'); ?></th></tr></thead><tbody>
            <?php foreach ($savings_report as $r): ?><tr><td><?php echo esc_html($r['txn_date']); ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo number_format((float)$r['amount'],2); ?></td></tr>
            <?php endforeach; if (!$savings_report): ?><tr><td colspan="3" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'savings_wd' ? 'is-active' : ''; ?>" data-oln-panel="savings_wd">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('পরিমাণ', 'Amount'); ?></th><th><?php echo ame_l('কারণ', 'Reason'); ?></th></tr></thead><tbody>
            <?php foreach ($savings_wd_report as $r): ?><tr><td><?php echo esc_html($r['withdrawal_date']); ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo number_format((float)$r['amount'],2); ?></td><td><?php echo esc_html($r['reason']); ?></td></tr>
            <?php endforeach; if (!$savings_wd_report): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'bank_wise' ? 'is-active' : ''; ?>" data-oln-panel="bank_wise">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('ব্যাংক', 'Bank'); ?></th><th><?php echo ame_l('ঋণ সংখ্যা', 'Loan count'); ?></th><th><?php echo ame_l('অনুমোদিত', 'Approved'); ?></th><th><?php echo ame_l('বকেয়া', 'Outstanding'); ?></th></tr></thead><tbody>
            <?php foreach ($bank_wise as $r): ?><tr><td><?php echo esc_html($r['bank_name']); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><?php echo number_format((float)$r['approved'],2); ?></td><td><?php echo number_format((float)$r['outstanding'],2); ?></td></tr>
            <?php endforeach; if (!$bank_wise): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'collector_wise' ? 'is-active' : ''; ?>" data-oln-panel="collector_wise">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('আদায়কারী', 'Collector'); ?></th><th><?php echo ame_l('সংখ্যা', 'Count'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th></tr></thead><tbody>
            <?php foreach ($collector_wise as $r): ?><tr><td><?php echo esc_html($r['username'] ?? '—'); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><b><?php echo number_format((float)$r['t'],2); ?></b></td></tr>
            <?php endforeach; if (!$collector_wise): ?><tr><td colspan="3" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>

        <div class="oln-tab-panel <?php echo $tab === 'complete_txn' ? 'is-active' : ''; ?>" data-oln-panel="complete_txn">
            <div class="oln-table-wrap"><table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('মালিক', 'Owner'); ?></th><th><?php echo ame_l('ধরন', 'Type'); ?></th><th><?php echo ame_l('ডেবিট', 'Debit'); ?></th><th><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th></tr></thead><tbody>
            <?php foreach ($complete_txn as $r): ?><tr><td><?php echo esc_html($r['txn_date']); ?></td><td><?php echo esc_html($r['owner_name']); ?></td><td><?php echo esc_html($r['txn_type']); ?></td><td><?php echo number_format((float)$r['debit'],2); ?></td><td><?php echo number_format((float)$r['credit'],2); ?></td></tr>
            <?php endforeach; if (!$complete_txn): ?><tr><td colspan="5" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<script>
(function () {
    var buttons = document.querySelectorAll('[data-oln-tab]');
    var panels = document.querySelectorAll('[data-oln-panel]');
    var tabField = document.getElementById('tabField');
    function activate(name) {
        buttons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-oln-tab') === name); });
        panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-oln-panel') === name); });
        if (tabField) { tabField.value = name; }
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () { activate(btn.getAttribute('data-oln-tab')); });
    });
})();
</script>
<?php include '../../../partials/footer.php'; ?>
