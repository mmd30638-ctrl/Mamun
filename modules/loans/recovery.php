<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$focus_loan_id = (int)($_GET['loan_id'] ?? 0);

// বিলম্বিত কিস্তি আছে এমন সব লোনের তালিকা (রিকভারি ফোকাস লিস্ট)
$overdue_loans = $db->query("
    SELECT l.id AS loan_id, l.person_name, c.phone AS customer_phone,
           COUNT(s.id) AS overdue_count, COALESCE(SUM(s.due_amount - s.paid_amount),0) AS overdue_amount,
           MAX(DATEDIFF(CURDATE(), s.due_date)) AS max_days_overdue
    FROM loan_repayment_schedule s
    JOIN loans l ON l.id = s.loan_id
    LEFT JOIN customers c ON c.id = l.customer_id
    WHERE s.status IN ('pending','partial') AND s.due_date < CURDATE() AND l.status = 'open'
    GROUP BY l.id
    ORDER BY max_days_overdue DESC
");

$focus_loan = null;
$focus_history = null;
if ($focus_loan_id > 0) {
    $fl = $db->prepare("SELECT l.*, c.phone AS customer_phone FROM loans l LEFT JOIN customers c ON c.id = l.customer_id WHERE l.id = ?");
    $fl->bind_param("i", $focus_loan_id);
    $fl->execute();
    $focus_loan = $fl->get_result()->fetch_assoc();

    $fh = $db->prepare("SELECT * FROM loan_recovery_actions WHERE loan_id = ? ORDER BY action_date DESC, id DESC");
    $fh->bind_param("i", $focus_loan_id);
    $fh->execute();
    $focus_history = $fh->get_result();
}

$action_labels = ['call' => '📞 ফোন কল', 'visit' => '🚶 সরাসরি ভিজিট', 'notice' => '📄 নোটিশ', 'legal' => '⚖️ আইনি ব্যবস্থা', 'other' => '📝 অন্যান্য'];
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">📞 রিকভারি ব্যবস্থাপনা</h3>

    <?php if (isset($_GET['saved'])): ?>
    <div class="brand-badge success" style="display:flex; margin-bottom:12px;">✅ রিকভারি অ্যাকশন সেভ করা হয়েছে</div>
    <?php endif; ?>

    <?php if ($focus_loan): ?>
    <div class="card">
        <h4 style="margin-top:0;">🎯 <?php echo htmlspecialchars($focus_loan['person_name'] ?? ''); ?></h4>
        <small style="color:#888;"><?php echo htmlspecialchars($focus_loan['customer_phone'] ?? ''); ?></small>

        <form action="recovery_save.php" method="POST" style="margin-top:14px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="loan_id" value="<?php echo (int)$focus_loan_id; ?>">

            <label>অ্যাকশনের ধরন *</label>
            <select name="action_type" class="input-style" required style="margin-bottom:14px;">
                <?php foreach ($action_labels as $k => $label): ?>
                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label>তারিখ *</label>
            <input type="date" name="action_date" class="input-style" required value="<?php echo date('Y-m-d'); ?>" style="margin-bottom:14px;">

            <label>মন্তব্য/ফলাফল</label>
            <textarea name="notes" class="input-style" rows="3" style="margin-bottom:14px;" placeholder="যেমন: গ্রাহক আগামী সপ্তাহে দেওয়ার কথা বলেছেন"></textarea>

            <label>পরবর্তী ফলো-আপ তারিখ</label>
            <input type="date" name="next_follow_up_date" class="input-style" style="margin-bottom:16px;">

            <button type="submit" class="btn-danger" style="width:100%;">➕ অ্যাকশন যোগ করুন</button>
        </form>
    </div>

    <?php if ($focus_history && $focus_history->num_rows > 0): ?>
    <div class="card">
        <h4 style="margin-top:0;">📜 রিকভারি ইতিহাস</h4>
        <?php while ($h = $focus_history->fetch_assoc()): ?>
        <div style="border-bottom:1px solid var(--brand-border); padding:10px 0;">
            <div style="display:flex; justify-content:space-between;">
                <b style="font-size:13px;"><?php echo htmlspecialchars($action_labels[$h['action_type']] ?? $h['action_type']); ?></b>
                <small style="color:#888;"><?php echo htmlspecialchars($h['action_date']); ?></small>
            </div>
            <?php if (!empty($h['notes'])): ?><p style="margin:6px 0 0; font-size:13px;"><?php echo htmlspecialchars($h['notes']); ?></p><?php endif; ?>
            <?php if (!empty($h['next_follow_up_date'])): ?><small style="color:var(--brand-gold);">পরবর্তী ফলো-আপ: <?php echo htmlspecialchars($h['next_follow_up_date']); ?></small><?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <a href="recovery.php" style="display:inline-block; margin:10px 0;">⬅️ সব বিলম্বিত ঋণগ্রহীতার তালিকা দেখুন</a>
    <?php else: ?>

    <?php if ($overdue_loans->num_rows === 0): ?>
        <div class="card" style="text-align:center; color:#888;">রিকভারির জন্য কোনো লোন নেই 🎉</div>
    <?php endif; ?>

    <?php while ($o = $overdue_loans->fetch_assoc()): ?>
    <a href="recovery.php?loan_id=<?php echo (int)$o['loan_id']; ?>" class="card" style="display:block; text-decoration:none; color:inherit;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h4 style="margin:0;"><?php echo htmlspecialchars($o['person_name'] ?? ''); ?></h4>
                <small style="color:#888;"><?php echo htmlspecialchars($o['customer_phone'] ?? ''); ?></small>
            </div>
            <div style="text-align:right;">
                <b style="font-size:15px; color:var(--brand-danger);"><?php echo number_format((float)$o['overdue_amount'], 2); ?> ৳</b><br>
                <span class="brand-badge danger"><?php echo (int)$o['overdue_count']; ?>টি কিস্তি, সর্বোচ্চ <?php echo (int)$o['max_days_overdue']; ?> দিন</span>
            </div>
        </div>
    </a>
    <?php endwhile; ?>
    <?php endif; ?>

    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
