<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$id = (int)($_GET['id'] ?? 0);
$app = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT a.*, p.name provider_name FROM bloan_applications a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$providers = $db->query("SELECT id, name, provider_type FROM bloan_providers WHERE status='active' ORDER BY name");
$list = $db->query('SELECT a.*, p.name provider_name FROM bloan_applications a JOIN bloan_providers p ON p.id=a.provider_id ORDER BY a.id DESC LIMIT 80');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ঋণ আবেদন', 'Loan application'); ?></h1>
    <p class="en"><?php echo ame_l('অনুমোদন শুধু অ্যাডমিন দেবেন। এটা দোকানের নিজের ঋণের আবেদন।', 'Only an admin can approve. This is an application for the shop’s own loan.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <?php if ($app): ?>
        <div class="bl-cards">
            <div><small>Application ID</small><b><?php echo ame_bloan_h((string)$app['app_no']); ?></b></div>
            <div><small>Provider</small><b><?php echo ame_bloan_h((string)$app['provider_name']); ?></b></div>
            <div><small>Amount</small><b><?php echo ame_bloan_taka((float)$app['amount']); ?></b></div>
            <div><small>Status</small><b class="badge st-<?php echo ame_bloan_h((string)$app['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$app['status'])); ?></b></div>
        </div>
        <p><?php echo ame_bloan_h((string)$app['purpose']); ?> · <?php echo (int)$app['tenure_months']; ?> <?php echo ame_l('মাস', 'months'); ?> · <?php echo ame_bloan_h((string)$app['interest_rate']); ?>% <?php echo ame_bloan_h(ame_bloan_label((string)$app['interest_type'])); ?> · <?php echo ame_bloan_h(ame_bloan_label((string)$app['frequency'])); ?></p>
        <p><?php echo ame_l('জামানত', 'Guarantee'); ?>: <?php echo ame_bloan_h((string)$app['guarantee_note']); ?><br><?php echo ame_l('ডকুমেন্ট', 'Documents'); ?>: <?php echo ame_bloan_h((string)$app['documents_note']); ?></p>
        <?php if (ame_bloan_is_admin() && $app['status'] !== 'disbursed'): ?>
        <form class="bl-inline" method="post" action="actions.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>">
            <label><?php echo ame_l('অ্যাডমিন স্ট্যাটাস', 'Admin status'); ?>
                <select name="status">
                    <?php foreach (['draft','applied','review','approved','rejected','closed'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $app['status']===$st?'selected':''; ?>><?php echo ame_bloan_h(ame_bloan_label($st)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit"><?php echo ame_l('স্ট্যাটাস সেভ', 'Save status'); ?></button>
        </form>
        <?php endif; ?>
        <?php if (ame_bloan_can_receive((string)$app['status'])): ?>
        <form class="grid" method="post" action="actions.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="disburse">
            <input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>">
            <label><?php echo ame_l('প্রাপ্ত টাকা', 'Amount received'); ?><input type="number" step="0.01" name="disbursed_amount" value="<?php echo ame_bloan_h((string)$app['amount']); ?>" required></label>
            <label><?php echo ame_l('তারিখ', 'Date'); ?><input type="date" name="disbursed_on" value="<?php echo date('Y-m-d'); ?>" required></label>
            <label class="wide"><?php echo ame_l('ক্যাশ/ব্যাংক', 'Cash or bank'); ?><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required><?php echo ame_bloan_account_options($db); ?></select></label>
            <p class="wide en"><?php echo ame_l('টাকা একই লেনদেনে ক্যাশ/ব্যাংকে যোগ হবে। প্রসেসিং ফি ও অন্যান্য চার্জ সেই অ্যাকাউন্ট থেকে কাটা হবে।', 'The amount is added to cash or bank in the same transaction. Processing fee and other charges are deducted from that account.'); ?></p>
            <div class="wide"><button type="submit"><?php echo ame_l('টাকা গ্রহণ', 'Receive loan'); ?></button></div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    <h2><?php echo ame_l('নতুন আবেদন', 'New application'); ?></h2>
    <p class="en"><a href="facility.php"><?php echo ame_l('মাসিক সুবিধা', 'Monthly facility'); ?></a> — <?php echo ame_l('একটি প্রতিষ্ঠানকে প্রতি মাসে একই টাকা। মেয়াদ নেই।', 'One institution, the same amount every month. No term.'); ?></p>
    <form class="grid" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="application">
        <label><?php echo ame_l('ঋণদাতা', 'Provider'); ?><select name="provider_id" required><option value=""><?php echo ame_l('নির্বাচন', 'Select'); ?></option><?php if ($providers) while ($p = $providers->fetch_assoc()): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo ame_bloan_h((string)$p['name']); ?></option><?php endwhile; ?></select></label>
        <label><?php echo ame_l('আবেদনের তারিখ', 'Application date'); ?><input type="date" name="apply_date" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label class="wide"><?php echo ame_l('উদ্দেশ্য', 'Purpose'); ?><input name="purpose" required></label>
        <label><?php echo ame_l('টাকা', 'Amount'); ?><input type="number" step="0.01" name="amount" required></label>
        <label><?php echo ame_l('মেয়াদ (মাস)', 'Tenure (months)'); ?><input type="number" name="tenure_months" value="12" min="1" required></label>
        <label><?php echo ame_l('সুদের হার %', 'Interest %'); ?><input type="number" step="0.01" name="interest_rate" value="0"></label>
        <label><?php echo ame_l('সুদের ধরন', 'Interest type'); ?><select name="interest_type"><option value="flat"><?php echo ame_l('সমান সুদ', 'Flat'); ?></option><option value="reducing"><?php echo ame_l('কমে আসা সুদ', 'Reducing'); ?></option></select></label>
        <label><?php echo ame_l('কিস্তির ধরন', 'Frequency'); ?><select name="frequency"><option value="monthly"><?php echo ame_l('মাসিক', 'Monthly'); ?></option><option value="weekly"><?php echo ame_l('সাপ্তাহিক', 'Weekly'); ?></option></select></label>
        <label><?php echo ame_l('প্রসেসিং ফি', 'Processing fee'); ?><input type="number" step="0.01" name="processing_fee" value="0"></label>
        <label><?php echo ame_l('অন্যান্য চার্জ', 'Other charge'); ?><input type="number" step="0.01" name="other_charge" value="0"></label>
        <label><?php echo ame_l('অবস্থা', 'Status'); ?><select name="status"><option value="draft"><?php echo ame_l('খসড়া', 'Draft'); ?></option><option value="applied" selected><?php echo ame_l('আবেদন', 'Applied'); ?></option><option value="review"><?php echo ame_l('পর্যালোচনা', 'Under Review'); ?></option></select></label>
        <label class="wide"><?php echo ame_l('জামানত', 'Guarantee'); ?><textarea name="guarantee_note"></textarea></label>
        <label class="wide"><?php echo ame_l('প্রয়োজনীয় কাগজ', 'Required documents'); ?><textarea name="documents_note"></textarea></label>
        <label class="wide"><?php echo ame_l('নোট', 'Notes'); ?><textarea name="notes"></textarea></label>
        <div class="wide"><button type="submit"><?php echo ame_l('আবেদন সেভ', 'Save application'); ?></button></div>
    </form>
    <table>
        <tr><th><?php echo ame_l('আইডি', 'ID'); ?></th><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ঋণদাতা', 'Provider'); ?></th><th><?php echo ame_l('টাকা', 'Amount'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr>
        <?php if ($list) while ($r = $list->fetch_assoc()): ?>
        <tr>
            <td><a href="application.php?id=<?php echo (int)$r['id']; ?>"><?php echo ame_bloan_h((string)$r['app_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$r['apply_date']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['provider_name']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['amount']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$r['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
