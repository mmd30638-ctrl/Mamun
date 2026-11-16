<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);
if (is_file(__DIR__ . '/../../includes/hire_functions.php')) {
    require_once __DIR__ . '/../../includes/hire_functions.php';
    hire_boot($db);
}

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$id = (int) ($_GET['id'] ?? 0);
$ame_body_class = 'ame-customer-ledger';
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/cust-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.64', ENT_QUOTES, 'UTF-8'); ?>">
<?php
if ($id <= 0) {
    $pick_live = function_exists('ame_customer_live_sql') ? ame_customer_live_sql($db) : 'purged_at IS NULL';
    $pick_gone = function_exists('ame_customer_purged_sql')
        ? ame_customer_purged_sql($db)
        : "purged_at IS NOT NULL AND purged_at <> '0000-00-00 00:00:00'";
    $pick_cols = 'id, customer_code, name, phone, receivable_balance';
    $stmt = $db->query('SELECT ' . $pick_cols . ' FROM customers WHERE is_customer = 1 AND ' . $pick_live . ' ORDER BY name ASC LIMIT 500');
    $pick_list = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];
    $gone_stmt = $db->query('SELECT ' . $pick_cols . ' FROM customers WHERE (is_customer = 1 OR is_customer IS NULL) AND ' . $pick_gone . ' ORDER BY name ASC LIMIT 500');
    $pick_gone_list = $gone_stmt ? $gone_stmt->fetch_all(MYSQLI_ASSOC) : [];
    ?>
<style>
.cdesk-gone-label { margin:14px 0 6px; font-weight:800; color:#7f1d1d; }
.cdesk-gone-note { margin:0 0 8px; font-size:12px; font-weight:700; color:#7f1d1d; }
.cdesk-row.cdesk-gone b { color:#7f1d1d; }
</style>
<div class="cdesk-wrap">
  <header class="cdesk-mast">
    <p class="cdesk-kicker"><?php echo ame_pair('সম্পূর্ণ স্টেটমেন্ট', 'Full statement'); ?></p>
    <h1><?php echo ame_pair('কাস্টমার বাছুন', 'Choose a customer'); ?></h1>
    <p><?php echo ame_pair('স্টেটমেন্টে তিনটি থাকবে — বিল, জমা ও উত্তোলন।', 'The statement has three columns — bill, deposit and withdrawal.'); ?></p>
  </header>
  <section class="cdesk-card">
    <input class="cdesk-search" type="search" id="stSearch" placeholder="নাম, কোড বা ফোন · name, code or phone" autocomplete="off">
    <div id="stList">
      <?php foreach ($pick_list as $c):
          $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $c['name']) : (string) $c['name'];
          $h = ame_customer_shop_held((float) $c['receivable_balance']);
          $d = ame_customer_shop_due((float) $c['receivable_balance']);
      ?>
      <a class="cdesk-row" href="ledger.php?id=<?php echo (int) $c['id']; ?>" data-q="<?php echo htmlspecialchars(strtolower($name . ' ' . $c['customer_code'] . ' ' . ($c['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
        <span>
          <b><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></b><br>
          <small><?php echo htmlspecialchars((string) $c['customer_code'], ENT_QUOTES, 'UTF-8'); ?></small>
        </span>
        <?php if ($h > 0): ?>
          <span class="cdesk-held"><?php echo ame_pair('জমা', 'Held'); ?> ৳<?php echo number_format($h, 2); ?></span>
        <?php elseif ($d > 0): ?>
          <span class="cdesk-due"><?php echo ame_pair('বকেয়া', 'Due'); ?> ৳<?php echo number_format($d, 2); ?></span>
        <?php else: ?>
          <span>৳0.00</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php if ($pick_gone_list): ?>
      <p class="cdesk-gone-label">মুছে ফেলা কাস্টমার</p>
      <p class="cdesk-gone-note">নামে চাপলে স্টেটমেন্ট খুলবে। সাধারণ তালিকায় এরা নেই। টাকা বদলায় না।</p>
      <?php foreach ($pick_gone_list as $c):
          $name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $c['name']) : (string) $c['name'];
          $h = ame_customer_shop_held((float) $c['receivable_balance']);
          $d = ame_customer_shop_due((float) $c['receivable_balance']);
      ?>
      <a class="cdesk-row cdesk-gone" href="ledger.php?id=<?php echo (int) $c['id']; ?>" data-q="<?php echo htmlspecialchars(strtolower($name . ' ' . $c['customer_code'] . ' ' . ($c['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
        <span>
          <b><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></b><br>
          <small><?php echo htmlspecialchars((string) $c['customer_code'], ENT_QUOTES, 'UTF-8'); ?> · মুছে ফেলা</small>
        </span>
        <?php if ($h > 0): ?>
          <span class="cdesk-held"><?php echo ame_pair('জমা', 'Held'); ?> ৳<?php echo number_format($h, 2); ?></span>
        <?php elseif ($d > 0): ?>
          <span class="cdesk-due"><?php echo ame_pair('বকেয়া', 'Due'); ?> ৳<?php echo number_format($d, 2); ?></span>
        <?php else: ?>
          <span>৳0.00</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <p class="cdesk-empty" id="stEmpty" hidden><?php echo ame_pair('কোনো কাস্টমার মিলছে না।', 'No customer matches.'); ?></p>
  </section>
</div>
<script>
(function () {
  var box = document.getElementById('stSearch');
  var list = document.getElementById('stList');
  var empty = document.getElementById('stEmpty');
  if (!box || !list) return;
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    var n = 0;
    var goneOn = 0;
    list.querySelectorAll('.cdesk-row').forEach(function (row) {
      var ok = !q || (row.getAttribute('data-q') || '').indexOf(q) !== -1;
      row.hidden = !ok;
      if (ok) n++;
      if (ok && row.classList.contains('cdesk-gone')) goneOn++;
    });
    var label = list.querySelector('.cdesk-gone-label');
    var note = list.querySelector('.cdesk-gone-note');
    if (label) label.hidden = goneOn === 0;
    if (note) note.hidden = goneOn === 0;
    if (empty) empty.hidden = n > 0;
  });
})();
</script>
<?php
    include __DIR__ . '/../../partials/footer.php';
    return;
}

