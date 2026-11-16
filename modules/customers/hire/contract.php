<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
$id = (int)($_GET['id'] ?? 0);
$c = $id > 0 ? hire_contract($db, $id) : null;
if (!$c) {
    include __DIR__ . '/../../../partials/header.php';
    echo '<div class="hire-wrap"><p class="hire-empty">চুক্তি পাওয়া যায়নি।</p></div>';
    include __DIR__ . '/../../../partials/footer.php';
    return;
}
hire_refresh_overdue($db, $id);
$c = hire_contract($db, $id);
$wallet = hire_wallet_for_contract($db, $id);
$due = hire_rental_due($db, $id);
$stmt = hire_statement($db, $id);
$hist = [];
$hs = $db->prepare('SELECT * FROM rental_history WHERE contract_id = ? ORDER BY id DESC LIMIT 80');
$hs->bind_param('i', $id);
$hs->execute();
$hist = $hs->get_result()->fetch_all(MYSQLI_ASSOC);
$hs->close();
$accounts = hire_accounts($db);
$preview = hire_early_return_preview($c, date('Y-m-d'));
$cusDue = (float)$c['receivable_balance'];
$snap = function_exists('hire_due_snapshot') ? hire_due_snapshot($db, $c) : [
    'due' => $due,
    'daily_rate' => (float)$c['rent_rate'],
    'elapsed_days' => 0,
    'charged_days' => 0,
    'unpaid_days' => 0,
    'term_months' => (int)($c['term_months'] ?? 0),
    'term_days' => (int)($c['term_days'] ?? 0),
    'term_total_days' => 0,
    'expected_return' => (string)($c['expected_return'] ?? ''),
    'start_date' => (string)($c['start_date'] ?? ''),
];
$canEdit = hire_can($db, 'edit');
$here = 'contract';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.315', ENT_QUOTES, 'UTF-8');
$canRate = hire_can($db, 'rate');
$canProd = hire_can($db, 'product');
$canDep = hire_can($db, 'deposit');
$canRef = hire_can($db, 'refund');
$canAdj = hire_can($db, 'adjust');
$canCan = hire_can($db, 'cancel');
?>
<?php
$stNow = strtoupper((string) ($c['status'] ?? ''));
$hireOn = in_array($stNow, ['ACTIVE', 'OVERDUE'], true);
$hireOff = $stNow === 'INACTIVE';
$hirePause = $stNow === 'PAUSED';
$hireDone = in_array($stNow, ['CANCELLED', 'COMPLETED', 'RETURNED'], true);
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-pro.css?v=<?php echo $av; ?>">
<div class="hire-wrap hire-contract-page">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <p class="hire-kicker"><?php echo hire_h($c['rental_no']); ?></p>
    <h1><?php echo hire_h($c['item_name']); ?></h1>
    <p>
      <a href="/modules/customers/profile.php?id=<?php echo (int)$c['customer_id']; ?>"><?php echo hire_h($c['customer_name']); ?></a>
      · <?php echo hire_h($c['customer_code']); ?>
      · <?php echo hire_h($c['customer_phone'] ?? ''); ?>
      · <span class="hire-badge st-<?php echo hire_h($c['status']); ?>"><?php echo $hireOn ? 'চালু' : ($hireOff ? 'বন্ধ' : hire_h(strip_tags(hire_status_label($c['status'])))); ?></span>
    </p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="hire-state no-print<?php echo $hireOn ? ' is-on' : ($hireOff ? ' is-off' : ($hirePause ? ' is-pause' : ' is-done')); ?>">
    <div>
      <p class="hire-state-kicker">চুক্তির অবস্থা</p>
      <?php if ($hireOn): ?>
        <h2>চালু</h2>
        <p>প্রতিদিন ভাড়া কাস্টমার খাতায় যোগ হচ্ছে।</p>
      <?php elseif ($hireOff): ?>
        <h2>বন্ধ</h2>
        <p>নতুন দিনের ভাড়া বন্ধ। আগের খাতা আছে।</p>
      <?php elseif ($hirePause): ?>
        <h2>বিরতি</h2>
        <p>সাময়িক বন্ধ। আবার চালু করলে দৈনিক ভাড়া চলবে।</p>
      <?php else: ?>
        <h2><?php echo hire_h(strip_tags(hire_status_label($stNow))); ?></h2>
        <p>এই চুক্তি আর দৈনিক ভাড়া যোগ করছে না।</p>
      <?php endif; ?>
    </div>
    <div class="hire-state-side">
      <span class="hire-state-mark"><?php echo $hireOn ? 'চালু' : ($hireOff ? 'বন্ধ' : hire_h(strip_tags(hire_status_label($stNow)))); ?></span>
      <?php if ($canEdit && ($hireOn || $hirePause)): ?>
      <form method="post" action="actions.php" onsubmit="return confirm('বন্ধ করলে আগামী দিনের ভাড়া খাতায় যোগ হবে না। আগের হিসাব থাকবে।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="deactivate">
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="reason" value="চুক্তি পাতা থেকে বন্ধ">
        <button class="hire-btn hire-btn-off" type="submit">বন্ধ করুন</button>
      </form>
      <?php elseif ($canEdit && $hireOff): ?>
      <form method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="activate">
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="reason" value="চুক্তি পাতা থেকে চালু">
        <button class="hire-btn hire-btn-primary" type="submit">চালু করুন</button>
      </form>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($canCan && !$hireDone): ?>
  <details class="hire-step hire-more no-print">
    <summary>বাতিল বা ভুল এন্ট্রি ডিলিট</summary>
    <p class="hire-muted">খাতার টাকা অন্য মডিউলে যায় না। ডিলিট শুধু ভুল চুক্তির জন্য।</p>
    <div class="hire-actions hire-stop-actions">
      <?php if (!in_array($stNow, ['CANCELLED', 'COMPLETED'], true)): ?>
      <form method="post" action="actions.php" onsubmit="return confirm('বাতিল করলে চুক্তি আর্কাইভ হবে। খাতার টাকা মুছে যাবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="cancel">
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input name="reason" required placeholder="বাতিলের কারণ">
        <button class="hire-btn hire-btn-danger" type="submit">বাতিল</button>
      </form>
      <?php endif; ?>
      <?php if (in_array($stNow, ['DRAFT', 'ACTIVE', 'OVERDUE', 'PAUSED', 'INACTIVE'], true)): ?>
      <form method="post" action="actions.php" onsubmit="return confirm('ভুল/ডাবল চুক্তি সম্পূর্ণ মুছে যাবে। পেমেন্ট থাকলে ডিলিট হবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input name="reason" required placeholder="ডিলিটের কারণ (যেমন: ডাবল এন্ট্রি)">
        <button class="hire-btn hire-btn-danger" type="submit">ভুল এন্ট্রি ডিলিট</button>
      </form>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>

  <section class="hire-cards">
    <article class="hire-card is-due"><span><?php echo ame_pair('বকেয়া দিন', 'Unpaid days'); ?></span><b><?php echo (int)$snap['unpaid_days']; ?></b></article>
    <article class="hire-card is-danger"><span><?php echo ame_pair('ভাড়া বকেয়া', 'Rental due'); ?></span><b><?php echo number_format((float)$snap['due'], 2); ?></b></article>
    <article class="hire-card is-navy"><span><?php echo ame_pair('দৈনিক হার', 'Daily rate'); ?></span><b><?php echo number_format((float)$snap['daily_rate'], 2); ?></b></article>
    <article class="hire-card"><span><?php echo ame_pair('চার্জ হয়েছে', 'Charged days'); ?></span><b><?php echo (int)$snap['charged_days']; ?> / <?php echo (int)$snap['elapsed_days']; ?></b></article>
    <article class="hire-card"><span><?php echo ame_pair('কাস্টমার খাতা', 'Customer ledger'); ?></span><b><?php echo number_format($cusDue, 2); ?></b></article>
    <article class="hire-card is-money"><span><?php echo ame_pair('জামানত অবশিষ্ট', 'Deposit remaining'); ?></span><b><?php echo number_format($wallet['remaining'], 2); ?></b></article>
    <article class="hire-card"><span><?php echo ame_pair('জমা / ব্যবহার / ফেরত', 'In / used / refunded'); ?></span><b><?php echo number_format($wallet['deposited'], 0) . ' / ' . number_format($wallet['used'], 0) . ' / ' . number_format($wallet['refunded'], 0); ?></b></article>
  </section>

  <?php if ($canEdit || $canRate): ?>
  <section class="hire-panel hire-term-editor no-print">
    <h2><?php echo ame_pair('মেয়াদ ও দৈনিক হার — এখানেই এডিট', 'Term and daily rate — edit here'); ?></h2>
    <p class="hire-muted"><?php echo ame_pair('মাসিক ঘর ও দৈনিক ঘর আলাদা। খাতায় দিনে একবার শুধু দৈনিক অংক যোগ হয়।', 'Monthly and daily are separate. The ledger posts the daily amount once each day.'); ?></p>
    <form method="post" action="actions.php" class="hire-grid hire-term-grid">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="term">
      <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <input type="hidden" name="rate_type" value="DAILY">
      <label><?php echo ame_pair('শুরু', 'Start'); ?><input type="date" name="start_date" id="termStart" value="<?php echo hire_h($snap['start_date'] ?: $c['start_date']); ?>" required></label>
      <label><?php echo ame_pair('মাস', 'Months'); ?>
        <select name="term_months" id="termMonths">
          <?php for ($m = 0; $m <= 36; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo (int)$snap['term_months'] === $m ? 'selected' : ''; ?>><?php echo $m; ?> মাস</option>
          <?php endfor; ?>
        </select>
      </label>
      <label><?php echo ame_pair('দিন', 'Days'); ?>
        <select name="term_days" id="termDays">
          <?php
            $selDays = (int)$snap['term_days'];
            if ($selDays === 0 && (int)$snap['term_months'] === 0) {
                $selDays = 1;
            }
          ?>
          <?php for ($d = 0; $d <= 30; $d++): ?>
            <option value="<?php echo $d; ?>" <?php echo $selDays === $d ? 'selected' : ''; ?>><?php echo $d; ?> দিন</option>
          <?php endfor; ?>
        </select>
      </label>
      <label><?php echo ame_pair('মাসিক ভাড়া (৳)', 'Monthly amount'); ?><input type="number" step="0.01" min="0" name="monthly_amount" id="termMonthly" value="<?php echo hire_h(number_format((float)($c['monthly_amount'] ?? 0), 2, '.', '')); ?>"></label>
      <label><?php echo ame_pair('দৈনিক ভাড়া (৳)', 'Daily amount'); ?><input type="number" step="0.01" min="0" name="rent_rate" id="termRate" value="<?php echo hire_h(number_format((float)$snap['daily_rate'], 2, '.', '')); ?>"></label>
      <label><?php echo ame_pair('প্রত্যাশিত ফেরত', 'Expected return'); ?><input type="date" name="expected_return" id="termEnd" value="<?php echo hire_h($snap['expected_return'] ?: ($c['expected_return'] ?? '')); ?>" readonly></label>
      <label><?php echo ame_pair('কারণ', 'Reason'); ?><input name="reason" required placeholder="<?php echo ame_l('কেন বদলাচ্ছেন', 'Why you are changing this'); ?>"></label>
      <p class="hire-term-preview" id="termPreview">মেয়াদ ও হার দিলে এখানে মোট দেখাবে।</p>
      <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_l('মেয়াদ সংরক্ষণ', 'Save term'); ?></button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($canEdit || $canRate): ?>
  <?php
    if (!function_exists('ame_contract_letter_text')) {
        require_once __DIR__ . '/../../../includes/contract_letter.php';
    }
    $hireLetter = ame_contract_letter_text((string)($c['contract_letter'] ?? ''), 'hire');
  ?>
  <section class="hire-panel no-print">
    <h2><?php echo ame_pair('চুক্তি নামা', 'Contract letter'); ?></h2>
    <form method="post" action="actions.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="letter">
      <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <textarea name="contract_letter" rows="12" style="width:100%;min-height:220px;background:#fff;color:#111827;border:1.5px solid #003F8F;border-radius:10px;padding:10px;font-weight:700;"><?php echo hire_h($hireLetter); ?></textarea>
      <p style="margin:8px 0;color:#7c2d12;font-weight:800;">স্বাক্ষর: ______________________________</p>
      <p class="hire-muted"><?php echo ame_pair('খালি রাখলে ডিফল্ট নামা দেখায়। পুরোনো চুক্তি মুছে যায় না।', 'Blank shows the default letter. Old contracts stay.'); ?></p>
      <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_l('নামা সংরক্ষণ', 'Save letter'); ?></button>
    </form>
  </section>
  <?php endif; ?>

  <div class="hire-split">
    <section class="hire-panel">
      <h2><?php echo ame_pair('চুক্তির তথ্য', 'Contract details'); ?></h2>
      <table class="hire-table">
        <tr><th><?php echo ame_pair('পণ্য', 'Product'); ?></th><td><?php echo hire_h($c['item_name']); ?></td></tr>
        <tr><th><?php echo ame_pair('পণ্যের উৎস', 'Source'); ?></th><td><?php
            $src = function_exists('hire_source_of') ? hire_source_of($c) : 'OWN';
            echo hire_h(function_exists('hire_source_label') ? hire_source_label($src) : $src);
            if ($src === 'THIRD') {
                echo ' · মালিক #' . (int) ($c['owner_id'] ?? 0);
                echo ' · প্রদেয় ৳' . number_format((float) ($c['owner_rate'] ?? 0), 2);
                echo ' / ' . hire_h($c['owner_cycle'] ?? 'daily');
                echo ' · মালিক জামানত ৳' . number_format((float) ($c['owner_deposit'] ?? 0), 2);
            }
        ?></td></tr>
        <tr><th>SKU / <?php echo ame_l('সিরিয়াল', 'Serial'); ?> / <?php echo ame_l('বারকোড', 'Barcode'); ?></th><td><?php echo hire_h(trim(($c['product_sku'] ?? '') . ' · ' . ($c['serial_no'] ?? '') . ' · ' . ($c['barcode'] ?? ''))); ?></td></tr>
        <tr><th><?php echo ame_pair('পরিমাণ', 'Qty'); ?></th><td><?php echo hire_h($c['qty']); ?></td></tr>
        <tr><th><?php echo ame_pair('মেয়াদ', 'Term'); ?></th><td><?php echo (int)$snap['term_months']; ?> মাস <?php echo (int)$snap['term_days']; ?> দিন · <?php echo (int)$snap['term_total_days']; ?> দিন</td></tr>
        <tr><th><?php echo ame_pair('শুরু / ফেরত / বিলিং', 'Start / return / billing'); ?></th><td><?php echo hire_h($c['start_date'] . ' / ' . ($c['expected_return'] ?: '—') . ' / ' . $c['billing_start']); ?></td></tr>
        <tr><th><?php echo ame_pair('দৈনিক হার', 'Daily rate'); ?></th><td>৳<?php echo number_format((float)$snap['daily_rate'], 2); ?> · <?php echo ame_pair('বকেয়া দিন', 'Unpaid days'); ?> <?php echo (int)$snap['unpaid_days']; ?></td></tr>
        <tr><th>Advance Usage</th><td><?php echo (int)$c['advance_usage'] === 1 ? ame_l('চালু', 'ON') : ame_l('বন্ধ', 'OFF'); ?></td></tr>
        <tr><th><?php echo ame_pair('বিরতি', 'Pause'); ?></th><td><?php echo hire_h(($c['pause_start'] ?: '—') . ' → ' . ($c['pause_end'] ?: '—')); ?></td></tr>
        <tr><th><?php echo ame_pair('নোট', 'Notes'); ?></th><td><?php echo nl2br(hire_h($c['notes'] ?? '')); ?></td></tr>
      </table>
      <p class="hire-muted"><?php echo ame_pair('আগে ফেরত: ব্যবহৃত দিন', 'Early return: used days'); ?> <?php echo (int)$preview['used_days']; ?> / <?php echo (int)$preview['expected_days']; ?> · <?php echo ame_pair('আনুমানিক ভাড়া', 'Estimated charge'); ?> ৳<?php echo number_format($preview['rental_charge'], 2); ?></p>
      <div class="hire-actions no-print">
        <a class="hire-btn" href="print.php?id=<?php echo $id; ?>&type=contract"><?php echo ame_l('চুক্তি প্রিন্ট', 'Print contract'); ?></a>
        <a class="hire-btn" href="print.php?id=<?php echo $id; ?>&type=statement"><?php echo ame_l('স্টেটমেন্ট', 'Statement'); ?></a>
        <a class="hire-btn" href="print.php?id=<?php echo $id; ?>&type=deposit"><?php echo ame_l('জামানত রসিদ', 'Deposit receipt'); ?></a>
      </div>
    </section>

    <section class="hire-panel no-print">
      <h2><?php echo ame_pair('কাজ', 'Actions'); ?></h2>

      <form method="post" action="actions.php" class="hire-grid" style="margin-bottom:14px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="do" value="pay">
        <label><?php echo ame_pair('ভাড়া পেমেন্ট', 'Rental payment'); ?><input type="number" step="0.01" min="0.01" name="amount" required></label>
        <label><?php echo ame_pair('মাধ্যম', 'Method'); ?>
          <select name="payment_method"><option>CASH</option><option>BANK</option><option>MOBILE</option><option>OTHER</option></select>
        </label>
        <label><?php echo ame_pair('অ্যাকাউন্ট', 'Account'); ?>
          <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id"><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo hire_h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select>
        </label>
        <label><?php echo ame_pair('তারিখ', 'Date'); ?><input type="date" name="pay_date" value="<?php echo date('Y-m-d'); ?>"></label>
        <label><?php echo ame_pair('রেফারেন্স', 'Reference'); ?><input name="reference_no"></label>
        <label><?php echo ame_pair('নোট', 'Note'); ?><input name="note"></label>
        <button class="hire-btn hire-btn-money" type="submit"><?php echo ame_l('পেমেন্ট নিন', 'Take payment'); ?></button>
      </form>

      <?php if ($canDep): ?>
      <form method="post" action="actions.php" class="hire-grid" style="margin-bottom:14px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="do" value="use_deposit">
        <label><?php echo ame_pair('জামানত থেকে সমন্বয় (Usage ON থাকতে হবে)', 'Apply deposit (usage must be ON)'); ?>
          <input type="number" step="0.01" min="0.01" name="amount" value="<?php echo htmlspecialchars((string)min($wallet['remaining'], max($due, 0))); ?>">
        </label>
        <label><?php echo ame_pair('কারণ', 'Reason'); ?><input name="reason" value="ভাড়া বকেয়া সমন্বয়"></label>
        <button class="hire-btn" type="submit"><?php echo ame_l('ওয়ালেট ব্যবহার', 'Use wallet'); ?></button>
      </form>
      <form method="post" action="actions.php" class="hire-grid" style="margin-bottom:14px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="do" value="add_deposit">
        <label><?php echo ame_pair('আরও জামানত', 'Add deposit'); ?><input type="number" step="0.01" min="0.01" name="amount" required></label>
        <label><?php echo ame_pair('অ্যাকাউন্ট', 'Account'); ?>
          <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id"><option value="0">—</option><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo hire_h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select>
        </label>
        <label><?php echo ame_pair('কারণ', 'Reason'); ?><input name="reason" required></label>
        <button class="hire-btn" type="submit"><?php echo ame_l('জামানত যোগ', 'Add deposit'); ?></button>
      </form>
      <form method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="do" value="toggle_usage">
        <input type="hidden" name="advance_usage" value="<?php echo (int)$c['advance_usage'] ? 0 : 1; ?>">
        <input type="hidden" name="reason" value="toggle">
        <button class="hire-btn" type="submit"><?php echo (int)$c['advance_usage'] ? ame_l('Usage বন্ধ করুন', 'Turn usage OFF') : ame_l('Usage চালু করুন', 'Turn usage ON'); ?></button>
      </form>
      <?php endif; ?>

      <?php if ($canRef): ?>
      <form method="post" action="actions.php" class="hire-grid" style="margin-top:14px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="do" value="refund">
        <label><?php echo ame_pair('জামানত ফেরত', 'Refund deposit'); ?><input type="number" step="0.01" min="0.01" name="amount" max="<?php echo hire_h($wallet['remaining']); ?>" required></label>
        <label><?php echo ame_pair('অ্যাকাউন্ট', 'Account'); ?>
          <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo hire_h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select>
        </label>
        <label><?php echo ame_pair('কারণ', 'Reason'); ?><input name="reason" required></label>
        <button class="hire-btn hire-btn-danger" type="submit"><?php echo ame_l('ফেরত দিন', 'Refund'); ?></button>
      </form>
      <?php endif; ?>
    </section>
  </div>

  <section class="hire-panel no-print">
    <h2><?php echo ame_pair('স্ট্যাটাস ও ফেরত', 'Status and return'); ?></h2>
    <div class="hire-actions">
      <?php if (in_array($c['status'], ['ACTIVE','OVERDUE'], true)): ?>
      <form method="post" action="actions.php"><?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="pause"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="pause_start" value="<?php echo date('Y-m-d'); ?>">
        <input name="reason" placeholder="<?php echo ame_l('বিরতির কারণ', 'Pause reason'); ?>" required>
        <button class="hire-btn" type="submit"><?php echo ame_l('বিরতি', 'Pause'); ?></button>
      </form>
      <?php endif; ?>
      <?php if ($c['status'] === 'PAUSED'): ?>
      <form method="post" action="actions.php"><?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="resume"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
        <input type="hidden" name="resume_date" value="<?php echo date('Y-m-d'); ?>">
        <input name="reason" placeholder="<?php echo ame_l('চালুর নোট', 'Resume note'); ?>">
        <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_l('আবার চালু', 'Resume'); ?></button>
      </form>
      <?php endif; ?>
    </div>
    <?php if (in_array($c['status'], ['ACTIVE','OVERDUE','PAUSED'], true)): ?>
    <form method="post" action="actions.php" class="hire-grid" style="margin-top:12px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="return"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <label><?php echo ame_pair('ফেরতের তারিখ', 'Return date'); ?><input type="date" name="return_date" value="<?php echo date('Y-m-d'); ?>"></label>
      <label><?php echo ame_pair('অবস্থা', 'Condition'); ?>
        <select name="condition"><option value="ok"><?php echo ame_l('ভালো', 'OK'); ?></option><option value="damaged"><?php echo ame_l('ক্ষতিগ্রস্ত', 'Damaged'); ?></option></select>
      </label>
      <label><?php echo ame_pair('মিটার', 'Meter'); ?><input name="meter_reading"></label>
      <label><?php echo ame_pair('হারিয়ে যাওয়া জিনিস', 'Missing accessories'); ?><input name="missing"></label>
      <label><?php echo ame_pair('ক্ষতি চার্জ', 'Damage charge'); ?><input type="number" step="0.01" min="0" name="damage_charge" value="0"></label>
      <label><?php echo ame_pair('নোট', 'Notes'); ?><input name="notes"></label>
      <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_l('পণ্য ফেরত নিন', 'Receive return'); ?></button>
    </form>
    <?php endif; ?>
    <?php if (in_array($c['status'], ['RETURNED','ACTIVE','OVERDUE'], true)): ?>
    <form method="post" action="actions.php" style="margin-top:10px;"><?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="complete"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <input name="reason" placeholder="<?php echo ame_l('সম্পন্নের নোট', 'Completion note'); ?>">
      <button class="hire-btn" type="submit"><?php echo ame_l('চুক্তি সম্পন্ন', 'Mark completed'); ?></button>
    </form>
    <?php endif; ?>
    <p class="hire-muted"><?php echo ame_l('পেমেন্ট থাকলে ডিলিট হবে না — ওপরের বন্ধ বা বাতিল ব্যবহার করুন। খাতার টাকা অন্য মডিউলে যায় না।', 'Delete is blocked when payments exist — use Stop or Cancel above. Ledger money in other modules does not change.'); ?></p>
  </section>

  <?php if ($canRate || $canProd || $canAdj): ?>
  <section class="hire-panel no-print">
    <h2><?php echo ame_pair('অনুমোদিত সম্পাদনা', 'Authorized edits'); ?></h2>
    <?php if ($canProd): ?>
    <form method="post" action="actions.php" class="hire-grid" style="margin-top:12px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="product"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <input type="hidden" name="product_id" id="swap_pid" value="">
      <label><?php echo ame_pair('নতুন পণ্যের নাম', 'New product name'); ?><input name="item_name" required></label>
      <label><?php echo ame_pair('SKU', 'SKU'); ?><input name="product_sku"></label>
      <label><?php echo ame_pair('সিরিয়াল', 'Serial'); ?><input name="serial_no"></label>
      <label><?php echo ame_pair('বারকোড', 'Barcode'); ?><input name="barcode"></label>
      <label><?php echo ame_pair('কারণ (আবশ্যক)', 'Reason required'); ?><input name="reason" required></label>
      <button class="hire-btn" type="submit"><?php echo ame_l('পণ্য বদলান', 'Change product'); ?></button>
    </form>
    <?php endif; ?>
    <?php if ($canAdj): ?>
    <form method="post" action="actions.php" class="hire-grid" style="margin-top:12px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="adjust"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <label><?php echo ame_pair('ডেবিট (বকেয়া বাড়ে)', 'Debit (increases due)'); ?><input type="number" step="0.01" min="0" name="debit" value="0"></label>
      <label><?php echo ame_pair('ক্রেডিট (বকেয়া কমে)', 'Credit (reduces due)'); ?><input type="number" step="0.01" min="0" name="credit" value="0"></label>
      <label><?php echo ame_pair('কারণ', 'Reason'); ?><input name="reason" required></label>
      <button class="hire-btn" type="submit"><?php echo ame_l('সমন্বয়', 'Post adjustment'); ?></button>
    </form>
    <?php endif; ?>
    <form method="post" action="actions.php" class="hire-grid" style="margin-top:12px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="renew"><input type="hidden" name="contract_id" value="<?php echo $id; ?>">
      <label><?php echo ame_pair('নবায়ন শুরু', 'Renew start'); ?><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>"></label>
      <label><?php echo ame_pair('নতুন ফেরত', 'New return'); ?><input type="date" name="expected_return"></label>
      <label><?php echo ame_pair('নতুন হার', 'New rate'); ?><input type="number" step="0.01" min="0.01" name="rent_rate" value="<?php echo hire_h($c['rent_rate']); ?>"></label>
      <label><?php echo ame_pair('নোট', 'Notes'); ?><input name="notes"></label>
      <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_l('নবায়ন', 'Renew'); ?></button>
    </form>
  </section>
  <?php endif; ?>

  <section class="hire-panel" id="ame-slip">
    <h2><?php echo ame_pair('ভাড়া স্টেটমেন্ট', 'Rental statement'); ?></h2>
    <table class="hire-table">
      <thead><tr>
        <th><?php echo ame_pair('তারিখ', 'Date'); ?></th>
        <th><?php echo ame_pair('বিবরণ', 'Description'); ?></th>
        <th><?php echo ame_pair('ডেবিট', 'Debit'); ?></th>
        <th><?php echo ame_pair('ক্রেডিট', 'Credit'); ?></th>
        <th><?php echo ame_pair('ব্যালেন্স', 'Balance'); ?></th>
      </tr></thead>
      <tbody>
      <?php if (!$stmt): ?>
        <tr><td colspan="5" class="hire-empty"><?php echo ame_l('এখনো কোনো সারি নেই।', 'No rows yet.'); ?></td></tr>
      <?php else: foreach ($stmt as $row): ?>
        <tr>
          <td><?php echo hire_h($row['date']); ?></td>
          <td><?php echo hire_h($row['desc']); ?><br><span class="hire-muted"><?php echo hire_h($row['ref']); ?></span></td>
          <td><?php echo $row['debit'] > 0 ? number_format($row['debit'], 2) : '—'; ?></td>
          <td><?php echo $row['credit'] > 0 ? number_format($row['credit'], 2) : '—'; ?></td>
          <td><?php echo number_format($row['balance'], 2); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>

  <section class="hire-panel no-print">
    <h2><?php echo ame_pair('অডিট লগ', 'Audit log'); ?></h2>
    <?php if (!$hist): ?>
      <p class="hire-empty"><?php echo ame_l('এখনো কোনো পরিবর্তন নেই।', 'No edits yet.'); ?></p>
    <?php else: ?>
    <table class="hire-table">
      <thead><tr><th><?php echo ame_l('সময়', 'When'); ?></th><th><?php echo ame_l('ঘর', 'Field'); ?></th><th><?php echo ame_l('পুরনো', 'Old'); ?></th><th><?php echo ame_l('নতুন', 'New'); ?></th><th><?php echo ame_l('কারণ', 'Reason'); ?></th></tr></thead>
      <tbody>
      <?php foreach ($hist as $h): ?>
        <tr>
          <td><?php echo hire_h($h['created_at']); ?></td>
          <td><?php echo hire_h($h['field_name']); ?></td>
          <td><?php echo hire_h($h['old_value']); ?></td>
          <td><?php echo hire_h($h['new_value']); ?></td>
          <td><?php echo hire_h($h['reason']); ?> · #<?php echo (int)$h['user_id']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
<script src="/assets/js/hire-term.js?v=<?php echo $av; ?>"></script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
