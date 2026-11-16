<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
$type = clean_input($_GET['type'] ?? '');
$sql = 'SELECT * FROM biz_loans';
if (in_array($type, ['bank', 'ngo', 'financial', 'individual'], true)) {
    $sql .= " WHERE provider_type = '" . $db->real_escape_string($type) . "'";
}
$sql .= ' ORDER BY provider_type, provider_name';
$rows = $db->query($sql);
include '../../partials/header.php';
?>
<style>
.br { padding:12px; }
.br a { margin-right:8px; }
.br table { width:100%; border-collapse:collapse; background:#fff; }
.br td, .br th { padding:8px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; }
</style>
<div class="br">
    <h1>ধারের রিপোর্ট</h1>
    <p>
        <a href="reports.php">সব</a>
        <a href="reports.php?type=bank">ব্যাংক</a>
        <a href="reports.php?type=ngo">এনজিও</a>
        <a href="reports.php?type=financial">প্রতিষ্ঠান</a>
        <a href="reports.php?type=individual">ব্যক্তি</a>
    </p>
    <table>
        <tr><th>উৎস</th><th>ধরন</th><th>মূল</th><th>বাকি</th><th>ফেরত</th><th>সুদ</th><th>ফি</th><th>সঞ্চয়</th><th>জামানত</th><th>অবস্থা</th></tr>
        <?php if ($rows) while ($l = $rows->fetch_assoc()): ?>
        <tr>
            <td><a href="ledger.php?id=<?php echo (int)$l['id']; ?>"><?php echo htmlspecialchars($l['provider_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            <td><?php echo htmlspecialchars($l['provider_type'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo number_format((float)$l['principal'], 2); ?></td>
            <td><?php echo number_format((float)$l['principal_outstanding'], 2); ?></td>
            <td><?php echo number_format((float)$l['principal_repaid'], 2); ?></td>
            <td><?php echo number_format((float)$l['interest_paid_total'], 2); ?></td>
            <td><?php echo number_format((float)$l['fee_paid_total'], 2); ?></td>
            <td><?php echo number_format((float)$l['savings_paid_total'], 2); ?></td>
            <td><?php echo number_format((float)$l['security_held'], 2); ?></td>
            <td><?php echo htmlspecialchars($l['status'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
