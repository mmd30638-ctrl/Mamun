<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query("SELECT a.*, p.name provider_name FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id ORDER BY a.id DESC");
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('চালু ঋণ', 'Active loans'); ?></h1>
    <p class="en"><?php echo ame_l('টাকা ক্যাশ বা ব্যাংকে ঢোকার পর আলাদা ঋণ অ্যাকাউন্ট তৈরি হয়।', 'A loan account is created only after the money is received in cash or bank.'); ?></p>
    <table>
        <tr><th><?php echo ame_l('ঋণ নম্বর', 'Loan ID'); ?></th><th><?php echo ame_l('ঋণদাতা', 'Provider'); ?></th><th><?php echo ame_l('অনুমোদিত', 'Approved'); ?></th><th><?php echo ame_l('গ্রহণ', 'Disbursed'); ?></th><th><?php echo ame_l('কিস্তি', 'Installment'); ?></th><th><?php echo ame_l('বাকি', 'Outstanding'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><a href="loan.php?id=<?php echo (int)$r['id']; ?>"><?php echo ame_bloan_h((string)$r['loan_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$r['provider_name']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['approved_amount']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['disbursed_amount']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['installment_amount']); ?> · <?php echo (int)$r['tenure_months']; ?> <?php echo ame_l('মাস', 'months'); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['principal_outstanding']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$r['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
