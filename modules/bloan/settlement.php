<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query("SELECT a.id, a.loan_no, a.principal_outstanding, p.name FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.status='active' ORDER BY a.loan_no");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('আগাম পরিশোধ', 'Early settlement'); ?></h1>
    <p class="en"><?php echo ame_l('বাকি আসল, বাকি সুদ ও সেটেলমেন্ট চার্জ ঋণের পাতায় যোগ হয়। শুধু অ্যাডমিন।', 'Remaining principal, unpaid interest, and a settlement charge are totalled on the loan page. Admin only.'); ?></p>
    <table>
        <tr><th>Loan ID</th><th>ঋণদাতা</th><th>বাকি আসল</th><th></th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()):
            $q = ame_bloan_settlement_quote($db, (int)$r['id']);
        ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['loan_no']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['name']); ?></td>
            <td><?php echo ame_bloan_taka($q['principal']); ?> + সুদ <?php echo ame_bloan_taka($q['interest']); ?></td>
            <td><a class="btn" href="loan.php?id=<?php echo (int)$r['id']; ?>">হিসাব</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
