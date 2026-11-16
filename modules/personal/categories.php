<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

$cats = $db->query("SELECT * FROM personal_categories ORDER BY type DESC, name ASC");
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">📁 ব্যক্তিগত হিসাবের ক্যাটাগরি</h3>
    
    <div class="card" style="margin-bottom: 20px; border-radius:15px;">
        <form action="process_category.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="text" name="name" class="input-style" placeholder="ক্যাটাগরি নাম (উদা: যাতায়াত খরচ)" required style="width:100%; padding:10px; margin-bottom:10px;">
            <select name="type" class="input-style" required style="width:100%; padding:10px; margin-bottom:10px;">
                <option value="expense">খরচ (Expense)</option>
                <option value="income">আয় (Income)</option>
            </select>
            <button type="submit" class="btn-success" style="width:100%; padding:12px;">ক্যাটাগরি যোগ করুন</button>
        </form>
    </div>

    <div class="card" style="padding:0; background:white; border-radius:15px; overflow:hidden;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">নাম</th>
                    <th style="padding:12px;">ধরণ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($c = $cats->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold;"><?php echo esc_html($c['name']); ?></td>
                    <td style="padding:12px;">
                        <span style="color:<?php echo ($c['type']=='income')?'var(--dark-green)':'var(--red-alert)'; ?>; font-weight:bold;">
                            <?php echo esc_html(strtoupper((string)$c['type'])); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="index.php" style="display:block; text-align:center; margin-top:20px; color:var(--dark-blue); font-weight:bold; text-decoration:none;">← ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>