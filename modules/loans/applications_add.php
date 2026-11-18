<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
ensure_loan_tables($db);
include '../../partials/header.php';

$customers = $db->query("SELECT id, name, customer_code, phone FROM customers WHERE status = 1 ORDER BY name ASC");
$products = $db->query("SELECT * FROM loan_products WHERE is_active = 1 ORDER BY name ASC");

$product_list = [];
$products->data_seek(0);
while ($p = $products->fetch_assoc()) { $product_list[] = $p; }
?>
<div style="padding: 15px;">
    <div class="card" style="max-width:560px; margin:0 auto;">
        <h3>📋 নতুন লোন আবেদন</h3>

        <?php if (count($product_list) === 0): ?>
            <div class="brand-badge danger" style="display:flex; margin-bottom:14px;">⚠️ কোনো সক্রিয় লোন প্রোডাক্ট নেই — আগে প্রোডাক্ট যোগ করুন</div>
            <a href="products_form.php" class="btn-success" style="text-decoration:none; display:inline-block;">+ প্রোডাক্ট যোগ করুন</a>
        <?php else: ?>
        <form action="applications_save.php" method="POST">
            <?php echo csrf_field(); ?>

            <label>কাস্টমার (আবেদনকারী) *</label>
            <select name="customer_id" class="input-style" required style="margin-bottom:14px;">
                <option value="">-- নির্বাচন করুন --</option>
                <?php while ($c = $customers->fetch_assoc()): ?>
                    <option value="<?php echo (int)$c['id']; ?>">
                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['customer_code'] . ') - ' . ($c['phone'] ?? 'N/A')); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>লোন প্রোডাক্ট *</label>
            <select name="product_id" id="productSelect" class="input-style" required style="margin-bottom:14px;" onchange="fillDefaults()">
                <option value="">-- নির্বাচন করুন --</option>
                <?php foreach ($product_list as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"
                        data-rate="<?php echo htmlspecialchars((string)$p['interest_rate']); ?>"
                        data-term="<?php echo (int)$p['default_term_months']; ?>"
                        data-min="<?php echo htmlspecialchars((string)$p['min_amount']); ?>"
                        data-max="<?php echo htmlspecialchars((string)$p['max_amount']); ?>">
                        <?php echo htmlspecialchars($p['name'] . ' (' . $p['interest_rate'] . '% ' . ($p['interest_type']==='flat'?'ফ্ল্যাট':'রিডিউসিং') . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="rangeHint" style="font-size:12px; color:#666; margin:-8px 0 14px;"></div>

            <label>চাহিদাকৃত পরিমাণ (৳) *</label>
            <input type="number" step="0.01" name="requested_amount" id="amountInput" class="input-style" required style="margin-bottom:14px;">

            <label>মেয়াদ (মাস) *</label>
            <input type="number" name="requested_term_months" id="termInput" class="input-style" required style="margin-bottom:14px;">

            <label>উদ্দেশ্য</label>
            <input type="text" name="purpose" class="input-style" style="margin-bottom:14px;" placeholder="যেমন: ব্যবসা সম্প্রসারণ, চিকিৎসা...">

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>জামিনদারের নাম</label>
                    <input type="text" name="guarantor_name" class="input-style" style="margin-bottom:14px;">
                </div>
                <div style="flex:1;">
                    <label>জামিনদারের ফোন</label>
                    <input type="text" name="guarantor_phone" class="input-style" style="margin-bottom:14px;">
                </div>
            </div>

            <button type="submit" class="btn-success" style="width:100%;">আবেদন জমা দিন</button>
        </form>
        <?php endif; ?>
        <a href="dashboard.php" style="display:block; text-align:center; margin-top:14px;">⬅️ ফিরে যান</a>
    </div>
</div>

<script>
function fillDefaults() {
    var sel = document.getElementById('productSelect');
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('termInput').value = opt.getAttribute('data-term') || '';
    var min = opt.getAttribute('data-min'), max = opt.getAttribute('data-max');
    document.getElementById('rangeHint').textContent = 'অনুমোদিত সীমা: ' + min + ' – ' + max + ' ৳';
}
</script>
<?php include '../../partials/footer.php'; ?>
