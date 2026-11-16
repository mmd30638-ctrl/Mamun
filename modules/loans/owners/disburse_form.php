<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$loan_id = (int)($_GET['loan_id'] ?? 0);
$loan = ame_oloan_loan_fetch($db, $loan_id);
if (!$loan) {
    die('❌ ' . ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
$accounts = ame_oloan_shop_accounts($db);

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:700px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-field { margin-bottom:14px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field select, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
}
.oln-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
.oln-net-box { padding:12px; background: var(--brand-success-soft); border-radius:8px; font-weight:800; color: var(--brand-success); margin-bottom:14px; }
.oln-form-wrap .ame-wait-spin i {
    width: 30px !important; height: 30px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-form-wrap">
    <div class="oln-form-head">
        <h3>🏧 <?php echo ame_l('ঋণ বিতরণ', 'Loan disbursement'); ?> — #<?php echo (int)$loan_id; ?></h3>
        <a href="loan_view.php?id=<?php echo (int)$loan_id; ?>" class="oln-back-link">⬅️ <?php echo ame_l('ফিরে যান', 'Back'); ?></a>
    </div>
    <div class="card">
        <form action="process_disburse.php" method="POST" id="disburseForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="loan_id" value="<?php echo (int)$loan_id; ?>">
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('বিতরণের তারিখ *', 'Disbursement date *'); ?></label>
                    <input type="date" name="disbursement_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('বিতরণকৃত পরিমাণ (৳) *', 'Disbursed amount (৳) *'); ?></label>
                    <input type="number" step="0.01" min="0.01" name="disbursed_amount" id="dAmount" value="<?php echo esc_html($loan['approved_amount']); ?>" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('প্রসেসিং ফি (৳)', 'Processing fee (৳)'); ?></label>
                    <input type="number" step="0.01" min="0" name="processing_fee" id="dFee" value="0">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('অন্যান্য চার্জ (৳)', 'Other charges (৳)'); ?></label>
                    <input type="number" step="0.01" min="0" name="other_charges" id="dOther" value="0">
                </div>
            </div>
            <div class="oln-net-box"><?php echo ame_l('নিট প্রাপ্ত পরিমাণ', 'Net amount received'); ?>: ৳<span id="dNet">0.00</span></div>
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('ব্যাংক রেফারেন্স নং', 'Bank reference no.'); ?></label>
                    <input type="text" name="bank_ref_no" maxlength="100">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('পেমেন্ট পদ্ধতি', 'Payment method'); ?></label>
                    <select name="payment_method">
                        <option value="cash"><?php echo ame_l('ক্যাশ', 'Cash'); ?></option>
                        <option value="bank"><?php echo ame_l('ব্যাংক', 'Bank'); ?></option>
                        <option value="mobile_banking"><?php echo ame_l('মোবাইল ব্যাংকিং', 'Mobile banking'); ?></option>
                    </select>
                </div>
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('যে অ্যাকাউন্ট থেকে টাকা যাবে *', 'Shop account money leaves from *'); ?></label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required>
                        <option value=""></option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>"><?php echo esc_html(ame_account_option_label($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('মন্তব্য', 'Remarks'); ?></label>
                    <textarea name="remarks" style="min-height:60px;"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('বিতরণ নিশ্চিত করুন', 'Confirm disbursement'); ?></button>
        </form>
    </div>
</div>
<script>
(function () {
    var a = document.getElementById('dAmount'), f = document.getElementById('dFee'), o = document.getElementById('dOther'), n = document.getElementById('dNet');
    function recalc() {
        var net = (parseFloat(a.value)||0) - (parseFloat(f.value)||0) - (parseFloat(o.value)||0);
        n.textContent = net.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    [a, f, o].forEach(function (el) { el.addEventListener('input', recalc); });
    recalc();
})();
</script>
<?php include '../../../partials/footer.php'; ?>
