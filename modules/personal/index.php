<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

// ব্যক্তিগত অ্যাকাউন্টগুলোর ব্যালেন্স নেওয়া
$personal_accs = $db->query("SELECT * FROM accounts WHERE context = 'personal' AND status='active'");
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">🏠 পারিবারিক ও ব্যক্তিগত হিসাব</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:15px;">
        <a href="add_entry.php" class="btn-success" style="text-decoration:none; text-align:center; padding:15px; background:var(--dark-blue);">➕ নতুন এন্ট্রি</a>
        <a href="categories.php" class="btn-success" style="text-decoration:none; text-align:center; padding:15px;">📁 ক্যাটাগরি</a>
    </div>

    <div class="card" style="background: white; border-radius:15px; padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background: var(--dark-green); color: white;">
                    <th style="padding:12px;">অ্যাকাউন্ট</th>
                    <th style="padding:12px; text-align:right;">ব্যালেন্স</th>
                </tr>
            </thead>
            <tbody>
                <?php while($acc = $personal_accs->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold;"><?php echo esc_html($acc['account_name']); ?></td>
                    <td style="padding:12px; text-align:right; color:var(--dark-blue); font-weight:bold;">
                        <?php echo number_format((float)($acc['balance'] ?? 0), 2); ?> ৳
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($personal_accs->num_rows == 0): ?>
                    <tr><td colspan="2" style="text-align:center; padding:20px; color:#999;">কোনো ব্যক্তিগত অ্যাকাউন্ট নেই।</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>