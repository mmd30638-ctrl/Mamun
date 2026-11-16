<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query("SELECT t.*, a.loan_no, c.account_name FROM bloan_txns t JOIN bloan_accounts a ON a.id=t.loan_id LEFT JOIN accounts c ON c.id=t.account_id WHERE t.kind IN ('payment','settlement') ORDER BY t.id DESC LIMIT 200");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('পরিশোধ', 'Payments'); ?></h1>
    <p class="en"><?php echo ame_l('প্রতিটি পরিশোধ ঋণ খাতা ও ক্যাশ/ব্যাংক খাতা একসাথে বদলায়। পোস্ট করা পেমেন্ট মুছে ফেলা হয় না।', 'Each payment updates the loan ledger and the cash or bank ledger together. Posted payments are not deleted.'); ?></p>
    <table>
        <tr><th>তারিখ</th><th>ঋণ</th><th>ধরন</th><th>আসল</th><th>সুদ</th><th>মোট</th><th>অ্যাকাউন্ট</th><th>রসিদ</th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['txn_date']); ?></td>
            <td><a href="loan.php?id=<?php echo (int)$r['loan_id']; ?>"><?php echo ame_bloan_h((string)$r['loan_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$r['kind']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['principal_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['interest_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['total']); ?></td>
            <td><?php echo ame_bloan_h((string)($r['account_name'] ?? '')); ?></td>
            <td><a href="receipt.php?id=<?php echo (int)$r['id']; ?>">ভাউচার</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
