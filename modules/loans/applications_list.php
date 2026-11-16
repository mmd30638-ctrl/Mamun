<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
ensure_loan_tables($db);
include '../../partials/header.php';

$filter = $_GET['status'] ?? 'all';
$valid_statuses = ['pending', 'approved', 'rejected', 'disbursed', 'cancelled'];
$where = '';
if (in_array($filter, $valid_statuses, true)) {
    $where = "WHERE la.status = '" . $db->real_escape_string($filter) . "'";
}

$apps = $db->query("
    SELECT la.*, c.name AS customer_name, c.phone AS customer_phone, lp.name AS product_name
    FROM loan_applications la
    JOIN customers c ON c.id = la.customer_id
    JOIN loan_products lp ON lp.id = la.product_id
    $where
    ORDER BY la.id DESC
");

$status_labels = [
    'pending' => ['লম্বিত', 'gold'],
    'approved' => ['অনুমোদিত', 'info'],
    'rejected' => ['প্রত্যাখ্যাত', 'danger'],
    'disbursed' => ['ছাড়কৃত', 'success'],
    'cancelled' => ['বাতিল', 'danger'],
];
?>
<div style="padding: 15px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="color: var(--dark-blue); margin:0;">📋 লোন আবেদনসমূহ</h3>
        <a href="applications_add.php" class="btn-success" style="text-decoration:none; font-size:13px;">+ নতুন আবেদন</a>
    </div>

    <?php if (isset($_GET['submitted'])): ?>
    <div class="brand-badge success" style="margin-bottom:12px; display:flex;">✅ আবেদন সফলভাবে জমা হয়েছে — অনুমোদনের অপেক্ষায়</div>
    <?php endif; ?>

    <div style="display:flex; gap:8px; overflow-x:auto; margin-bottom:14px; padding-bottom:4px;">
        <a href="?status=all" class="brand-badge <?php echo $filter==='all'?'info':''; ?>" style="text-decoration:none; white-space:nowrap;">সব</a>
        <?php foreach ($status_labels as $key => $meta): ?>
            <a href="?status=<?php echo $key; ?>" class="brand-badge <?php echo $meta[1]; ?>" style="text-decoration:none; white-space:nowrap; <?php echo $filter===$key ? 'outline:2px solid currentColor;' : ''; ?>"><?php echo $meta[0]; ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr>
                    <th style="padding:12px;">কাস্টমার</th>
                    <th style="padding:12px;">প্রোডাক্ট</th>
                    <th style="padding:12px; text-align:right;">চাহিদা</th>
                    <th style="padding:12px; text-align:center;">স্ট্যাটাস</th>
                    <th style="padding:12px; text-align:center;">একশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($apps->num_rows === 0): ?>
                <tr><td colspan="5" style="padding:20px; text-align:center; color:#888;">কোনো আবেদন নেই</td></tr>
                <?php endif; ?>
                <?php while ($a = $apps->fetch_assoc()): $meta = $status_labels[$a['status']] ?? ['?','info']; ?>
                <tr>
                    <td style="padding:12px;"><?php echo htmlspecialchars($a['customer_name']); ?><br><small style="color:#888;"><?php echo htmlspecialchars($a['customer_phone'] ?? ''); ?></small></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($a['product_name']); ?></td>
                    <td style="padding:12px; text-align:right;"><?php echo number_format((float)$a['requested_amount'], 2); ?> ৳<br><small style="color:#888;"><?php echo (int)$a['requested_term_months']; ?> মাস</small></td>
                    <td style="padding:12px; text-align:center;"><span class="brand-badge <?php echo $meta[1]; ?>"><?php echo $meta[0]; ?></span></td>
                    <td style="padding:12px; text-align:center;">
                        <?php if ($a['status'] === 'pending'): ?>
                            <a href="approvals.php">✅ অনুমোদন করুন</a>
                        <?php elseif ($a['status'] === 'approved'): ?>
                            <a href="disburse.php?application_id=<?php echo (int)$a['id']; ?>">💸 ছাড় করুন</a>
                        <?php elseif ($a['status'] === 'disbursed' && $a['resulting_loan_id']): ?>
                            <a href="pay.php?id=<?php echo (int)$a['resulting_loan_id']; ?>">🏦 লোন দেখুন</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="dashboard.php" style="display:inline-block; margin-top:10px;">⬅️ লোন ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