$custStmt = $db->prepare('SELECT id, customer_code, name, phone, address, receivable_balance, purged_at FROM customers WHERE id = ?');
$custStmt->bind_param('i', $id);
$custStmt->execute();
$customer = $custStmt->get_result()->fetch_assoc();
$custStmt->close();
if (!$customer) {
    echo '<div class="cdesk-wrap"><p class="cdesk-empty">কাস্টমার পাওয়া যায়নি। / Customer not found.</p></div>';
    include __DIR__ . '/../../partials/footer.php';
    return;
}
$ledger_gone = function_exists('ame_customer_is_purged') && ame_customer_is_purged($customer);

$today = date('Y-m-d');
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$statement = ame_customer_statement($db, $id, $from, $to);
$entries = $statement['rows'];
$customer['receivable_balance'] = $statement['current_balance'];
$period_label = 'শুরু থেকে আজ';
if ($from !== '' && $to !== '') {
    $period_label = $from . ' — ' . $to;
} elseif ($from !== '') {
    $period_label = $from . ' থেকে';
} elseif ($to !== '') {
    $period_label = $to . ' পর্যন্ত';
}

$type = trim((string) ($_GET['type'] ?? 'all'));
$hire_types = ['all', 'sales', 'payment', 'rental', 'deposit', 'deposit_use', 'refund', 'damage', 'late', 'adjustment'];
if (!in_array($type, $hire_types, true)) {
    $type = 'all';
}
if ($type !== 'all') {
    $entries = array_values(array_filter($entries, static function ($e) use ($type) {
        $kind = function_exists('hire_ledger_kind') ? hire_ledger_kind($e) : 'other';
        if ($type === 'refund') {
            return $kind === 'deposit' && str_contains(mb_strtolower((string)($e['description'] ?? ''), 'UTF-8'), 'ফেরত');
        }
        return $kind === $type;
    }));
}

