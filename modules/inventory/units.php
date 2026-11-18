<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
?>
<div style="padding:15px;">
    <h3 style="color:var(--primary);">📏 পণ্যের ইউনিট ব্যবস্থাপনা</h3>
    <div class="card" style="padding:15px; margin-bottom:15px; border-radius:15px;">
        <form action="process_unit.php" method="POST" style="display:flex; gap:8px;">
            <input type="text" name="unit_name" placeholder="নতুন ইউনিটের নাম (যেমন: পিস, কেজি, লিটার)" required class="input-style" style="flex:1; padding:10px;">
            <button type="submit" class="btn-success" style="padding:10px 16px;">যোগ করুন</button>
        </form>
    </div>
    <div class="card" style="padding:0; overflow-x:auto; border-radius:15px;">
        <table style="width:100%; border-collapse:collapse;">
            <thead><tr style="background:var(--primary); color:#fff;"><th style="padding:10px; text-align:left;">ইউনিটের নাম</th></tr></thead>
            <tbody>
            <?php $units = $db->query("SELECT * FROM product_units ORDER BY unit_name ASC");
            if ($units->num_rows > 0): while($u = $units->fetch_assoc()): ?>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;"><?php echo htmlspecialchars($u['unit_name']); ?></td></tr>
            <?php endwhile; else: ?>
            <tr><td style="padding:15px; text-align:center; color:#888;">এখনো কোনো ইউনিট যোগ হয়নি।</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="list.php" style="display:block; text-align:center; margin-top:15px; color:var(--primary); text-decoration:none;">ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
