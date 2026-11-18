<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$id = (int)($_GET['id'] ?? 0);
$product = ['name' => '', 'interest_rate' => 0, 'interest_type' => 'flat', 'default_term_months' => 12,
            'min_amount' => 0, 'max_amount' => 0, 'processing_fee_percent' => 0, 'penalty_rate_percent' => 0, 'is_active' => 1];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM loan_products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if ($found) { $product = $found; }
}
?>
<div style="padding: 15px;">
    <div class="card" style="max-width:520px; margin:0 auto;">
        <h3><?php echo $id > 0 ? '✏️ প্রোডাক্ট সম্পাদনা' : '➕ নতুন লোন প্রোডাক্ট'; ?></h3>
        <form action="products_save.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

            <label>প্রোডাক্টের নাম *</label>
            <input type="text" name="name" class="input-style" required value="<?php echo htmlspecialchars($product['name']); ?>" style="margin-bottom:14px;">

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>সুদের হার (বার্ষিক %) *</label>
                    <input type="number" step="0.01" name="interest_rate" class="input-style" required value="<?php echo htmlspecialchars((string)$product['interest_rate']); ?>" style="margin-bottom:14px;">
                </div>
                <div style="flex:1;">
                    <label>সুদের ধরণ</label>
                    <select name="interest_type" class="input-style" style="margin-bottom:14px;">
                        <option value="flat" <?php echo $product['interest_type'] === 'flat' ? 'selected' : ''; ?>>ফ্ল্যাট</option>
                        <option value="reducing" <?php echo $product['interest_type'] === 'reducing' ? 'selected' : ''; ?>>রিডিউসিং</option>
                    </select>
                </div>
            </div>

            <label>ডিফল্ট মেয়াদ (মাস) *</label>
            <input type="number" name="default_term_months" class="input-style" required value="<?php echo (int)$product['default_term_months']; ?>" style="margin-bottom:14px;">

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>সর্বনিম্ন পরিমাণ (৳)</label>
                    <input type="number" step="0.01" name="min_amount" class="input-style" value="<?php echo htmlspecialchars((string)$product['min_amount']); ?>" style="margin-bottom:14px;">
                </div>
                <div style="flex:1;">
                    <label>সর্বোচ্চ পরিমাণ (৳)</label>
                    <input type="number" step="0.01" name="max_amount" class="input-style" value="<?php echo htmlspecialchars((string)$product['max_amount']); ?>" style="margin-bottom:14px;">
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>প্রসেসিং ফি (%)</label>
                    <input type="number" step="0.01" name="processing_fee_percent" class="input-style" value="<?php echo htmlspecialchars((string)$product['processing_fee_percent']); ?>" style="margin-bottom:14px;">
                </div>
                <div style="flex:1;">
                    <label>বিলম্ব জরিমানা হার (%)</label>
                    <input type="number" step="0.01" name="penalty_rate_percent" class="input-style" value="<?php echo htmlspecialchars((string)$product['penalty_rate_percent']); ?>" style="margin-bottom:14px;">
                </div>
            </div>

            <label style="display:flex; align-items:center; gap:8px; font-weight:600; margin-bottom:16px;">
                <input type="checkbox" name="is_active" value="1" <?php echo $product['is_active'] ? 'checked' : ''; ?> style="width:18px; height:18px;">
                সক্রিয় (নতুন আবেদনে এই প্রোডাক্ট বাছাই করা যাবে)
            </label>

            <button type="submit" class="btn-success" style="width:100%;">সেভ করুন</button>
        </form>
        <a href="products_list.php" style="display:block; text-align:center; margin-top:14px;">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
