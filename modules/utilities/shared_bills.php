<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

$partners = $db->query("SELECT * FROM bill_partners");
$current_month = date('F Y');
?>
<div style="padding: 15px;">
    <h3 style="color: var(--primary);">🔌 ইউটিলিটি বিল কালেকশন (<?php echo $current_month; ?>)</h3>
    
    <div class="card" style="padding:15px; border-radius:15px; background:white;">
        <form action="process_bill.php" method="POST">
            <?php echo csrf_field(); ?>
            <label>বিলার/পার্টনার নির্বাচন</label>
            <select name="partner_id" class="input-style" required style="width:100%; padding:12px; margin:10px 0;">
                <option value="">নির্বাচন করুন</option>
                <?php while ($p = $partners->fetch_assoc()): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo esc_html($p['name']); ?> (শেয়ার: <?php echo number_format((float)($p['monthly_share'] ?? 0), 0); ?> ৳)</option>
                <?php endwhile; ?>
            </select>
            
            <label>টাকার পরিমাণ</label>
            <input type="number" step="0.01" name="amount" class="input-style" required style="width:100%; padding:12px; margin:10px 0;">
            
            <input type="hidden" name="month_year" value="<?php echo $current_month; ?>">
            <button type="submit" class="btn-success" style="width:100%; padding:15px; font-weight:bold;">✔️ টাকা গ্রহণ করুন</button>
        </form>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>