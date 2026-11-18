<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
$ym = trim((string)($_GET['m'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = date('Y-m');
}
[$y, $mo] = array_map('intval', explode('-', $ym));
$start = new DateTime("$y-$mo-01");
$days = (int)$start->format('t');
$pad = (int)$start->format('N') - 1;
$prev = (clone $start)->modify('-1 month')->format('Y-m');
$next = (clone $start)->modify('+1 month')->format('Y-m');
$from = $start->format('Y-m-01');
$to = $start->format('Y-m-t');
$events = fam_rows("SELECT e.*, m.name AS member_name FROM family_events e LEFT JOIN family_members m ON m.id=e.member_id WHERE e.event_date BETWEEN '$from' AND '$to' ORDER BY e.event_date");
$by = [];
foreach ($events as $e) {
    $by[$e['event_date']][] = $e;
}
fam_open('fam_title_calendar', 'fam_lead_calendar');
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_events WHERE id=' . (int)$_GET['id']) : null;
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>ক্যালেন্ডার</h2>
  <p>বিক্রয় পাতার নেভি রং — তারিখ দূর থেকে স্পষ্ট, হিসাব আগের মতো।</p>
</header>
<div class="fam-top-r" style="margin-bottom:0.8rem">
  <a class="fam-btn soft" href="calendar.php?m=<?php echo $prev; ?>">← আগে</a>
  <strong><?php echo $start->format('F Y'); ?></strong>
  <a class="fam-btn soft" href="calendar.php?m=<?php echo $next; ?>">পরে →</a>
</div>
<div class="fam-cal" style="margin-bottom:1rem">
  <?php foreach (['সোম', 'মঙ্গল', 'বুধ', 'বৃহ', 'শুক্র', 'শনি', 'রবি'] as $d) echo '<b>' . $d . '</b>'; ?>
  <?php for ($i = 0; $i < $pad; $i++) echo '<div class="fam-day out"></div>'; ?>
  <?php for ($d = 1; $d <= $days; $d++):
      $ds = sprintf('%04d-%02d-%02d', $y, $mo, $d);
      $cls = $ds === date('Y-m-d') ? ' today' : '';
  ?>
    <div class="fam-day<?php echo $cls; ?>">
      <em><?php echo $d; ?></em>
      <?php foreach (($by[$ds] ?? []) as $e): ?>
        <span class="fam-dot"><?php echo esc_html($e['title']); ?></span>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write()): ?>
<section class="fam-panel">
  <h2>নতুন তারিখ</h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="event_save">
    <input type="hidden" name="back" value="/modules/family/calendar.php?m=<?php echo $ym; ?>">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label>শিরোনাম<input name="title" required value="<?php echo esc_html($edit['title'] ?? ''); ?>"></label>
    <label>ধরন<select name="kind"><?php echo fam_opts(fam_event_kinds(), $edit['kind'] ?? 'other'); ?></select></label>
    <label>তারিখ<input type="date" name="event_date" required value="<?php echo esc_html($edit['event_date'] ?? date('Y-m-d')); ?>"></label>
    <label>সদস্য<select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    <label>নোট<textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit">যোগ করুন</button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2>এই মাসের তালিকা</h2>
  <?php if (!$events) echo fam_empty('এই মাসে ইভেন্ট নেই।'); ?>
  <?php foreach ($events as $e): ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($e['title']); ?></strong>
        <small><?php echo esc_html($e['event_date'] . ' · ' . fam_kind_label($e['kind']) . ' · ' . ($e['member_name'] ?: '')); ?></small>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
