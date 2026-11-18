<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
ensure_loan_tables($db);
include '../../partials/header.php';

$products = $db->query("SELECT * FROM loan_products ORDER BY is_active DESC, id DESC");
?>
<div style="padding: 15px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="color: var(--dark-blue); margin:0;">🏦 লোন প্রোডাক্ট</h3>
        <a href="products_form.php" class="btn-success" style="text-decoration:none; font-size:13px;">+ নতুন প্রোডাক্ট</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="brand-badge success" style="margin-bottom:12px; display:flex;">✅ সেভ হয়েছে</div>
    <?php endif; ?>

    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr>
                    <th style="padding:12px;">নাম</th>
                    <th style="padding:12px;">সুদের হার</th>
                    <th style="padding:12px;">মেয়াদ</th>
                    <th style="padding:12px; text-align:right;">সীমা</th>
                    <th style="padding:12px; text-align:center;">স্ট্যাটাস</th>
                    <th style="padding:12px; text-align:center;">একশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products->num_rows === 0): ?>
                <tr><td colspan="6" style="padding:20px; text-align:center; color:#888;">কোনো লোন প্রোডাক্ট নেই — নতুন যোগ করুন</td></tr>
                <?php endif; ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                <tr>
                    <td style="padding:12px;"><?php echo htmlspecialchars($p['name']); ?></td>
                    <td style="padding:12px;"><?php echo number_format((float)$p['interest_rate'], 2); ?>% <?php echo $p['interest_type'] === 'flat' ? '(ফ্ল্যাট)' : '(রিডিউসিং)'; ?></td>
                    <td style="padding:12px;"><?php echo (int)$p['default_term_months']; ?> মাস</td>
                    <td style="padding:12px; text-align:right;"><?php echo number_format((float)$p['min_amount']); ?> – <?php echo number_format((float)$p['max_amount']); ?> ৳</td>
                    <td style="padding:12px; text-align:center;">
                        <?php if ($p['is_active']): ?>
                            <span class="brand-badge success">সক্রিয়</span>
                        <?php else: ?>
                            <span class="brand-badge danger">নিষ্ক্রিয়</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px; text-align:center;">
                        <a href="products_form.php?id=<?php echo (int)$p['id']; ?>">✏️ সম্পাদনা</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
