<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$today = ame_smart_profit_today($db);
$sales = ame_smart_profit_sales($db, 24);
$en = is_en();

ame_smart_open('smart_title_profit', 'smart_lead_profit');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">৳</i>
      <small><?php echo $en ? 'Sell value' : 'বিক্রয়মূল্য'; ?></small>
      <b><?php echo ame_smart_money((float)$today['sell']); ?></b>
      <em><?php echo $en ? 'Item total today' : 'আজকের আইটেম যোগফল'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">▣</i>
      <small><?php echo $en ? 'Cost value' : 'ক্রয়মূল্য'; ?></small>
      <b><?php echo ame_smart_money((float)$today['cost']); ?></b>
      <em><?php echo $en ? 'Today sales cost' : 'আজকের সেল খরচ'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">−</i>
      <small><?php echo $en ? 'Discount' : 'ডিসকাউন্ট'; ?></small>
      <b><?php echo ame_smart_money((float)$today['discount']); ?></b>
      <em><?php echo $en ? 'Bill discount' : 'বিল ছাড়'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">✎</i>
      <small><?php echo $en ? 'Other cost' : 'অন্যান্য খরচ'; ?></small>
      <b><?php echo ame_smart_money((float)$today['other']); ?></b>
      <em><?php echo $en ? 'Expense entries' : 'আজকের খরচ এন্ট্রি'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">✓</i>
      <small><?php echo $en ? 'Real profit' : 'প্রকৃত লাভ'; ?></small>
      <b><?php echo ame_smart_money((float)$today['real']); ?></b>
      <em><?php echo $en ? 'Gross ' : 'গ্রস '; ?><?php echo ame_smart_money((float)$today['gross']); ?></em>
    </article>
  </section>

  <section class="smt-dash-bottom">
    <article class="smt-dash-panel">
      <header><h3><?php echo $en ? 'Today mix' : 'আজকের হিসাব'; ?></h3></header>
      <div class="smt-dash-summary">
        <div><span><?php echo $en ? 'Net bills' : 'নেট বিল'; ?></span><b><?php echo ame_smart_money((float)$today['net']); ?></b></div>
        <div><span><?php echo $en ? 'Gross profit' : 'গ্রস লাভ'; ?></span><b><?php echo ame_smart_money((float)$today['gross']); ?></b></div>
        <div><span><?php echo $en ? 'Sales rows' : 'সেল সংখ্যা'; ?></span><b><?php echo count($sales); ?></b></div>
        <div><span><?php echo $en ? 'Real profit' : 'প্রকৃত লাভ'; ?></span><b><?php echo ame_smart_money((float)$today['real']); ?></b></div>
      </div>
    </article>
    <article class="smt-dash-panel">
      <header><h3><?php echo $en ? 'Quick actions' : 'দ্রুত কাজ'; ?></h3></header>
      <div class="smt-dash-quick">
        <a href="/modules/sales/pos.php"><span>🛒</span><?php echo $en ? 'POS' : 'POS'; ?></a>
        <a href="/modules/sales/history.php"><span>▤</span><?php echo $en ? 'Sales' : 'বিক্রি'; ?></a>
        <a href="/modules/reports/day_closing.php"><span>✓</span><?php echo $en ? 'Day close' : 'দিন শেষ'; ?></a>
        <a href="/modules/smart/index.php"><span>◎</span><?php echo $en ? 'Dashboard' : 'ড্যাশবোর্ড'; ?></a>
      </div>
    </article>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Recent sales' : 'সাম্প্রতিক সেল'; ?></h3>
        <p><?php echo $en ? 'Cost, sell, discount and real profit per invoice.' : 'প্রতি ইনভয়েসে ক্রয়, বিক্রি, ছাড় ও প্রকৃত লাভ।'; ?></p>
      </div>
      <a href="/modules/sales/history.php"><?php echo $en ? 'All sales' : 'সব বিক্রি'; ?></a>
    </header>
    <?php if (!$sales): ?>
    <div class="smt-dash-empty"><?php echo $en ? 'No completed sales yet.' : 'সম্পন্ন সেল এখনো নেই।'; ?></div>
    <?php else: ?>
    <div class="smt-table-wrap">
      <table class="smt-table">
        <thead>
          <tr>
            <th><?php echo $en ? 'Invoice' : 'ইনভয়েস'; ?></th>
            <th><?php echo $en ? 'Customer' : 'ক্রেতা'; ?></th>
            <th><?php echo $en ? 'Cost' : 'ক্রয়'; ?></th>
            <th><?php echo $en ? 'Sell' : 'বিক্রি'; ?></th>
            <th><?php echo $en ? 'Discount' : 'ছাড়'; ?></th>
            <th><?php echo $en ? 'Profit' : 'লাভ'; ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sales as $sale): ?>
          <tr>
            <td><?php echo esc_html((string)$sale['invoice_no']); ?></td>
            <td><?php echo esc_html((string)$sale['customer_name']); ?></td>
            <td><?php echo ame_smart_money((float)$sale['cost']); ?></td>
            <td><?php echo ame_smart_money((float)$sale['sell']); ?></td>
            <td><?php echo ame_smart_money((float)$sale['discount']); ?></td>
            <td><strong><?php echo ame_smart_money((float)$sale['real']); ?></strong></td>
            <td><a class="smt-ghost" href="/modules/sales/invoice.php?id=<?php echo (int)$sale['id']; ?>"><?php echo $en ? 'Receipt' : 'রসিদ'; ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
