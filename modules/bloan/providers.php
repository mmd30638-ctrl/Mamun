<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query('SELECT * FROM bloan_providers ORDER BY name');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ঋণদাতা', 'Loan providers'); ?></h1>
    <p class="en"><?php echo ame_l('ব্যাংক, এনজিও, আর্থিক প্রতিষ্ঠান বা অন্য অনুমোদিত ঋণদাতা।', 'Bank, NGO, financial institution, or another approved lender.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <form class="grid" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="provider">
        <label><?php echo ame_l('ধরন', 'Type'); ?><select name="provider_type"><option value="bank"><?php echo ame_l('ব্যাংক', 'Bank'); ?></option><option value="ngo"><?php echo ame_l('এনজিও', 'NGO'); ?></option><option value="financial"><?php echo ame_l('আর্থিক প্রতিষ্ঠান', 'Financial institution'); ?></option><option value="other"><?php echo ame_l('অন্যান্য', 'Other'); ?></option></select></label>
        <label><?php echo ame_l('প্রতিষ্ঠানের নাম', 'Organisation'); ?><input name="name" required></label>
        <label><?php echo ame_l('শাখা', 'Branch'); ?><input name="branch"></label>
        <label><?php echo ame_l('যোগাযোগ ব্যক্তি', 'Officer'); ?><input name="officer_name"></label>
        <label><?php echo ame_l('মোবাইল', 'Mobile'); ?><input name="phone"></label>
        <label><?php echo ame_l('অ্যাকাউন্ট আইডি', 'Account ID'); ?><input name="account_ref"></label>
        <label class="wide"><?php echo ame_l('ঠিকানা', 'Address'); ?><input name="address"></label>
        <label class="wide"><?php echo ame_l('নোট', 'Notes'); ?><textarea name="notes"></textarea></label>
        <div class="wide"><button type="submit"><?php echo ame_l('সংরক্ষণ', 'Save'); ?></button></div>
    </form>
    <table>
        <tr><th><?php echo ame_l('নাম', 'Name'); ?></th><th><?php echo ame_l('ধরন', 'Type'); ?></th><th><?php echo ame_l('শাখা', 'Branch'); ?></th><th><?php echo ame_l('ব্যক্তি', 'Officer'); ?></th><th><?php echo ame_l('মোবাইল', 'Mobile'); ?></th><th><?php echo ame_l('আইডি', 'ID'); ?></th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['name']); ?></td>
            <td><?php echo ame_bloan_h(ame_bloan_label((string)$r['provider_type'])); ?></td>
            <td><?php echo ame_bloan_h((string)$r['branch']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['officer_name']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['phone']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['account_ref']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
