<?php include '../../partials/header.php'; 
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$l = $stmt->get_result()->fetch_assoc();
if (!$l) {
    echo "<div class='card' style='margin:15px;'>লোনটি খুঁজে পাওয়া যায়নি।</div>";
    include '../../partials/footer.php';
    exit();
}
?>
<?php
$schedule = null;
if (!empty($l['product_id'])) {
    $sched_stmt = $db->prepare("
        SELECT *,
            CASE WHEN status IN ('pending','partial') AND due_date < CURDATE() THEN 'overdue' ELSE status END AS effective_status
        FROM loan_repayment_schedule WHERE loan_id = ? ORDER BY installment_no ASC
    ");
    $sched_stmt->bind_param("i", $id);
    $sched_stmt->execute();
    $schedule = $sched_stmt->get_result();
}
?>
<div class="card" style="margin: 15px;">
    <?php if (isset($_GET['disbursed'])): ?>
        <div class="brand-badge success" style="display:flex; margin-bottom:14px;">✅ লোন সফলভাবে ছাড় করা হয়েছে</div>
    <?php endif; ?>
    <h3 style="color: var(--dark-blue);">🏦 লোন লেনদেন: <?php echo htmlspecialchars($l['person_name'] ?? ''); ?></h3>
    <p>মোট লোন: <?php echo number_format((float)($l['amount'] ?? 0), 2); ?> ৳</p>
    <p>বকেয়া: <span class="text-danger"><?php echo number_format($l['amount'] - $l['paid_amount'], 2); ?> ৳</span></p>

    <form action="process_pay.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="loan_id" value="<?php echo $id; ?>">
        <label>টাকার পরিমাণ</label>
        <input type="number" step="0.01" name="pay_amount" class="input-style" required style="width:100%; padding:10px; margin:10px 0;">
        
        <label>পেমেন্ট অ্যাকাউন্ট</label>
        <select name="account_id" class="ame-account-select input-style" required style="width:100%; padding:10px; margin-bottom:15px;" data-ame-account-select="1" data-ame-direction="pay">
            <?php echo ame_render_account_options(ame_active_accounts($db)); ?>
        </select>
        <button type="submit" class="btn-success" style="width: 100%; padding: 15px;">লেনদেন সম্পন্ন করুন</button>
    </form>
</div>

<?php if ($schedule && $schedule->num_rows > 0): ?>
<div class="card" style="margin: 15px;">
    <h4 style="margin-top:0;">📅 রিপেমেন্ট শিডিউল</h4>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead><tr><th style="padding:8px;">কিস্তি</th><th style="padding:8px;">তারিখ</th><th style="padding:8px; text-align:right;">কিস্তির পরিমাণ</th><th style="padding:8px; text-align:center;">স্ট্যাটাস</th></tr></thead>
            <tbody>
                <?php while ($s = $schedule->fetch_assoc()): ?>
                <tr>
                    <td style="padding:8px;">#<?php echo (int)$s['installment_no']; ?></td>
                    <td style="padding:8px;"><?php echo htmlspecialchars($s['due_date']); ?></td>
                    <td style="padding:8px; text-align:right;"><?php echo number_format((float)$s['due_amount'], 2); ?> ৳</td>
                    <td style="padding:8px; text-align:center;">
                        <span class="brand-badge <?php echo $s['status']==='paid'?'success':($s['status']==='overdue'?'danger':'info'); ?>">
                            <?php echo ['pending'=>'বাকি','partial'=>'আংশিক','paid'=>'পরিশোধিত','overdue'=>'বিলম্বিত'][$s['status']] ?? $s['status']; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
