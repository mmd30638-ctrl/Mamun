<?php
require_once '../../includes/config.php';

function ensure_brand_status_column($db) {
    $check = $db->query("SHOW COLUMNS FROM brands LIKE 'status'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE brands ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1");
    }
}
ensure_brand_status_column($db);

include '../../partials/header.php';
?>
<style>
    .brd-wrap { padding: 15px; }
    .brd-card { background:#fff; border-radius:14px; max-width:520px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .brd-topbar { display:flex; height:6px; }
    .brd-topbar span { flex:1; }
    .brd-c1{background:#2E7D32;} .brd-c2{background:#000;} .brd-c3{background:#B71C1C;}
    .brd-header { text-align:center; padding:20px 15px 15px; }
    .brd-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .brd-header small { color:#666; font-size:12px; }
    .brd-form { display:flex; gap:8px; padding:0 20px 18px; }
    .brd-form input { flex:1; padding:11px; border:1px solid #ccc; border-radius:8px; font-size:14px; background:#fafafa; }
    .brd-form input:focus { outline:none; border-color:#2E7D32; background:#fff; }
    .brd-form button { padding:11px 18px; border:none; border-radius:8px; background:#000; color:#fff; font-weight:700; cursor:pointer; }
    .brd-list { padding:0 20px 20px; }
    .brd-item { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:#fafafa; border-radius:8px; margin-bottom:8px; border:1px solid #eee; }
    .brd-item.inactive { opacity:0.55; }
    .brd-item span.brd-name { font-weight:600; color:#000; font-size:14px; }
    .brd-right { display:flex; align-items:center; gap:8px; }
    .brd-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; }
    .brd-badge.active { background:#E8F5E9; color:#1B5E20; }
    .brd-badge.inactive { background:#FFEBEE; color:#B71C1C; }
    .brd-toggle-form { margin:0; }
    .brd-toggle-btn { border:none; background:none; font-size:16px; cursor:pointer; }
    .brd-empty { text-align:center; color:#999; font-size:13px; padding:20px; }
    .brd-back { display:block; text-align:center; margin-top:14px; color:#000; text-decoration:none; font-size:13px; }
</style>

<div class="brd-wrap">
    <div class="brd-card">
        <div class="brd-topbar"><span class="brd-c1"></span><span class="brd-c2"></span><span class="brd-c3"></span></div>
        <div class="brd-header">
            <h3>🏭 কোম্পানি/ব্র্যান্ড ব্যবস্থাপনা</h3>
            <small>নতুন ব্র্যান্ড যোগ করুন বা Active/Inactive করুন</small>
        </div>
        <form action="process_brand.php" method="POST" class="brd-form">
            <input type="text" name="name" placeholder="নতুন ব্র্যান্ডের নাম (যেমন: রহিম আফরোজ)" required>
            <button type="submit">যোগ করুন</button>
        </form>
        <div class="brd-list">
            <?php $brands = $db->query("SELECT * FROM brands ORDER BY name ASC");
            if ($brands->num_rows > 0): while($b = $brands->fetch_assoc()):
                $is_active = ((int)($b['status'] ?? 1) === 1);
            ?>
            <div class="brd-item <?php echo $is_active ? '' : 'inactive'; ?>">
                <span class="brd-name">🏭 <?php echo htmlspecialchars($b['name']); ?></span>
                <div class="brd-right">
                    <span class="brd-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
                    <form action="toggle_brand_status.php" method="POST" class="brd-toggle-form" onsubmit="return confirm('স্ট্যাটাস পরিবর্তন করতে চান?');">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <button type="submit" class="brd-toggle-btn" title="Active/Inactive টগল করুন">⏻</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="brd-empty">এখনো কোনো ব্র্যান্ড যোগ হয়নি।</div>
            <?php endif; ?>
        </div>
    </div>
    <a href="list.php" class="brd-back">⬅️ ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
