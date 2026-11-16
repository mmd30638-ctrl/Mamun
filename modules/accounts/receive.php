<?php
/**
 * বাহির থেকে টাকা রিসিভ — যেকোনো ব্যক্তি (কাস্টমার তালিকায় নেই এমনও)
 * থেকে টাকা পেলে সহজে ক্যাশ/ব্যাংক অ্যাকাউন্টে জমা করার সহজ ফর্ম।
 * "সাধারণ লেনদেন" (add_transaction.php)-এর হালকা, একক-উদ্দেশ্যের সংস্করণ —
 * ক্যাটেগরি/মার্চেন্ট নিয়ে না ভেবে শুধু "কার কাছ থেকে, কত, কোন অ্যাকাউন্টে"।
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
ensure_accounts_schema($db);

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$flash = (string) ($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$accounts = ame_active_accounts($db);

$ame_body_class = 'ame-txn-page';
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/cust-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.64', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.319', ENT_QUOTES, 'UTF-8'); ?>">

<div class="txn-wrap">
  <header class="txn-hero">
    <h1>বাহির থেকে টাকা রিসিভ</h1>
    <p><?php echo ame_pair('কাস্টমার তালিকায় নেই এমন কেউ (আত্মীয়, পার্টনার, বিনিয়োগ, ধার ইত্যাদি) টাকা দিলে এখান থেকে জমা করুন। ক্যাশ/ব্যাংকে যোগ হবে, হিসাবেও এন্ট্রি বসবে।', 'Someone outside your customer list handed you cash — record it here. It adds to cash/bank and logs an entry in accounts.'); ?></p>
  </header>

  <?php if ($flash !== ''): ?>
    <div class="txn-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php
      if (str_contains($flash, ' / ')) {
          [$bn, $en] = explode(' / ', $flash, 2);
          echo htmlspecialchars($bn, ENT_QUOTES, 'UTF-8');
      } else {
          echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8');
      }
    ?></div>
  <?php endif; ?>

  <div class="txn-toolbar no-print">
    <a class="txn-btn ghost" href="add_transaction.php">সাধারণ লেনদেন</a>
    <a class="txn-btn ghost" href="cash_book.php">ক্যাশ বই</a>
  </div>

  <?php if (count($accounts) > 0): ?>
  <form class="cdesk-card cdesk-form" method="post" action="actions/process_receive.php" data-confirm="বাহির থেকে টাকা জমা নিচ্ছেন? ক্যাশ/ব্যাংক বাড়বে, হিসাবে জমা বসবে।

Receive this money? Cash/bank goes up and an income entry is logged.">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
    <label class="cdesk-field">
      <span><?php echo ame_pair('কার কাছ থেকে', 'Received from'); ?></span>
      <input type="text" name="source_name" placeholder="নাম লিখুন · e.g. Rahim Uddin" required maxlength="150">
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('মোবাইল', 'Mobile'); ?></span>
      <input type="text" name="source_phone" placeholder="ঐচ্ছিক · optional" maxlength="20">
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('পরিমাণ', 'Amount'); ?></span>
      <input type="number" name="amount" step="0.01" min="0.01" required>
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('তারিখ', 'Date'); ?></span>
      <input type="date" name="receive_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('যে অ্যাকাউন্টে জমা হবে', 'Deposit into account'); ?></span>
      <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required>
        <option value=""><?php echo htmlspecialchars('বাছুন · Choose', ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars((string) $a['account_name'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo ame_balance_label((float) $a['balance']); ?></option>
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
      <textarea name="note" rows="2" placeholder="যেমন: ব্যবসায় বিনিয়োগ, ধার, অগ্রিম ইত্যাদি · optional"></textarea>
    </label>
    <div class="full">
      <button type="submit" class="cdesk-btn"><?php echo ame_pair('টাকা রিসিভ করুন', 'Receive money'); ?></button>
    </div>
  </form>
  <?php else: ?>
    <p class="cdesk-empty"><?php echo ame_pair('সক্রিয় ক্যাশ/ব্যাংক অ্যাকাউন্ট নেই। আগে একটা অ্যাকাউন্ট যোগ করুন।', 'No active cash/bank account. Add one first.'); ?></p>
    <a class="cdesk-btn ghost" href="add.php"><?php echo ame_pair('নতুন অ্যাকাউন্ট যোগ করুন', 'Add an account'); ?></a>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
