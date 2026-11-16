<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query("SELECT a.id, a.loan_no, a.principal_outstanding, a.status, p.name FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id ORDER BY a.id DESC");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ঋণ খাতা', 'Loan ledger'); ?></h1>
    <p class="en"><?php echo ame_l('একটি ঋণ খুললে শুরু থেকে শেষ পর্যন্ত প্রতিটি লেনদেন ও ব্যালেন্স দেখা যায়।', 'Open a loan to see every receipt, payment, interest line, and balance.'); ?></p>
    <table>
        <tr><th>Loan ID</th><th>ঋণদাতা</th><th>বাকি</th><th>অবস্থা</th><th></th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['loan_no']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['name']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['principal_outstanding']); ?></td>
            <td><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></td>
            <td><a href="loan.php?id=<?php echo (int)$r['id']; ?>">খাতা</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
