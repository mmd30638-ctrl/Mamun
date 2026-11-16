<?php
require_once '../../includes/config.php';
if (!ame_can_see_agents($db)) {
    http_response_code(403);
    die('এই অংশ দেখার অনুমতি নেই।');
}

$p = ame_pulse_data($db);
$agents = ame_shop_agents($db, $p);
$asset_v = htmlspecialchars(AME_ASSET_VERSION);
$by_id = [];
foreach ($agents as $agent) {
    $by_id[(string)$agent['id']] = $agent;
}
$collection = $by_id['collection'] ?? ['id' => 'collection', 'name' => 'কালেকশন এজেন্ট', 'verdict' => '', 'why' => '', 'next' => '/modules/customers/due-reminder.php', 'next_label' => 'রিমাইন্ডার', 'items' => [], 'domain' => ''];
$stock = $by_id['stock'] ?? ['id' => 'stock', 'name' => 'স্টক এজেন্ট', 'verdict' => '', 'why' => '', 'next' => '/modules/inventory/list.php', 'next_label' => 'স্টক', 'items' => [], 'domain' => ''];
$cash = $by_id['cash'] ?? ['id' => 'cash', 'name' => 'ক্যাশ এজেন্ট', 'verdict' => '', 'why' => '', 'next' => '/modules/reports/day_closing.php', 'next_label' => 'দিন শেষ', 'items' => [], 'domain' => '', 'score' => 0];

$urgent_stock = 0;
foreach ($p['restock'] as $row) {
    if (!empty($row['urgent'])) {
        $urgent_stock++;
    }
}

$pay_map = [];
foreach (ame_pulse_rows(
    $db,
    "SELECT DATE_FORMAT(payment_date,'%Y-%m') ym,
            DATE_FORMAT(payment_date,'%b') label,
            COALESCE(SUM(amount),0) total
     FROM customer_payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY ym, label ORDER BY ym",
    12
) as $row) {
    $pay_map[(string)$row['ym']] = $row;
}
$collect_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('first day of -' . $i . ' months');
    $ym = date('Y-m', $ts);
    $collect_trend[] = $pay_map[$ym] ?? [
        'ym' => $ym,
        'label' => date('M', $ts),
        'total' => 0,
    ];
}
$trend_values = array_map(static fn($row) => (float)$row['total'], $collect_trend);
$trend_max = ame_chart_peak($trend_values);
$has_collect_trend = array_sum($trend_values) > 0;

$today_sales = (float)($p['today_sales'] ?? 0);
$today_collected = (float)($p['today_collected'] ?? 0);
$due_sum = (float)($p['due_sum'] ?? 0);
$cash_parts = [
    ['name' => is_en() ? 'Collected today' : 'আজকের আদায়', 'total' => $today_collected, 'color' => '#28ad67'],
    ['name' => is_en() ? 'Still due' : 'বকেয়া বাকি', 'total' => $due_sum, 'color' => '#f2b91d'],
];
$cash_total = array_sum(array_map(static fn($row) => (float)$row['total'], $cash_parts));
$donut_parts = [];
$donut_cursor = 0.0;
foreach ($cash_parts as $row) {
    $pct = $cash_total > 0 ? ((float)$row['total'] / $cash_total) * 100 : 0;
    $next = $donut_cursor + $pct;
    $donut_parts[] = $row['color'] . ' ' . round($donut_cursor, 2) . '% ' . round($next, 2) . '%';
    $donut_cursor = $next;
}
$donut = $donut_parts && $cash_total > 0
    ? 'conic-gradient(' . implode(',', $donut_parts) . ')'
    : 'conic-gradient(#dceee0 0 100%)';

$collect_rate = $today_sales > 0
    ? ($today_collected / $today_sales) * 100
    : ($today_collected > 0 ? 100.0 : 0.0);

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/agents.css?v=<?php echo $asset_v; ?>">

