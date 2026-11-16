<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
ame_bloan_refresh_open($db);
$rows = $db->query("SELECT i.*, a.loan_no, p.name FROM bloan_installments i JOIN bloan_accounts a ON a.id=i.loan_id JOIN bloan_providers p ON p.id=a.provider_id WHERE i.status='overdue' ORDER BY i.due_date");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('বকেয়া কিস্তি', 'Overdue installments'); ?></h1>
    <p class="en"><?php echo ame_l('পরিশোধ ঋণের পাতা থেকে হবে। পেমেন্ট পোস্ট না হওয়া পর্যন্ত ক্যাশ/ব্যাংক অপরিবর্তিত থাকে।', 'Pay from the loan page. Cash and bank stay unchanged until a payment is posted.'); ?></p>
    <table>
        <tr><th>তারিখ</th><th>ঋণ</th><th>ঋণদাতা</th><th>কিস্তি</th><th>বাকি</th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['due_date']); ?></td>
            <td><a href="loan.php?id=<?php echo (int)$r['loan_id']; ?>"><?php echo ame_bloan_h((string)$r['loan_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$r['name']); ?></td>
            <td><?php echo (int)$r['installment_no']; ?></td>
            <td><?php echo ame_bloan_taka((float)$r['remaining_amount']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
