<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
admin_only();
ensure_loan_tables($db);
include '../../partials/header.php';

$rows = $db->query("
    SELECT s.*, l.person_name, l.id AS loan_id, c.phone AS customer_phone
    FROM loan_repayment_schedule s
    JOIN loans l ON l.id = s.loan_id
    LEFT JOIN customers c ON c.id = l.customer_id
    WHERE s.status IN ('pending','partial') AND s.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.status = 'open'
    ORDER BY s.due_date ASC
");

$items = [];
$today_total = 0;
while ($r = $rows->fetch_assoc()) {
    $r['due_left'] = round((float)$r['due_amount'] - (float)$r['paid_amount'], 2);
    if ($r['due_date'] === date('Y-m-d')) { $today_total += $r['due_left']; }
    $items[] = $r;
}
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">💰 কালেকশন ওয়ার্কলিস্ট (আজ + আগামী ৭ দিন)</h3>

    <div class="card" style="margin-bottom:16px; text-align:center;">
        <div style="font-size:13px; color:#666;">আজ আদায়যোগ্য</div>
        <div style="font-size:24px; font-weight:800; color:var(--brand-success);"><?php echo number_format($today_total, 2); ?> ৳</div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card" style="text-align:center; color:#888;">আগামী ৭ দিনে কোনো কিস্তি বাকি নেই</div>
    <?php endif; ?>

    <?php foreach ($items as $r): $is_today = $r['due_date'] === date('Y-m-d'); ?>
    <div class="card" style="<?php echo $is_today ? 'border-left:4px solid var(--brand-gold);' : ''; ?>">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>
                <h4 style="margin:0;"><?php echo htmlspecialchars($r['person_name'] ?? ''); ?></h4>
                <small style="color:#888;"><?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?> | কিস্তি #<?php echo (int)$r['installment_no']; ?></small>
            </div>
            <div style="text-align:right;">
                <b style="font-size:16px; color:var(--dark-blue);"><?php echo number_format((float)($r['due_left'] ?? 0), 2); ?> ৳</b><br>
                <span class="brand-badge <?php echo $is_today ? 'gold' : 'info'; ?>"><?php echo $is_today ? 'আজকের তারিখ' : htmlspecialchars($r['due_date']); ?></span>
            </div>
        </div>
        <a href="pay.php?id=<?php echo (int)$r['loan_id']; ?>" class="btn-success" style="display:inline-block; text-decoration:none; font-size:13px; padding:8px 16px; margin-top:12px;">💰 কিস্তি নিন</a>
    </div>
    <?php endforeach; ?>

    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
