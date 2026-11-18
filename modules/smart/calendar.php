<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$month = (string)($_GET['m'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));
$events = ame_smart_calendar($db, $from, $to);
$by = [];
$kind_counts = ['due' => 0, 'supplier' => 0, 'purchase' => 0, 'sale' => 0, 'stock' => 0];
foreach ($events as $event) {
    $by[$event['date']][] = $event;
    $kind = (string)($event['kind'] ?? '');
    if (isset($kind_counts[$kind])) {
        $kind_counts[$kind]++;
    }
}
$start = new DateTime($from);
$end = new DateTime($to);
$prev = (clone $start)->modify('-1 month')->format('Y-m');
$next = (clone $start)->modify('+1 month')->format('Y-m');
$kinds = [
    'due' => is_en() ? 'Customer due' : 'ক্রেতা বকেয়া',
    'supplier' => is_en() ? 'Supplier pay' : 'সাপ্লায়ার পেমেন্ট',
    'purchase' => is_en() ? 'Purchase' : 'ক্রয়',
    'sale' => is_en() ? 'Sale' : 'বিক্রি',
    'stock' => is_en() ? 'Stock check' : 'স্টক চেক',
];
$en = is_en();

ame_smart_open('smart_title_calendar', 'smart_lead_calendar');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">▦</i>
      <small><?php echo $en ? 'This month' : 'এই মাস'; ?></small>
      <b><?php echo count($events); ?></b>
      <em><?php echo esc_html($start->format('F Y')); ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">৳</i>
      <small><?php echo $kinds['due']; ?></small>
      <b><?php echo (int)$kind_counts['due']; ?></b>
      <em class="<?php echo $kind_counts['due'] ? 'down' : ''; ?>"><?php echo $en ? 'Collection dates' : 'কালেকশন তারিখ'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">▣</i>
      <small><?php echo $kinds['purchase']; ?></small>
      <b><?php echo (int)$kind_counts['purchase']; ?></b>
      <em><?php echo $en ? 'Buy events' : 'ক্রয় ইভেন্ট'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">✓</i>
      <small><?php echo $kinds['sale']; ?></small>
      <b><?php echo (int)$kind_counts['sale']; ?></b>
      <em><?php echo $en ? 'Sale marks' : 'বিক্রি চিহ্ন'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">△</i>
      <small><?php echo $kinds['stock']; ?></small>
      <b><?php echo (int)$kind_counts['stock']; ?></b>
      <em><?php echo $en ? 'Stock checks' : 'স্টক চেক'; ?></em>
    </article>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Business calendar' : 'ব্যবসা ক্যালেন্ডার'; ?></h3>
        <p><?php echo $en ? 'Due, supplier, purchase, sale and stock in one month.' : 'বকেয়া, সাপ্লায়ার, ক্রয়, বিক্রি ও স্টক এক মাসে।'; ?></p>
      </div>
      <div class="smt-dash-actions">
        <a class="soft" href="?m=<?php echo $prev; ?>">← <?php echo $en ? 'Prev' : 'আগে'; ?></a>
        <a href="?m=<?php echo $next; ?>"><?php echo $en ? 'Next' : 'পরে'; ?> →</a>
      </div>
    </header>
    <?php if (!$events): ?>
    <div class="smt-dash-empty"><?php echo $en ? 'No marked work this month.' : 'এই মাসে চিহ্নিত কাজ নেই।'; ?></div>
    <?php else:
      $cursor = clone $start;
      while ($cursor <= $end):
          $day = $cursor->format('Y-m-d');
          if (!empty($by[$day])):
    ?>
    <div class="smt-cal-day"><?php echo esc_html($day); ?></div>
    <?php foreach ($by[$day] as $event): ?>
    <a class="smt-dash-row link" href="<?php echo esc_html((string)$event['href']); ?>">
      <div>
        <div class="nm"><?php echo esc_html((string)$event['title']); ?></div>
        <div class="meta"><?php echo esc_html((string)($kinds[$event['kind']] ?? $event['kind'])); ?></div>
      </div>
      <span class="smt-mini"><?php echo esc_html((string)$event['kind']); ?></span>
    </a>
    <?php endforeach; endif; $cursor->modify('+1 day'); endwhile; endif; ?>
  </section>

  <p class="smt-dash-foot">
    <?php echo $en ? 'Family appointments are separate:' : 'পরিবারের অ্যাপয়েন্টমেন্ট আলাদা:'; ?>
    <a href="/modules/family/calendar.php"><?php echo $en ? 'Family calendar' : 'পারিবারিক ক্যালেন্ডার'; ?></a>
    · <a href="/modules/reports/warranty.php"><?php echo $en ? 'Warranty search' : 'ওয়ারেন্টি সার্চ'; ?></a>
  </p>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
