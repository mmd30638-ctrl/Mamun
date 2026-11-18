<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$rows = ame_smart_predictions($db);
$en = is_en();
$urgent = 0;
$qty = 0;
$cost = 0.0;
foreach ($rows as $row) {
    if (!empty($row['urgent'])) {
        $urgent++;
    }
    $qty += (int)$row['suggest'];
    $cost += ((float)($row['purchase_price'] ?? 0)) * (int)$row['suggest'];
}

ame_smart_open('smart_title_stock', 'smart_lead_stock');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">▤</i>
      <small><?php echo $en ? 'Forecast items' : 'অনুমান পণ্য'; ?></small>
      <b><?php echo count($rows); ?></b>
      <em><?php echo $en ? 'Moving or low stock' : 'চলমান বা কম স্টক'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">△</i>
      <small><?php echo $en ? 'Urgent' : 'জরুরি'; ?></small>
      <b><?php echo $urgent; ?></b>
      <em class="<?php echo $urgent ? 'down' : ''; ?>"><?php echo $en ? 'Cover running out' : 'কভার শেষ হচ্ছে'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">#</i>
      <small><?php echo $en ? 'Suggested qty' : 'সাজেস্ট পরিমাণ'; ?></small>
      <b><?php echo $qty; ?></b>
      <em><?php echo $en ? 'Units to buy' : 'কিনতে হবে'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">৳</i>
      <small><?php echo $en ? 'Estimated cost' : 'আনুমানিক খরচ'; ?></small>
      <b><?php echo ame_smart_money($cost); ?></b>
      <em><?php echo $en ? 'If all suggestions bought' : 'সব সাজেস্ট কিনলে'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">▣</i>
      <small><?php echo $en ? 'Purchase' : 'ক্রয়'; ?></small>
      <b><?php echo $en ? 'Open' : 'খুলুন'; ?></b>
      <em><?php echo $en ? 'Go to buy form' : 'ক্রয় ফর্মে যান'; ?></em>
    </article>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Stock forecast register' : 'স্টক অনুমান রেজিস্টার'; ?></h3>
        <p><?php echo $en ? 'Last 14 days of sales decide cover days and suggested qty.' : 'গত ১৪ দিনের বিক্রি থেকে কভার ও সাজেস্ট বের হয়।'; ?></p>
      </div>
      <a href="/modules/inventory/list.php"><?php echo $en ? 'Stock list' : 'স্টক তালিকা'; ?></a>
    </header>
    <?php if (!$rows): ?>
    <div class="smt-dash-empty"><?php echo $en ? 'No sales or low stock to forecast yet.' : 'এখন অনুমান করার মতো বিক্রি বা কম স্টক নেই।'; ?></div>
    <?php else: ?>
    <div class="smt-table-wrap">
      <table class="smt-table">
        <thead>
          <tr>
            <th><?php echo $en ? 'Product' : 'পণ্য'; ?></th>
            <th><?php echo $en ? 'Stock' : 'স্টক'; ?></th>
            <th><?php echo $en ? 'Sold 14d' : '১৪ দিনে বিক্রি'; ?></th>
            <th><?php echo $en ? 'Cover' : 'কভার'; ?></th>
            <th><?php echo $en ? 'Buy' : 'কিনুন'; ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row):
            $cover = (float)($row['cover_days'] ?? 999);
            $when = $cover >= 900 ? ($en ? 'Slow' : 'ধীর বিক্রি') : ($cover <= 0 ? ($en ? 'Empty now' : 'এখনই শেষ') : (int)ceil($cover) . ($en ? ' days' : ' দিনে শেষ'));
        ?>
          <tr>
            <td><strong><?php echo esc_html((string)$row['name']); ?></strong></td>
            <td><?php echo (int)$row['stock']; ?></td>
            <td><?php echo number_format((float)($row['sold_14'] ?? 0), 1); ?></td>
            <td><?php echo esc_html($when); ?></td>
            <td><span class="smt-mini <?php echo !empty($row['urgent']) ? 'hot' : 'gold'; ?>"><?php echo (int)$row['suggest']; ?></span></td>
            <td><a class="smt-ghost" href="/modules/purchase/add.php"><?php echo $en ? 'Purchase' : 'ক্রয়'; ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
