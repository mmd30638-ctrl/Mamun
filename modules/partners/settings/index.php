<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
include __DIR__ . '/../../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (function_exists('ame_ensure_category_archive') || is_file(__DIR__ . '/../../../includes/inventory_functions.php')) {
    require_once __DIR__ . '/../../../includes/inventory_functions.php';
    ame_ensure_category_archive($db);
}
$categories = $db->query("SELECT id, name, is_partnership_shared FROM categories WHERE archived=0 OR archived IS NULL ORDER BY name ASC");
$category_rows = $categories ? $categories->fetch_all(MYSQLI_ASSOC) : [];

$expense_categories = $db->query("SELECT id, name, is_partnership_shared FROM expense_categories ORDER BY name ASC");
$expense_rows = $expense_categories ? $expense_categories->fetch_all(MYSQLI_ASSOC) : [];

$partners = $db->query("SELECT name, share_percentage, status FROM partners ORDER BY status='active' DESC, name ASC");
$partner_rows = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];
$active_sum = 0;
foreach ($partner_rows as $p) {
    if ($p['status'] === 'active') { $active_sum += (float)$p['share_percentage']; }
}
?>
<style>
    .set-wrap { padding: 15px; }
    .set-wrap h2 { margin:0 0 16px; color:#0B1E4D; font-size:19px; }
    .set-card { background:#fff; border-radius:16px; padding:20px; margin-bottom:18px; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .set-card h3 { margin:0 0 6px; color:#0B1E4D; font-size:14.5px; }
    .set-card .desc { font-size:12px; color:#64748B; margin-bottom:14px; }
    .set-list-item { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #F1F5F9; font-size:13.5px; }
    .set-switch { position:relative; width:42px; height:24px; }
    .set-switch input { opacity:0; width:0; height:0; }
    .set-slider { position:absolute; cursor:pointer; inset:0; background:#CFE3F7; border-radius:24px; transition:.2s; }
    .set-slider:before { content:""; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
    .set-switch input:checked + .set-slider { background:#0091EA; }
    .set-switch input:checked + .set-slider:before { transform:translateX(18px); }

    .set-share-table { width:100%; border-collapse:collapse; font-size:13px; }
    .set-share-table td { padding:8px 0; border-bottom:1px solid #F1F5F9; }
    .set-share-sum { font-weight:800; }
    .set-share-ok { color:#0B8043; }
    .set-share-warn { color:#C62828; }

    .set-empty { color:#999; font-size:13px; text-align:center; padding:20px; }
</style>

<div class="set-wrap">
    <h2>⚙️ পার্টনারশিপ সেটিংস</h2>

    <div class="set-card">
        <h3>👥 পার্টনার শেয়ার শতাংশ যাচাই</h3>
        <div class="desc">সক্রিয় পার্টনারদের শতাংশের যোগফল সবসময় ১০০% হওয়া উচিত</div>
        <table class="set-share-table">
            <?php foreach ($partner_rows as $p): if ($p['status'] !== 'active') continue; ?>
                <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td style="text-align:right;"><?php echo number_format((float)$p['share_percentage'], 2); ?>%</td></tr>
            <?php endforeach; ?>
            <tr>
                <td class="set-share-sum">মোট (সক্রিয়)</td>
                <td class="set-share-sum <?php echo abs($active_sum - 100) < 0.01 ? 'set-share-ok' : 'set-share-warn'; ?>" style="text-align:right;">
                    <?php echo number_format($active_sum, 2); ?>% <?php echo abs($active_sum - 100) < 0.01 ? '✅' : '⚠️'; ?>
                </td>
            </tr>
        </table>
        <a href="/modules/partners/index.php" style="display:inline-block; margin-top:12px; color:#0091EA; text-decoration:none; font-size:12.5px; font-weight:700;">পার্টনার শতাংশ পরিবর্তন করুন →</a>
    </div>

    <div class="set-card">
        <h3>📦 কোন পণ্যের ক্যাটাগরির লাভ পার্টনারশিপে যাবে</h3>
        <div class="desc">শুধু নির্বাচিত ক্যাটাগরির বিক্রয়-লাভ পার্টনারশিপ হিসাবে যুক্ত হবে</div>
        <?php if (empty($category_rows)): ?>
            <div class="set-empty">কোনো ক্যাটাগরি পাওয়া যায়নি।</div>
        <?php else: foreach ($category_rows as $c): ?>
            <div class="set-list-item">
                <span><?php echo htmlspecialchars($c['name']); ?></span>
                <label class="set-switch">
                    <input type="checkbox" onchange="toggleShare('toggle_category', <?php echo (int)$c['id']; ?>, this.checked)" <?php echo $c['is_partnership_shared'] ? 'checked' : ''; ?>>
                    <span class="set-slider"></span>
                </label>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="set-card">
        <h3>💸 কোন খরচের ক্যাটাগরি পার্টনারশিপে কাটা হবে</h3>
        <div class="desc">শুধু নির্বাচিত ক্যাটাগরির খরচ নিট লাভ হিসাব করার সময় বাদ যাবে</div>
        <?php if (empty($expense_rows)): ?>
            <div class="set-empty">কোনো খরচ-ক্যাটাগরি পাওয়া যায়নি।</div>
        <?php else: foreach ($expense_rows as $ec): ?>
            <div class="set-list-item">
                <span><?php echo htmlspecialchars($ec['name']); ?></span>
                <label class="set-switch">
                    <input type="checkbox" onchange="toggleShare('toggle_expense', <?php echo (int)$ec['id']; ?>, this.checked)" <?php echo $ec['is_partnership_shared'] ? 'checked' : ''; ?>>
                    <span class="set-slider"></span>
                </label>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="set-card" style="background:#EAF4FF;">
        <h3>🔒 নিরাপত্তা নীতি</h3>
        <ul style="font-size:12.5px; color:#334155; margin:0; padding-left:18px; line-height:1.8;">
            <li>শুধু <strong>admin</strong> রোলের ইউজার এই মডিউলের যেকোনো পেজ দেখতে/পরিবর্তন করতে পারবেন</li>
            <li>একবার <strong>অনুমোদিত (approved)</strong> লাভ বণ্টন কখনো মুছে/পরিবর্তন করা যায় না — শুধু নতুন সংশোধনী এন্ট্রি যোগ করা যাবে</li>
            <li>প্রতিটা গুরুত্বপূর্ণ পরিবর্তন (পার্টনার যোগ/এডিট, মূলধন লেনদেন, বণ্টন অনুমোদন) <strong>অ্যাক্টিভিটি লগে</strong> স্থায়ীভাবে রেকর্ড হয়</li>
        </ul>
    </div>

    <div style="margin-top:10px;">
        <a href="/modules/partners/dashboard.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনারশিপ ড্যাশবোর্ডে ফিরে যান</a>
    </div>
</div>

<script>
var CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
var endpoints = {
    toggle_category: '/modules/accounts/actions/toggle_partnership_category.php',
    toggle_expense: '/modules/accounts/actions/toggle_partnership_expense.php'
};
function toggleShare(kind, id, checked) {
    fetch(endpoints[kind], {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&checked=' + (checked ? 1 : 0) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).catch(function () {
        alert('পরিবর্তন সংরক্ষণে সমস্যা হয়েছে, আবার চেষ্টা করুন।');
    });
}
</script>
