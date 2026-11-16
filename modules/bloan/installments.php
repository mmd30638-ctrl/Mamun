<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
ame_bloan_refresh_open($db);
$rows = $db->query("SELECT i.*, a.loan_no, p.name FROM bloan_installments i JOIN bloan_accounts a ON a.id=i.loan_id JOIN bloan_providers p ON p.id=a.provider_id ORDER BY i.due_date DESC LIMIT 200");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('কিস্তি', 'Installments'); ?></h1>
    <p class="en"><?php echo ame_l('আংশিক পরিশোধ আগে পুরনো খোলা কিস্তিতে বসে।', 'A partial payment is applied to the oldest open installment first.'); ?></p>
    <table>
        <tr><th>#</th><th>ঋণ</th><th>ঋণদাতা</th><th>তারিখ</th><th>মোট</th><th>পরিশোধ</th><th>বাকি</th><th>অবস্থা</th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo (int)$r['installment_no']; ?></td>
            <td><a href="loan.php?id=<?php echo (int)$r['loan_id']; ?>"><?php echo ame_bloan_h((string)$r['loan_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$r['name']); ?></td>
            <td><?php echo ame_bloan_h((string)$r['due_date']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['total_due']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['paid_amount']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['remaining_amount']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$r['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
