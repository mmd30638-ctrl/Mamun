<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$id = (int)($_GET['id'] ?? 0);
$is_new = $id <= 0;
$owner = $is_new ? [] : ame_oloan_owner_fetch($db, $id);
if (!$is_new && !$owner) {
    die('❌ ' . ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
function osv($owner, $key, $default = '')
{
    return htmlspecialchars((string)($owner[$key] ?? $default), ENT_QUOTES, 'UTF-8');
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
.oln-field { margin-bottom:4px; }
.oln-field label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color: var(--brand-navy); }
.oln-field input, .oln-field select, .oln-field textarea {
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px;
}
.oln-field textarea { min-height:70px; resize:vertical; }
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
        <h3><?php echo $is_new ? '🏪 ' . ame_l('নতুন দোকান মালিক', 'New shop owner') : '🏪 ' . ame_l('মালিক এডিট', 'Edit owner') . ': ' . osv($owner, 'owner_name'); ?></h3>
        <a href="list.php" class="oln-back-link">⬅️ <?php echo ame_l('তালিকায় ফিরে যান', 'Back to list'); ?></a>
    </div>
    <?php if ($flash_err !== ''): ?>
        <div class="oln-flash err">⚠️ <?php echo esc_html($flash_err); ?></div>
    <?php endif; ?>
    <div class="card">
        <form action="process_save.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="oln-grid-2">
                <div class="oln-field">
                    <label><?php echo ame_l('মালিকের নাম *', "Owner's name *"); ?></label>
                    <input type="text" name="owner_name" value="<?php echo osv($owner, 'owner_name'); ?>" required maxlength="150">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('পিতা/অভিভাবকের নাম', "Parent/guardian name"); ?></label>
                    <input type="text" name="parent_name" value="<?php echo osv($owner, 'parent_name'); ?>" maxlength="150">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('জাতীয় পরিচয়পত্র নং', 'NID number'); ?></label>
                    <input type="text" name="nid_no" value="<?php echo osv($owner, 'nid_no'); ?>" maxlength="60">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('মোবাইল *', 'Mobile *'); ?></label>
                    <input type="text" name="mobile" value="<?php echo osv($owner, 'mobile'); ?>" required maxlength="30">
                </div>
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('ঠিকানা', 'Address'); ?></label>
                    <textarea name="address"><?php echo osv($owner, 'address'); ?></textarea>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('দোকানের নাম *', 'Shop name *'); ?></label>
                    <input type="text" name="shop_name" value="<?php echo osv($owner, 'shop_name'); ?>" required maxlength="150">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('দোকানের ধরন', 'Shop type'); ?></label>
                    <input type="text" name="shop_type" value="<?php echo osv($owner, 'shop_type'); ?>" maxlength="80">
                </div>
                <div class="oln-field" style="grid-column:1/-1;">
                    <label><?php echo ame_l('ব্যবসার ঠিকানা', 'Business address'); ?></label>
                    <textarea name="business_address"><?php echo osv($owner, 'business_address'); ?></textarea>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ট্রেড লাইসেন্স নং', 'Trade license no.'); ?></label>
                    <input type="text" name="trade_license_no" value="<?php echo osv($owner, 'trade_license_no'); ?>" maxlength="80">
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('ছবি', 'Photo'); ?></label>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($owner['photo'])): ?>
                        <div style="margin-top:6px;"><img src="/<?php echo esc_html(ltrim($owner['photo'], '/')); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--brand-border);"></div>
                    <?php endif; ?>
                </div>
                <div class="oln-field">
                    <label><?php echo ame_l('অবস্থা', 'Status'); ?></label>
                    <select name="status">
                        <option value="1" <?php echo (int)($owner['status'] ?? 1) === 1 ? 'selected' : ''; ?>><?php echo ame_l('সক্রিয়', 'Active'); ?></option>
                        <option value="0" <?php echo (int)($owner['status'] ?? 1) === 0 ? 'selected' : ''; ?>><?php echo ame_l('নিষ্ক্রিয়', 'Inactive'); ?></option>
                    </select>
                </div>
            </div>
            <div class="oln-footer-actions">
                <button type="submit" class="btn-success" style="padding:12px 28px; border-radius:10px;">💾 <?php echo ame_l('সংরক্ষণ করুন', 'Save'); ?></button>
                <a href="list.php" class="oln-cancel-btn"><?php echo ame_l('বাতিল', 'Cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
