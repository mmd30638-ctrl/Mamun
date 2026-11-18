<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$id = (int)($_GET['id'] ?? 0);
$bank = [];
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM banks WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $bank = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}
function bsv($bank, $key, $default = '')
{
    return htmlspecialchars((string)($bank[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-form-wrap { padding:15px; max-width:640px; margin:0 auto; }
.oln-form-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-form-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-field { margin-bottom:14px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
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
        <h3>🏦 <?php echo $id > 0 ? ame_l('ব্যাংক এডিট', 'Edit bank') : ame_l('নতুন ব্যাংক', 'New bank'); ?></h3>
        <a href="banks_list.php" class="oln-back-link">⬅️ <?php echo ame_l('তালিকায় ফিরে যান', 'Back to list'); ?></a>
    </div>
    <div class="card">
        <form action="process_bank_save.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="oln-field">
                <label><?php echo ame_l('ব্যাংকের নাম *', 'Bank name *'); ?></label>
                <input type="text" name="bank_name" value="<?php echo bsv($bank, 'bank_name'); ?>" required maxlength="150">
            </div>
            <div class="oln-field">
                <label><?php echo ame_l('শাখার নাম', 'Branch name'); ?></label>
                <input type="text" name="branch_name" value="<?php echo bsv($bank, 'branch_name'); ?>" maxlength="150">
            </div>
            <div class="oln-field">
                <label><?php echo ame_l('শাখার ঠিকানা', 'Branch address'); ?></label>
                <textarea name="branch_address" style="min-height:70px;"><?php echo bsv($bank, 'branch_address'); ?></textarea>
            </div>
            <div class="oln-field">
                <label><?php echo ame_l('হিসাব তথ্য', 'Account info'); ?></label>
                <input type="text" name="account_info" value="<?php echo bsv($bank, 'account_info'); ?>" maxlength="255">
            </div>
            <div class="oln-field">
                <label><?php echo ame_l('যোগাযোগ তথ্য', 'Contact info'); ?></label>
                <input type="text" name="contact_info" value="<?php echo bsv($bank, 'contact_info'); ?>" maxlength="255">
            </div>
            <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('সংরক্ষণ করুন', 'Save'); ?></button>
        </form>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
