<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$owner_id = (int)($_GET['owner_id'] ?? 0);
$owners = ame_oloan_owner_options($db, true);
$accounts = ame_oloan_shop_accounts($db);
$savings_account = null;
if ($owner_id > 0) {
    $savings_account = ame_oloan_find_or_create_savings_account($db, $owner_id);
}
$flash_err = (string)($_GET['err'] ?? '');
$flash_ok = (string)($_GET['saved'] ?? '');

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:700px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:13.5px; }
.oln-flash.err { background: var(--brand-danger-soft); color: var(--brand-danger); }
.oln-flash.ok { background: var(--brand-success-soft); color: var(--brand-success); }
.oln-field { margin-bottom:14px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field select, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
}
.oln-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
.oln-balance-box { padding:14px; background: var(--oln-kpi-teal-bg); border-radius:10px; font-weight:800; color: var(--oln-kpi-teal-fg); margin-bottom:14px; font-size:16px; }
.oln-form-wrap .ame-wait-spin i {
    width: 30px !important; height: 30px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-form-wrap">
    <div class="oln-form-head">
        <h3>🏧 <?php echo te('nav_oloan_savings'); ?></h3>
        <a href="dashboard.php" class="oln-back-link">⬅️ <?php echo ame_l('ড্যাশবোর্ড', 'Dashboard'); ?></a>
    </div>
    <?php if ($flash_ok === '1'): ?><div class="oln-flash ok">✅ <?php echo ame_l('উত্তোলন সম্পন্ন হয়েছে।', 'Withdrawal completed.'); ?></div><?php endif; ?>
    <?php if ($flash_err !== ''): ?><div class="oln-flash err">⚠️ <?php echo esc_html($flash_err); ?></div><?php endif; ?>
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

        <?php if ($owner_id > 0 && $savings_account): ?>
        <div class="oln-balance-box"><?php echo ame_l('বর্তমান সঞ্চয় ব্যালেন্স', 'Current savings balance'); ?>: ৳<?php echo number_format((float)$savings_account['current_balance'], 2); ?></div>
        <form action="process_savings_withdraw.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="owner_id" value="<?php echo (int)$owner_id; ?>">
            <input type="hidden" name="savings_account_id" value="<?php echo (int)$savings_account['id']; ?>">
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('উত্তোলনের পরিমাণ (৳) *', 'Withdrawal amount (৳) *'); ?></label>
                    <input type="number" step="0.01" min="0.01" max="<?php echo esc_html($savings_account['current_balance']); ?>" name="amount" required>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('কারণ', 'Reason'); ?></label>
                    <input type="text" name="reason" maxlength="255">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('অনুমোদনকারী', 'Approved by'); ?></label>
                    <input type="text" value="<?php echo htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <input type="hidden" name="approved_by" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('পরিশোধকারী', 'Paid by'); ?></label>
                    <input type="text" value="<?php echo htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <input type="hidden" name="paid_by" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('পেমেন্ট পদ্ধতি', 'Payment method'); ?></label>
                    <select name="payment_method">
                        <option value="cash"><?php echo ame_l('ক্যাশ', 'Cash'); ?></option>
                        <option value="bank"><?php echo ame_l('ব্যাংক', 'Bank'); ?></option>
                        <option value="mobile_banking"><?php echo ame_l('মোবাইল ব্যাংকিং', 'Mobile banking'); ?></option>
                    </select>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('যে অ্যাকাউন্ট থেকে টাকা যাবে *', 'Shop account money leaves from *'); ?></label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required>
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
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('মন্তব্য', 'Remarks'); ?></label>
                    <textarea name="remarks" style="min-height:60px;"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('উত্তোলন নিশ্চিত করুন', 'Confirm withdrawal'); ?></button>
        </form>
        <?php elseif ($owner_id > 0): ?>
            <div class="oln-flash err"><?php echo ame_l('সঞ্চয় হিসাব পাওয়া যায়নি।', 'Savings account not found.'); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
