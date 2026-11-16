<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        $text = function_exists('ame_l') ? ame_l($bn, $en) : $bn;
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
$filter_id = (int) ($_GET['customer_id'] ?? 0);
$view = strtolower(trim((string) ($_GET['view'] ?? 'live')));
if ($view !== 'inactive') {
    $view = 'live';
}
$filter_cust = null;
if ($filter_id > 0) {
    $st = $db->prepare('SELECT id, name, customer_code, phone, status FROM customers WHERE id = ?');
    if ($st) {
        $st->bind_param('i', $filter_id);
        $st->execute();
        $filter_cust = $st->get_result()->fetch_assoc();
        $st->close();
    }
    if ($filter_cust && function_exists('ame_customer_row_operable') && !ame_customer_row_operable($filter_cust)) {
        $filter_cust = null;
        $filter_id = 0;
    }
}
$stats = hire_dashboard_stats($db);
$rows = function_exists('hire_desk_live_rows') ? hire_desk_live_rows($db, $filter_id, $view) : [];
$inactiveSql = "SELECT COUNT(*) FROM rental_contracts WHERE status = 'INACTIVE'";
if ($filter_id > 0) {
    $inactiveSql .= ' AND customer_id = ' . $filter_id;
}
$inactiveN = 0;
$ires = @$db->query($inactiveSql);
if ($ires) {
    $inactiveN = (int) ($ires->fetch_row()[0] ?? 0);
}
$accounts = hire_accounts($db);
$canPay = hire_can($db, 'edit');
$canStop = hire_can($db, 'edit');
$here = 'index';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.754', ENT_QUOTES, 'UTF-8');
$qs = $filter_id > 0 ? ('customer_id=' . $filter_id . '&') : '';
$createHref = 'create.php' . ($filter_id > 0 ? ('?customer_id=' . $filter_id) : '');
$liveHref = 'index.php' . ($filter_id > 0 ? ('?customer_id=' . $filter_id) : '');
$offHref = 'index.php?' . $qs . 'view=inactive';
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-pro.css?v=<?php echo $av; ?>">
<div class="hire-wrap hire-desk-simple">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <h1><?php echo $filter_cust ? hire_h($filter_cust['name']) . ' — ভাড়া' : 'দৈনিক ভাড়া'; ?></h1>
    <p>টাকা নিলে বকেয়া কমে। প্রতিদিন বাংলাদেশ রাত ১২টায় খাতায় যোগ হয়। শুরুর তারিখ আগে হলে মিসিং দিন এখনই যোগ হয়। ইনঅ্যাকটিভ করলে নতুন দিনের ভাড়া বন্ধ — আগের খাতা থাকে।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="hire-cards hire-cards-simple hire-cards-four">
    <article class="hire-card"><span>চলমান</span><b><?php echo (int) $stats['active']; ?></b></article>
    <article class="hire-card is-danger"><span>বকেয়া</span><b>৳<?php echo number_format((float) $stats['total_due'], 0); ?></b></article>
    <article class="hire-card"><span>আজকের ভাড়া</span><b>৳<?php echo number_format((float) $stats['today_rental'], 0); ?></b></article>
    <article class="hire-card"><span>ইনঅ্যাকটিভ</span><b><?php echo $inactiveN; ?></b></article>
  </section>

  <nav class="hire-view-tabs no-print">
    <a class="<?php echo $view === 'live' ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($liveHref, ENT_QUOTES, 'UTF-8'); ?>">চলমান</a>
    <a class="<?php echo $view === 'inactive' ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($offHref, ENT_QUOTES, 'UTF-8'); ?>">ইনঅ্যাকটিভ (<?php echo $inactiveN; ?>)</a>
    <a class="hire-btn hire-btn-primary" href="<?php echo htmlspecialchars($createHref, ENT_QUOTES, 'UTF-8'); ?>">নতুন চুক্তি</a>
  </nav>

  <?php if ($rows === []): ?>
  <section class="hire-panel">
    <p class="hire-empty">
      <?php if ($view === 'inactive'): ?>
        কোনো ইনঅ্যাকটিভ চুক্তি নেই।
      <?php else: ?>
        <?php echo $filter_cust
          ? 'এই কাস্টমারের চলমান ভাড়া চুক্তি নেই।'
          : 'এখন কোনো চলমান ভাড়া চুক্তি নেই। নতুন চুক্তি দিয়ে আবার যোগ করুন।'; ?>
      <?php endif; ?>
    </p>
  </section>
  <?php else:
        $plainStyle = function_exists('hire_plain_box_style') ? hire_plain_box_style('#86efac') : 'display:block!important;visibility:visible!important;opacity:1!important;position:static!important;height:auto!important;margin:0 0 10px;padding:10px 12px;background:#fff;border:1px solid #86efac;border-radius:12px;color:#111;font-size:16px';
        $nameStyle = function_exists('hire_plain_name_style') ? hire_plain_name_style() : 'display:block!important;visibility:visible!important;font-weight:800;margin:8px 0 12px;color:#111;font-size:15px';
        $nameBits = [];
        foreach ($rows as $bit) {
            $nm = trim((string) ($bit['customer_name'] ?? ''));
            if ($nm === '') {
                $nm = 'কাস্টমার';
            }
            $nameBits[] = (string) ($bit['rental_no'] ?? '') . ' · ' . $nm . ' · ৳' . number_format((float) ($bit['due'] ?? 0), 0);
        }
  ?>
  <p id="hireDeskNames" style="<?php echo $nameStyle; ?>">দেখাচ্ছে <?php echo count($rows); ?>টি: <?php echo hire_h(implode(' | ', $nameBits)); ?></p>
  <div id="hirePlainBox" class="hire-plain-box">
    <?php foreach ($rows as $u):
        $due = (float) $u['due'];
        $rate = (float) $u['daily_rate'];
        $st = (string) $u['status'];
        $inactive = $st === 'INACTIVE';
        $rowBorder = $inactive ? '#d7dee8' : '#86efac';
        $rowStyle = function_exists('hire_plain_box_style') ? hire_plain_box_style($rowBorder) : $plainStyle;
        $who = trim((string) $u['customer_name']) !== '' ? (string) $u['customer_name'] : 'কাস্টমার';
    ?>
    <div id="hire-plain-<?php echo (int) $u['id']; ?>" data-hire-row="<?php echo (int) $u['id']; ?>" style="<?php echo $rowStyle; ?>">
      <div style="font-size:12px;font-weight:800;color:#003f8f"><?php echo hire_h($u['rental_no']); ?> · <?php echo hire_h($u['item_name']); ?> · <?php echo hire_h(hire_status_label($st)); ?></div>
      <div style="font-size:18px;font-weight:800;margin:2px 0 6px">
        <a href="/modules/customers/profile.php?id=<?php echo (int) $u['customer_id']; ?>" style="color:#111;text-decoration:none"><?php echo hire_h($who); ?></a>
      </div>
      <div style="display:block;margin:0 0 8px">দৈনিক ৳<?php echo number_format($rate, 0); ?> · বকেয়া ৳<?php echo number_format($due, 0); ?></div>
      <?php if ($canPay && $due > 0.009): ?>
      <form method="post" action="actions.php" style="display:block;margin:0 0 8px">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="pay">
        <input type="hidden" name="return" value="desk">
        <input type="hidden" name="desk_customer_id" value="<?php echo $filter_id; ?>">
        <input type="hidden" name="contract_id" value="<?php echo (int) $u['id']; ?>">
        <input type="hidden" name="pay_date" value="<?php echo hire_h(function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d')); ?>">
        <label>টাকা নিন (৳)
          <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars(number_format($due, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>মাধ্যম
          <select name="payment_method">
            <option value="CASH">নগদ</option>
            <option value="BANK">ব্যাংক</option>
            <option value="MOBILE">মোবাইল</option>
          </select>
        </label>
        <label>অ্যাকাউন্ট
          <select name="account_id">
            <?php foreach ($accounts as $a): ?>
            <option value="<?php echo (int) $a['id']; ?>"><?php echo hire_h(function_exists('ame_account_option_label') ? ame_account_option_label($a) : ($a['account_name'] ?? $a['name'] ?? '#' . $a['id'])); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="hire-btn hire-btn-money" type="submit">পেমেন্ট নিন</button>
      </form>
      <?php endif; ?>
      <div style="display:block;margin-top:6px">
        <?php if ($canStop && !$inactive): ?>
        <form method="post" action="actions.php" style="display:inline-block;margin-right:8px" onsubmit="return confirm('ইনঅ্যাকটিভ করলে আগামী দিনের ভাড়া খাতায় যোগ হবে না। আগের হিসাব থাকবে।');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="do" value="deactivate">
          <input type="hidden" name="return" value="desk">
          <input type="hidden" name="desk_customer_id" value="<?php echo $filter_id; ?>">
          <input type="hidden" name="contract_id" value="<?php echo (int) $u['id']; ?>">
          <input type="hidden" name="reason" value="ইনঅ্যাকটিভ">
          <button class="hire-btn hire-btn-off" type="submit">ইনঅ্যাকটিভ</button>
        </form>
        <?php endif; ?>
        <?php if ($canStop && $inactive): ?>
        <form method="post" action="actions.php" style="display:inline-block;margin-right:8px">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="do" value="activate">
          <input type="hidden" name="return" value="desk">
          <input type="hidden" name="desk_customer_id" value="<?php echo $filter_id; ?>">
          <input type="hidden" name="contract_id" value="<?php echo (int) $u['id']; ?>">
          <input type="hidden" name="reason" value="আবার চালু">
          <button class="hire-btn hire-btn-primary" type="submit">আবার চালু</button>
        </form>
        <?php endif; ?>
        <a class="hire-btn" href="contract.php?id=<?php echo (int) $u['id']; ?>">বিস্তারিত</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
