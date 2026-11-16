<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

$filter_group = (int)($_GET['group_id'] ?? 0);
$sql = "SELECT c.*, g.group_name FROM contacts c JOIN contact_groups g ON c.group_id = g.id";
if ($filter_group > 0) {
    $sql .= " WHERE c.group_id = " . $filter_group;
}
$sql .= " ORDER BY c.name ASC";
$res = $db->query($sql);
?>
<div style="padding: 15px;">
    <h3 style="color: var(--primary);">👥 কন্টাক্ট ডিরেক্টরি</h3>
    
    <div class="card" style="margin-bottom:15px;">
        <form method="GET" style="display:flex; gap:5px;">
            <select name="group_id" class="input-style" style="flex:1; padding:8px;">
                <option value="">সব গ্রুপ</option>
                <?php 
                $gs = $db->query("SELECT * FROM contact_groups");
                while($g = $gs->fetch_assoc()) {
                    $sel = ($filter_group == $g['id']) ? 'selected' : '';
                    echo "<option value='" . (int)$g['id'] . "' $sel>" . esc_html($g['group_name']) . "</option>";
                }
                ?>
            </select>
            <button type="submit" class="btn-success" style="padding:8px 15px;">ফিল্টার</button>
        </form>
    </div>

    <div class="card" style="padding:0; overflow-x:auto; background:white;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background: var(--primary); color: white;">
                    <th style="padding:12px;">নাম ও গ্রুপ</th>
                    <th style="padding:12px;">মোবাইল ও ঠিকানা</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $res->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;">
                        <b><?php echo htmlspecialchars($row['name'] ?? ''); ?></b><br>
                        <small style="color:var(--secondary); font-weight:bold;">📁 <?php echo esc_html($row['group_name']); ?></small>
                    </td>
                    <td style="padding:10px;">
                        <span style="color:var(--primary); font-weight:bold;">📞 <?php echo htmlspecialchars($row['phone'] ?? ''); ?></span><br>
                        <small style="color:#666;">📍 <?php echo htmlspecialchars($row['address'] ?? ''); ?></small>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>