<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT a.*, p.name provider_name, p.branch FROM bloan_accounts a JOIN bloan_providers p ON p.id=a.provider_id WHERE a.id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$loan) {
    header('Location: /modules/bloan/loans.php');
    exit;
}
ame_bloan_refresh($db, $id);
$inst = $db->prepare('SELECT * FROM bloan_installments WHERE loan_id=? ORDER BY installment_no');
$inst->bind_param('i', $id);
$inst->execute();
$installments = $inst->get_result();
$tx = $db->prepare('SELECT * FROM bloan_txns WHERE loan_id=? ORDER BY id');
$tx->bind_param('i', $id);
$tx->execute();
$txns = $tx->get_result();
$docs = $db->prepare('SELECT * FROM bloan_documents WHERE loan_id=? OR application_id=? ORDER BY id DESC');
$appId = (int)$loan['application_id'];
$docs->bind_param('ii', $id, $appId);
$docs->execute();
$documents = $docs->get_result();
$quote = ame_bloan_settlement_quote($db, $id);
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_bloan_h((string)$loan['loan_no']); ?></h1>
    <p class="en"><?php echo ame_bloan_h((string)$loan['provider_name']); ?> · <?php echo ame_bloan_h((string)$loan['interest_rate']); ?>% · <?php echo (int)$loan['tenure_months']; ?> months</p>
    <?php ame_bloan_flash(); ?>
    <div class="bl-cards">
        <div><small><?php echo ame_l('অনুমোদিত', 'Approved'); ?></small><b><?php echo ame_bloan_taka((float)$loan['approved_amount']); ?></b></div>
        <div><small><?php echo ame_l('গ্রহণ', 'Disbursed'); ?></small><b><?php echo ame_bloan_taka((float)$loan['disbursed_amount']); ?></b></div>
        <div><small><?php echo ame_l('কিস্তি', 'Installment'); ?></small><b><?php echo ame_bloan_taka((float)$loan['installment_amount']); ?></b></div>
        <div><small><?php echo ame_l('বাকি', 'Outstanding'); ?></small><b><?php echo ame_bloan_taka((float)$loan['principal_outstanding']); ?></b></div>
    </div>
    <?php if ($loan['status'] === 'active'): ?>
    <h2><?php echo ame_l('কিস্তি পরিশোধ', 'Installment payment'); ?></h2>
    <form class="grid" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="loan_id" value="<?php echo $id; ?>">
        <label><?php echo ame_l('আসল', 'Principal'); ?><input type="number" step="0.01" name="principal_part" required></label>
        <label><?php echo ame_l('সুদ', 'Interest'); ?><input type="number" step="0.01" name="interest_part" value="0"></label>
        <label><?php echo ame_l('চার্জ', 'Charge'); ?><input type="number" step="0.01" name="charge_part" value="0"></label>
        <label><?php echo ame_l('পেমেন্ট অ্যাকাউন্ট', 'Payment account'); ?><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required><?php echo ame_bloan_account_options($db); ?></select></label>
        <label><?php echo ame_l('মাধ্যম', 'Method'); ?><select name="method"><option value="bank"><?php echo ame_l('ব্যাংক', 'Bank'); ?></option><option value="cash"><?php echo ame_l('ক্যাশ', 'Cash'); ?></option><option value="mobile"><?php echo ame_l('মোবাইল', 'Mobile'); ?></option></select></label>
        <label><?php echo ame_l('রসিদ নম্বর', 'Receipt no.'); ?><input name="reference_no"></label>
        <label class="wide"><?php echo ame_l('নোট', 'Notes'); ?><input name="notes"></label>
        <div class="wide"><button type="submit"><?php echo ame_l('আংশিক বা পূর্ণ পরিশোধ', 'Pay partial or full'); ?></button></div>
    </form>
    <?php if (ame_bloan_is_admin()): ?>
    <h2><?php echo ame_l('আগাম পরিশোধ', 'Early settlement'); ?></h2>
    <form class="grid" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="settle">
        <input type="hidden" name="loan_id" value="<?php echo $id; ?>">
        <label><?php echo ame_l('বাকি আসল', 'Remaining principal'); ?><input value="<?php echo ame_bloan_h((string)$quote['principal']); ?>" readonly></label>
        <label><?php echo ame_l('প্রযোজ্য সুদ', 'Applicable interest'); ?><input value="<?php echo ame_bloan_h((string)$quote['interest']); ?>" readonly></label>
        <label><?php echo ame_l('সেটেলমেন্ট চার্জ', 'Settlement charge'); ?><input type="number" step="0.01" name="charge_part" value="0"></label>
        <label><?php echo ame_l('মোট', 'Total'); ?><input value="<?php echo ame_bloan_h((string)round($quote['principal'] + $quote['interest'], 2)); ?>" readonly></label>
        <label class="wide"><?php echo ame_l('অ্যাকাউন্ট', 'Account'); ?><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required><?php echo ame_bloan_account_options($db); ?></select></label>
        <div class="wide"><button type="submit"><?php echo ame_l('ঋণ বন্ধ করুন', 'Close the loan'); ?></button></div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <h2><?php echo ame_l('কিস্তি', 'Installments'); ?></h2>
    <table>
        <tr><th>#</th><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th><th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th><?php echo ame_l('পরিশোধ', 'Paid'); ?></th><th><?php echo ame_l('বাকি', 'Remaining'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr>
        <?php while ($r = $installments->fetch_assoc()): ?>
        <tr>
            <td><?php echo (int)$r['installment_no']; ?></td>
            <td><?php echo ame_bloan_h((string)$r['due_date']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['principal_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['interest_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['total_due']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['paid_amount']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['remaining_amount']); ?></td>
            <td><span class="badge st-<?php echo ame_bloan_h((string)$r['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$r['status'])); ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <h2><?php echo ame_l('খাতা', 'Ledger'); ?></h2>
    <table>
        <tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('বিবরণ', 'Description'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th><th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('পেমেন্ট', 'Payment'); ?></th><th><?php echo ame_l('ব্যালেন্স', 'Balance'); ?></th></tr>
        <?php while ($r = $txns->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['txn_date']); ?></td>
            <td><a href="receipt.php?id=<?php echo (int)$r['id']; ?>"><?php echo ame_bloan_h((string)$r['kind']); ?></a> <?php echo ame_bloan_h((string)$r['notes']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['principal_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['interest_part']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['total']); ?></td>
            <td><?php echo ame_bloan_taka((float)$r['balance_after']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <h2><?php echo ame_l('ডকুমেন্ট', 'Documents'); ?></h2>
    <form class="grid" method="post" action="actions.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="document">
        <input type="hidden" name="loan_id" value="<?php echo $id; ?>">
        <input type="hidden" name="application_id" value="<?php echo $appId; ?>">
        <input type="hidden" name="back" value="/modules/bloan/loan.php?id=<?php echo $id; ?>">
        <label><?php echo ame_l('নাম', 'Title'); ?><input name="title" required></label>
        <label><?php echo ame_l('ফাইল', 'File'); ?><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <label class="wide"><?php echo ame_l('নোট', 'Notes'); ?><input name="notes"></label>
        <div class="wide"><button type="submit"><?php echo ame_l('যোগ করুন', 'Add'); ?></button></div>
    </form>
    <ul>
        <?php while ($d = $documents->fetch_assoc()): ?>
        <li><?php echo ame_bloan_h((string)$d['title']); ?>
            <?php if ($d['file_name'] !== ''): ?><a href="/uploads/business-loans/<?php echo rawurlencode((string)$d['file_name']); ?>" target="_blank"><?php echo ame_l('ফাইল', 'File'); ?></a><?php endif; ?>
        </li>
        <?php endwhile; ?>
    </ul>
</div>
<?php include '../../partials/footer.php'; ?>
