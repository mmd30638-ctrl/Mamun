<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

// ame_pair()/ame_l() — includes/lang_helper.php-তে কেন্দ্রীয়ভাবে সংজ্ঞায়িত (config.php
// থেকে সবচেয়ে আগে লোড হয়), সেটিংসে বাছাই করা ভাষা অনুযায়ী শুধু একটা ভাষাই দেখায়।

$today = date('Y-m-d');
$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$cust_id = (int) ($_GET['id'] ?? 0);
$picked_customer = null;
if ($cust_id > 0) {
    $cst = $db->prepare('SELECT id, name, customer_code, phone, receivable_balance FROM customers WHERE id = ? LIMIT 1');
    $cst->bind_param('i', $cust_id);
    $cst->execute();
    $picked_customer = $cst->get_result()->fetch_assoc();
    $cst->close();
    if (!$picked_customer) {
        $cust_id = 0;
    }
}

$sql = "SELECT w.id, w.withdraw_date, w.amount, w.method, w.note, w.reference_no,
               c.id AS customer_id, c.name, c.customer_code, c.phone, a.account_name
        FROM customer_withdrawals w
        LEFT JOIN customers c ON c.id = w.customer_id
        LEFT JOIN accounts a ON a.id = w.account_id
        WHERE w.withdraw_date BETWEEN ? AND ?" . ($cust_id > 0 ? ' AND w.customer_id = ?' : '') . '
        ORDER BY w.withdraw_date ASC, w.id ASC';
$stmt = $db->prepare($sql);
if ($cust_id > 0) {
    $stmt->bind_param('ssi', $from, $to, $cust_id);
} else {
    $stmt->bind_param('ss', $from, $to);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = 0.0;
$by_customer = [];
foreach ($rows as $r) {
    $total += (float) $r['amount'];
    $cid = (int) $r['customer_id'];
    $by_customer[$cid] = ($by_customer[$cid] ?? 0) + (float) $r['amount'];
}

$clean = static function (string $value): string {
    return function_exists('ame_clean_display_text') ? ame_clean_display_text($value) : trim($value);
};
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';

/** ব্যালেন্সকে "জমা ৳X" বা "বকেয়া ৳X" হিসেবে লেবেল+রঙ দেয়। */
$balance_line = static function (float $balance): array {
    if ($balance < -0.009) {
        return ['জমা', 'Held', round(abs($balance), 2), 'net'];
    }
    if ($balance > 0.009) {
        return ['বকেয়া', 'Due', round($balance, 2), 'due'];
    }
    return ['সমান', 'Settled', 0.0, ''];
};

// একজন কাস্টমার বাছা থাকলে — সাবেক + উত্তোলন + বর্তমান তিনটা সংখ্যা সবসময় নিজে থেকে
// মিলবে: সাবেক = বর্তমান (লাইভ ব্যালেন্স) + এই রিপোর্টে মোট উত্তোলন। কোনো এক্সট্রা
// কোয়েরি লাগে না — কাস্টমারের একটা কলাম আর আগে হিসাব করা $total দিয়েই হয়ে যায়।
//
// ⚠️ সাইন সাবধানতা: receivable_balance ঋণাত্মক (negative) মানে দোকানে জমা আছে
// (ame_customer_shop_held() দেখুন)। উত্তোলন হলে ডেবিট বসে যা ব্যালেন্সকে শূন্যের
// দিকে বাড়ায় — অর্থাৎ add_ledger_entry()-র নিয়মে: বর্তমান = সাবেক + উত্তোলন (raw
// balance-এ)। তাই raw balance হিসেবে সাবেক = বর্তমান − উত্তোলন, যোগ নয়। এই একই
// সূত্র withdraw_receipt.php-তেও ($prev_balance = $post_balance − $amount) — দুই
// জায়গায় এক নিয়ম রাখা হয়েছে যাতে কখনো ভিন্ন উত্তর না আসে।
$cust_prev = null;
$cust_now = null;
if ($cust_id > 0 && $picked_customer) {
    $cust_now_balance = (float) $picked_customer['receivable_balance'];
    $cust_prev_balance = round($cust_now_balance - $total, 2);
    $cust_prev = $balance_line($cust_prev_balance);
    $cust_now = $balance_line($cust_now_balance);
}

$method_map = [
    'CASH' => ['নগদ', 'Cash'],
    'BKASH' => ['বিকাশ', 'bKash'],
    'NAGAD' => ['নগদ (মোবাইল)', 'Nagad'],
    'BANK' => ['ব্যাংক', 'Bank'],
    'CHEQUE' => ['চেক', 'Cheque'],
];

$title_who = $picked_customer ? $clean((string) $picked_customer['name']) : ame_l('সব কাস্টমার', 'All customers');
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'withdrawals-' . $from . '-to-' . $to,
    'title' => $shop . ' · ' . ame_l('জমা উত্তোলন রিপোর্ট', 'Deposit withdrawal report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('জমা উত্তোলন রিপোর্ট', 'Deposit withdrawal report'),
        $from . ' — ' . $to,
        $title_who,
        ame_l('সর্বমোট', 'Total') . ': ৳' . number_format($total, 2),
        count($rows) . ' ' . ame_l('টি লেনদেন', 'transactions'),
    ])),
    'shop' => $shop,
    'links' => [
        [
            'href' => 'withdraw.php' . ($cust_id > 0 ? ('?id=' . $cust_id) : ''),
            'html' => ame_pair('জমা টাকা তোলা', 'Payout deposit'),
            'ghost' => true,
        ],
    ],
];

