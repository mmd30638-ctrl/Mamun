<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);

$userId = (int)($_SESSION['user_id'] ?? 0);
$syncError = '';
$idList = $db->query('SELECT id FROM bloan_facilities ORDER BY id');
if ($idList) {
    while ($row = $idList->fetch_assoc()) {
        try {
            ame_bloan_facility_sync($db, (int)$row['id'], $userId);
        } catch (Throwable $e) {
            if ($syncError === '') {
                $syncError = $e->getMessage();
            }
        }
    }
}

$providers = [];
$providerRes = $db->query("SELECT id, name FROM bloan_providers WHERE status='active' ORDER BY name");
if ($providerRes) {
    $providers = $providerRes->fetch_all(MYSQLI_ASSOC);
}

$listRes = $db->query("SELECT f.*, p.name provider_name, a.account_name,
    (SELECT m.balance_after FROM bloan_facility_months m WHERE m.facility_id=f.id ORDER BY m.period_key DESC, m.id DESC LIMIT 1) AS running_balance
    FROM bloan_facilities f
    JOIN bloan_providers p ON p.id=f.provider_id
    LEFT JOIN accounts a ON a.id=f.account_id
    ORDER BY f.status='active' DESC, f.id DESC");
$facilities = $listRes ? $listRes->fetch_all(MYSQLI_ASSOC) : [];

$viewId = (int)($_GET['id'] ?? 0);
$view = null;
$months = [];
if ($viewId > 0) {
    $view = ame_bloan_facility_row($db, $viewId);
    if ($view) {
        $monthStmt = $db->prepare('SELECT period_key, txn_date, amount, balance_after FROM bloan_facility_months WHERE facility_id=? ORDER BY period_key, id');
        $monthStmt->bind_param('i', $viewId);
        $monthStmt->execute();
        $months = $monthStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $monthStmt->close();
        $acct = $db->prepare('SELECT account_name FROM accounts WHERE id=?');
        $accountId = (int)$view['account_id'];
        $acct->bind_param('i', $accountId);
        $acct->execute();
        $acctRow = $acct->get_result()->fetch_assoc();
        $acct->close();
        $view['account_name'] = (string)($acctRow['account_name'] ?? '');
    }
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('মাসিক সুবিধা', 'Monthly facility'); ?></h1>
    <p class="en"><?php echo ame_l('একটি প্রতিষ্ঠানকে প্রতি মাসে একই টাকা দেওয়া হবে। মেয়াদ নেই। যতদিন চালু রাখবেন, ততদিন প্রতি মাসে নির্বাচিত অ্যাকাউন্ট থেকে একবার টাকা যাবে। বন্ধ করলে পরের মাস আর কাটা হবে না। আগের মাস মুছে যাবে না।', 'One institution is paid the same amount every month. There is no term. While it stays on, the chosen account is debited once each month. Stopping skips later months and keeps the months already paid.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <?php if ($syncError !== '' && empty($_GET['err'])): ?>
        <p class="err"><?php echo ame_bloan_h($syncError); ?></p>
    <?php endif; ?>

    <h2><?php echo ame_l('নতুন মাসিক সুবিধা', 'New monthly facility'); ?></h2>
    <form class="grid" method="post" action="actions.php" id="blFacilityForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="facility_open">
        <?php if ($providers): ?>
        <label class="wide"><?php echo ame_l('ঋণদাতা', 'Lender'); ?>
            <select name="provider_id" required>
                <option value=""><?php echo ame_l('নির্বাচন', 'Select'); ?></option>
                <?php foreach ($providers as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo ame_bloan_h((string)$p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php else: ?>
        <label class="wide"><?php echo ame_l('প্রতিষ্ঠানের নাম', 'Institution name'); ?>
            <input name="institution_name" required placeholder="<?php echo ame_bloan_h(ame_l('যে প্রতিষ্ঠানকে প্রতি মাসে দেওয়া হবে', 'Institution paid every month')); ?>">
        </label>
        <?php endif; ?>
        <label><?php echo ame_l('মাসিক টাকা', 'Monthly amount'); ?>
            <input type="number" step="0.01" min="0.01" name="monthly_amount" required>
        </label>
        <label><?php echo ame_l('মেয়াদ', 'Term'); ?>
            <input value="<?php echo ame_bloan_h(ame_l('মেয়াদ নেই', 'No term')); ?>" readonly tabindex="-1">
        </label>
        <label class="wide"><?php echo ame_l('যে অ্যাকাউন্ট থেকে দেওয়া হবে', 'Account the money leaves'); ?>
            <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required><?php echo ame_bloan_account_options($db); ?></select>
        </label>
        <div class="wide"><button type="submit" id="blFacilityStart"><?php echo ame_l('শুরু করুন', 'Start'); ?></button></div>
    </form>

    <h2><?php echo ame_l('সুবিধার তালিকা', 'Facilities'); ?></h2>
    <?php if (!$facilities): ?>
        <p class="empty"><?php echo ame_l('এখনো কোনো মাসিক সুবিধা চালু হয়নি। একটি প্রতিষ্ঠান বেছে মাসিক টাকা দিন। মেয়াদ লাগবে না।', 'No monthly facility is open yet. Choose one institution and a monthly amount. A term is not required.'); ?></p>
    <?php else: ?>
    <table>
        <tr>
            <th><?php echo ame_l('প্রতিষ্ঠান', 'Institution'); ?></th>
            <th><?php echo ame_l('মাসিক টাকা', 'Monthly amount'); ?></th>
            <th><?php echo ame_l('অ্যাকাউন্ট', 'Account'); ?></th>
            <th><?php echo ame_l('অবস্থা', 'Status'); ?></th>
            <th><?php echo ame_l('মোট দেওয়া', 'Total paid'); ?></th>
            <th></th>
        </tr>
        <?php foreach ($facilities as $f): ?>
        <tr>
            <td><a href="facility.php?id=<?php echo (int)$f['id']; ?>"><?php echo ame_bloan_h((string)$f['provider_name']); ?></a></td>
            <td><?php echo ame_bloan_taka((float)$f['monthly_amount']); ?></td>
            <td><?php echo ame_bloan_h((string)($f['account_name'] ?? '')); ?></td>
            <td><b class="badge st-<?php echo ame_bloan_h((string)$f['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$f['status'])); ?></b></td>
            <td><?php echo ame_bloan_taka((float)($f['running_balance'] ?? 0)); ?></td>
            <td>
                <?php if ((string)$f['status'] === 'active'): ?>
                <form class="bl-inline" method="post" action="actions.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="facility_stop">
                    <input type="hidden" name="facility_id" value="<?php echo (int)$f['id']; ?>">
                    <button type="submit"><?php echo ame_l('বন্ধ করুন', 'Stop'); ?></button>
                </form>
                <?php else: ?>
                <a class="btn" href="facility.php?id=<?php echo (int)$f['id']; ?>"><?php echo ame_l('হিসাব', 'Statement'); ?></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($view): ?>
    <h2><?php echo ame_bloan_h((string)$view['provider_name']); ?></h2>
    <div class="bl-cards">
        <div><small><?php echo ame_l('মাসিক টাকা', 'Monthly amount'); ?></small><b><?php echo ame_bloan_taka((float)$view['monthly_amount']); ?></b></div>
        <div><small><?php echo ame_l('মেয়াদ', 'Term'); ?></small><b><?php echo ame_l('মেয়াদ নেই', 'No term'); ?></b></div>
        <div><small><?php echo ame_l('অবস্থা', 'Status'); ?></small><b class="badge st-<?php echo ame_bloan_h((string)$view['status']); ?>"><?php echo ame_bloan_h(ame_bloan_label((string)$view['status'])); ?></b></div>
        <div><small><?php echo ame_l('অ্যাকাউন্ট', 'Account'); ?></small><b><?php echo ame_bloan_h((string)$view['account_name']); ?></b></div>
    </div>
    <form class="bl-inline" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="facility_post">
        <input type="hidden" name="facility_id" value="<?php echo (int)$view['id']; ?>">
        <label><?php echo ame_l('বাদ পড়া মাস', 'Missed month'); ?>
            <input type="month" name="year_month" required>
        </label>
        <button type="submit"><?php echo ame_l('এই মাস পূরণ', 'Fill this month'); ?></button>
    </form>
    <table id="blFacilityStatement">
        <tr>
            <th><?php echo ame_l('মাস', 'Month'); ?></th>
            <th><?php echo ame_l('দেওয়া', 'Paid out'); ?></th>
            <th><?php echo ame_l('বাকি', 'Running balance'); ?></th>
        </tr>
        <?php if (!$months): ?>
        <tr><td colspan="3"><?php echo ame_l('এই সুবিধায় এখনো কোনো মাস কাটা হয়নি।', 'No month has been paid on this facility yet.'); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($months as $m): ?>
        <tr>
            <td><?php echo ame_bloan_h(substr((string)$m['period_key'], 2)); ?></td>
            <td>−<?php echo ame_bloan_taka((float)$m['amount']); ?></td>
            <td><?php echo ame_bloan_taka((float)$m['balance_after']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