<div class="ag-dashboard">
  <section class="ag-dash-intro">
    <div>
      <span><?php echo is_en() ? 'THREE AGENTS · ONE LEDGER' : 'তিন এজেন্ট · এক খাতা'; ?></span>
      <h1><?php echo is_en() ? 'Agent command center' : 'এজেন্ট কমান্ড সেন্টার'; ?></h1>
      <p><?php echo is_en()
        ? 'Collection, stock and cash — shop-owned models, not a cloud AI. Every verdict says why.'
        : 'কালেকশন, স্টক ও ক্যাশ — দোকানের নিজস্ব মডেল, ক্লাউড AI নয়। প্রতিটা রায়ের সাথে কারণ আছে।'; ?></p>
    </div>
    <div class="ag-dash-actions">
      <a href="<?php echo esc_html((string)$collection['next']); ?>"><?php echo esc_html((string)$collection['next_label']); ?></a>
      <a class="soft" href="/modules/pulse/index.php"><?php echo is_en() ? 'Shop pulse' : 'দোকানের অবস্থা'; ?></a>
    </div>
  </section>

  <section class="ag-dash-kpis">
    <article class="ag-dash-kpi">
      <i class="ag-dash-icon green">৳</i>
      <small><?php echo esc_html(t('pulse_total_due')); ?></small>
      <b>৳<?php echo number_format($due_sum, 0); ?></b>
      <em class="<?php echo $due_sum > 0 ? 'down' : ''; ?>"><?php echo esc_html(t('pulse_due_meta', ['over' => (int)$p['due_over'], 'today' => (int)$p['due_today']])); ?></em>
    </article>
    <article class="ag-dash-kpi">
      <i class="ag-dash-icon blue">✓</i>
      <small><?php echo esc_html(t('pulse_today_collect')); ?></small>
      <b>৳<?php echo number_format($today_collected, 0); ?></b>
      <em><?php echo esc_html(t('pulse_bills', ['n' => (int)$p['today_bills']])); ?></em>
    </article>
    <article class="ag-dash-kpi">
      <i class="ag-dash-icon purple">%</i>
      <small><?php echo is_en() ? 'Collection rate' : 'আদায়ের হার'; ?></small>
      <b><?php echo number_format($collect_rate, 0); ?>%</b>
      <em><?php echo is_en() ? 'Collected vs today sales' : 'আজকের বিক্রির বিপরীতে আদায়'; ?></em>
    </article>
    <article class="ag-dash-kpi">
      <i class="ag-dash-icon amber">△</i>
      <small><?php echo is_en() ? 'Urgent restock' : 'জরুরি রিস্টক'; ?></small>
      <b><?php echo $urgent_stock; ?></b>
      <em class="<?php echo $urgent_stock > 0 ? 'down' : ''; ?>"><?php echo esc_html((string)$stock['verdict']); ?></em>
    </article>
    <article class="ag-dash-kpi">
      <i class="ag-dash-icon red">!</i>
      <small><?php echo is_en() ? 'Cash pressure' : 'ক্যাশ চাপ'; ?></small>
      <b><?php echo (int)($cash['score'] ?? 0); ?></b>
      <em class="down"><?php echo esc_html((string)$cash['verdict']); ?></em>
    </article>
  </section>

  <section class="ag-dash-main">
    <article class="ag-dash-panel">
      <header>
        <h2><?php echo is_en() ? 'Collection trend' : 'আদায়ের প্রবণতা'; ?></h2>
        <span><?php echo is_en() ? 'Last 6 months' : 'গত ৬ মাস'; ?></span>
      </header>
      <?php if ($has_collect_trend): ?>
      <div class="ag-dash-trend">
        <?php foreach ($collect_trend as $row):
          $height = max(8, ((float)$row['total'] / $trend_max) * 100);
        ?>
        <div class="ag-dash-trend-bar" style="--height:<?php echo round($height, 2); ?>%" title="৳<?php echo number_format((float)$row['total'], 0); ?>">
          <i></i><span><?php echo esc_html((string)$row['label']); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ag-dash-empty"><?php echo is_en() ? 'Collection trend will appear after payments are recorded.' : 'পেমেন্ট হলে আদায়ের ট্রেন্ড এখানে দেখা যাবে।'; ?></div>
      <?php endif; ?>
    </article>

    <article class="ag-dash-panel">
      <header>
        <h2><?php echo is_en() ? 'Cash mix' : 'ক্যাশ মিশ্রণ'; ?></h2>
        <a href="<?php echo esc_html((string)$cash['next']); ?>"><?php echo esc_html((string)$cash['next_label']); ?></a>
      </header>
      <div class="ag-dash-category">
        <div class="ag-dash-donut" style="--donut:<?php echo htmlspecialchars($donut, ENT_QUOTES); ?>">
          <strong><?php echo number_format($collect_rate, 0); ?>%</strong>
          <small><?php echo is_en() ? 'Collected' : 'আদায়'; ?></small>
        </div>
        <div class="ag-dash-legend">
          <?php foreach ($cash_parts as $row):
            $pct = $cash_total > 0 ? round(((float)$row['total'] / $cash_total) * 100) : 0;
          ?>
          <span><i style="--color:<?php echo $row['color']; ?>"></i><?php echo esc_html($row['name']); ?><b><?php echo $pct; ?>%</b></span>
          <?php endforeach; ?>
          <span><i style="--color:#4c8ee7;"></i><?php echo esc_html((string)$cash['why']); ?></span>
        </div>
      </div>
    </article>
  </section>

  <section class="ag-dash-three">
    <?php foreach ([$collection, $stock, $cash] as $agent): ?>
    <article class="ag-dash-panel" id="<?php echo esc_html((string)$agent['id']); ?>">
      <header>
        <h2><?php echo esc_html((string)$agent['name']); ?></h2>
        <a href="<?php echo esc_html((string)$agent['next']); ?>"><?php echo esc_html((string)$agent['next_label']); ?></a>
      </header>
      <p class="ag-dash-verdict"><?php echo esc_html((string)$agent['verdict']); ?></p>
      <p class="ag-dash-why"><?php echo esc_html((string)$agent['why']); ?></p>
      <div class="ag-dash-list">
        <?php foreach (array_slice($agent['items'] ?? [], 0, 4) as $item):
          $href = (string)($item['href'] ?? '');
          if ($href === '' && !empty($item['id'])) {
              $href = '/modules/customers/payment-receive.php?id=' . (int)$item['id'];
          }
          if ($href === '') {
              $href = (string)$agent['next'];
          }
        ?>
        <a href="<?php echo esc_html($href); ?>">
          <span>
            <strong><?php echo esc_html((string)$item['name']); ?></strong>
            <small><?php echo esc_html((string)($item['why'] ?? '')); ?></small>
          </span>
          <b><?php echo (int)($item['score'] ?? 0); ?></b>
        </a>
        <?php endforeach; ?>
        <?php if (empty($agent['items'])): ?>
        <div class="ag-dash-empty"><?php echo is_en() ? 'No queue for this agent right now.' : 'এই এজেন্টের এখন কোনো কিউ নেই।'; ?></div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </section>

  <section class="ag-dash-bottom">
    <article class="ag-dash-panel">
      <header><h2><?php echo is_en() ? 'Quick actions' : 'দ্রুত কাজ'; ?></h2></header>
      <div class="ag-dash-quick">
        <a href="/modules/customers/due-reminder.php"><span>৳</span><?php echo is_en() ? 'Due list' : 'বকেয়া তালিকা'; ?></a>
        <a href="/modules/purchase/add.php"><span>▣</span><?php echo is_en() ? 'Buy stock' : 'স্টক কিনুন'; ?></a>
        <a href="/modules/reports/day_closing.php"><span>✓</span><?php echo is_en() ? 'Day close' : 'দিন শেষ'; ?></a>
        <a href="/modules/settings/trust.php"><span>★</span><?php echo is_en() ? 'Trust scores' : 'ট্রাস্ট স্কোর'; ?></a>
      </div>
    </article>
    <article class="ag-dash-panel">
      <header><h2><?php echo is_en() ? "Today's summary" : 'আজকের সারসংক্ষেপ'; ?></h2></header>
      <div class="ag-dash-summary">
        <div><span><?php echo esc_html(t('pulse_today_sales')); ?></span><b>৳<?php echo number_format($today_sales, 0); ?></b></div>
        <div><span><?php echo esc_html(t('pulse_today_collect')); ?></span><b>৳<?php echo number_format($today_collected, 0); ?></b></div>
        <div><span><?php echo is_en() ? 'Overdue people' : 'তারিখ পার'; ?></span><b><?php echo (int)$p['due_over']; ?></b></div>
        <div><span><?php echo is_en() ? 'Urgent products' : 'জরুরি পণ্য'; ?></span><b><?php echo $urgent_stock; ?></b></div>
      </div>
    </article>
  </section>

  <section class="ag-dash-detail" id="collection-register">
    <header>
      <div>
        <h2><?php echo is_en() ? 'Collection queue' : 'কালেকশন কিউ'; ?></h2>
        <p><?php echo is_en() ? 'Highest-score dues first. Open a name to take payment.' : 'স্কোর বেশি যাদের, তারা আগে। নামে ক্লিক করে জমা নিন।'; ?></p>
      </div>
      <a href="/modules/customers/due-reminder.php"><?php echo is_en() ? 'View all' : 'সব দেখুন'; ?></a>
    </header>
    <?php if (empty($collection['items'])): ?>
    <div class="ag-dash-empty"><?php echo is_en() ? 'No collection pressure right now.' : 'এখন কালেকশনের চাপ নেই।'; ?></div>
    <?php else: foreach ($collection['items'] as $item): ?>
    <div class="ag-dash-row">
      <div>
        <div class="nm"><?php echo esc_html((string)$item['name']); ?></div>
        <div class="meta"><?php echo esc_html((string)$item['why']); ?></div>
      </div>
      <div class="amt"><?php echo number_format((float)($item['amount'] ?? 0), 0); ?> ৳</div>
      <div class="ag-dash-btns">
        <span class="score"><?php echo (int)($item['score'] ?? 0); ?></span>
        <a class="ink" href="/modules/customers/payment-receive.php?id=<?php echo (int)$item['id']; ?>"><?php echo is_en() ? 'Collect' : 'জমা নিন'; ?></a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </section>

  <section class="ag-dash-detail" id="stock-register">
    <header>
      <div>
        <h2><?php echo is_en() ? 'Stock queue' : 'স্টক কিউ'; ?></h2>
        <p><?php echo is_en() ? 'Velocity-based restock pressure for the next two weeks.' : 'গত ১৪ দিনের বিক্রি দেখে রিস্টক চাপ।'; ?></p>
      </div>
      <a href="/modules/inventory/list.php"><?php echo is_en() ? 'Stock list' : 'স্টক তালিকা'; ?></a>
    </header>
    <?php if (empty($stock['items'])): ?>
    <div class="ag-dash-empty"><?php echo is_en() ? 'No urgent restock queue.' : 'জরুরি রিস্টক কিউ নেই।'; ?></div>
    <?php else: foreach ($stock['items'] as $item): ?>
    <div class="ag-dash-row">
      <div>
        <div class="nm"><?php echo esc_html((string)$item['name']); ?></div>
        <div class="meta"><?php echo esc_html((string)$item['why']); ?></div>
      </div>
      <div class="amt"><?php echo (int)($item['score'] ?? 0); ?></div>
      <div class="ag-dash-btns">
        <a class="ink" href="<?php echo esc_html((string)($item['href'] ?? '/modules/purchase/add.php')); ?>"><?php echo is_en() ? 'Buy' : 'কিনুন'; ?></a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </section>
</div>
<?php include '../../partials/footer.php'; ?>
