<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

$accounts = $db->query("SELECT * FROM accounts WHERE context = 'personal' AND status='active'");
$categories = $db->query("SELECT * FROM personal_categories ORDER BY name ASC");
?>
<div class="card" style="margin: 15px; border-radius:15px;">
    <h3 style="color: var(--dark-blue); border-bottom: 2px solid var(--dark-blue); padding-bottom:10px;">➕ ব্যক্তিগত আয়/ব্যয় এন্ট্রি</h3>
    <form action="process_entry.php" method="POST">
        <?php echo csrf_field(); ?>
        <label>ক্যাটাগরি</label>
        <select name="cat_id" class="input-style" required style="width:100%; padding:10px; margin:10px 0;">
            <?php while($c = $categories->fetch_assoc()): ?>
                <option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html($c['name']); ?> (<?php echo esc_html($c['type']); ?>)</option>
            <?php endwhile; ?>
        </select>

        <label>অ্যাকাউন্ট</label>
        <select name="account_id" class="input-style" required style="width:100%; padding:10px; margin:10px 0;">
            <?php while($a = $accounts->fetch_assoc()): ?>
                <option value="<?php echo (int)$a['id']; ?>"><?php echo esc_html($a['account_name']); ?> (ব্যালেন্স: <?php echo ame_balance_label((float)$a['balance']); ?>)</option>
            <?php endwhile; ?>
        </select>

        <label>টাকার পরিমাণ</label>
        <input type="number" step="0.01" name="amount" class="input-style" required style="width:100%; padding:12px; margin:10px 0;">

        <label>নোট/বিবরণ</label>
        <input type="text" name="note" class="input-style" placeholder="অল্প কিছু লিখুন" style="width:100%; padding:12px; margin:10px 0;">

        <button type="submit" class="btn-success" style="width: 100%; padding: 15px; font-size:16px;">এন্ট্রি সেভ করুন</button>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>