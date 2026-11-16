<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM loans WHERE id = ? AND type = 'given' AND status = 'open'");
$stmt->bind_param("i", $id);
$stmt->execute();
$l = $stmt->get_result()->fetch_assoc();

if (!$l) {
    echo "<div class='card' style='margin:15px;'>এই লোনটি পুনর্গঠনের জন্য প্রস্তুত নয় (বন্ধ অথবা ঋণগ্রহণ-টাইপ লোন পুনর্গঠন করা যায় না)।</div>";
    include '../../partials/footer.php';
    exit();
}

$outstanding = round((float)$l['amount'] - (float)$l['paid_amount'], 2);

$history = $db->prepare("SELECT * FROM loan_restructure_log WHERE loan_id = ? ORDER BY id DESC");
$history->bind_param("i", $id);
$history->execute();
$history_rows = $history->get_result();
?>
<div style="padding: 15px;">
    <div class="card" style="max-width:520px; margin:0 auto;">
        <h3>🔄 লোন পুনর্গঠন (Restructure)</h3>
        <p><b><?php echo htmlspecialchars($l['person_name'] ?? ''); ?></b></p>
        <p style="font-size:13px; color:#666;">বর্তমান বকেয়া: <b><?php echo number_format($outstanding, 2); ?> ৳</b> | বর্তমান মেয়াদ: <?php echo (int)$l['term_months']; ?> মাস | সুদ: <?php echo $l['interest_rate']; ?>%</p>

        <?php if (isset($_GET['error'])): ?>
        <div class="brand-badge danger" style="display:flex; margin-bottom:12px;">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="brand-badge gold" style="display:flex; margin-bottom:14px; align-items:flex-start;">
            ⚠️ পুনর্গঠন করলে ভবিষ্যতের অপরিশোধিত কিস্তিগুলো নতুন শর্তে পুনরায় তৈরি হবে। ইতিমধ্যে পরিশোধিত কিস্তি অক্ষত থাকবে। আগের শর্ত ইতিহাসে সংরক্ষিত থাকবে।
        </div>

        <form action="restructure_process.php" method="POST" onsubmit="return confirm('নিশ্চিত? এই কাজ ফিরিয়ে আনা যাবে না।');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="loan_id" value="<?php echo (int)$id; ?>">

            <label>নতুন মোট বকেয়া (৳) *</label>
            <input type="number" step="0.01" name="new_amount" class="input-style" required value="<?php echo htmlspecialchars((string)$outstanding); ?>" style="margin-bottom:14px;">
            <small style="display:block; margin:-10px 0 14px; color:#888;">ডিফল্টভাবে বর্তমান বকেয়াই বসানো আছে — চাইলে বাড়াতে/কমাতে পারেন (যেমন জরিমানা যোগ করা বা আংশিক মওকুফ)।</small>

            <label>নতুন মেয়াদ (মাস) *</label>
            <input type="number" name="new_term_months" class="input-style" required value="<?php echo (int)$l['term_months']; ?>" style="margin-bottom:14px;">

            <label>নতুন সুদের হার (বার্ষিক %) *</label>
            <input type="number" step="0.01" name="new_interest_rate" class="input-style" required value="<?php echo htmlspecialchars((string)$l['interest_rate']); ?>" style="margin-bottom:14px;">

            <label>কারণ *</label>
            <textarea name="reason" class="input-style" rows="2" required style="margin-bottom:16px;" placeholder="যেমন: গ্রাহকের অনুরোধে মেয়াদ বৃদ্ধি"></textarea>

            <button type="submit" class="btn-danger" style="width:100%;">🔄 নিশ্চিত করে পুনর্গঠন করুন</button>
        </form>
        <a href="pay.php?id=<?php echo (int)$id; ?>" style="display:block; text-align:center; margin-top:14px;">⬅️ ফিরে যান</a>
    </div>

    <?php if ($history_rows->num_rows > 0): ?>
    <div class="card" style="max-width:520px; margin:15px auto 0;">
        <h4 style="margin-top:0;">📜 পুনর্গঠনের ইতিহাস</h4>
        <?php while ($h = $history_rows->fetch_assoc()): ?>
        <div style="border-bottom:1px solid var(--brand-border); padding:10px 0; font-size:13px;">
            <div><?php echo htmlspecialchars($h['restructured_at']); ?></div>
            <div>পুরনো: <?php echo number_format((float)$h['old_amount'], 2); ?> ৳, <?php echo (int)$h['old_term_months']; ?> মাস, <?php echo $h['old_interest_rate']; ?>%</div>
            <div>নতুন: <?php echo number_format((float)$h['new_amount'], 2); ?> ৳, <?php echo (int)$h['new_term_months']; ?> মাস, <?php echo $h['new_interest_rate']; ?>%</div>
            <?php if (!empty($h['reason'])): ?><div style="color:#666;">কারণ: <?php echo htmlspecialchars($h['reason']); ?></div><?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
