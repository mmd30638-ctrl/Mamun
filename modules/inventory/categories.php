<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/partner_accounting_v2.php';

function ensure_category_status_column($db) {
    $check = $db->query("SHOW COLUMNS FROM categories LIKE 'status'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE categories ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1");
    }
}
ensure_category_status_column($db);
ensure_category_exchange_schema($db);
ame_ensure_category_archive($db);
pav2_ensure_schema($db);

include '../../partials/header.php';
$exchange_id = ame_exchange_category_id($db);
$is_english = function_exists('current_lang') && current_lang() === 'en';
$flash = (string)($_SESSION['cat_flash'] ?? '');
$error = (string)($_SESSION['cat_error'] ?? '');
unset($_SESSION['cat_flash'], $_SESSION['cat_error']);

$has_product_cat = false;
$col = $db->query("SHOW COLUMNS FROM products LIKE 'category_id'");
$has_product_cat = $col && $col->num_rows > 0;
$count_sql = $has_product_cat ? '(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id)' : '0';
$cats = $db->query("SELECT c.*, {$count_sql} AS product_count FROM categories c ORDER BY c.archived ASC, c.name ASC");

$open_split = [];
foreach (pav2_category_rules($db, true) as $rule) {
    $cid = (int)$rule['category_id'];
    $open = empty($rule['effective_to']) || (string)$rule['effective_to'] >= date('Y-m-d');
    if ($open && !isset($open_split[$cid])) {
        $open_split[$cid] = $rule;
    }
}
?>
<style>
html,body,#main-content{background:#ffffff!important}
.cat-wrap{box-sizing:border-box;width:100%;max-width:920px;margin:0 auto;padding:12px 12px 28px;color:#10233f}
.cat-card,.cat-item,.cat-note,.cat-flash,.cat-form input,.cat-edit input,.cat-badge,.cat-ex-btn,.cat-text-btn,.cat-form button{
    background:#ffffff!important;background-image:none!important
}
.cat-card{width:100%;border:1px solid #dbeafe;border-top:6px solid #0b5cad;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);overflow:hidden}
.cat-header{text-align:center;padding:18px 16px 8px}
.cat-header h3{margin:0;font-size:clamp(18px,4vw,24px);color:#0b5cad}
.cat-header small{color:#64748b;font-size:13px;display:block;margin-top:6px;line-height:1.5}
.cat-note,.cat-flash{margin:10px 16px;padding:10px 12px;border-radius:10px;font-size:13px;line-height:1.5}
.cat-note{border:1px solid #fde68a;color:#b45309}
.cat-flash{border:1px solid #86efac;color:#166534}
.cat-flash.bad{border-color:#fca5a5;color:#991b1b}
.cat-form{display:flex;gap:8px;flex-wrap:wrap;padding:0 16px 14px}
.cat-form input,.cat-edit input{flex:1 1 180px;min-width:0;min-height:44px;padding:10px 12px;border:1.5px solid #bfdbfe;border-radius:10px;font-size:16px;color:#0b5cad}
.cat-form button,.cat-text-btn,.cat-ex-btn{min-height:44px;border-radius:10px;font-weight:800;cursor:pointer;font-size:14px}
.cat-form button{border:1.5px solid #0e8f52;color:#0e8f52;padding:10px 16px}
.cat-list{padding:0 12px 16px;display:grid;gap:8px}
.cat-item{display:grid;grid-template-columns:1fr;gap:8px;border:1px solid #e2e8f0;border-radius:12px;padding:12px;align-items:center}
.cat-item.inactive{border-color:#fecaca}
.cat-item.is-exchange{border-color:#86efac}
.cat-item.is-archived{border-style:dashed;border-color:#cbd5e1}
.cat-name{font-weight:800;color:#0b5cad;font-size:15px;overflow-wrap:anywhere}
.cat-meta{color:#64748b;font-size:12px;font-weight:700;margin-top:3px}
.cat-right{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.cat-badge{display:inline-flex;align-items:center;min-height:28px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;border:1.5px solid #86efac;color:#166534}
.cat-badge.inactive{border-color:#fca5a5;color:#b91c1c}
.cat-badge.exchange{border-color:#7c3aed;color:#6d28d9}
.cat-badge.split{border-color:#0284c7;color:#0369a1}
.cat-ex-btn,.cat-text-btn{border:1.5px solid #0b5cad;color:#0b5cad;padding:8px 10px;background:#fff}
.cat-text-btn.edit{border-color:#7c3aed;color:#6d28d9}
.cat-text-btn.stop{border-color:#c62828;color:#c62828}
.cat-text-btn.del{border-color:#c62828;color:#c62828}
.cat-text-btn.ok{border-color:#0e8f52;color:#0e8f52}
.cat-edit{width:100%}
.cat-edit summary{cursor:pointer;color:#6d28d9;font-weight:800;min-height:44px;display:flex;align-items:center}
.cat-edit form{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
.cat-empty,.cat-arch-title{text-align:center;color:#64748b;font-size:13px;padding:12px}
.cat-arch-title{color:#b45309;font-weight:800}
@media (min-width:760px){
    .cat-wrap{padding:20px 16px 36px}
    .cat-item{grid-template-columns:minmax(180px,1fr) auto}
    .cat-right{justify-content:flex-end;max-width:100%}
}
@media (max-width:759px){
    .cat-right{width:100%}
    .cat-ex-btn,.cat-text-btn,.cat-form button{flex:1 1 auto}
}
</style>

<div class="cat-wrap">
    <div class="cat-card">
        <div class="cat-header">
            <h3><?php echo $is_english ? 'Product categories' : 'পণ্যের ক্যাটাগরি'; ?></h3>
            <small><?php echo $is_english
                ? 'Add, rename, hide, or split profit 50/50. Hiding a category never deletes products, sales, or old partner reports.'
                : 'সব ক্যাটাগরির ৫০% পার্টনার লাভ নিষ্ক্রিয়। দরকার হলে অ্যাক্টিভ করুন। এডিট বা লুকানোতে পুরোনো রিপোর্ট মুছে যায় না।'; ?></small>
        </div>
        <?php if ($error !== ''): ?><div class="cat-flash bad"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($flash !== ''): ?><div class="cat-flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <p class="cat-note"><?php echo $is_english
            ? 'Only one category is the exchange খাত. Products there are old-battery trade-in on sales. Profit split starts today and does not rewrite older dates.'
            : 'একবারে শুধু একটি খাত এক্সচেঞ্জ। ৫০% লাভ নিজে অ্যাক্টিভ না করা পর্যন্ত কোনো ক্যাটাগরিতে ভাগ হয় না।'; ?></p>
        <form action="process_category.php" method="POST" class="cat-form">
            <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
            <input type="text" name="name" placeholder="<?php echo $is_english ? 'New category name' : 'নতুন ক্যাটাগরির নাম'; ?>" required>
            <button type="submit"><?php echo $is_english ? 'Add' : 'যোগ করুন'; ?></button>
        </form>
        <div class="cat-list">
            <?php
            $shown_archive = false;
            if ($cats && $cats->num_rows > 0): while ($c = $cats->fetch_assoc()):
                $is_active = ((int)($c['status'] ?? 1) === 1);
                $is_ex = ((int)$c['id'] === $exchange_id);
                $is_archived = ((int)($c['archived'] ?? 0) === 1);
                $split = $open_split[(int)$c['id']] ?? null;
                if ($is_archived && !$shown_archive) {
                    $shown_archive = true;
                    echo '<div class="cat-arch-title">' . ($is_english ? 'Hidden — old reports kept' : 'লুকানো ক্যাটাগরি — পুরোনো রিপোর্ট আছে') . '</div>';
                }
            ?>
            <div class="cat-item <?php echo $is_active ? '' : 'inactive'; ?><?php echo $is_ex ? ' is-exchange' : ''; ?><?php echo $is_archived ? ' is-archived' : ''; ?>">
                <div>
                    <div class="cat-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="cat-meta"><?php echo (int)$c['product_count']; ?> <?php echo $is_english ? 'products stay linked' : 'পণ্য যুক্ত আছে'; ?></div>
                </div>
                <div class="cat-right">
                    <?php if ($is_ex): ?>
                    <span class="cat-badge exchange"><?php echo $is_english ? 'Exchange' : 'এক্সচেঞ্জ খাত'; ?></span>
                    <?php endif; ?>
                    <?php if ($split || (int)($c['is_partnership_shared'] ?? 0) === 1): ?>
                    <span class="cat-badge split">৫০% অ্যাক্টিভ<?php echo $split ? ' · ' . htmlspecialchars((string)$split['partner_one_name']) . ' / ' . htmlspecialchars((string)$split['partner_two_name']) : ''; ?></span>
                    <?php else: ?>
                    <span class="cat-badge inactive">৫০% নিষ্ক্রিয়</span>
                    <?php endif; ?>
                    <span class="cat-badge <?php echo $is_active && !$is_archived ? 'active' : 'inactive'; ?>"><?php echo $is_archived ? ($is_english ? 'Hidden' : 'লুকানো') : ($is_active ? 'Active' : 'Inactive'); ?></span>
                    <?php if (!$is_archived): ?>
                    <form action="set_exchange_category.php" method="POST" class="cat-ex-form">
                        <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <?php if ($is_ex): ?>
                        <input type="hidden" name="clear" value="1">
                        <button type="submit" class="cat-ex-btn"><?php echo $is_english ? 'Clear exchange' : 'এক্সচেঞ্জ সরান'; ?></button>
                        <?php else: ?>
                        <button type="submit" class="cat-ex-btn"><?php echo $is_english ? 'Set as exchange' : 'এই খাত এক্সচেঞ্জ'; ?></button>
                        <?php endif; ?>
                    </form>
                    <form action="category_split.php" method="POST">
                        <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <?php if ($split || (int)($c['is_partnership_shared'] ?? 0) === 1): ?>
                        <input type="hidden" name="stop" value="1">
                        <button type="submit" class="cat-text-btn stop"><?php echo $is_english ? 'Make inactive' : 'নিষ্ক্রিয়'; ?></button>
                        <?php else: ?>
                        <button type="submit" class="cat-text-btn ok"><?php echo $is_english ? 'Activate 50%' : 'অ্যাক্টিভ'; ?></button>
                        <?php endif; ?>
                    </form>
                    <form action="toggle_category_status.php" method="POST" onsubmit="return confirm('<?php echo $is_english ? 'Change status?' : 'স্ট্যাটাস পরিবর্তন করতে চান?'; ?>');">
                        <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <button type="submit" class="cat-text-btn" title="Active/Inactive"><?php echo $is_active ? 'Inactive' : 'Active'; ?></button>
                    </form>
                    <details class="cat-edit">
                        <summary><?php echo $is_english ? 'Edit' : 'এডিট'; ?></summary>
                        <form action="update_category.php" method="POST">
                            <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            <button class="cat-text-btn edit" type="submit"><?php echo $is_english ? 'Save' : 'সেভ'; ?></button>
                        </form>
                    </details>
                    <form action="archive_category.php" method="POST" onsubmit="return confirm('<?php echo $is_english ? 'Hide this category? Products, sales and old reports stay.' : 'ক্যাটাগরি লুকাবেন? পণ্য, বিক্রয় ও আগের রিপোর্ট মুছবে না।'; ?>');">
                        <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <button type="submit" class="cat-text-btn del"><?php echo $is_english ? 'Hide' : 'ডিলিট'; ?></button>
                    </form>
                    <?php else: ?>
                    <form action="archive_category.php" method="POST">
                        <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <input type="hidden" name="restore" value="1">
                        <button type="submit" class="cat-text-btn ok"><?php echo $is_english ? 'Restore' : 'ফেরত আনুন'; ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="cat-empty"><?php echo $is_english ? 'No categories yet.' : 'এখনো কোনো ক্যাটাগরি যোগ হয়নি।'; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
