<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/pulse_functions.php';
if (!ame_can_see_pulse($db)) {
    http_response_code(403);
    die(t('pulse_forbidden'));
}

$p = ame_pulse_data($db);
$script = ame_pulse_script($p);
$agents = ame_shop_agents($db, $p);
$can_pay = isset($_SESSION['role']) && (strtolower((string)$_SESSION['role']) === 'admin' || has_permission($db, 'm-customers'));
$can_buy = isset($_SESSION['role']) && (strtolower((string)$_SESSION['role']) === 'admin' || has_permission($db, 'm-purchase') || has_permission($db, 'm-inv'));
$today = date('Y-m-d');
$who = trim((string)($_SESSION['username'] ?? $_SESSION['name'] ?? 'Admin'));
$asset_v = htmlspecialchars(AME_ASSET_VERSION);

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
$trend_max = $trend_values ? max(1, max($trend_values)) : 1;
$has_sales_trend = array_sum($trend_values) > 0;

$category_rows = ame_pulse_rows(
    $db,
    "SELECT COALESCE(NULLIF(c.name,''),'সাধারণ') name,
            COALESCE(SUM(COALESCE(p.stock,0) * COALESCE(p.purchase_price,0)),0) total
     FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.status='active' OR p.status IS NULL OR p.status=''
     GROUP BY COALESCE(NULLIF(c.name,''),'সাধারণ')
     HAVING total > 0 ORDER BY total DESC",
    5
);
$category_total = array_sum(array_map(static fn($row) => (float)$row['total'], $category_rows));
$category_colors = ['#047857', '#1D4ED8', '#C2410C', '#7C3AED', '#B45309'];
$donut_parts = [];
$donut_cursor = 0.0;
foreach ($category_rows as $i => $row) {
    $pct = $category_total > 0 ? ((float)$row['total'] / $category_total) * 100 : 0;
    $next = $donut_cursor + $pct;
    $donut_parts[] = $category_colors[$i % count($category_colors)] . ' '
        . round($donut_cursor, 2) . '% ' . round($next, 2) . '%';
    $donut_cursor = $next;
}
$donut = $donut_parts ? 'conic-gradient(' . implode(',', $donut_parts) . ')' : 'conic-gradient(#C5D4E8 0 100%)';

$total_products = (int)ame_pulse_scalar(
    $db,
    "SELECT COUNT(*) FROM products WHERE status='active' OR status IS NULL OR status=''"
);
$out_of_stock = (int)ame_pulse_scalar(
    $db,
    "SELECT COUNT(*) FROM products WHERE (status='active' OR status IS NULL OR status='') AND COALESCE(stock,0)<=0"
);
$out_stock_value = (float)ame_pulse_scalar(
    $db,
    "SELECT COALESCE(SUM(GREATEST(COALESCE(min_stock,0),1) * COALESCE(purchase_price,0)),0)
     FROM products
     WHERE (status='active' OR status IS NULL OR status='') AND COALESCE(stock,0)<=0"
);

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/studio.css?v=<?php echo $asset_v; ?>">
<link rel="stylesheet" href="/assets/css/pulse.css?v=<?php echo $asset_v; ?>">

