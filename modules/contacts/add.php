<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
$groups = $db->query("SELECT * FROM contact_groups ORDER BY group_name ASC");
?>
<div class="card" style="margin: 15px; border-radius:15px;">
    <h3 style="color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 10px;">👤 নতুন কন্টাক্ট যুক্ত করুন</h3>
    <form action="process_contact.php" method="POST">
        <?php echo csrf_field(); ?>
        <label>গ্রুপ সিলেক্ট করুন</label>
        <select name="group_id" class="input-style" required style="width:100%; padding:12px; margin:10px 0;">
            <?php while ($g = $groups->fetch_assoc()): ?>
            <option value="<?php echo (int)$g['id']; ?>"><?php echo esc_html($g['group_name']); ?></option>
            <?php endwhile; ?>
        </select>

        <label>মানুষের নাম</label>
        <input type="text" name="name" class="input-style" placeholder="নাম লিখুন" required style="width:100%; padding:12px; margin:10px 0;">

        <label>মোবাইল নম্বর</label>
        <input type="text" name="phone" class="input-style" placeholder="০১৭XXXXXXXX" required style="width:100%; padding:12px; margin:10px 0;">

        <label>বিস্তারিত ঠিকানা</label>
        <textarea name="address" class="input-style" placeholder="গ্রাম, থানা, জেলা..." style="width:100%; padding:12px; margin:10px 0;"></textarea>

        <button type="submit" class="btn-success" style="width: 100%; padding: 15px; font-size:16px;">💾 কন্টাক্ট সেভ করুন</button>
        <a href="list.php" style="display:block; text-align:center; margin-top:15px; color:var(--primary); text-decoration:none;">তালিকা দেখুন</a>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>