include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/cust-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.67', ENT_QUOTES, 'UTF-8'); ?>">
<div class="cdesk-wrap">
  <header class="cdesk-mast no-print">
    <p class="cdesk-kicker"><?php echo ame_pair('দোকানের জমা', 'Shop deposit'); ?></p>
    <h1><?php echo ame_pair('জমা উত্তোলন রিপোর্ট', 'Deposit withdrawal report'); ?></h1>
    <p><?php echo ame_pair('নির্দিষ্ট সময়ে (ও চাইলে একজন কাস্টমারের) সব জমা-উত্তোলন এক জায়গায় — প্রিন্ট, PDF বা ছবি করে শেয়ার করুন।', 'All deposit payouts in a date range (optionally for one customer) — print it or share as PDF/image.'); ?></p>
  </header>

  <form class="cdesk-card no-print" method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <label class="cdesk-field">
      <span><?php echo ame_pair('শুরু', 'From'); ?></span>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('শেষ', 'To'); ?></span>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <?php if ($cust_id > 0): ?>
      <input type="hidden" name="id" value="<?php echo (int) $cust_id; ?>">
      <span class="cdesk-meta"><?php echo ame_pair('কাস্টমার', 'Customer'); ?>: <?php echo htmlspecialchars($title_who, ENT_QUOTES, 'UTF-8'); ?> —
        <a href="withdrawals_report.php?from=<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>&amp;to=<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"><?php echo ame_pair('সব দেখান', 'Show all'); ?></a>
      </span>
    <?php endif; ?>
    <button type="submit" class="cdesk-btn"><?php echo ame_pair('ফিল্টার', 'Filter'); ?></button>
  </form>

  <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
  <style>
    /* এই মেমো (#ame-slip) ছবি/PDF হিসেবে ক্যাপচার হয় — এর ভেতরের সব বক্সের লেখা
       মাঝ বরাবর (center) থাকবে, দোকানের তথ্যও মেমোর ঠিক মাঝখানে। */
    #ame-slip .wdr-shop-head { text-align: center; }
    #ame-slip .wdr-shop-name { margin: 0; font-size: 18px; font-weight: 800; color: #0B1E4D; }
    #ame-slip .wdr-shop-line { margin: 2px 0 0; font-size: 12px; color: #555; }
    #ame-slip .cdesk-mast { text-align: center; }
    #ame-slip .cdesk-mast .ame-pair { align-items: center; }
    #ame-slip .cdesk-stats article { text-align: center; }
    #ame-slip .cdesk-stats .ame-pair { align-items: center; width: 100%; }
    #ame-slip .wdr-formula { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0 0 12px; }
    #ame-slip .wdr-formula article { padding: 14px 10px; border-radius: 16px; background: #fff8e1; border: 1px solid #ffe082; text-align: center; }
    #ame-slip .wdr-formula .ame-pair { align-items: center; width: 100%; }
    #ame-slip .wdr-formula span.wdr-label { display: block; font-size: 12px; font-weight: 800; color: #8D6E63; }
    #ame-slip .wdr-formula b { display: block; margin-top: 6px; font-size: clamp(16px, 3vw, 22px); font-variant-numeric: tabular-nums; }
    #ame-slip .wdr-formula .wdr-op { display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: #E65100; }
  </style>
  <div class="ame-slip-statement" id="ame-slip">
    <header class="cdesk-mast">
      <div class="wdr-shop-head">
        <p class="wdr-shop-name"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($shop_addr !== ''): ?><p class="wdr-shop-line"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($shop_ph !== ''): ?><p class="wdr-shop-line"><?php echo ame_pair('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      </div>
      <h1 style="margin-top:10px;"><?php echo ame_pair('জমা উত্তোলন রিপোর্ট', 'Deposit withdrawal report'); ?></h1>
      <p><?php echo htmlspecialchars($from . ' — ' . $to, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($title_who, ENT_QUOTES, 'UTF-8'); ?></p>
    </header>

    <div class="cdesk-stats">
      <article class="is-out">
        <span><?php echo ame_pair('সর্বমোট তোলা', 'Total paid out'); ?></span>
        <b>৳<?php echo number_format($total, 2); ?></b>
      </article>
      <article>
        <span><?php echo ame_pair('লেনদেন সংখ্যা', 'Transactions'); ?></span>
        <b><?php echo count($rows); ?></b>
      </article>
      <article>
        <span><?php echo ame_pair('কাস্টমার সংখ্যা', 'Customers'); ?></span>
        <b><?php echo count($by_customer); ?></b>
      </article>
    </div>

    <?php if ($cust_id > 0 && $cust_prev && $cust_now):
        $prev_label = $cust_prev[3] === 'due' ? ame_l('সাবেক বকেয়া ছিল', 'Previous due was') : ame_l('সাবেক জমা ছিল', 'Previous deposit was');
        $now_label = $cust_now[3] === 'due' ? ame_l('বর্তমানে বকেয়া আছে', 'Now due remaining') : ame_l('বর্তমানে জমা আছে', 'Now deposit remaining');
    ?>
    <div class="wdr-formula">
      <article>
        <span class="wdr-label"><?php echo htmlspecialchars($prev_label, ENT_QUOTES, 'UTF-8'); ?></span>
        <b style="color:<?php echo $cust_prev[3] === 'due' ? '#C62828' : '#087F3D'; ?>;">৳<?php echo number_format($cust_prev[2], 2); ?></b>
      </article>
      <article>
        <span class="wdr-label"><?php echo ame_l('উত্তোলন হলো', 'Withdrawn'); ?></span>
        <b style="color:#be123c;">৳<?php echo number_format($total, 2); ?></b>
      </article>
      <article>
        <span class="wdr-label"><?php echo htmlspecialchars($now_label, ENT_QUOTES, 'UTF-8'); ?></span>
        <b style="color:<?php echo $cust_now[3] === 'due' ? '#C62828' : '#087F3D'; ?>;">৳<?php echo number_format($cust_now[2], 2); ?></b>
      </article>
    </div>
    <?php endif; ?>

    <div class="cdesk-table-wrap">
      <table class="cdesk-table">
        <thead>
          <tr>
            <th><?php echo ame_pair('তারিখ', 'Date'); ?></th>
            <th><?php echo ame_pair('কাস্টমার', 'Customer'); ?></th>
            <th><?php echo ame_pair('রেফ', 'Ref'); ?></th>
            <th><?php echo ame_pair('অ্যাকাউন্ট', 'Account'); ?></th>
            <th><?php echo ame_pair('মাধ্যম', 'Method'); ?></th>
            <th class="num"><?php echo ame_pair('পরিমাণ', 'Amount'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="cdesk-empty"><?php echo ame_pair('এই সময়ে কোনো উত্তোলন নেই।', 'No payouts in this range.'); ?></td></tr>
          <?php else: foreach ($rows as $r):
              $name = $clean((string) ($r['name'] ?? ''));
              $code = trim((string) ($r['customer_code'] ?? ''));
              $ref = trim((string) ($r['reference_no'] ?? '')) ?: ('WD-' . str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT));
              $method = strtoupper(trim((string) ($r['method'] ?? 'CASH')));
              $method_bn = $method_map[$method][0] ?? $method;
              $method_en = $method_map[$method][1] ?? $method;
          ?>
          <tr>
            <td><?php echo htmlspecialchars((string) $r['withdraw_date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <?php echo htmlspecialchars($name !== '' ? $name : '—', ENT_QUOTES, 'UTF-8'); ?>
              <?php if ($code !== ''): ?><div class="cdesk-meta"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['account_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo ame_pair($method_bn, $method_en); ?></td>
            <td class="num">৳<?php echo number_format((float) $r['amount'], 2); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <td colspan="5"><?php echo ame_pair('সর্বমোট', 'Total'); ?></td>
            <td class="num">৳<?php echo number_format($total, 2); ?></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
    <div class="ame-slip-foot no-print"><?php echo ame_pair('এই রিপোর্ট ইলেকট্রনিকভাবে তৈরি।', 'This report was generated electronically.'); ?></div>
  </div>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
