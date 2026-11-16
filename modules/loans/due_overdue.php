<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

// সব বকেয়া/বিলম্বিত কিস্তি একসাথে — সবচেয়ে বেশি দিন বিলম্বিত আগে দেখানো হচ্ছে
$rows = $db->query("
    SELECT s.*, l.person_name, l.customer_id, l.id AS loan_id, c.phone AS customer_phone,
           DATEDIFF(CURDATE(), s.due_date) AS days_overdue
    FROM loan_repayment_schedule s
    JOIN loans l ON l.id = s.loan_id
    LEFT JOIN customers c ON c.id = l.customer_id
    WHERE s.status IN ('pending','partial') AND s.due_date < CURDATE() AND l.status = 'open'
    ORDER BY days_overdue DESC
");

$total_overdue_amount = 0;
$rows_data = [];
while ($r = $rows->fetch_assoc()) {
    $r['due_left'] = round((float)$r['due_amount'] - (float)$r['paid_amount'], 2);
    $total_overdue_amount += $r['due_left'];
    $rows_data[] = $r;
}

function aging_bucket($days) {
    if ($days <= 30) return ['১-৩০ দিন', 'gold'];
    if ($days <= 60) return ['৩১-৬০ দিন', 'danger'];
    return ['৬০+ দিন', 'danger'];
}
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">⏰ বকেয়া ও বিলম্বিত কিস্তি (Due &amp; Overdue)</h3>

    <div class="card" style="margin-bottom:16px; text-align:center;">
        <div style="font-size:13px; color:#666;">মোট বিলম্বিত কিস্তির পরিমাণ</div>
        <div style="font-size:24px; font-weight:800; color:var(--brand-danger);"><?php echo number_format($total_overdue_amount, 2); ?> ৳</div>
        <div style="font-size:12px; color:#888;"><?php echo count($rows_data); ?>টি কিস্তি, <?php echo count(array_unique(array_column($rows_data, 'loan_id'))); ?>টি লোনে</div>
    </div>

    <?php if (empty($rows_data)): ?>
        <div class="card" style="text-align:center; color:#888;">কোনো বিলম্বিত কিস্তি নেই 🎉</div>
    <?php endif; ?>

    <?php foreach ($rows_data as $r): $bucket = aging_bucket((int)$r['days_overdue']); ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h4 style="margin:0;"><?php echo htmlspecialchars($r['person_name'] ?? ''); ?></h4>
                <small style="color:#888;"><?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?> | কিস্তি #<?php echo (int)$r['installment_no']; ?></small>
            </div>
            <div style="text-align:right;">
                <b style="font-size:16px; color:var(--brand-danger);"><?php echo number_format((float)($r['due_left'] ?? 0), 2); ?> ৳</b><br>
                <span class="brand-badge <?php echo $bucket[1]; ?>"><?php echo (int)$r['days_overdue']; ?> দিন বিলম্বিত (<?php echo $bucket[0]; ?>)</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
            <a href="pay.php?id=<?php echo (int)$r['loan_id']; ?>" class="btn-success" style="text-decoration:none; font-size:13px; padding:8px 16px;">💰 কিস্তি নিন</a>
            <a href="recovery.php?loan_id=<?php echo (int)$r['loan_id']; ?>" class="btn-danger" style="text-decoration:none; font-size:13px; padding:8px 16px;">📞 রিকভারি অ্যাকশন</a>
        </div>
    </div>
    <?php endforeach; ?>

    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
