<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$id = (int)($_GET['id'] ?? 0);
$is_new = $id <= 0;
$loan = $is_new ? [] : ame_oloan_loan_fetch($db, $id);
if (!$is_new && !$loan) {
    die('❌ ' . ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
$preselect_owner = (int)($_GET['owner_id'] ?? ($loan['owner_id'] ?? 0));
$owners = ame_oloan_owner_options($db, true);
$banks = ame_oloan_bank_options($db, true);

function lsv($loan, $key, $default = '')
{
    return htmlspecialchars((string)($loan[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
$flash_err = (string)($_GET['err'] ?? '');

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:900px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:13.5px; }
.oln-flash.err { background: var(--brand-danger-soft); color: var(--brand-danger); }
.oln-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px 18px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field select, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
}
.oln-calc-box { margin-top:18px; padding:16px; border-radius:10px; background: var(--oln-kpi-blue-bg); border:1px solid var(--brand-border); }
.oln-calc-box h4 { margin:0 0 10px; color: var(--oln-kpi-blue-fg); }
.oln-calc-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; }
.oln-calc-item .l { font-size:12px; color: var(--brand-muted); }
.oln-calc-item .v { font-size:16px; font-weight:800; color: var(--brand-navy); }
.oln-footer-actions { display:flex; gap:10px; margin-top:22px; padding-top:18px; border-top:1px solid var(--brand-border); }
.oln-cancel-btn {
    padding:12px 24px; border-radius:10px; border:1.5px solid var(--brand-border);
    background: var(--brand-white); color: var(--brand-muted) !important; font-weight:700; text-decoration:none; display:inline-flex; align-items:center;
}
.oln-form-wrap .ame-wait-spin i {
    width: 30px !important; height: 30px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-form-wrap">
    <div class="oln-form-head">
        <h3>💵 <?php echo $is_new ? ame_l('নতুন ঋণ', 'New loan') : ame_l('ঋণ এডিট', 'Edit loan') . ' #' . (int)$id; ?></h3>
        <a href="list.php" class="oln-back-link">⬅️ <?php echo ame_l('তালিকায় ফিরে যান', 'Back to list'); ?></a>
    </div>
    <?php if ($flash_err !== ''): ?>
        <div class="oln-flash err">⚠️ <?php echo esc_html($flash_err); ?></div>
    <?php endif; ?>
    <div class="card">
        <form action="process_loan_save.php" method="POST" id="olnLoanForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('দোকান মালিক *', 'Shop owner *'); ?></label>
                    <select name="owner_id" required <?php echo (!$is_new) ? 'disabled' : ''; ?>>
                        <option value=""></option>
                        <?php foreach ($owners as $o): ?>
                        <option value="<?php echo (int)$o['id']; ?>" <?php echo $preselect_owner === (int)$o['id'] ? 'selected' : ''; ?>><?php echo esc_html($o['owner_name'] . ' — ' . $o['shop_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$is_new): ?><input type="hidden" name="owner_id" value="<?php echo (int)$loan['owner_id']; ?>"><?php endif; ?>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ব্যাংক', 'Bank'); ?></label>
                    <select name="bank_id">
                        <option value=""><?php echo ame_l('— কোনোটি নয় —', '— None —'); ?></option>
                        <?php foreach ($banks as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo (int)($loan['bank_id'] ?? 0) === (int)$b['id'] ? 'selected' : ''; ?>><?php echo esc_html($b['bank_name'] . ($b['branch_name'] ? ' — ' . $b['branch_name'] : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ঋণের ধরন', 'Loan type'); ?></label>
                    <input type="text" name="loan_type" value="<?php echo lsv($loan, 'loan_type'); ?>" maxlength="80" placeholder="<?php echo ame_l('যেমন: চলতি মূলধন', 'e.g. Working capital'); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ঋণের উদ্দেশ্য', 'Loan purpose'); ?></label>
                    <input type="text" name="loan_purpose" value="<?php echo lsv($loan, 'loan_purpose'); ?>" maxlength="255">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('অনুমোদিত পরিমাণ (৳) *', 'Approved amount (৳) *'); ?></label>
                    <input type="number" step="0.01" min="0.01" name="approved_amount" id="calcPrincipal" value="<?php echo lsv($loan, 'approved_amount', '0'); ?>" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('সুদের হার (% বার্ষিক) *', 'Interest rate (% p.a.) *'); ?></label>
                    <input type="number" step="0.01" min="0" name="interest_rate" id="calcRate" value="<?php echo lsv($loan, 'interest_rate', '0'); ?>" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ঋণের মেয়াদ (মাস)', 'Loan term (months)'); ?></label>
                    <input type="number" min="0" name="loan_term_months" value="<?php echo lsv($loan, 'loan_term_months', '0'); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('মোট কিস্তি সংখ্যা *', 'Installment count *'); ?></label>
                    <input type="number" min="1" name="installment_count" id="calcCount" value="<?php echo lsv($loan, 'installment_count', '12'); ?>" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('কিস্তির ফ্রিকোয়েন্সি', 'Installment frequency'); ?></label>
                    <select name="installment_frequency">
                        <option value="monthly" <?php echo ($loan['installment_frequency'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>><?php echo ame_l('মাসিক', 'Monthly'); ?></option>
                    </select>
                    <div style="font-size:11.5px;color:var(--brand-muted);margin-top:4px;"><?php echo ame_l('বর্তমানে শুধু মাসিক সমর্থিত (সরলীকরণ)।', 'Only monthly is fully supported for now (simplification).'); ?></div>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('অনুমোদনের তারিখ', 'Approval date'); ?></label>
                    <input type="date" name="approval_date" value="<?php echo lsv($loan, 'approval_date', date('Y-m-d')); ?>">
                </div>
                <?php if (!$is_new): ?>
                <div class="oln-field">
                    <label><?php echo ame_l('স্ট্যাটাস', 'Status'); ?></label>
                    <select name="status">
                        <?php foreach (['pending','approved','active','completed','overdue','cancelled'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($loan['status'] ?? 'pending') === $st ? 'selected' : ''; ?>><?php echo esc_html($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="oln-calc-box">
                <h4>🧮 <?php echo ame_l('হিসাব প্রিভিউ (শুধু দেখার জন্য — চূড়ান্ত হিসাব সার্ভারে হবে)', 'Calculation preview (server computes the authoritative figures)'); ?></h4>
                <div class="oln-calc-grid">
                    <div class="oln-calc-item"><div class="l"><?php echo ame_l('মোট সুদ', 'Total interest'); ?></div><div class="v" id="calcOutInterest">৳0.00</div></div>
                    <div class="oln-calc-item"><div class="l"><?php echo ame_l('মোট প্রদেয়', 'Total payable'); ?></div><div class="v" id="calcOutPayable">৳0.00</div></div>
                    <div class="oln-calc-item"><div class="l"><?php echo ame_l('প্রতি কিস্তি', 'Per installment'); ?></div><div class="v" id="calcOutInstallment">৳0.00</div></div>
                </div>
            </div>

            <div class="oln-footer-actions">
                <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('সংরক্ষণ করুন', 'Save'); ?></button>
                <a href="list.php" class="oln-cancel-btn"><?php echo ame_l('বাতিল', 'Cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var p = document.getElementById('calcPrincipal');
    var r = document.getElementById('calcRate');
    var c = document.getElementById('calcCount');
    var oi = document.getElementById('calcOutInterest');
    var op = document.getElementById('calcOutPayable');
    var oa = document.getElementById('calcOutInstallment');
    function fmt(n) { return '৳' + (isFinite(n) ? n : 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function recalc() {
        var principal = parseFloat(p.value) || 0;
        var rate = parseFloat(r.value) || 0;
        var count = parseInt(c.value, 10) || 0;
        if (principal <= 0 || count <= 0) {
            oi.textContent = fmt(0); op.textContent = fmt(principal); oa.textContent = fmt(0);
            return;
        }
        var totalInterest = principal * (rate / 100) * (count / 12);
        var totalPayable = principal + totalInterest;
        var perInstallment = totalPayable / count;
        oi.textContent = fmt(totalInterest);
        op.textContent = fmt(totalPayable);
        oa.textContent = fmt(perInstallment);
    }
    [p, r, c].forEach(function (el) { el.addEventListener('input', recalc); });
    recalc();
})();
</script>
<?php include '../../../partials/footer.php'; ?>