$total_bill = (float)$statement['total_charges'];
$total_dep = (float)$statement['total_payments'];
$total_wd = (float)$statement['total_withdrawals'];
$total_ret = (float)($statement['total_returns'] ?? 0);
$opening = (float)($statement['opening'] ?? 0);
$period_close = (float)($statement['period_closing'] ?? $statement['current_balance']);
$live_bal = (float)($statement['live_balance'] ?? $statement['current_balance']);
$held = (float)$statement['held'];
$due = (float)$statement['current_due'];
$period_due = (float)($statement['remaining_due'] ?? 0);
$period_adv = (float)($statement['advance'] ?? 0);
$formula = (string)($statement['formula'] ?? '');
$name = function_exists('ame_clean_display_text') ? ame_clean_display_text((string) $customer['name']) : (string) $customer['name'];
$clean = static function (string $value): string {
    return function_exists('ame_clean_display_text') ? ame_clean_display_text($value) : trim($value);
};
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$code = trim((string) ($customer['customer_code'] ?? ''));
$phone = trim((string) ($customer['phone'] ?? ''));
$who_line = $code;
if ($phone !== '') {
    $who_line .= ($who_line !== '' ? ' · ' : '') . $phone;
}
$remain_label = $held > 0 ? 'বর্তমান অগ্রিম জমা / Live advance' : ($due > 0 ? 'বর্তমান বকেয়া / Live due' : 'বর্তমান ব্যালেন্স / Live balance');
$remain_amt = $held > 0 ? $held : $due;
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_taka = static function (float $n): string {
    if (function_exists('ame_balance_book_money')) {
        return ame_balance_book_money($n);
    }
    $sign = $n < -0.009 ? '−' : '';
    return $sign . '৳' . number_format(abs($n), 2);
};
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'statement-' . ($code !== '' ? $code : ('C' . $id)) . '-' . $from . '-to-' . $to,
    'title' => $shop . ' · ' . $name,
    'text' => implode("\n", array_filter([
        $shop,
        'সম্পূর্ণ স্টেটমেন্ট / Full statement',
        $name,
        $who_line !== '' ? $who_line : null,
        $period_label,
        'সাবেক: ' . $ame_taka($opening),
        'মোট চার্জ / Total charges: ৳' . number_format($total_bill, 2),
        'মোট পেমেন্ট / Total payments: ৳' . number_format($total_dep, 2),
        $formula !== '' ? $formula : null,
        $remain_label . ': ৳' . number_format($remain_amt, 2),
    ])),
    'shop' => $shop,
    'links' => $ledger_gone ? [
        [
            'href' => 'ledger.php',
            'html' => ame_pair('অন্য কাস্টমার', 'Another customer'),
            'ghost' => true,
        ],
        [
            'href' => 'profile.php?id=' . $id,
            'html' => ame_pair('প্রোফাইল', 'Profile'),
            'ghost' => true,
        ],
    ] : [
        [
            'href' => 'ledger.php',
            'html' => ame_pair('অন্য কাস্টমার', 'Another customer'),
            'ghost' => true,
        ],
        [
            'href' => 'withdraw.php?id=' . $id,
            'html' => ame_pair('জমা টাকা তোলা', 'Payout deposit'),
            'ghost' => false,
        ],
        [
            'href' => 'payment-receive.php?id=' . $id,
            'html' => ame_pair('পেমেন্ট গ্রহণ', 'Receive payment'),
            'ghost' => true,
        ],
    ],
];
?>
<div class="cdesk-wrap">
  <form class="cdesk-card no-print" method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <label class="cdesk-field">
      <span><?php echo ame_pair('শুরু', 'From'); ?></span>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label class="cdesk-field">
      <span><?php echo ame_pair('শেষ', 'To'); ?></span>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <button type="submit" class="cdesk-btn"><?php echo ame_pair('ফিল্টার', 'Filter'); ?></button>
  </form>
  <?php
    $hire_filter_labels = [
        'all' => ame_pair('সব', 'All'),
        'sales' => ame_pair('বিক্রি', 'Sales'),
        'payment' => ame_pair('পেমেন্ট', 'Payment'),
        'rental' => ame_pair('ভাড়া', 'Rental'),
        'deposit' => ame_pair('জামানত', 'Deposit'),
        'deposit_use' => ame_pair('জামানত ব্যবহার', 'Deposit use'),
        'refund' => ame_pair('জামানত ফেরত', 'Deposit refund'),
        'damage' => ame_pair('ক্ষতি চার্জ', 'Damage'),
        'late' => ame_pair('বিলম্ব ফি', 'Late fee'),
        'adjustment' => ame_pair('সমন্বয়', 'Adjustment'),
    ];
  ?>
  <nav class="cdesk-toolbar no-print" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:6px;">
    <?php foreach ($hire_filter_labels as $key => $lab):
        $href = 'ledger.php?id=' . (int)$id . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&type=' . rawurlencode($key);
    ?>
      <a class="cdesk-btn<?php echo $type === $key ? '' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" style="<?php echo $type === $key ? 'background:#003F8F;color:#fff;' : ''; ?>"><?php echo $lab; ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!empty($ledger_gone)): ?>
  <p class="no-print" style="margin:0 0 12px;padding:10px 12px;border:1px solid #fecaca;border-radius:10px;background:#fff1f2;color:#7f1d1d;font-weight:800;">তালিকা থেকে সরানো কাস্টমার। স্টেটমেন্ট শুধু দেখা যাচ্ছে। বিক্রয়, পেমেন্ট ও টাকা বদলায় না।</p>
  <?php endif; ?>
  <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
  <div class="ame-slip-statement" id="ame-slip">
  <div class="wdr-shop-head" style="text-align:center;">
    <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_pair('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  </div>
  <header class="cdesk-mast">
    <p class="cdesk-kicker"><?php echo ame_pair('সম্পূর্ণ স্টেটমেন্ট', 'Full statement'); ?></p>
    <h1><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($period_label, ENT_QUOTES, 'UTF-8'); ?></p>
    <p>
      <?php echo htmlspecialchars((string) $customer['customer_code'], ENT_QUOTES, 'UTF-8'); ?>
      <?php echo !empty($customer['phone']) ? ' · ' . htmlspecialchars((string) $customer['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>
    </p>
    <p><?php echo ame_pair('সাবেক + চার্জ − পরিশোধ = এই সময়কালের মোট। সর্বশেষ লেনদেন উপরে। ডানদিকে সেই সারির রানিং। বর্তমান লেজার আলাদা।', 'Opening + charges − payments = period total. Newest transaction on top. Running on the right is that row. Live ledger is separate.'); ?></p>
  </header>

  <div class="cdesk-stats">
    <article>
      <span><?php echo ame_pair('সাবেক ব্যালেন্স', 'Opening'); ?></span>
      <b><?php echo $ame_taka($opening); ?></b>
    </article>
    <article class="is-out">
      <span><?php echo ame_pair('মোট চার্জ', 'Total charges'); ?></span>
      <b>৳<?php echo number_format($total_bill, 2); ?></b>
    </article>
    <article class="is-in">
      <span><?php echo ame_pair('মোট পেমেন্ট', 'Total payments'); ?></span>
      <b>৳<?php echo number_format($total_dep, 2); ?></b>
    </article>
    <?php if ($total_ret > 0.009): ?>
    <article class="is-in">
      <span><?php echo ame_pair('বিক্রয় ফেরত', 'Sales return'); ?></span>
      <b>৳<?php echo number_format($total_ret, 2); ?></b>
    </article>
    <?php endif; ?>
    <?php if ($total_wd > 0.009): ?>
    <article class="is-out">
      <span><?php echo ame_pair('উত্তোলন', 'Withdrawal'); ?></span>
      <b>৳<?php echo number_format($total_wd, 2); ?></b>
    </article>
    <?php endif; ?>
    <article class="<?php echo $period_adv > 0.009 ? 'is-left' : ($period_due > 0.009 ? 'is-due' : ''); ?>">
      <span><?php echo $period_adv > 0.009
        ? ame_pair('অগ্রিম জমা', 'Advance held')
        : ($period_due > 0.009 ? ame_pair('অবশিষ্ট বকেয়া', 'Remaining due') : ame_pair('হিসাব সমান', 'Settled')); ?></span>
      <b><?php echo $ame_taka($period_close); ?></b>
    </article>
    <?php if (abs($live_bal - $period_close) > 0.009): ?>
    <article class="<?php echo $held > 0.009 ? 'is-left' : ''; ?>">
      <span><?php echo ame_pair('বর্তমান লেজার', 'Live ledger'); ?></span>
      <b><?php echo $ame_taka($live_bal); ?></b>
    </article>
    <?php endif; ?>
  </div>
  <?php if ($formula !== ''): ?>
    <p class="cdesk-formula"><?php echo htmlspecialchars($formula, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="cdesk-table-wrap">
    <table class="cdesk-table">
      <thead>
        <tr>
          <th><?php echo ame_pair('তারিখ ও সময়', 'Date and time'); ?></th>
          <th><?php echo ame_pair('রেফারেন্স', 'Reference'); ?></th>
          <th><?php echo ame_pair('বিবরণ', 'Description'); ?></th>
          <th class="num"><?php echo ame_pair('ডেবিট', 'Debit'); ?></th>
          <th class="num"><?php echo ame_pair('ক্রেডিট', 'Credit'); ?></th>
          <th class="num"><?php echo ame_pair('রানিং ব্যালেন্স', 'Running Balance'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
          <tr><td colspan="6" class="cdesk-empty"><?php echo ame_pair('কোনো লেনদেন নেই।', 'No transactions yet.'); ?></td></tr>
        <?php else: foreach ($entries as $e):
            $desc = $clean((string) ($e['description'] ?? ''));
            $ref = $clean((string) ($e['ref_no'] ?? ''));
            $debit = (float) ($e['debit'] ?? 0);
            $credit = (float) ($e['credit'] ?? 0);
            $when = (string) ($e['txn_date'] ?? '');
            if (!empty($e['created_at'])) {
                $clock = strtotime((string) $e['created_at']);
                if ($clock) {
                    $when .= ' ' . date('H:i', $clock);
                }
            }
            $who = trim((string) ($e['user_label'] ?? ''));
            if ($who !== '') {
                $ref = ($ref !== '' ? $ref . ' · ' : '') . $who;
            }
            $href = is_array($e['memo_href'] ?? null) ? $e['memo_href'] : [];
        ?>
        <tr>
          <td><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <?php if (!empty($href['view'])): ?>
              <a href="<?php echo htmlspecialchars((string)$href['view'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ref !== '' ? $ref : 'মেমো', ENT_QUOTES, 'UTF-8'); ?></a>
              <span class="cdesk-memo-acts no-print">
                <a href="<?php echo htmlspecialchars((string)$href['share'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ame_pair('শেয়ার', 'Share'); ?></a>
                <a href="<?php echo htmlspecialchars((string)$href['print'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo ame_pair('প্রিন্ট', 'Print'); ?></a>
              </span>
            <?php else: ?>
              <?php echo htmlspecialchars($ref !== '' ? $ref : '—', ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
          </td>
          <td<?php echo !empty($e['work_lines']) && is_array($e['work_lines']) ? ' class="cdesk-has-work"' : ''; ?>>
            <?php echo htmlspecialchars($desc !== '' ? $desc : (string) $e['module_type'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($e['work_lines']) && is_array($e['work_lines'])): ?>
              <div class="cdesk-work">
                <b><?php echo ame_pair('ইঞ্জিনিয়ার ও কাজের বিল', 'Engineer work'); ?></b>
                <table class="cdesk-work-table">
                  <thead>
                    <tr>
                      <th><?php echo ame_pair('কর্মী', 'Worker'); ?></th>
                      <th><?php echo ame_pair('কাজের বিবরণ', 'Work'); ?></th>
                      <th class="num"><?php echo ame_pair('টাকা', 'Amount'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($e['work_lines'] as $work):
                        $wname = trim((string)($work['engineer_name'] ?? ''));
                        $wtype = trim((string)($work['service_type'] ?? ''));
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($wname !== '' ? $wname : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($wtype !== '' ? $wtype : 'কাজের বিল', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="num"><?php echo number_format((float)($work['amount'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </td>
          <td class="num bill"><?php echo ame_statement_signed_amount($debit, '+'); ?></td>
          <td class="num dep"><?php echo ame_statement_signed_amount($credit, '-'); ?></td>
          <td class="num"><?php echo number_format((float) $e['running_balance'], 2); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if ($entries): ?>
      <tfoot>
        <tr>
          <td colspan="3"><?php echo ame_pair('সর্বমোট', 'Totals'); ?></td>
          <td class="num bill"><?php echo ame_statement_signed_amount($total_bill, '+'); ?></td>
          <td class="num dep"><?php echo ame_statement_signed_amount($total_dep, '-'); ?></td>
          <td class="num"><?php echo $ame_taka($period_close); ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  </div>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
