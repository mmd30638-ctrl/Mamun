<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$id = (int)($_GET['id'] ?? 0);
$loan = ame_oloan_loan_fetch($db, $id);
if (!$loan) {
    die('❌ ' . ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
ame_owner_loan_recalc_installment_statuses($db, $id);
$loan = ame_oloan_loan_fetch($db, $id); // রিফ্রেশড স্ট্যাটাস নিয়ে আবার লোড

$schedule = $db->prepare("SELECT * FROM owner_loan_installments WHERE loan_id = ? ORDER BY installment_no ASC");
$schedule->bind_param('i', $id);
$schedule->execute();
$installments = $schedule->get_result()->fetch_all(MYSQLI_ASSOC);
$schedule->close();

$disb_stmt = $db->prepare("SELECT * FROM owner_loan_disbursements WHERE loan_id = ? ORDER BY id DESC");
$disb_stmt->bind_param('i', $id);
$disb_stmt->execute();
$disbursements = $disb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$disb_stmt->close();

$pay_stmt = $db->prepare("SELECT * FROM owner_loan_payments WHERE loan_id = ? ORDER BY id DESC");
$pay_stmt->bind_param('i', $id);
$pay_stmt->execute();
$payments = $pay_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pay_stmt->close();

$flash_ok = (string)($_GET['saved'] ?? '');
$is_disbursed = count($disbursements) > 0;

$status_class_map = ['upcoming' => 'upcoming', 'partial' => 'partial', 'overdue' => 'overdue', 'paid' => 'paid'];

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:1080px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:13.5px; background: var(--brand-success-soft); color: var(--brand-success); }
.oln-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
.oln-summary-item { background: var(--brand-white); border:1px solid var(--brand-border); border-radius:10px; padding:12px; }
.oln-summary-item .l { font-size:11.5px; color: var(--brand-muted); font-weight:700; }
.oln-summary-item .v { font-size:16px; font-weight:800; color: var(--brand-navy); }
.oln-actionbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
.oln-actionbar a, .oln-actionbar button {
    background: var(--brand-gradient); color: var(--brand-white) !important; text-decoration:none; border:none;
    padding:10px 16px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;
}
.oln-actionbar .ghost { background: var(--brand-white); color: var(--brand-accent) !important; border:1.5px solid var(--brand-border); }
.oln-section { background: var(--brand-white); border:1px solid var(--brand-border); border-radius:12px; padding:16px; margin-bottom:18px; }
.oln-section h4 { margin:0 0 12px; color: var(--brand-navy); }
.oln-table { width:100%; border-collapse: collapse; font-size:13px; }
.oln-table th { text-align:left; padding:8px; background: var(--brand-navy); color: var(--brand-white); }
.oln-table td { padding:8px; border-bottom:1px solid var(--brand-border); }
.oln-empty { text-align:center; padding:16px; color: var(--brand-muted); }
.oln-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.oln-badge.upcoming { background: var(--oln-status-upcoming-bg); color: var(--oln-status-upcoming-fg); }
.oln-badge.partial { background: var(--oln-status-partial-bg); color: var(--oln-status-partial-fg); }
.oln-badge.overdue { background: var(--oln-status-overdue-bg); color: var(--oln-status-overdue-fg); }
.oln-badge.paid { background: var(--oln-status-paid-bg); color: var(--oln-status-paid-fg); }
.oln-form-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-form-wrap">
    <div class="oln-form-head">
        <h3>💵 <?php echo ame_l('ঋণ বিবরণ', 'Loan detail'); ?> #<?php echo (int)$id; ?> — <?php echo esc_html($loan['owner_name']); ?></h3>
        <a href="list.php" class="oln-back-link">⬅️ <?php echo ame_l('তালিকায় ফিরে যান', 'Back to list'); ?></a>
    </div>
    <?php if ($flash_ok === '1'): ?><div class="oln-flash">✅ <?php echo ame_l('সংরক্ষণ করা হয়েছে।', 'Saved successfully.'); ?></div><?php endif; ?>

    <div class="oln-summary-grid">
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('দোকান', 'Shop'); ?></div><div class="v"><?php echo esc_html($loan['shop_name']); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('ব্যাংক', 'Bank'); ?></div><div class="v"><?php echo esc_html($loan['bank_name'] ?? '—'); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('অনুমোদিত', 'Approved'); ?></div><div class="v">৳<?php echo number_format((float)$loan['approved_amount'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট প্রদেয়', 'Total payable'); ?></div><div class="v">৳<?php echo number_format((float)$loan['total_payable'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('পরিশোধিত', 'Paid'); ?></div><div class="v">৳<?php echo number_format((float)$loan['paid_amount'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('বকেয়া', 'Outstanding'); ?></div><div class="v" style="color:var(--brand-danger);">৳<?php echo number_format((float)$loan['outstanding_amount'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('স্ট্যাটাস', 'Status'); ?></div><div class="v"><?php echo esc_html($loan['status']); ?></div></div>
    </div>

    <div class="oln-actionbar">
        <?php if ($loan['status'] === 'pending'): ?>
        <form action="process_loan_save.php" method="POST" style="display:inline;" onsubmit="return true;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="owner_id" value="<?php echo (int)$loan['owner_id']; ?>">
            <input type="hidden" name="bank_id" value="<?php echo (int)($loan['bank_id'] ?? 0); ?>">
            <input type="hidden" name="loan_type" value="<?php echo esc_html($loan['loan_type']); ?>">
            <input type="hidden" name="loan_purpose" value="<?php echo esc_html($loan['loan_purpose']); ?>">
            <input type="hidden" name="approved_amount" value="<?php echo esc_html($loan['approved_amount']); ?>">
            <input type="hidden" name="interest_rate" value="<?php echo esc_html($loan['interest_rate']); ?>">
            <input type="hidden" name="loan_term_months" value="<?php echo esc_html($loan['loan_term_months']); ?>">
            <input type="hidden" name="installment_count" value="<?php echo esc_html($loan['installment_count']); ?>">
            <input type="hidden" name="installment_frequency" value="<?php echo esc_html($loan['installment_frequency']); ?>">
            <input type="hidden" name="approval_date" value="<?php echo esc_html($loan['approval_date'] ?: date('Y-m-d')); ?>">
            <input type="hidden" name="status" value="approved">
            <button type="submit">✅ <?php echo ame_l('অনুমোদন করুন', 'Approve'); ?></button>
        </form>
        <?php endif; ?>
        <a href="loan_add.php?id=<?php echo (int)$id; ?>" class="ghost">✏️ <?php echo ame_l('এডিট', 'Edit'); ?></a>
        <?php if (in_array($loan['status'], ['approved', 'active'], true) && !$is_disbursed): ?>
        <a href="disburse_form.php?loan_id=<?php echo (int)$id; ?>">🏧 <?php echo ame_l('বিতরণ করুন', 'Disburse'); ?></a>
        <?php endif; ?>
        <?php if (in_array($loan['status'], ['active', 'overdue'], true)): ?>
        <a href="payment_entry.php?owner_id=<?php echo (int)$loan['owner_id']; ?>&loan_id=<?php echo (int)$id; ?>">💰 <?php echo ame_l('পেমেন্ট নিন', 'Record payment'); ?></a>
        <?php endif; ?>
        <a href="statement.php?owner_id=<?php echo (int)$loan['owner_id']; ?>" class="ghost">🖨️ <?php echo ame_l('স্টেটমেন্ট প্রিন্ট', 'Print statement'); ?></a>
    </div>

    <div class="oln-section">
        <h4>📆 <?php echo ame_l('কিস্তির শিডিউল', 'Installment schedule'); ?></h4>
        <?php if (!$installments): ?>
            <div class="oln-empty"><?php echo ame_l('এখনো কোনো শিডিউল তৈরি হয়নি (অনুমোদনের পর তৈরি হবে)।', 'No schedule generated yet (created after approval).'); ?></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr>
                <th>#</th><th><?php echo ame_l('মেয়াদ', 'Due'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th>
                <th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th>
                <th><?php echo ame_l('পরিশোধিত', 'Paid'); ?></th><th><?php echo ame_l('বাকি', 'Remaining'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($installments as $r): $cls = $status_class_map[$r['status']] ?? 'upcoming'; ?>
                <tr>
                    <td><?php echo (int)$r['installment_no']; ?></td>
                    <td><?php echo esc_html($r['due_date']); ?></td>
                    <td><?php echo number_format((float)$r['principal_amount'], 2); ?></td>
                    <td><?php echo number_format((float)$r['interest_amount'], 2); ?></td>
                    <td><?php echo number_format((float)$r['total_installment'], 2); ?></td>
                    <td><?php echo number_format((float)$r['paid_amount'], 2); ?></td>
                    <td><?php echo number_format((float)$r['remaining_amount'], 2); ?></td>
                    <td><span class="oln-badge <?php echo $cls; ?>"><?php echo esc_html($r['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="oln-section">
        <h4>🏧 <?php echo ame_l('বিতরণ ইতিহাস', 'Disbursement history'); ?></h4>
        <?php if (!$disbursements): ?>
            <div class="oln-empty"><?php echo ame_l('এখনো কোনো বিতরণ হয়নি।', 'No disbursement yet.'); ?></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('বিতরণ', 'Disbursed'); ?></th><th><?php echo ame_l('চার্জ', 'Fees'); ?></th><th><?php echo ame_l('নিট প্রাপ্ত', 'Net received'); ?></th><th><?php echo ame_l('পদ্ধতি', 'Method'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($disbursements as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['disbursement_date']); ?></td>
                    <td>৳<?php echo number_format((float)$r['disbursed_amount'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['processing_fee'] + (float)$r['other_charges'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['net_amount_received'], 2); ?></td>
                    <td><?php echo esc_html($r['payment_method']); ?></td>
                    <td><a href="receipt.php?type=disbursement&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="oln-section">
        <h4>💰 <?php echo ame_l('পেমেন্ট ইতিহাস', 'Payment history'); ?></h4>
        <?php if (!$payments): ?>
            <div class="oln-empty"><?php echo ame_l('এখনো কোনো পেমেন্ট হয়নি।', 'No payments yet.'); ?></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="oln-table">
            <thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th><th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('জরিমানা', 'Penalty'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['payment_date']); ?></td>
                    <td>৳<?php echo number_format((float)$r['principal_paid'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['interest_paid'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['penalty_charge'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['total_payment'], 2); ?></td>
                    <td><a href="receipt.php?type=payment&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
