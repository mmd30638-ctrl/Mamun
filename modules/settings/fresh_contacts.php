<?php
require_once '../../includes/config.php';
require_once '../../includes/fresh_contacts.php';
admin_only();

$data = ame_fresh_contacts_load();
$sourceCustomers = count($data['customers'] ?? []);
$sourceSuppliers = count($data['suppliers'] ?? []);
$phrase = 'ক্রেতা সাপ্লায়ার বদলান';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $typed = trim((string) ($_POST['confirm_text'] ?? ''));
    if ($typed !== $phrase) {
        $error = ame_l('নিশ্চিত করতে ঠিক এই কথা লিখুন।', 'Type the confirmation words exactly.');
    } elseif ($sourceCustomers < 1 || $sourceSuppliers < 1) {
        $error = ame_l('সোর্স ফাইল নেই।', 'The source file is missing.');
    } else {
        $result = ame_fresh_contacts_apply($db);
        if (empty($result['ok'])) {
            $error = (string) ($result['message'] ?? '');
        }
    }
}

include '../../partials/header.php';
?>
<div style="max-width:640px;margin:16px auto;padding:16px;box-sizing:border-box;color:#111;">
    <h1 style="font-size:22px;margin:0 0 8px;"><?php echo ame_l('ক্রেতা ও সাপ্লায়ার নতুন করে বসান', 'Replace customers and suppliers'); ?></h1>
    <p><?php echo ame_l('সোর্স: পুরনো সাইট, ২৫ সেপ্টেম্বর ২০২৬, সন্ধ্যা ৬:৩৯। বিক্রয়, ক্রয়, স্টক ও হিসাব মুছবে না।', 'Source: old site, 25 September 2026, 6:39 PM. Sales, purchases, stock, and accounts stay.'); ?></p>
    <ul>
        <li><?php echo ame_l('ক্রেতা', 'Customers'); ?>: <b><?php echo number_format($sourceCustomers); ?></b></li>
        <li><?php echo ame_l('সাপ্লায়ার', 'Suppliers'); ?>: <b><?php echo number_format($sourceSuppliers); ?></b></li>
    </ul>
    <?php if ($error !== ''): ?>
        <p style="background:#ffebee;border:1px solid #c62828;padding:12px;border-radius:8px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if (!empty($result['ok'])): $s = $result['stats']; ?>
        <div style="background:#e8f5e9;border:1px solid #2e7d32;padding:12px;border-radius:8px;">
            <p><?php echo ame_l('হয়ে গেছে।', 'Finished.'); ?></p>
            <p><?php echo ame_l('ক্রেতা আপডেট', 'Customers updated'); ?>: <?php echo (int) $s['customers_updated']; ?>,
                <?php echo ame_l('নতুন', 'added'); ?>: <?php echo (int) $s['customers_added']; ?>,
                <?php echo ame_l('সরানো', 'removed'); ?>: <?php echo (int) $s['customers_removed']; ?></p>
            <p><?php echo ame_l('এখন ক্রেতা', 'Customers now'); ?>: <?php echo (int) $s['customers_now']; ?>,
                <?php echo ame_l('বকেয়া', 'due'); ?>: <?php echo number_format((float) $s['customer_due_now'], 2); ?>
                (<?php echo ame_l('সোর্স', 'source'); ?> <?php echo number_format((float) $s['source_customer_due'], 2); ?>)</p>
            <p><?php echo ame_l('সাপ্লায়ার আপডেট', 'Suppliers updated'); ?>: <?php echo (int) $s['suppliers_updated']; ?>,
                <?php echo ame_l('নতুন', 'added'); ?>: <?php echo (int) $s['suppliers_added']; ?>,
                <?php echo ame_l('সরানো', 'removed'); ?>: <?php echo (int) $s['suppliers_removed']; ?></p>
            <p><?php echo ame_l('এখন সাপ্লায়ার', 'Suppliers now'); ?>: <?php echo (int) $s['suppliers_now']; ?>,
                <?php echo ame_l('বকেয়া', 'due'); ?>: <?php echo number_format((float) $s['supplier_due_now'], 2); ?>
                (<?php echo ame_l('সোর্স', 'source'); ?> <?php echo number_format((float) $s['source_supplier_due'], 2); ?>)</p>
            <?php if ((int) $s['customers_kept_for_sales'] > 0 || (int) $s['suppliers_kept_for_purchase'] > 0): ?>
                <p><?php echo ame_l('বিক্রয় বা ক্রয়ের সাথে বাঁধা কিছু নাম রাখা হয়েছে, যাতে পুরনো মেমো না ভাঙে।', 'Some names linked to a sale or purchase were kept so old memos still open.'); ?>
                    <?php echo (int) $s['customers_kept_for_sales']; ?> / <?php echo (int) $s['suppliers_kept_for_purchase']; ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="post" style="margin-top:16px;">
        <?php echo csrf_field(); ?>
        <label style="display:block;font-weight:800;margin-bottom:6px;"><?php echo ame_l('লিখুন', 'Type'); ?>: <?php echo htmlspecialchars($phrase, ENT_QUOTES, 'UTF-8'); ?></label>
        <input name="confirm_text" required style="width:100%;box-sizing:border-box;min-height:44px;padding:10px;font-size:16px;border:1px solid #9aa7b5;border-radius:8px;">
        <button type="submit" style="width:100%;margin-top:12px;min-height:48px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:800;font-size:16px;"><?php echo ame_l('বদলান', 'Replace'); ?></button>
    </form>
    <p><a href="tools.php"><?php echo ame_l('টুলসে ফিরে যান', 'Back to tools'); ?></a></p>
</div>
<?php include '../../partials/footer.php'; ?>
