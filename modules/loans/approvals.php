<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$apps = $db->query("
    SELECT la.*, c.name AS customer_name, c.phone AS customer_phone, c.receivable_balance,
           lp.name AS product_name, lp.min_amount, lp.max_amount, lp.interest_rate
    FROM loan_applications la
    JOIN customers c ON c.id = la.customer_id
    JOIN loan_products lp ON lp.id = la.product_id
    WHERE la.status = 'pending'
    ORDER BY la.id ASC
");
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">✅ অনুমোদনের অপেক্ষায় থাকা লোন আবেদন</h3>

    <?php if (isset($_GET['msg'])): ?>
    <div class="brand-badge success" style="margin-bottom:12px; display:flex;">✅ <?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php if ($apps->num_rows === 0): ?>
        <div class="card" style="text-align:center; color:#888;">কোনো লম্বিত আবেদন নেই 🎉</div>
    <?php endif; ?>

    <?php while ($a = $apps->fetch_assoc()): ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h4 style="margin:0;"><?php echo htmlspecialchars($a['customer_name']); ?></h4>
                <small style="color:#888;"><?php echo htmlspecialchars($a['customer_phone'] ?? ''); ?> | বর্তমান বকেয়া: <?php echo number_format((float)$a['receivable_balance'], 2); ?> ৳</small>
            </div>
            <div style="text-align:right;">
                <b style="font-size:17px; color:var(--dark-blue);"><?php echo number_format((float)$a['requested_amount'], 2); ?> ৳</b><br>
                <small style="color:#888;"><?php echo (int)$a['requested_term_months']; ?> মাস, <?php echo htmlspecialchars($a['product_name']); ?> (<?php echo $a['interest_rate']; ?>%)</small>
            </div>
        </div>
        <?php if (!empty($a['purpose'])): ?><p style="margin:10px 0 0; font-size:13px;">📝 উদ্দেশ্য: <?php echo htmlspecialchars($a['purpose']); ?></p><?php endif; ?>
        <?php if (!empty($a['guarantor_name'])): ?><p style="margin:4px 0 0; font-size:13px;">👤 জামিনদার: <?php echo htmlspecialchars($a['guarantor_name']); ?> (<?php echo htmlspecialchars($a['guarantor_phone'] ?? ''); ?>)</p><?php endif; ?>

        <form action="approvals_process.php" method="POST" style="margin-top:14px; display:flex; gap:10px;" onsubmit="return confirm('নিশ্চিত?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="application_id" value="<?php echo (int)$a['id']; ?>">
            <input type="text" name="decision_note" class="input-style" placeholder="মন্তব্য (ঐচ্ছিক)" style="flex:1;">
            <button type="submit" name="action" value="approve" class="btn-success" style="white-space:nowrap;">✅ অনুমোদন</button>
            <button type="submit" name="action" value="reject" class="btn-danger" style="white-space:nowrap;">❌ প্রত্যাখ্যান</button>
        </form>
    </div>
    <?php endwhile; ?>
    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
