<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/pay_mark.php';
ensure_accounts_schema($db);
check_low_balance_alerts($db);
ame_pay_mark_ensure($db);
include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$marked_id = ame_pay_mark_get($db);
$total_balance = 0;
$low_balance_accounts = [];
$all_accounts_check = $db->query("SELECT id, account_name, balance, low_balance_threshold FROM accounts WHERE status = 'active'");
if ($all_accounts_check) {
    while ($chk = $all_accounts_check->fetch_assoc()) {
        $total_balance += (float)$chk['balance'];
        if ($chk['low_balance_threshold'] !== null && (float)$chk['balance'] < (float)$chk['low_balance_threshold']) {
            $low_balance_accounts[] = $chk;
        }
    }
}

$opening_map = [];
$openRes = @$db->query(
    "SELECT account_id,
            COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0) AS n
     FROM account_ledger
     WHERE ref_type = 'opening_balance'
     GROUP BY account_id"
);
if ($openRes) {
    while ($orow = $openRes->fetch_assoc()) {
        $opening_map[(int)$orow['account_id']] = round((float)$orow['n'], 2);
    }
}
$has_def = ame_pay_mark_has_is_default($db);
$order = $has_def ? 'ORDER BY is_default DESC, id DESC' : 'ORDER BY id DESC';
$accounts = $db->query('SELECT * FROM accounts ' . $order);
$rows = [];
$marked_name = '';
if ($accounts) {
    while ($acc = $accounts->fetch_assoc()) {
        $rows[] = $acc;
        if ($marked_id && (int)$acc['id'] === $marked_id) {
            $marked_name = (string)$acc['account_name'];
        }
    }
}
?>
<link rel="stylesheet" href="/assets/css/treasury.808.css?v=<?php echo defined('AME_ASSET_VERSION') ? htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8') : '1'; ?>">
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="ty-wrap">
  <header class="ty-mast">
    <div class="ty-mast-copy">
      <p class="ty-kicker"><?php echo ame_pair('ট্রেজারি ট্রে', 'Treasury tray'); ?></p>
      <h1><?php echo ame_pair('ক্যাশ ও ব্যাংক অ্যাকাউন্ট', 'Cash & Bank accounts'); ?></h1>
      <p><?php echo ame_pair(
          'এখানে ★ মার্ক চাপুন। যে অ্যাকাউন্ট মার্ক থাকবে, POS ও সব পেমেন্ট অপশন সেখানেই অটো কানেক্ট থাকবে — ম্যানুয়ালি বদলানোর আগে পর্যন্ত।',
          'Tap ★ Mark here. That account auto-connects on every payment select until you change it by hand.'
      ); ?></p>
    </div>
    <div class="ty-mast-total">
      <span><?php echo ame_pair('সব সক্রিয় অ্যাকাউন্ট মিলিয়ে মোট ব্যালেন্স', 'Active accounts total'); ?></span>
      <b>৳<?php echo number_format($total_balance, 2); ?></b>
    </div>
  </header>

  <div class="ty-toolbar no-print">
    <a class="ty-btn" href="add.php"><?php echo ame_pair('+ নতুন অ্যাকাউন্ট', '+ New account'); ?></a>
    <a class="ty-btn" href="transfer.php"><?php echo ame_pair('⇄ ট্রান্সফার', '⇄ Transfer'); ?></a>
  </div>

  <section class="ty-mark-banner<?php echo $marked_name !== '' ? ' on' : ''; ?>">
    <?php if ($marked_name !== ''): ?>
      <strong>★ <?php echo htmlspecialchars($marked_name, ENT_QUOTES, 'UTF-8'); ?></strong>
      <span><?php echo ame_pair('ডিফল্ট পেমেন্ট — সব অপশনে অটো কানেক্ট', 'Default pay account — auto-connected everywhere'); ?></span>
    <?php else: ?>
      <strong><?php echo ame_pair('এখনো মার্ক নেই', 'No account marked'); ?></strong>
      <span><?php echo ame_pair('নিচের সারিতে ★ মার্ক চাপুন।', 'Tap ★ Mark on a row below.'); ?></span>
    <?php endif; ?>
  </section>

  <?php if (count($low_balance_accounts) > 0): ?>
  <div class="ty-warn">
    <strong><?php echo ame_pair('কম ব্যালেন্স সতর্কতা', 'Low balance alert'); ?>:</strong>
    <?php foreach ($low_balance_accounts as $lb): ?>
      <div>
        <?php echo htmlspecialchars((string)$lb['account_name'], ENT_QUOTES, 'UTF-8'); ?>
        — বর্তমান ৳<?php echo number_format((float)($lb['balance'] ?? 0), 2); ?>
        (সীমা ৳<?php echo number_format((float)($lb['low_balance_threshold'] ?? 0), 2); ?>-এর নিচে)
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <section class="ty-tray">
    <?php if (!$rows): ?>
      <p class="ty-empty"><?php echo ame_pair('কোনো অ্যাকাউন্ট যোগ করা হয়নি।', 'No accounts have been added.'); ?></p>
    <?php else: ?>
    <div class="ty-table-wrap">
      <table>
        <thead>
          <tr>
            <th><?php echo ame_pair('মার্ক', 'Mark'); ?></th>
            <th><?php echo ame_pair('অ্যাকাউন্টের নাম', 'Account'); ?></th>
            <th><?php echo ame_pair('ধরণ', 'Type'); ?></th>
            <th class="num"><?php echo ame_pair('ওপেনিং', 'Opening'); ?></th>
            <th class="num"><?php echo ame_pair('বর্তমান ব্যালেন্স', 'Balance'); ?></th>
            <th><?php echo ame_pair('স্ট্যাটাস', 'Status'); ?></th>
            <th class="acc-print-hide">#</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $acc):
              $id = (int)$acc['id'];
              $acc_status = $acc['status'] ?? 'active';
              $is_active = ($acc_status === 'active');
              $is_on = $marked_id && $id === $marked_id;
              $native_def = $has_def && !empty($acc['is_default']);
              $post_id = $is_on ? 0 : $id;
          ?>
          <tr class="<?php echo $is_on ? 'marked' : ''; ?>">
            <td>
              <form method="POST" action="mark_pay_account.php" class="ty-star-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="account_id" value="<?php echo $post_id; ?>">
                <button type="submit" class="ty-star<?php echo $is_on ? ' on' : ''; ?>" aria-pressed="<?php echo $is_on ? 'true' : 'false'; ?>">
                  <?php echo $is_on ? '★' : '☆'; ?>
                  <span><?php echo $is_on ? ame_pair('মার্ক আছে', 'Marked') : ame_pair('মার্ক করুন', 'Mark'); ?></span>
                </button>
              </form>
            </td>
            <td class="name">
              <?php echo htmlspecialchars((string)$acc['account_name'], ENT_QUOTES, 'UTF-8'); ?>
              <?php if ($is_on || $native_def): ?>
                <em class="ty-chip" style="--ty-chip:#3f6212;">Default</em>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars((string)$acc['account_type'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num">
              <?php echo number_format((float)($opening_map[$id] ?? 0), 2); ?> ৳
            </td>
            <td class="num<?php echo (float)($acc['balance'] ?? 0) < 0 ? ' neg' : ''; ?>">
              <?php echo number_format((float)($acc['balance'] ?? 0), 2); ?> ৳
            </td>
            <td>
              <span class="ty-status <?php echo $is_active ? 'ok' : 'off'; ?>">
                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
              </span>
            </td>
            <td class="acc-print-hide">
              <div class="ty-ops">
                <a class="ty-link led" href="ledger.php?id=<?php echo $id; ?>"><?php echo ame_pair('লেজার', 'Ledger'); ?></a>
                <a class="ty-link edit" href="edit_account.php?id=<?php echo $id; ?>#acc-opening"><?php echo ame_pair('ওপেনিং', 'Opening'); ?></a>
                <a class="ty-link edit" href="edit_account.php?id=<?php echo $id; ?>"><?php echo ame_pair('এডিট', 'Edit'); ?></a>
                <?php if (!$native_def && !$is_on): ?>
                  <?php if ($is_active): ?>
                  <form action="deactivate.php" method="post" onsubmit="return confirm('আপনি কি নিশ্চিত এই অ্যাকাউন্ট বন্ধ করতে চান?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="ty-link off"><?php echo ame_pair('বন্ধ করুন', 'Close'); ?></button>
                  </form>
                  <?php else: ?>
                  <form action="reactivate.php" method="post" onsubmit="return confirm('আপনি কি নিশ্চিত এই অ্যাকাউন্ট আবার চালু করতে চান?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="ty-link on"><?php echo ame_pair('চালু করুন', 'Reopen'); ?></button>
                  </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php include '../../partials/footer.php'; ?>
