<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
admin_only();
?>
<div style="padding: 15px;">
    <h3 style="color: var(--primary);">📁 কন্টাক্ট গ্রুপ তৈরি করুন</h3>
    <div class="card" style="margin-bottom: 20px;">
        <form action="process_group.php" method="POST" style="display:flex; gap:10px;">
            <input type="text" name="group_name" class="input-style" placeholder="গ্রুপের নাম (উদা: সিলেট এরিয়া)" required style="flex:1; padding:12px;">
            <button type="submit" class="btn-success" style="padding:10px 20px;">তৈরি করুন</button>
        </form>
    </div>

    <div class="card" style="padding:0; background:white; overflow:hidden;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr style="background: var(--primary); color: white;">
                    <th style="padding:12px;">গ্রুপের নাম</th>
                    <th style="padding:12px; text-align:center;">সদস্য সংখ্যা</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $groups = $db->query("SELECT g.*, (SELECT COUNT(*) FROM contacts WHERE group_id = g.id) as total FROM contact_groups g");
                while($g = $groups->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold;"><?php echo esc_html($g['group_name']); ?></td>
                    <td style="padding:12px; text-align:center;"><?php echo $g['total']; ?> জন</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>