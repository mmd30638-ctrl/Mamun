<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$owner_id = (int)($_GET['owner_id'] ?? 0);
$loan_id = (int)($_GET['loan_id'] ?? 0);
$owners = ame_oloan_owner_options($db, true);
$accounts = ame_oloan_shop_accounts($db);

$loans = [];
$next_installment = null;
$savings_balance = 0.0;
if ($owner_id > 0) {
    ame_owner_loan_recalc_installment_statuses($db);
    $stmt = $db->prepare("SELECT id, loan_type, approved_amount, outstanding_amount, status FROM owner_loans WHERE owner_id = ? AND status IN ('active','overdue') ORDER BY id DESC");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($loan_id <= 0 && $loans) {
        $loan_id = (int)$loans[0]['id'];
    }
    if ($loan_id > 0) {
        $ni = $db->prepare("SELECT * FROM owner_loan_installments WHERE loan_id = ? AND status IN ('upcoming','partial','overdue') ORDER BY due_date ASC LIMIT 1");
        $ni->bind_param('i', $loan_id);
        $ni->execute();
        $next_installment = $ni->get_result()->fetch_assoc();
        $ni->close();
    }
    $sv = $db->prepare("SELECT COALESCE(SUM(current_balance),0) v FROM owner_savings_accounts WHERE owner_id = ?");
    $sv->bind_param('i', $owner_id);
    $sv->execute();
    $svr = $sv->get_result()->fetch_assoc();
    $savings_balance = $svr ? (float)$svr['v'] : 0.0;
    $sv->close();
}
$collector_name = htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:760px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-field { margin-bottom:14px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field select, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
}
.oln-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
.oln-hint { font-size:12px; color: var(--brand-muted); margin-top:4px; }
.oln-total-box { padding:14px; background: var(--oln-kpi-blue-bg); border-radius:10px; font-weight:800; color: var(--oln-kpi-blue-fg); margin-bottom:14px; font-size:16px; }
.oln-form-wrap .ame-wait-spin i {
    width: 30px !important; height: 30px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-form-wrap">
    <div class="oln-form-head">
        <h3>💰 <?php echo te('nav_oloan_payment'); ?></h3>
        <a href="dashboard.php" class="oln-back-link">⬅️ <?php echo ame_l('ড্যাশবোর্ড', 'Dashboard'); ?></a>
    </div>
    <div class="card">
        <form method="GET" class="oln-grid-2" style="margin-bottom:16px;">
            <div class="oln-field">
                <label><?php echo ame_l('দোকান মালিক নির্বাচন করুন', 'Select shop owner'); ?></label>
                <select name="owner_id" onchange="this.form.submit()">
                    <option value=""></option>
                    <?php foreach ($owners as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>" <?php echo $owner_id === (int)$o['id'] ? 'selected' : ''; ?>><?php echo esc_html($o['owner_name'] . ' — ' . $o['shop_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($owner_id > 0): ?>
        <form action="process_payment.php" method="POST" id="olnPayForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="owner_id" value="<?php echo (int)$owner_id; ?>">
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('সক্রিয় ঋণ', 'Active loan'); ?></label>
                    <select onchange="window.location.href='payment_entry.php?owner_id=<?php echo (int)$owner_id; ?>&loan_id='+this.value;">
                        <option value="0"><?php echo ame_l('— কোনো ঋণ কিস্তি নেই, শুধু সঞ্চয় জমা —', '— No loan installment, savings deposit only —'); ?></option>
                        <?php foreach ($loans as $l): ?>
                        <option value="<?php echo (int)$l['id']; ?>" <?php echo $loan_id === (int)$l['id'] ? 'selected' : ''; ?>><?php echo ame_l('ঋণ', 'Loan'); ?> #<?php echo (int)$l['id']; ?> — <?php echo ame_l('বকেয়া', 'Outstanding'); ?> ৳<?php echo number_format((float)$l['outstanding_amount'], 2); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$loans): ?><div class="oln-hint"><?php echo ame_l('এই মালিকের কোনো সক্রিয় ঋণ নেই।', 'This owner has no active loan.'); ?></div><?php endif; ?>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('বর্তমান সঞ্চয় ব্যালেন্স', 'Current savings balance'); ?></label>
                    <input type="text" value="৳<?php echo number_format($savings_balance, 2); ?>" disabled>
                </div>
            </div>

            <input type="hidden" name="loan_id" value="<?php echo (int)$loan_id; ?>">
            <?php if ($loan_id > 0 && $next_installment): ?>
            <input type="hidden" name="installment_id" value="<?php echo (int)$next_installment['id']; ?>">
            <div class="oln-hint" style="margin-bottom:10px;">
                <?php echo ame_l('পরবর্তী কিস্তি', 'Next installment'); ?> #<?php echo (int)$next_installment['installment_no']; ?> —
                <?php echo ame_l('মেয়াদ', 'Due'); ?> <?php echo esc_html($next_installment['due_date']); ?>,
                <?php echo ame_l('বাকি', 'Remaining'); ?> ৳<?php echo number_format((float)$next_installment['remaining_amount'], 2); ?>
            </div>
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('আসল (৳)', 'Principal (৳)'); ?></label>
                    <input type="number" step="0.01" min="0" name="principal_paid" id="pPrincipal" value="<?php echo number_format((float)$next_installment['principal_amount'] - (float)max(0,$next_installment['paid_amount']), 2, '.', ''); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('সুদ (৳)', 'Interest (৳)'); ?></label>
                    <input type="number" step="0.01" min="0" name="interest_paid" id="pInterest" value="<?php echo number_format((float)$next_installment['interest_amount'], 2, '.', ''); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('জরিমানা/অতিরিক্ত চার্জ (৳)', 'Penalty/other charge (৳)'); ?></label>
                    <input type="number" step="0.01" min="0" name="penalty_charge" id="pPenalty" value="0">
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="installment_id" value="0">
            <input type="hidden" name="principal_paid" id="pPrincipal" value="0">
            <input type="hidden" name="interest_paid" id="pInterest" value="0">
            <input type="hidden" name="penalty_charge" id="pPenalty" value="0">
            <?php endif; ?>

            <div class="oln-field">
                <label><?php echo ame_l('সঞ্চয় জমা (ঐচ্ছিক, ৳)', 'Savings deposit (optional, ৳)'); ?></label>
                <input type="number" step="0.01" min="0" name="savings_deposit" id="pSavings" value="0">
            </div>

            <div class="oln-total-box"><?php echo ame_l('মোট আদায়', 'Grand total collection'); ?>: ৳<span id="pGrandTotal">0.00</span></div>

            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('পেমেন্ট পদ্ধতি', 'Payment method'); ?></label>
                    <select name="payment_method">
                        <option value="cash"><?php echo ame_l('ক্যাশ', 'Cash'); ?></option>
                        <option value="bank"><?php echo ame_l('ব্যাংক', 'Bank'); ?></option>
                        <option value="mobile_banking"><?php echo ame_l('মোবাইল ব্যাংকিং', 'Mobile banking'); ?></option>
                    </select>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('যে অ্যাকাউন্টে জমা হবে *', 'Deposit into shop account *'); ?></label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required>
                        <option value=""></option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>"><?php echo esc_html(ame_account_option_label($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('রেফারেন্স নং', 'Reference no.'); ?></label>
                    <input type="text" name="reference_no" maxlength="100">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('আদায়কারী', 'Collector'); ?></label>
                    <input type="text" value="<?php echo $collector_name; ?>" disabled>
                </div>
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('মন্তব্য', 'Remarks'); ?></label>
                    <textarea name="remarks" style="min-height:60px;"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('আদায় নিশ্চিত করুন', 'Confirm collection'); ?></button>
        </form>
        <?php else: ?>
            <div class="oln-hint"><?php echo ame_l('উপরে মালিক নির্বাচন করুন।', 'Select an owner above.'); ?></div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var p = document.getElementById('pPrincipal'), i = document.getElementById('pInterest'), pen = document.getElementById('pPenalty'), s = document.getElementById('pSavings');
    var out = document.getElementById('pGrandTotal');
    if (!out) { return; }
    function recalc() {
        var total = (parseFloat(p.value)||0) + (parseFloat(i.value)||0) + (parseFloat(pen.value)||0) + (parseFloat(s.value)||0);
        out.textContent = total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    [p, i, pen, s].forEach(function (el) { if (el) { el.addEventListener('input', recalc); } });
    recalc();
})();
</script>
<?php include '../../../partials/footer.php'; ?>
