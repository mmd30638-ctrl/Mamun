<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT t.*, a.loan_no, p.name provider_name, c.account_name FROM bloan_txns t JOIN bloan_accounts a ON a.id=t.loan_id JOIN bloan_providers p ON p.id=a.provider_id LEFT JOIN accounts c ON c.id=t.account_id WHERE t.id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    header('Location: /modules/bloan/payments.php');
    exit;
}
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1>ভাউচার <?php echo (int)$row['id']; ?></h1>
    <p class="en">Payment voucher · <?php echo ame_bloan_h((string)$row['loan_no']); ?></p>
    <table>
        <tr><th>তারিখ</th><td><?php echo ame_bloan_h((string)$row['txn_date']); ?></td></tr>
        <tr><th>ঋণদাতা</th><td><?php echo ame_bloan_h((string)$row['provider_name']); ?></td></tr>
        <tr><th>ধরন</th><td><?php echo ame_bloan_h((string)$row['kind']); ?></td></tr>
        <tr><th>আসল</th><td><?php echo ame_bloan_taka((float)$row['principal_part']); ?></td></tr>
        <tr><th>সুদ</th><td><?php echo ame_bloan_taka((float)$row['interest_part']); ?></td></tr>
        <tr><th>চার্জ</th><td><?php echo ame_bloan_taka((float)$row['charge_part']); ?></td></tr>
        <tr><th>মোট</th><td><?php echo ame_bloan_taka((float)$row['total']); ?></td></tr>
        <tr><th>বাকি আসল</th><td><?php echo ame_bloan_taka((float)$row['balance_after']); ?></td></tr>
        <tr><th>অ্যাকাউন্ট</th><td><?php echo ame_bloan_h((string)($row['account_name'] ?? '')); ?></td></tr>
        <tr><th>রেফারেন্স</th><td><?php echo ame_bloan_h((string)$row['reference_no']); ?></td></tr>
    </table>
    <p><button type="button" onclick="window.print()">প্রিন্ট · Print</button> <a class="btn" href="loan.php?id=<?php echo (int)$row['loan_id']; ?>">ঋণ</a></p>
</div>
<?php include '../../partials/footer.php'; ?>
