<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$d = ame_smart_dashboard($db);
$p = $d['pulse'];
$pr = $d['profit'];
$en = is_en();

$sales_map = [];
foreach (ame_pulse_rows(
    $db,
    "SELECT DATE_FORMAT(sale_date,'%Y-%m') ym,
            DATE_FORMAT(sale_date,'%b') label,
            COALESCE(SUM(net_payable),0) total
     FROM sales
     WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY ym, label ORDER BY ym",
    12
) as $row) {
    $sales_map[(string)$row['ym']] = $row;
}
$sales_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('first day of -' . $i . ' months');
    $ym = date('Y-m', $ts);
    $sales_trend[] = $sales_map[$ym] ?? [
        'ym' => $ym,
        'label' => date('M', $ts),
        'total' => 0,
    ];
}
$trend_values = array_map(static fn($row) => (float)$row['total'], $sales_trend);
$trend_max = ame_chart_peak($trend_values);
$has_sales_trend = array_sum($trend_values) > 0;

$mix = [
    ['name' => $en ? 'Sales' : 'বিক্রি', 'total' => (float)($p['today_sales'] ?? 0), 'color' => '#28ad67'],
    ['name' => $en ? 'Purchase' : 'ক্রয়', 'total' => (float)($d['today_buy'] ?? 0), 'color' => '#4c8ee7'],
    ['name' => $en ? 'Profit' : 'লাভ', 'total' => max(0, (float)($pr['real'] ?? 0)), 'color' => '#70cc8c'],
    ['name' => $en ? 'Due' : 'বকেয়া', 'total' => (float)($p['due_sum'] ?? 0), 'color' => '#f2b91d'],
];
$mix_total = array_sum(array_map(static fn($row) => (float)$row['total'], $mix));
$donut_parts = [];
$donut_cursor = 0.0;
foreach ($mix as $row) {
    $pct = $mix_total > 0 ? ((float)$row['total'] / $mix_total) * 100 : 0;
    $next = $donut_cursor + $pct;
    $donut_parts[] = $row['color'] . ' ' . round($donut_cursor, 2) . '% ' . round($next, 2) . '%';
    $donut_cursor = $next;
}
$donut = $donut_parts && $mix_total > 0
    ? 'conic-gradient(' . implode(',', $donut_parts) . ')'
    : 'conic-gradient(#dceee0 0 100%)';

