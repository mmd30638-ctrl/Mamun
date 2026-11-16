<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
ame_bloan_refresh_open($db);
$sum = ame_bloan_summary($db);
$providers = $db->query("SELECT p.name, p.provider_type, COUNT(a.id) loans, COALESCE(SUM(a.principal_outstanding),0) outstanding, COALESCE(SUM(a.disbursed_amount),0) disbursed FROM bloan_providers p LEFT JOIN bloan_accounts a ON a.provider_id=p.id GROUP BY p.id ORDER BY outstanding DESC");
$alerts = $db->query("SELECT i.*, a.loan_no, p.name FROM bloan_installments i JOIN bloan_accounts a ON a.id=i.loan_id JOIN bloan_providers p ON p.id=a.provider_id WHERE i.status IN ('overdue','due') ORDER BY i.due_date LIMIT 12");
include '../../partials/header.php';
$cards = [
    [ame_l('মোট ঋণ', 'Total loans'), (string)(int)($sum['c'] ?? 0)],
    [ame_l('চালু ঋণ', 'Active'), (string)(int)($sum['active_n'] ?? 0)],
    [ame_l('অনুমোদিত', 'Approved'), ame_bloan_taka((float)($sum['approved'] ?? 0))],
    [ame_l('গ্রহণ', 'Disbursed'), ame_bloan_taka((float)($sum['disbursed'] ?? 0))],
    [ame_l('পরিশোধ', 'Paid'), ame_bloan_taka((float)($sum['paid_total'] ?? 0))],
    [ame_l('বাকি আসল', 'Outstanding'), ame_bloan_taka((float)($sum['outstanding'] ?? 0))],
    [ame_l('এই মাসের কিস্তি', 'This month'), ame_bloan_taka((float)($sum['month_due'] ?? 0))],
    [ame_l('আসন্ন কিস্তি', 'Upcoming'), (string)(int)($sum['upcoming_n'] ?? 0)],
    [ame_l('বকেয়া কিস্তি', 'Overdue'), (string)(int)($sum['overdue_n'] ?? 0) . ' · ' . ame_bloan_taka((float)($sum['overdue_amt'] ?? 0))],
    [ame_l('সুদ পরিশোধ', 'Interest paid'), ame_bloan_taka((float)($sum['interest_paid'] ?? 0))],
    [ame_l('সম্পন্ন', 'Completion'), (float)($sum['completion'] ?? 0) . '%'],
    [ame_l('বন্ধ ঋণ', 'Closed'), (string)(int)($sum['closed_n'] ?? 0)],
];
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ব্যবসার ঋণ', 'Business Loan'); ?></h1>
    <p class="en"><?php echo ame_l('দোকান ব্যাংক, এনজিও বা অনুমোদিত ঋণদাতার কাছ থেকে ঋণ নেয়। কাস্টমারকে ঋণ দেওয়া হয় না।', 'The shop borrows from a bank, NGO, or approved lender. Customers are not lent money.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <div class="bl-cards">
        <?php foreach ($cards as $card): ?>
        <div><small><?php echo ame_bloan_h($card[0]); ?></small><b><?php echo ame_bloan_h($card[1]); ?></b></div>
        <?php endforeach; ?>
    </div>
    <h2><?php echo ame_l('ঋণদাতা অনুযায়ী', 'By provider'); ?></h2>
    <table>
        <tr><th><?php echo ame_l('ঋণদাতা', 'Provider'); ?></th><th><?php echo ame_l('ধরন', 'Type'); ?></th><th><?php echo ame_l('ঋণ', 'Loans'); ?></th><th><?php echo ame_l('গ্রহণ', 'Disbursed'); ?></th><th><?php echo ame_l('বাকি', 'Outstanding'); ?></th></tr>
        <?php if ($providers) while ($p = $providers->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$p['name']); ?></td>
            <td><?php echo ame_bloan_h(ame_bloan_label((string)$p['provider_type'])); ?></td>
            <td><?php echo (int)$p['loans']; ?></td>
            <td><?php echo ame_bloan_taka((float)$p['disbursed']); ?></td>
            <td><?php echo ame_bloan_taka((float)$p['outstanding']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <h2><?php echo ame_l('সতর্কতা', 'Due and overdue'); ?></h2>
    <table>
        <tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('ঋণদাতা', 'Provider'); ?></th><th><?php echo ame_l('বাকি', 'Remaining'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr>
        <?php if ($alerts) while ($a = $alerts->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$a['due_date']); ?></td>
            <td><a href="loan.php?id=<?php echo (int)$a['loan_id']; ?>"><?php echo ame_bloan_h((string)$a['loan_no']); ?></a></td>
            <td><?php echo ame_bloan_h((string)$a['name']); ?></td>
            <td><?php echo ame_bloan_taka((float)$a['remaining_amount']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$a['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$a['status'])); ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
