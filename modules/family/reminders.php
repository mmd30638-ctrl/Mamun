<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_remind', 'fam_lead_remind');
$items = fam_reminders();
$later = fam_upcoming_events(45);
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>রিমাইন্ডার</h2>
  <p>বিক্রয় পাতার নেভি রং — জরুরি কাজ দূর থেকে স্পষ্ট।</p>
</header>
<section class="fam-panel">
  <h2>এখন মনোযোগ দিন</h2>
  <?php if (!$items) echo fam_empty('কোনো জরুরি রিমাইন্ডার নেই।'); ?>
  <?php foreach ($items as $r): ?>
    <a class="fam-row" href="<?php echo esc_html($r['href']); ?>">
      <div>
        <strong><?php echo esc_html($r['title']); ?></strong>
        <small><?php echo esc_html($r['meta']); ?></small>
      </div>
      <span class="fam-mini"><?php echo $r['tone'] === 'hot' ? 'জরুরি' : 'খুলুন'; ?></span>
    </a>
  <?php endforeach; ?>
</section>
<section class="fam-panel" style="margin-top:1rem">
  <h2>আগামী ৪৫ দিন</h2>
  <?php if (!$later) echo fam_empty('আসন্ন ইভেন্ট নেই।', 'calendar.php', 'তারিখ যোগ'); ?>
  <?php foreach ($later as $e): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($e['title']); ?></strong>
        <small><?php echo esc_html($e['event_date'] . ' · ' . fam_kind_label($e['kind']) . ' · ' . ($e['member_name'] ?: '')); ?></small>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
