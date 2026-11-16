<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$application_id = (int)($_GET['application_id'] ?? 0);

$stmt = $db->prepare("
    SELECT la.*, c.name AS customer_name, c.phone AS customer_phone,
           lp.name AS product_name, lp.interest_rate, lp.interest_type, lp.processing_fee_percent
    FROM loan_applications la
    JOIN customers c ON c.id = la.customer_id
    JOIN loan_products lp ON lp.id = la.product_id
    WHERE la.id = ? AND la.status = 'approved'
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    echo "<div class='card' style='margin:15px;'>এই আবেদনটি ছাড়ের জন্য প্রস্তুত নয় (হয়তো অনুমোদিত না, বা ইতিমধ্যে ছাড় হয়ে গেছে)।</div>";
    include '../../partials/footer.php';
    exit();
}

$accs = ame_active_accounts($db);
?>
<div style="padding: 15px;">
    <div class="card" style="max-width:520px; margin:0 auto;">
        <h3>💸 লোন ছাড় (Disbursement)</h3>
        <p><b><?php echo htmlspecialchars($app['customer_name']); ?></b> — <?php echo htmlspecialchars($app['customer_phone'] ?? ''); ?></p>
        <p>প্রোডাক্ট: <?php echo htmlspecialchars($app['product_name']); ?> (<?php echo $app['interest_rate']; ?>% <?php echo $app['interest_type']==='flat'?'ফ্ল্যাট':'রিডিউসিং'; ?>)</p>

        <?php if (isset($_GET['error'])): ?>
        <div class="brand-badge danger" style="display:flex; margin-bottom:12px;">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <form action="disburse_process.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>">

            <label>ছাড়কৃত পরিমাণ (৳) *</label>
            <input type="number" step="0.01" name="disburse_amount" class="input-style" required value="<?php echo htmlspecialchars((string)$app['requested_amount']); ?>" style="margin-bottom:14px;">

            <label>মেয়াদ (মাস) *</label>
            <input type="number" name="term_months" class="input-style" required value="<?php echo (int)$app['requested_term_months']; ?>" style="margin-bottom:14px;">

            <label>ছাড়ের তারিখ *</label>
            <input type="date" name="disburse_date" class="input-style" required value="<?php echo date('Y-m-d'); ?>" style="margin-bottom:14px;">

            <label>কোন অ্যাকাউন্ট থেকে টাকা যাবে? *</label>
            <select name="account_id" class="ame-account-select input-style" required style="margin-bottom:14px;" data-ame-account-select="1" data-ame-direction="pay">
                <?php echo ame_render_account_options($accs); ?>
            </select>

            <?php if ((float)$app['processing_fee_percent'] > 0): ?>
            <div class="brand-badge info" style="display:flex; margin-bottom:14px;">
                ℹ️ এই প্রোডাক্টে <?php echo $app['processing_fee_percent']; ?>% প্রসেসিং ফি প্রযোজ্য — অনুমোদিত পরিমাণের উপর ফি বাদ দিয়ে বাকি টাকা নগদ হিসেবে হাতে যাবে (গ্রাহক তবুও পুরো পরিমাণই ফেরত দেবেন)।
            </div>
            <?php endif; ?>

            <div class="brand-badge gold" style="display:flex; margin-bottom:14px; align-items:flex-start;">
                ⚠️ ছাড় করার পর টাকা সরাসরি নির্বাচিত অ্যাকাউন্ট থেকে কাটা হবে এবং একটা রিপেমেন্ট শিডিউল তৈরি হবে — এই কাজ বাতিল করা যাবে না।
            </div>

            <button type="submit" class="btn-success" style="width:100%;">💸 নিশ্চিত করে ছাড় করুন</button>
        </form>
        <a href="applications_list.php" style="display:block; text-align:center; margin-top:14px;">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
