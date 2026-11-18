<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$week = ame_smart_week_buy($db);
$all = ame_smart_predictions($db);
$cmp = ame_smart_supplier_compare($db);
$en = is_en();
$week_qty = 0;
$week_cost = 0.0;
$urgent = 0;
foreach ($week as $row) {
    $qty = (int)$row['suggest'];
    $week_qty += $qty;
    $week_cost += ((float)($row['purchase_price'] ?? 0)) * $qty;
}
foreach ($all as $row) {
    if (!empty($row['urgent'])) {
        $urgent++;
    }
}

ame_smart_open('smart_title_purchase', 'smart_lead_purchase');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">▣</i>
      <small><?php echo $en ? 'This week' : 'এই সপ্তাহে'; ?></small>
      <b><?php echo count($week); ?></b>
      <em><?php echo $en ? 'Products to buy' : 'পণ্য কিনতে হবে'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">#</i>
      <small><?php echo $en ? 'Suggested qty' : 'সাজেস্ট পরিমাণ'; ?></small>
      <b><?php echo $week_qty; ?></b>
      <em><?php echo $en ? 'Units this week' : 'এই সপ্তাহের ইউনিট'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">৳</i>
      <small><?php echo $en ? 'Estimated cost' : 'আনুমানিক খরচ'; ?></small>
      <b><?php echo ame_smart_money($week_cost); ?></b>
      <em><?php echo $en ? 'Week buy list' : 'সাপ্তাহিক তালিকা'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">△</i>
      <small><?php echo $en ? 'Urgent' : 'জরুরি'; ?></small>
      <b><?php echo $urgent; ?></b>
      <em class="<?php echo $urgent ? 'down' : ''; ?>"><?php echo $en ? 'Need restock now' : 'এখনই রিস্টক'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">⇄</i>
      <small><?php echo $en ? 'Supplier compare' : 'দাম তুলনা'; ?></small>
      <b><?php echo count($cmp); ?></b>
      <em><?php echo $en ? 'Products with history' : 'ক্রয় হিস্টরি আছে'; ?></em>
    </article>
  </section>

  <section class="smt-dash-main">
    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Buy this week' : 'এই সপ্তাহে কিনতে হবে'; ?></h3>
        <a href="/modules/purchase/add.php"><?php echo $en ? 'Open purchase' : 'ক্রয় খুলুন'; ?></a>
      </header>
      <div class="smt-dash-list smt-dash-alerts">
        <?php if (!$week): ?>
        <div class="smt-dash-empty"><?php echo $en ? 'No urgent buy this week.' : 'এই সপ্তাহে জরুরি কেনা নেই।'; ?></div>
        <?php else: foreach ($week as $row): ?>
        <div>
          <span>
            <strong><?php echo esc_html((string)$row['name']); ?></strong>
            <small><?php echo $en ? 'Stock ' : 'স্টক '; ?><?php echo (int)$row['stock']; ?> · <?php echo $en ? 'Suggest ' : 'সাজেস্ট '; ?><?php echo (int)$row['suggest']; ?> · <?php echo ame_smart_money(((float)($row['purchase_price'] ?? 0)) * (int)$row['suggest']); ?></small>
          </span>
          <a class="smt-mini hot" href="/modules/purchase/add.php"><?php echo $en ? 'Buy' : 'কিনুন'; ?></a>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </article>

    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Auto purchase list' : 'অটো পারচেজ লিস্ট'; ?></h3>
        <span><?php echo count($all); ?></span>
      </header>
      <div class="smt-dash-list">
        <?php if (!$all): ?>
        <div class="smt-dash-empty"><?php echo $en ? 'No low stock or moving sales.' : 'কম স্টক বা চলমান বিক্রি নেই।'; ?></div>
        <?php else: foreach (array_slice($all, 0, 12) as $row): ?>
        <div>
          <span>
            <strong><?php echo esc_html((string)$row['name']); ?></strong>
            <small><?php echo !empty($row['urgent']) ? ($en ? 'Urgent' : 'জরুরি') : ($en ? 'Watch' : 'নজরে রাখুন'); ?></small>
          </span>
          <b><?php echo (int)$row['suggest']; ?></b>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </article>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Supplier price compare' : 'সাপ্লায়ার দাম তুলনা'; ?></h3>
        <p><?php echo $en ? 'Cheapest recent offer is marked for each product.' : 'প্রতি পণ্যের সস্তা সাম্প্রতিক অফার চিহ্নিত।'; ?></p>
      </div>
      <a href="/modules/purchase/add.php"><?php echo $en ? 'New purchase' : 'নতুন ক্রয়'; ?></a>
    </header>
    <?php if (!$cmp): ?>
    <div class="smt-dash-empty"><?php echo $en ? 'Purchase history will appear here after bills are saved.' : 'ক্রয় সেভ হলে এখানে দাম দেখাবে।'; ?></div>
    <?php else: foreach ($cmp as $product): ?>
    <div class="smt-dash-row">
      <div>
        <div class="nm"><?php echo esc_html((string)$product['name']); ?></div>
        <div class="meta">
          <?php foreach ($product['offers'] as $offer): ?>
            <?php echo esc_html($offer['supplier'] . ' ' . ame_smart_money((float)$offer['price']) . ' (' . $offer['when'] . ')'); ?> ·
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!empty($product['best'])): ?>
      <div class="amt"><?php echo ame_smart_money((float)$product['best']['price']); ?></div>
      <div class="ag-like"><?php echo esc_html((string)$product['best']['supplier']); ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
