<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$approved = ame_bloan_receivable_applications($db);
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('টাকা গ্রহণ', 'Disbursement'); ?></h1>
    <p class="en"><?php echo ame_l('সেভ করা আবেদনের টাকা নির্বাচিত ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্টে একবার জমা হয় এবং ঋণের বাকিতে যোগ হয়। আবেদন স্ট্যাটাস আলাদা অনুমোদন চায় না।', 'A saved application is credited once to the selected cash, bank, or mobile account and added to the loan balance. The আবেদন status does not need a separate approval.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <?php if (!$approved): ?>
        <p class="empty"><?php echo ame_l('কোনো আবেদন নেই। আবেদন সেভ করলে এখানে দেখা যাবে।', 'There is no application. Save one and it will show here.'); ?></p>
    <?php else: ?>
    <form class="grid" method="post" action="actions.php" id="blReceiveForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="disburse">
        <label class="wide"><?php echo ame_l('আবেদন', 'Application'); ?>
            <select name="application_id" id="blReceiveApp" required>
                <option value=""><?php echo ame_l('নির্বাচন', 'Select'); ?></option>
                <?php foreach ($approved as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>" data-amount="<?php echo ame_bloan_h((string)$r['amount']); ?>"><?php echo ame_bloan_h((string)$r['app_no'] . ' · ' . $r['provider_name'] . ' · ' . ame_bloan_taka((float)$r['amount']) . ' · ' . ame_bloan_label((string)$r['status'])); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?php echo ame_l('প্রাপ্ত টাকা', 'Amount received'); ?><input type="number" step="0.01" min="0.01" name="disbursed_amount" id="blReceiveAmt" required></label>
        <label><?php echo ame_l('তারিখ', 'Date'); ?><input type="date" name="disbursed_on" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label class="wide"><?php echo ame_l('ক্যাশ/ব্যাংক/মোবাইল', 'Cash, bank, or mobile'); ?><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required><?php echo ame_bloan_account_options($db); ?></select></label>
        <div class="wide"><button type="submit" id="blReceiveBtn"><?php echo ame_l('টাকা গ্রহণ', 'Receive loan'); ?></button></div>
    </form>
    <?php endif; ?>
    <table>
        <tr><th><?php echo ame_l('আবেদন', 'Application'); ?></th><th><?php echo ame_l('ঋণদাতা', 'Provider'); ?></th><th><?php echo ame_l('টাকা', 'Amount'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th><th></th></tr>
        <?php if (!$approved): ?>
        <tr><td colspan="5"><?php echo ame_l('কোনো আবেদন নেই।', 'No application.'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($approved as $r): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['app_no']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['provider_name']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['amount']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$r['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></span></td>
            <td><a class="btn" href="application.php?id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('আবেদন', 'Application'); ?></a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<script>
(function () {
    var sel = document.getElementById('blReceiveApp');
    var amt = document.getElementById('blReceiveAmt');
    if (!sel || !amt) return;
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        var value = opt ? opt.getAttribute('data-amount') : '';
        if (value) amt.value = value;
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
