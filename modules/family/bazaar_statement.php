<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_bazaar_stmt', 'fam_lead_bazaar_stmt');

$db = fam_db();
$month = fam_bazaar_month_norm((string) ($_GET['m'] ?? date('Y-m')));
$demand = fam_bazaar_find($db, $month);
$lines = fam_bazaar_lines($db, (int) ($demand['id'] ?? 0), '');
$stats = fam_bazaar_stats($lines);
$history = fam_bazaar_month_rows($db);
$open = max(0, $stats['items'] - $stats['bought']);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2><?php echo esc_html(fam_bazaar_month_label($month)); ?> · বাজার স্টেটমেন্ট</h2>
  <p>এই মাসে কত আইটেমের চাহিদা ছিল, কত কেনা হয়েছে, কত টাকা বাজার লেগেছে।</p>
</header>

<form class="fam-q" method="get" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
  <input type="month" name="m" value="<?php echo esc_html($month); ?>">
  <button type="submit">মাস দেখুন</button>
  <a class="fam-mini" href="bazaar.php?m=<?php echo esc_html($month); ?>">চাহিদা</a>
  <a class="fam-mini" href="bazaar_memo.php?m=<?php echo esc_html($month); ?>">মেমো প্রিন্ট</a>
</form>

<div class="fam-kpis">
  <div class="fam-kpi"><span>চাহিদা আইটেম</span><b><?php echo (int) $stats['items']; ?></b></div>
  <div class="fam-kpi"><span>কেনা আইটেম</span><b><?php echo (int) $stats['bought']; ?></b></div>
  <div class="fam-kpi"><span>বাকি</span><b><?php echo (int) $open; ?></b></div>
  <div class="fam-kpi"><span>আনুমানিক</span><b><?php echo fam_money_txt($stats['est']); ?></b></div>
  <div class="fam-kpi"><span>বাজার খরচ</span><b><?php echo fam_money_txt($stats['paid']); ?></b></div>
</div>

<div class="fam-grid fam-grid-2">
<section class="fam-panel">
  <h2>ক্যাটাগরি অনুযায়ী · <?php echo esc_html(fam_bazaar_month_label($month)); ?></h2>
  <?php if ($stats['items'] <= 0): ?>
    <?php echo fam_empty('এই মাসে বাজার চাহিদা নেই।', 'bazaar.php?m=' . urlencode($month), 'চাহিদা লিখুন'); ?>
  <?php else: ?>
  <table class="fam-table">
    <thead>
      <tr><th>ক্যাটাগরি</th><th>আইটেম</th><th>কেনা</th><th>আনুমানিক</th><th>খরচ</th></tr>
    </thead>
    <tbody>
    <?php foreach ($stats['by_cat'] as $c => $row):
        if ((int) $row['items'] <= 0) {
            continue;
        }
    ?>
      <tr>
        <td><a href="bazaar.php?m=<?php echo esc_html($month); ?>&amp;cat=<?php echo urlencode($c); ?>"><?php echo esc_html($c); ?></a></td>
        <td><?php echo (int) $row['items']; ?></td>
        <td><?php echo (int) $row['bought']; ?></td>
        <td><?php echo fam_money_txt($row['est']); ?></td>
        <td><?php echo fam_money_txt($row['paid']); ?></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td><strong>মোট</strong></td>
        <td><strong><?php echo (int) $stats['items']; ?></strong></td>
        <td><strong><?php echo (int) $stats['bought']; ?></strong></td>
        <td><strong><?php echo fam_money_txt($stats['est']); ?></strong></td>
        <td><strong><?php echo fam_money_txt($stats['paid']); ?></strong></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<section class="fam-panel">
  <h2>মাস অনুযায়ী</h2>
  <?php if (!$history): ?>
    <?php echo fam_empty('এখনো কোনো মাসের বাজার নেই।'); ?>
  <?php else: ?>
  <table class="fam-table">
    <thead>
      <tr><th>মাস</th><th>আইটেম</th><th>কেনা</th><th>খরচ</th></tr>
    </thead>
    <tbody>
    <?php foreach ($history as $h):
        $ym = (string) $h['ym'];
    ?>
      <tr<?php echo $ym === $month ? ' style="font-weight:800"' : ''; ?>>
        <td><a href="bazaar_statement.php?m=<?php echo esc_html($ym); ?>"><?php echo esc_html(fam_bazaar_month_label($ym)); ?></a></td>
        <td><?php echo (int) $h['items']; ?></td>
        <td><?php echo (int) $h['bought']; ?></td>
        <td><?php echo fam_money_txt($h['paid']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
</div>

<?php if ($lines): ?>
<section class="fam-panel" style="margin-top:12px">
  <h2>আইটেম তালিকা</h2>
  <table class="fam-table">
    <thead>
      <tr><th>ক্যাটাগরি</th><th>পণ্য</th><th>পরিমাণ</th><th>আনুমানিক</th><th>খরচ</th><th>অবস্থা</th></tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $row): ?>
      <tr>
        <td><?php echo esc_html((string) $row['category']); ?></td>
        <td><?php echo esc_html((string) $row['name']); ?></td>
        <td><?php echo esc_html(trim((string) $row['qty'] . ' ' . (string) $row['unit'])); ?></td>
        <td><?php echo fam_money_txt((float) $row['est_price']); ?></td>
        <td><?php echo !empty($row['is_bought']) ? fam_money_txt(fam_bazaar_line_spend($row)) : '—'; ?></td>
        <td><?php echo !empty($row['is_bought']) ? 'কেনা' : 'চাহিদা'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