ame_smart_open('smart_title_dash', 'smart_lead_dash');
?>
<div class="smt-dashboard smt-if">
  <section class="smt-dash-kpis">
    <a class="smt-dash-kpi" href="/modules/sales/history.php">
      <i class="smt-dash-icon green">৳</i>
      <small><?php echo $en ? 'Today sales' : 'আজকের বিক্রি'; ?></small>
      <b><?php echo ame_smart_money((float)$p['today_sales']); ?></b>
      <em class="<?php echo ((float)$p['delta'] < 0) ? 'down' : ''; ?>"><?php echo ((float)$p['delta'] >= 0) ? '+' : ''; ?><?php echo number_format((float)$p['delta'], 1); ?>% <?php echo $en ? 'vs yesterday' : 'গতকালের তুলনায়'; ?></em>
    </a>
    <a class="smt-dash-kpi" href="/modules/smart/profit.php">
      <i class="smt-dash-icon blue">✓</i>
      <small><?php echo $en ? 'Today profit' : 'আজকের লাভ'; ?></small>
      <b><?php echo ame_smart_money((float)$pr['real']); ?></b>
      <em><?php echo $en ? 'After discount and cost' : 'ডিসকাউন্ট ও খরচ বাদ'; ?></em>
    </a>
    <a class="smt-dash-kpi" href="/modules/customers/due-reminder.php">
      <i class="smt-dash-icon purple">▤</i>
      <small><?php echo $en ? 'Total due' : 'মোট বকেয়া'; ?></small>
      <b><?php echo ame_smart_money((float)$p['due_sum']); ?></b>
      <em class="down"><?php echo (int)$p['due_over']; ?><?php echo $en ? ' overdue' : ' জন পার'; ?></em>
    </a>
    <a class="smt-dash-kpi" href="/modules/smart/stock.php">
      <i class="smt-dash-icon amber">△</i>
      <small><?php echo $en ? 'Low stock' : 'কম স্টক'; ?></small>
      <b><?php echo (int)$p['low_stock']; ?></b>
      <em class="<?php echo (int)$p['low_stock'] > 0 ? 'down' : ''; ?>"><?php echo $en ? 'vs stock floor' : 'মিনিমাম স্টকের তুলনায়'; ?></em>
    </a>
    <a class="smt-dash-kpi" href="/modules/smart/purchase.php">
      <i class="smt-dash-icon red">▣</i>
      <small><?php echo $en ? 'Buy this week' : 'ক্রয় সাজেশন'; ?></small>
      <b><?php echo count($d['week']); ?></b>
      <em><?php echo $en ? 'Suggested this week' : 'এই সপ্তাহে কিনুন'; ?></em>
    </a>
  </section>

  <section class="smt-dash-main">
    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Sales value trend' : 'বিক্রয় মূল্যের প্রবণতা'; ?></h3>
        <span><?php echo $en ? 'Last 6 months' : 'গত ৬ মাস'; ?></span>
      </header>
      <?php if ($has_sales_trend): ?>
      <div class="smt-dash-trend smt-if-trend">
        <?php echo ame_smart_line_svg($sales_trend); ?>
      </div>
      <?php else: ?>
      <div class="smt-dash-empty"><?php echo $en ? 'Sales trend will appear after bills are recorded.' : 'বিল হলে বিক্রয়ের ট্রেন্ড এখানে দেখা যাবে।'; ?></div>
      <?php endif; ?>
    </article>

    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Today mix' : 'আজকের মিশ্রণ'; ?></h3>
        <a href="/modules/smart/profit.php"><?php echo $en ? 'Profit' : 'লাভ হিসাব'; ?></a>
      </header>
      <div class="smt-dash-category">
        <div class="smt-dash-donut" style="--donut:<?php echo htmlspecialchars($donut, ENT_QUOTES); ?>">
          <strong><?php echo ame_smart_money((float)$pr['real']); ?></strong>
          <small><?php echo $en ? 'Net profit' : 'নিট লাভ'; ?></small>
        </div>
        <div class="smt-dash-legend">
          <?php foreach ($mix as $row):
            $pct = $mix_total > 0 ? round(((float)$row['total'] / $mix_total) * 100) : 0;
          ?>
          <span><i style="--color:<?php echo $row['color']; ?>"></i><?php echo esc_html($row['name']); ?><b><?php echo $pct; ?>%</b></span>
          <?php endforeach; ?>
        </div>
      </div>
    </article>
  </section>

  <section class="smt-dash-three">
    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? "Today's tasks" : 'আজকের গুরুত্বপূর্ণ কাজ'; ?></h3>
        <a href="/modules/smart/calendar.php"><?php echo $en ? 'Calendar' : 'ক্যালেন্ডার'; ?></a>
      </header>
      <div class="smt-dash-list">
        <?php foreach (array_slice($d['tasks'], 0, 5) as $task): ?>
        <a href="<?php echo esc_html((string)$task['href']); ?>">
          <span>
            <strong><?php echo esc_html((string)$task['title']); ?></strong>
            <small><?php echo esc_html((string)($task['date'] . ' · ' . $task['kind'])); ?></small>
          </span>
          <b>›</b>
        </a>
        <?php endforeach; ?>
        <?php if (!$d['tasks']): ?>
        <div class="smt-dash-empty"><?php echo $en ? 'Pressure is low today. Open a new sale.' : 'আজ চাপ কম। নতুন সেল খুলুন।'; ?></div>
        <?php endif; ?>
      </div>
    </article>

    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Top selling today' : 'বেশি বিক্রি'; ?></h3>
        <a href="/modules/sales/history.php"><?php echo $en ? 'Sales' : 'বিক্রি'; ?></a>
      </header>
      <div class="smt-dash-list">
        <?php foreach ($d['top'] as $row): ?>
        <div>
          <span>
            <strong><?php echo esc_html((string)$row['name']); ?></strong>
            <small><?php echo $en ? 'Today' : 'আজ'; ?></small>
          </span>
          <b><?php echo (int)$row['q']; ?></b>
        </div>
        <?php endforeach; ?>
        <?php if (!$d['top']): ?>
        <div class="smt-dash-empty"><?php echo $en ? 'No products sold yet today.' : 'আজ এখনো পণ্য বিক্রি জমেনি।'; ?></div>
        <?php endif; ?>
      </div>
    </article>

    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Buy this week' : 'এই সপ্তাহে কিনতে হবে'; ?></h3>
        <a href="/modules/smart/stock.php"><?php echo $en ? 'Forecast' : 'অনুমান'; ?></a>
      </header>
      <div class="smt-dash-list smt-dash-alerts">
        <?php foreach (array_slice($d['week'], 0, 5) as $row): ?>
        <a href="/modules/smart/stock.php">
          <span>
            <strong><?php echo esc_html((string)$row['name']); ?></strong>
            <small><?php echo $en ? 'Stock ' : 'স্টক '; ?><?php echo (int)$row['stock']; ?> · <?php echo $row['cover_days'] >= 900 ? '—' : (int)$row['cover_days']; ?><?php echo $en ? ' days' : ' দিন'; ?></small>
          </span>
          <b><?php echo (int)$row['suggest']; ?></b>
        </a>
        <?php endforeach; ?>
        <?php if (!$d['week']): ?>
        <div class="smt-dash-empty"><?php echo $en ? 'No urgent purchase this week.' : 'জরুরি ক্রয় নেই।'; ?></div>
        <?php endif; ?>
      </div>
    </article>
  </section>

  <section class="smt-dash-bottom">
    <article class="smt-dash-panel">
      <header><h3><?php echo $en ? 'Quick actions' : 'দ্রুত কাজ'; ?></h3></header>
      <div class="smt-dash-quick">
        <a href="/modules/sales/pos.php"><span>🛒</span><?php echo $en ? 'New sale' : 'নতুন সেল'; ?></a>
        <a href="/modules/smart/purchase.php"><span>▣</span><?php echo $en ? 'Buy list' : 'ক্রয় তালিকা'; ?></a>
        <a href="/modules/smart/customers.php"><span>👤</span><?php echo $en ? 'Customers' : 'ক্রেতা'; ?></a>
        <a href="/modules/smart/assistant.php"><span>?</span><?php echo $en ? 'Ask' : 'জিজ্ঞেস'; ?></a>
      </div>
    </article>
    <article class="smt-dash-panel">
      <header><h3><?php echo $en ? "Today's summary" : 'আজকের সারসংক্ষেপ'; ?></h3></header>
      <div class="smt-dash-summary">
        <div><span><?php echo $en ? 'Purchases' : 'আজকের ক্রয়'; ?></span><b><?php echo ame_smart_money((float)$d['today_buy']); ?></b></div>
        <div><span><?php echo $en ? 'New customers' : 'নতুন ক্রেতা'; ?></span><b><?php echo (int)$d['new_customers']; ?></b></div>
        <div><span><?php echo $en ? 'New suppliers' : 'নতুন সাপ্লায়ার'; ?></span><b><?php echo (int)$d['new_suppliers']; ?></b></div>
        <div><span>VIP</span><b><?php echo (int)$d['vip']; ?></b></div>
      </div>
    </article>
  </section>
</div>
<?php
ame_smart_close();
include __DIR__ . '/../../partials/footer.php';
