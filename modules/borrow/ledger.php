<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
$id = (int)($_GET['id'] ?? 0);
$loan = ame_borrow_fetch($db, $id);
if (!$loan) {
    header('Location: dashboard.php');
    exit;
}
$tx = $db->prepare('SELECT * FROM biz_loan_txns WHERE loan_id = ? ORDER BY id DESC');
$tx->bind_param('i', $id);
$tx->execute();
$rows = $tx->get_result();
include '../../partials/header.php';
?>
<style>
.bl { padding:12px; }
.bl table { width:100%; border-collapse:collapse; background:#fff; }
.bl td, .bl th { padding:8px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; }
</style>
<div class="bl">
    <h1><?php echo htmlspecialchars($loan['provider_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>মূল ৳ <?php echo number_format((float)$loan['principal'], 2); ?> · বাকি ৳ <?php echo number_format((float)$loan['principal_outstanding'], 2); ?> · ফেরত ৳ <?php echo number_format((float)$loan['principal_repaid'], 2); ?></p>
    <p>সুদ ৳ <?php echo number_format((float)$loan['interest_paid_total'], 2); ?> · ফি ৳ <?php echo number_format((float)$loan['fee_paid_total'], 2); ?> · সঞ্চয় ৳ <?php echo number_format((float)$loan['savings_paid_total'], 2); ?> · জামানত ৳ <?php echo number_format((float)$loan['security_held'], 2); ?></p>
    <p><a href="pay.php?loan_id=<?php echo (int)$id; ?>">পেমেন্ট</a></p>
    <table>
        <tr><th>তারিখ</th><th>ধরন</th><th>আসল</th><th>সুদ</th><th>ফি</th><th>সঞ্চয়</th><th>জামানত</th><th>মোট</th></tr>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['txn_date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($r['kind'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo number_format((float)$r['principal_part'], 2); ?></td>
            <td><?php echo number_format((float)$r['interest_part'], 2); ?></td>
            <td><?php echo number_format((float)$r['fee_part'], 2); ?></td>
            <td><?php echo number_format((float)$r['savings_part'], 2); ?></td>
            <td><?php echo number_format((float)$r['security_part'], 2); ?></td>
            <td><?php echo number_format((float)$r['total'], 2); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
