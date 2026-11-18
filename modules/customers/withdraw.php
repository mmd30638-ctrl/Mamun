<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$id = (int) ($_GET['id'] ?? 0);
$flash = (string) ($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$accounts = ame_active_accounts($db);

$picked = null;
if ($id > 0) {
    $st = $db->prepare('SELECT id, customer_code, name, phone, address, receivable_balance FROM customers WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $id);
        $st->execute();
        $picked = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

$list = [];
$res = @$db->query('SELECT id, customer_code, name, phone, receivable_balance FROM customers WHERE is_customer = 1 ORDER BY (receivable_balance < 0) DESC, name ASC LIMIT 500');
if ($res) {
    $list = $res->fetch_all(MYSQLI_ASSOC);
}

$held_n = 0;
$held_sum = 0.0;
foreach ($list as $c) {
    $h = ame_customer_shop_held((float) $c['receivable_balance']);
    if ($h > 0) {
        $held_n++;
        $held_sum += $h;
    }
}

$held = $picked ? ame_customer_shop_held((float) $picked['receivable_balance']) : 0.0;

$wdw_msg = $_SESSION['wdw_msg'] ?? null;
unset($_SESSION['wdw_msg']);
if (!is_array($wdw_msg) && $flash !== '') {
    $bn = $flash;
    $en = '';
    if (str_contains($flash, ' / ')) {
        [$bn, $en] = explode(' / ', $flash, 2);
    }
    $wdw_msg = [
        'kind' => $flash_ok ? 'ok' : 'fail',
        'title_bn' => $flash_ok ? 'জমা টাকা তোলা হয়েছে' : 'উত্তোলন হয়নি',
        'title_en' => $flash_ok ? 'Deposit paid out' : 'Payout not recorded',
        'bn' => $bn,
        'en' => $en,
    ];
}

$ame_body_class = 'ame-withdraw-page';
$wdw_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.577', ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/cust-desk.css?v=<?php echo $wdw_ver; ?>">
<link rel="stylesheet" href="/assets/css/withdraw-desk.css?v=<?php echo $wdw_ver; ?>">

<div class="cdesk-wrap">
  <header class="cdesk-mast">
    <p class="cdesk-kicker"><?php echo ame_pair('দোকানের জমা', 'Shop deposit'); ?></p>
    <h1><?php echo ame_pair('জমা টাকা তোলা', 'Payout deposit'); ?></h1>
    <p><?php echo ame_pair('যাদের টাকা দোকানে জমা আছে — তারা কিছু বা সব তুলতে চাইলে এখান থেকে দেন। ক্যাশ/ব্যাংক থেকে কাটে, লেজারে উত্তোলন বসে।', 'Pay out cash the shop is holding for a customer. Money leaves cash/bank and a withdrawal hits the statement.'); ?></p>
  </header>

  <div class="cdesk-stats">
    <article class="is-left">
      <span><?php echo ame_pair('জমা আছে', 'Have deposit'); ?></span>
      <b><?php echo (int) $held_n; ?></b>
    </article>
    <article class="is-in">
      <span><?php echo ame_pair('মোট জমা', 'Held total'); ?></span>
      <b>৳<?php echo number_format($held_sum, 2); ?></b>
    </article>
    <article class="is-pick">
      <span><?php echo ame_pair('নির্বাচিত জমা', 'Selected hold'); ?></span>
      <b>৳<?php echo number_format($held, 2); ?></b>
    </article>
  </div>

  <?php if (is_array($wdw_msg)):
      $wdw_kind = preg_replace('/[^a-z]/', '', strtolower((string) ($wdw_msg['kind'] ?? 'fail'))) ?: 'fail';
      $wdw_icon = $wdw_kind === 'ok' ? '৳' : ($wdw_kind === 'cash' ? '!' : ($wdw_kind === 'already' ? '✓' : ($wdw_kind === 'need' ? '?' : '×')));
  ?>
    <aside class="wdw-slip wdw-slip-<?php echo htmlspecialchars($wdw_kind, ENT_QUOTES, 'UTF-8'); ?>" id="wdwMsg" role="alert">
      <div class="wdw-slip-punches" aria-hidden="true"></div>
      <div class="wdw-slip-inner">
        <span class="wdw-slip-seal" aria-hidden="true"><?php echo $wdw_icon; ?></span>
        <p class="wdw-slip-kicker">APONSHOP · জমা উত্তোলন</p>
        <h2><?php echo htmlspecialchars((string) ($wdw_msg['title_bn'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="wdw-slip-en"><?php echo htmlspecialchars((string) ($wdw_msg['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (trim((string) ($wdw_msg['bn'] ?? '')) !== ''): ?>
          <p class="wdw-slip-body"><?php echo htmlspecialchars((string) $wdw_msg['bn'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($wdw_msg['en'] ?? '')) !== ''): ?>
          <p class="wdw-slip-en"><?php echo htmlspecialchars((string) $wdw_msg['en'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
    </aside>
  <?php endif; ?>

  <div class="cdesk-toolbar no-print">
    <a class="cdesk-btn ghost" href="index.php"><?php echo ame_pair('কাস্টমার তালিকা', 'Customer list'); ?></a>
    <a class="cdesk-btn ghost" href="ledger.php<?php echo $id > 0 ? ('?id=' . $id) : ''; ?>"><?php echo ame_pair('সম্পূর্ণ স্টেটমেন্ট', 'Full statement'); ?></a>
    <a class="cdesk-btn ghost" href="withdrawals_report.php<?php echo $id > 0 ? ('?id=' . $id) : ''; ?>"><?php echo ame_pair('উত্তোলন রিপোর্ট', 'Withdrawal report'); ?></a>
  </div>

  <?php if (!$picked): ?>
  <section class="cdesk-card">
    <input class="cdesk-search" type="search" id="wdSearch" placeholder="নাম, কোড বা ফোন · name, code or phone" autocomplete="off">
    <div id="wdList">
      <?php foreach ($list as $c):
          $h = ame_customer_shop_held((float) $c['receivable_balance']);
          $d = ame_customer_shop_due((float) $c['receivable_balance']);
          $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $c['name']) : (string) $c['name'];
      ?>
      <a class="cdesk-row" href="withdraw.php?id=<?php echo (int) $c['id']; ?>" data-q="<?php echo htmlspecialchars(strtolower($name . ' ' . $c['customer_code'] . ' ' . ($c['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
        <span>
          <b><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></b><br>
          <small><?php echo htmlspecialchars((string) $c['customer_code'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($c['phone']) ? ' · ' . htmlspecialchars((string) $c['phone'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
        </span>
        <?php if ($h > 0): ?>
          <span class="cdesk-held">৳<?php echo number_format($h, 2); ?></span>
        <?php elseif ($d > 0): ?>
          <span class="cdesk-due">বকেয়া ৳<?php echo number_format($d, 2); ?></span>
        <?php else: ?>
          <span class="cdesk-meta">৳0.00</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <p class="cdesk-empty" id="wdEmpty" hidden><?php echo ame_pair('কোনো কাস্টমার মিলছে না।', 'No customer matches.'); ?></p>
  </section>
  <?php else:
      $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $picked['name']) : (string) $picked['name'];
  ?>
  <section class="cdesk-card">
    <div class="cdesk-who"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="cdesk-meta">
      <?php echo htmlspecialchars((string) $picked['customer_code'], ENT_QUOTES, 'UTF-8'); ?>
      <?php echo !empty($picked['phone']) ? ' · ' . htmlspecialchars((string) $picked['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>
    </div>
    <p><?php echo $held > 0
      ? ame_pair('দোকানে জমা আছে ৳' . number_format($held, 2) . ' — এর বেশি তুললে বাড়তি অংশ কাস্টমারের বকেয়া হিসেবে যোগ হবে।', 'Shop is holding ৳' . number_format($held, 2) . ' — anything paid out beyond this becomes the customer\'s due.')
      : ame_pair('এই কাস্টমারের দোকানে জমা নেই — তারপরও টাকা দিলে সেটা পুরোটাই কাস্টমারের বকেয়া হিসেবে যোগ হবে।', 'This customer has no shop deposit — any payout will be added entirely as the customer\'s due.'); ?></p>
    <a class="cdesk-btn ghost" href="withdraw.php"><?php echo ame_pair('অন্য কাস্টমার', 'Another customer'); ?></a>
  </section>

  <?php if (count($accounts) > 0): ?>
  <form class="cdesk-card cdesk-form" method="post" action="actions/process_withdraw.php" data-ame-once="1" data-confirm-type="withdraw" data-confirm="জমা টাকা তুলবেন? এই টাকা ক্যাশ/ব্যাংক থেকে যাবে এবং স্টেটমেন্টে উত্তোলন বসবে। জমার চেয়ে বেশি দিলে বাড়তি অংশ কাস্টমারের বকেয়া হবে।

Pay out this deposit? Money leaves cash/bank and the statement gets a withdrawal. Anything beyond the held amount becomes the customer's due.">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="customer_id" value="<?php echo (int) $picked['id']; ?>">
    <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
    <label class="cdesk-field">
      <span><?php echo ame_pair('তোলার পরিমাণ', 'Payout amount'); ?></span>
      <input type="number" name="amount" step="0.01" min="0.01" required>
      <?php if ($held > 0): ?>
      <small style="color:#64748b;">জমা আছে ৳<?php echo number_format($held, 2); ?> — এর বেশি লিখলে বাড়তি অংশ বকেয়া হবে।</small>
      <?php else: ?>
      <small style="color:#B71C1C;">⚠️ কোনো জমা নেই — যা লিখবেন পুরোটাই কাস্টমারের বকেয়া হিসেবে যোগ হবে।</small>
      <?php endif; ?>
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('তারিখ', 'Date'); ?></span>
      <input type="date" name="withdraw_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('যে অ্যাকাউন্ট থেকে যাবে', 'Account money leaves'); ?></span>
      <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" required>
        <option value=""><?php echo htmlspecialchars('বাছুন · Choose', ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars(ame_account_option_label($a), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('মাধ্যম', 'Method'); ?></span>
      <select name="method">
        <option value="CASH">নগদ · Cash</option>
        <option value="BKASH">বিকাশ · bKash</option>
        <option value="NAGAD">নগদ অ্যাপ · Nagad</option>
        <option value="BANK">ব্যাংক · Bank</option>
        <option value="CHEQUE">চেক · Cheque</option>
      </select>
    </label>
    <label class="cdesk-field full">
      <span><?php echo ame_pair('নোট', 'Note'); ?></span>
      <textarea name="note" rows="2" placeholder="ঐচ্ছিক · optional"></textarea>
    </label>
    <div class="full">
      <button type="submit" class="cdesk-btn ame-calc-btn wdw-pay"><?php echo ame_pair('টাকা তুলুন', 'Pay out'); ?></button>
    </div>
  </form>
  <?php else: ?>
    <p class="cdesk-empty"><?php echo ame_pair('সক্রিয় ক্যাশ/ব্যাংক অ্যাকাউন্ট নেই।', 'No active cash/bank account.'); ?></p>
  <?php endif; endif; ?>
</div>
<script>
(function () {
  var box = document.getElementById('wdSearch');
  var list = document.getElementById('wdList');
  var empty = document.getElementById('wdEmpty');
  if (!box || !list) return;
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    var n = 0;
    list.querySelectorAll('.cdesk-row').forEach(function (row) {
      var ok = !q || (row.getAttribute('data-q') || '').indexOf(q) !== -1;
      row.hidden = !ok;
      if (ok) n++;
    });
    if (empty) empty.hidden = n > 0;
  });
})();
(function () {
  var slip = document.getElementById('wdwMsg');
  if (slip && slip.scrollIntoView) {
    slip.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