<div class="studio-page pulse-page pulse-dashboard pulse-board">
  <div class="studio-shell">
    <section class="pulse-dash-intro">
      <div>
        <span><?php echo esc_html(t('pulse_kicker')); ?> · <?php echo esc_html(ame_pulse_board_when()); ?></span>
        <h1><?php echo is_en() ? 'Shop status' : 'দোকানের অবস্থা'; ?></h1>
        <p><?php echo is_en() ? 'Hello, ' . $who . '. ' : 'হ্যালো, ' . $who . '। '; ?><?php echo esc_html($script); ?></p>
      </div>
      <div class="pulse-dash-intro-mark">
        <?php echo is_en() ? 'Live board' : 'লাইভ বোর্ড'; ?>
        <small><?php echo esc_html((string)($p['shop'] ?? '')); ?></small>
      </div>
    </section>

    <?php if ($is_admin_hdr || has_permission($db, 'm-transactions') || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')): ?>
    <a class="pulse-dash-audit" href="/modules/accounts/daily_audit.php">
      <strong><?php echo is_en() ? 'Complete books & audit' : 'সম্পূর্ণ হিসাব ও অডিট'; ?></strong>
      <small><?php echo is_en() ? 'Today through 100 days — cash, bank, users, bills. One page.' : 'আজ থেকে ১০০ দিন — ক্যাশ, ব্যাংক, ইউজার, বিল। একটি পেজ।'; ?></small>
    </a>
    <?php endif; ?>

    <section class="pulse-dash-kpis">
      <article class="pulse-dash-kpi tone-sales">
        <small><?php echo esc_html(t('pulse_today_sales')); ?></small>
        <b class="pulse-taka"><?php echo ame_pulse_taka((float)($p['today_sales'] ?? 0)); ?></b>
        <em class="<?php echo ((float)$p['delta'] < 0) ? 'down' : ''; ?>"><?php echo ((float)$p['delta'] >= 0) ? '+' : ''; ?><?php echo number_format((float)($p['delta'] ?? 0), 0); ?>% <?php echo esc_html(t('pulse_vs_yesterday')); ?></em>
      </article>
      <article class="pulse-dash-kpi tone-collect">
        <small><?php echo esc_html(t('pulse_today_collect')); ?></small>
        <b class="pulse-taka"><?php echo ame_pulse_taka((float)($p['today_collected'] ?? 0)); ?></b>
        <em><?php echo esc_html(t('pulse_bills', ['n' => (int)$p['today_bills']])); ?></em>
      </article>
      <article class="pulse-dash-kpi tone-due">
        <small><?php echo esc_html(t('pulse_total_due')); ?></small>
        <b class="pulse-taka"><?php echo ame_pulse_taka((float)($p['due_sum'] ?? 0)); ?></b>
        <em class="down"><?php echo esc_html(t('pulse_due_meta', ['over' => (int)$p['due_over'], 'today' => (int)$p['due_today']])); ?></em>
      </article>
      <article class="pulse-dash-kpi tone-stock">
        <small><?php echo is_en() ? 'Stock value' : 'স্টক মূল্য'; ?></small>
        <b class="pulse-taka"><?php echo ame_pulse_taka((float)$category_total); ?></b>
        <em class="<?php echo ((int)$p['low_stock'] > 0) ? 'down' : ''; ?>"><?php echo (int)$p['low_stock']; ?> <?php echo esc_html(t('pulse_alert_line')); ?></em>
      </article>
      <article class="pulse-dash-kpi tone-out">
        <small><?php echo is_en() ? 'Out of stock value' : 'স্টক শেষ · টাকা'; ?></small>
        <b class="pulse-taka"><?php echo ame_pulse_taka((float)$out_stock_value); ?></b>
        <em><?php echo is_en() ? $out_of_stock . ' empty · ' . $total_products . ' active' : $out_of_stock . 'টি খালি · ' . $total_products . 'টি সক্রিয়'; ?></em>
      </article>
    </section>

    <section class="pulse-dash-main">
      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo is_en() ? 'Sales value trend' : 'বিক্রয় মূল্যের প্রবণতা'; ?></h2>
          <span><?php echo is_en() ? 'Last 6 months' : 'গত ৬ মাস'; ?></span>
        </header>
        <?php if ($has_sales_trend): ?>
        <div class="pulse-dash-trend">
          <?php foreach ($sales_trend as $row):
            $height = max(8, ((float)$row['total'] / $trend_max) * 100);
          ?>
          <div class="pulse-dash-trend-bar" style="--height:<?php echo round($height, 2); ?>%" title="৳<?php echo number_format((float)$row['total'], 0); ?>">
            <i></i><span><?php echo esc_html((string)$row['label']); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pulse-dash-empty"><?php echo is_en() ? 'Sales trend will appear when transactions are recorded.' : 'লেনদেন হলে বিক্রয়ের ট্রেন্ড এখানে দেখা যাবে।'; ?></div>
        <?php endif; ?>
      </article>

      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo is_en() ? 'Stock value by category' : 'ক্যাটাগরিভিত্তিক স্টক মূল্য'; ?></h2>
          <a href="/modules/reports/valuation.php"><?php echo is_en() ? 'View details' : 'বিস্তারিত'; ?></a>
        </header>
        <div class="pulse-dash-cat-total">
          <small><?php echo is_en() ? 'Total stock value' : 'মোট স্টক মূল্য'; ?></small>
          <b class="pulse-taka"><?php echo ame_pulse_taka((float)$category_total); ?></b>
        </div>
        <div class="pulse-dash-category">
          <div class="pulse-dash-donut" style="--donut:<?php echo htmlspecialchars($donut, ENT_QUOTES); ?>" aria-hidden="true"></div>
          <div class="pulse-dash-legend">
            <?php foreach ($category_rows as $i => $row):
              $pct = $category_total > 0 ? round(((float)$row['total'] / $category_total) * 100) : 0;
            ?>
            <span>
              <i style="--color:<?php echo $category_colors[$i % count($category_colors)]; ?>"></i>
              <em><?php echo esc_html((string)$row['name']); ?> · <?php echo (int)$pct; ?>%</em>
              <b class="pulse-taka"><?php echo ame_pulse_taka((float)$row['total']); ?></b>
            </span>
            <?php endforeach; ?>
            <?php if (!$category_rows): ?><div class="pulse-dash-empty"><?php echo is_en() ? 'No stock value yet.' : 'এখনো স্টক মূল্য নেই।'; ?></div><?php endif; ?>
          </div>
        </div>
      </article>
    </section>

    <section class="pulse-dash-three">
      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo is_en() ? 'Business agents' : 'ব্যবসা এজেন্ট'; ?></h2>
          <a href="/modules/agents/index.php"><?php echo is_en() ? 'View all' : 'সব দেখুন'; ?></a>
        </header>
        <div class="pulse-dash-list">
          <?php foreach ($agents as $agent): ?>
          <a href="<?php echo esc_html((string)($agent['next'] ?? '/modules/agents/index.php')); ?>">
            <span>
              <strong><?php echo esc_html((string)$agent['name']); ?></strong>
              <small><?php echo esc_html((string)$agent['verdict']); ?></small>
            </span>
            <b>›</b>
          </a>
          <?php endforeach; ?>
        </div>
      </article>

      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo esc_html(t('pulse_do_now')); ?></h2>
          <span><?php echo count($p['actions']); ?></span>
        </header>
        <div class="pulse-dash-list">
          <?php foreach (array_slice($p['actions'], 0, 5) as $action): ?>
          <a href="<?php echo esc_html((string)$action['href']); ?>">
            <span>
              <strong><?php echo esc_html((string)$action['title']); ?></strong>
              <small><?php echo esc_html((string)$action['lead']); ?></small>
            </span>
            <b>›</b>
          </a>
          <?php endforeach; ?>
        </div>
      </article>

      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo is_en() ? 'Low stock alerts' : 'কম স্টক সতর্কতা'; ?></h2>
          <a href="#restock"><?php echo is_en() ? 'View all' : 'সব দেখুন'; ?></a>
        </header>
        <div class="pulse-dash-list pulse-dash-alerts">
          <?php foreach (array_slice($p['restock'], 0, 5) as $row): ?>
          <a href="/modules/inventory/edit.php?id=<?php echo (int)$row['id']; ?>">
            <span>
              <strong><?php echo esc_html((string)$row['name']); ?></strong>
              <small><?php echo esc_html(t('pulse_stock_left', ['n' => number_format((float)($row['stock'] ?? 0), 1)])); ?> · <?php echo esc_html(t('pulse_days_left', ['n' => $row['cover_days'] >= 90 ? '৯০+' : (string)round((float)$row['cover_days'])])); ?></small>
            </span>
            <b class="pulse-taka"><?php echo ame_pulse_taka((float)$row['suggest'] * (float)($row['purchase_price'] ?? 0)); ?></b>
          </a>
          <?php endforeach; ?>
          <?php if (!$p['restock']): ?><div class="pulse-dash-empty"><?php echo esc_html(t('pulse_restock_empty')); ?></div><?php endif; ?>
        </div>
      </article>
    </section>

    <section class="pulse-dash-bottom">
      <article class="pulse-dash-panel">
        <header><h2><?php echo esc_html(t('pulse_shortcuts')); ?></h2></header>
        <div class="pulse-dash-quick">
          <?php if ($is_admin_hdr || has_permission($db, 'm-sales')): ?><a class="q-sale" href="/modules/sales/pos.php"><span class="pulse-ico"><?php echo ame_pulse_ico('sale'); ?></span><?php echo esc_html(t('pulse_new_sale')); ?></a><?php endif; ?>
          <?php if ($is_admin_hdr || has_permission($db, 'm-transactions') || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')): ?><a class="q-audit" href="/modules/accounts/daily_audit.php"><span class="pulse-ico"><?php echo ame_pulse_ico('pay'); ?></span><?php echo is_en() ? 'Full accounts' : 'সম্পূর্ণ হিসাব'; ?></a><?php endif; ?>
          <?php if ($can_pay): ?><a class="q-pay" href="/modules/customers/payment-receive.php"><span class="pulse-ico"><?php echo ame_pulse_ico('pay'); ?></span><?php echo esc_html(t('pulse_take_pay')); ?></a><?php endif; ?>
          <?php if ($can_buy): ?><a class="q-buy" href="/modules/purchase/add.php"><span class="pulse-ico"><?php echo ame_pulse_ico('buy'); ?></span><?php echo esc_html(t('pulse_buy_stock')); ?></a><?php endif; ?>
          <a class="q-close" href="/modules/reports/day_closing.php"><span class="pulse-ico"><?php echo ame_pulse_ico('close'); ?></span><?php echo esc_html(t('pulse_day_end')); ?></a>
        </div>
      </article>
      <article class="pulse-dash-panel">
        <header>
          <h2><?php echo is_en() ? "Today's summary" : 'আজকের সারসংক্ষেপ'; ?></h2>
          <?php if ($is_admin_hdr || has_permission($db, 'm-transactions') || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')): ?>
          <a href="/modules/accounts/daily_audit.php"><?php echo is_en() ? 'Full books' : 'সম্পূর্ণ হিসাব'; ?></a>
          <?php endif; ?>
        </header>
        <div class="pulse-dash-summary">
          <div><span><?php echo esc_html(t('pulse_today_sales')); ?></span><b>৳<?php echo number_format((float)($p['today_sales'] ?? 0), 0); ?></b></div>
          <div><span><?php echo esc_html(t('pulse_today_collect')); ?></span><b>৳<?php echo number_format((float)($p['today_collected'] ?? 0), 0); ?></b></div>
          <div><span><?php echo is_en() ? 'Bills' : 'বিল'; ?></span><b><?php echo (int)$p['today_bills']; ?></b></div>
          <div><span><?php echo is_en() ? 'Due today' : 'আজকের বকেয়া'; ?></span><b><?php echo (int)$p['due_today']; ?></b></div>
        </div>
      </article>
    </section>

    <section class="pulse-dash-detail">
      <header>
        <div>
          <h2><?php echo esc_html(t('pulse_collect_wa')); ?></h2>
          <p><?php echo esc_html(t('pulse_collect_lead')); ?></p>
        </div>
        <a href="/modules/customers/due-reminder.php"><?php echo is_en() ? 'View all' : 'সব দেখুন'; ?></a>
      </header>
      <?php if (!$p['dues']): ?>
      <div class="pulse-dash-empty"><?php echo esc_html(t('pulse_no_due')); ?></div>
      <?php else: foreach ($p['dues'] as $customer):
        $due = (string)($customer['next_due_date'] ?? '');
        $chip = $due !== '' && $due < $today ? 'late' : ($due === $today ? 'today' : '');
        $wa = ame_wa_digits((string)($customer['phone'] ?? ''));
        $amount = number_format((float)($customer['receivable_balance'] ?? 0), 0);
      ?>
      <div class="due-row">
        <div>
          <div class="nm"><?php echo esc_html((string)$customer['name']); ?></div>
          <div class="meta"><?php echo esc_html((string)($customer['customer_code'] ?: ($customer['phone'] ?: '—'))); ?><?php if ($due): ?> · <span class="chip <?php echo $chip; ?>"><?php echo esc_html($due); ?></span><?php endif; ?></div>
        </div>
        <div class="due-amt"><?php echo $amount; ?> ৳</div>
        <div class="pulse-btns">
          <?php if ($can_pay): ?><a class="ink" href="/modules/customers/payment-receive.php?id=<?php echo (int)$customer['id']; ?>"><?php echo esc_html(t('pulse_deposit')); ?></a><?php endif; ?>
          <?php if ($wa): ?><button type="button" data-wa="<?php echo esc_html($wa); ?>" data-name="<?php echo esc_html((string)$customer['name']); ?>" data-amount="<?php echo esc_html($amount); ?>" data-due="<?php echo esc_html($due); ?>" data-shop="<?php echo esc_html((string)$p['shop']); ?>" onclick="amePulseWa(this)"><?php echo esc_html(t('whatsapp')); ?></button><?php endif; ?>
          <button type="button" data-shop="<?php echo esc_html((string)$p['shop']); ?>" data-shop-phone="<?php echo esc_html((string)$p['shop_phone']); ?>" data-name="<?php echo esc_html((string)$customer['name']); ?>" data-code="<?php echo esc_html((string)$customer['customer_code']); ?>" data-amount="<?php echo esc_html($amount); ?>" data-due="<?php echo esc_html($due); ?>" data-wa="<?php echo esc_html($wa); ?>" data-note="দয়া করে বকেয়া জমা দিয়ে রাখুন। — <?php echo esc_html((string)$p['shop']); ?>" onclick="amePulseShare(this)"><?php echo esc_html(t('pulse_share_slip')); ?></button>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </section>

    <section class="pulse-dash-detail" id="restock">
      <header>
        <div>
          <h2><?php echo esc_html(t('pulse_restock')); ?></h2>
          <p><?php echo esc_html(t('pulse_restock_lead')); ?></p>
        </div>
        <a href="/modules/inventory/list.php"><?php echo is_en() ? 'Stock list' : 'স্টক তালিকা'; ?></a>
      </header>
      <?php if (!$p['restock']): ?>
      <div class="pulse-dash-empty"><?php echo esc_html(t('pulse_restock_empty')); ?></div>
      <?php else: foreach ($p['restock'] as $row):
        $cover = $row['cover_days'] >= 90 ? '৯০+' : (string)round((float)$row['cover_days']);
      ?>
      <div class="stk-row">
        <div>
          <div class="nm"><?php echo esc_html((string)$row['name']); ?><?php if (!empty($row['urgent'])): ?> <span class="chip hot"><?php echo esc_html(t('pulse_urgent')); ?></span><?php endif; ?></div>
          <div class="meta"><?php echo esc_html(t('pulse_sold_14', ['n' => number_format((float)($row['sold_14'] ?? 0), 1)])); ?> · <?php echo esc_html(t('pulse_per_day', ['n' => number_format((float)($row['daily'] ?? 0), 2)])); ?></div>
        </div>
        <div class="meta"><?php echo esc_html(t('pulse_stock_left', ['n' => number_format((float)($row['stock'] ?? 0), 1)])); ?><br><?php echo esc_html(t('pulse_days_left', ['n' => $cover])); ?></div>
        <div class="meta"><?php echo esc_html(t('pulse_buy', ['n' => (int)$row['suggest']])); ?></div>
        <div class="pulse-btns">
          <?php if ($can_buy): ?><a class="ink" href="/modules/purchase/add.php?product_id=<?php echo (int)$row['id']; ?>&qty=<?php echo (int)$row['suggest']; ?>"><?php echo esc_html(t('pulse_go_buy')); ?></a><?php endif; ?>
          <a href="/modules/inventory/edit.php?id=<?php echo (int)$row['id']; ?>"><?php echo esc_html(t('pulse_product')); ?></a>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </section>
  </div>
</div>

<script src="/assets/js/pulse-slip.js?v=<?php echo $asset_v; ?>"></script>
<script src="/assets/js/edge-pulse.js?v=<?php echo $asset_v; ?>"></script>
<?php include '../../partials/footer.php'; ?>
