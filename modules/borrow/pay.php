<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
$loan_id = (int)($_GET['loan_id'] ?? 0);
$loans = $db->query("SELECT id, provider_name, provider_type, repayment_style, principal_outstanding, monthly_fee FROM biz_loans WHERE status = 'active' ORDER BY provider_name");
$loan = $loan_id > 0 ? ame_borrow_fetch($db, $loan_id) : null;
include '../../partials/header.php';
?>
<style>
.bp { max-width:720px; margin:0 auto; padding:12px 12px 40px; }
.bp form { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px; display:grid; gap:10px; }
.bp input, .bp select { width:100%; box-sizing:border-box; padding:10px; border:1px solid #dbe4f0; border-radius:10px; }
.bp button { background:#0b1e4d; color:#fff; border:0; border-radius:10px; padding:12px; font-weight:800; }
</style>
<div class="bp">
    <h1>ধার পরিশোধ</h1>
    <?php if (!empty($_GET['err'])): ?><p><?php echo htmlspecialchars((string)$_GET['err'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <form method="post" action="process_pay.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <label>কোন ধার</label>
        <select name="loan_id" required onchange="location='pay.php?loan_id='+this.value">
            <option value="">বাছুন</option>
            <?php if ($loans) while ($l = $loans->fetch_assoc()): ?>
            <option value="<?php echo (int)$l['id']; ?>" <?php echo $loan_id === (int)$l['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['provider_name'] . ' · বাকি ' . number_format((float)$l['principal_outstanding'], 2), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endwhile; ?>
        </select>
        <?php if ($loan): ?>
        <p><?php echo $loan['repayment_style'] === 'service_fee' ? 'এই ব্যক্তির মাসিক ফি মূল টাকা কমাবে না। মূল ফেরত আলাদা দিন।' : 'কিস্তির আসল অংশ মূল বাকি কমাবে। সুদ, সঞ্চয় ও জামানত আলাদা।'; ?></p>
        <label>কী দিচ্ছেন</label>
        <select name="kind">
            <?php if ($loan['repayment_style'] === 'service_fee'): ?>
            <option value="fee">মাসিক ফি</option>
            <option value="principal_settlement">মূল টাকা ফেরত</option>
            <?php else: ?>
            <option value="installment">কিস্তি</option>
            <option value="savings">সঞ্চয়</option>
            <option value="security_in">জামানত জমা</option>
            <option value="security_out">জামানত ফেরত</option>
            <?php endif; ?>
        </select>
        <label>আসল অংশ</label><input type="number" step="0.01" name="principal_part" value="0">
        <label>সুদ</label><input type="number" step="0.01" name="interest_part" value="0">
        <label>ফি / অন্যান্য</label><input type="number" step="0.01" name="fee_part" value="<?php echo $loan['repayment_style'] === 'service_fee' ? htmlspecialchars((string)$loan['monthly_fee'], ENT_QUOTES, 'UTF-8') : '0'; ?>">
        <label>সঞ্চয়</label><input type="number" step="0.01" name="savings_part" value="0">
        <label>জামানত</label><input type="number" step="0.01" name="security_part" value="0">
        <label>অ্যাকাউন্ট</label>
        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required>
            <?php echo ame_render_account_options(ame_active_accounts($db)); ?>
        </select>
        <label>মাধ্যম</label>
        <select name="method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option></select>
        <label>রেফারেন্স</label><input name="reference_no">
        <label>নোট</label><input name="notes">
        <button type="submit">পেমেন্ট সেভ</button>
        <?php endif; ?>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>
