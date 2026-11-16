<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
require_once __DIR__ . '/_boot.php';
$stats = rental_dashboard_stats($db);
$dues = rental_owner_due_rows($db);
$rental_page_title = rental_t('module');
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
?>
<header class="hy-mast">
  <p class="hy-kicker">Hire yard · ভাড়া ইয়ার্ড</p>
  <h1><?php echo ame_pair('ভাড়া মালিক স্টেটমেন্ট', 'Owner hire ledger'); ?></h1>
  <p><?php echo ame_pair(
      'কাস্টমার ভাড়া আগের মতো কাস্টমার প্রোফাইলে। এখানে শুধু যার কাছ থেকে জিনিস আনা হয় তার স্টেটমেন্ট। উদাহরণ: মালিককে ৳৪,০০০, কাস্টমার থেকে ৳৫,০০০, লাভ ৳১,০০০।',
      'Customer hire stays on the customer profile. This desk is the source-owner statement: pay ৳4,000, collect ৳5,000, keep ৳1,000.'
  ); ?></p>
</header>

<section class="hy-scale" aria-label="<?php echo htmlspecialchars('Ledger scale', ENT_QUOTES, 'UTF-8'); ?>">
  <article class="hy-leaf hy-in">
    <b><?php echo format_bdt($stats['billed']); ?></b>
    <span><?php echo ame_pair('কাস্টমার ভাড়া (হিসাব)', 'Customer hire billed'); ?></span>
  </article>
  <div class="hy-op" aria-hidden="true">−</div>
  <article class="hy-leaf hy-out">
    <b><?php echo format_bdt($stats['owner_accrued']); ?></b>
    <span><?php echo ame_pair('মালিক পাওনা', 'Owner accrued'); ?></span>
  </article>
  <div class="hy-op" aria-hidden="true">=</div>
  <article class="hy-leaf hy-margin">
    <b><?php echo format_bdt($stats['profit']); ?></b>
    <span><?php echo ame_pair('আপনার লাভ (৫০০০−৪০০০)', 'Your margin (5000−4000)'); ?></span>
  </article>
</section>

<div class="hy-strip">
  <article class="hy-plate">
    <b><?php echo format_bdt($stats['owner_paid']); ?></b>
    <span><?php echo ame_pair('মালিককে দেওয়া', 'Paid to owners'); ?></span>
  </article>
  <article class="hy-plate">
    <b><?php echo format_bdt($stats['owner_payable']); ?></b>
    <span><?php echo ame_pair('মালিকের বাকি', 'Owner payable'); ?></span>
  </article>
</div>

<div class="rnt-grid">
  <article class="hy-plate">
    <b><?php echo (int)$stats['owners']; ?></b>
    <span><?php echo ame_pair('মালিক / সোর্স', 'Owners / sources'); ?></span>
  </article>
  <article class="hy-plate">
    <b><?php echo (int)$stats['customers']; ?></b>
    <span><?php echo ame_pair('ভাড়া গ্রহীতা', 'Hire customers'); ?></span>
  </article>
  <article class="hy-plate">
    <b><?php echo (int)$stats['rentals']; ?></b>
    <span><?php echo ame_pair('কাস্টমার ভাড়া চুক্তি', 'Customer contracts'); ?></span>
  </article>
  <article class="hy-plate">
    <b><?php echo (int)$stats['linked']; ?></b>
    <span><?php echo ame_pair('মালিক যুক্ত চুক্তি', 'Owner-linked contracts'); ?></span>
  </article>
</div>

<p class="hy-sec"><?php echo ame_pair('মালিক স্টেটমেন্ট — বকেয়া', 'Owner statement — outstanding'); ?></p>
<div class="hy-board">
<?php if (!$dues): ?>
  <div class="hy-empty"><?php echo ame_pair('এখনো কোনো রেকর্ড নেই।', 'No records yet.'); ?></div>
<?php endif; ?>
<?php foreach ($dues as $o):
    $s = $o['summary'];
    $bucket = (string)($o['bucket'] ?? '');
    $bucketKey = $bucket === 'today' ? 'due_today' : $bucket;
    ?>
  <article class="hy-slip">
    <header>
      <strong><a href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?></a></strong>
      <small><?php echo htmlspecialchars((string)($o['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
      <span class="rnt-badge <?php echo htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(rental_t($bucketKey), ENT_QUOTES, 'UTF-8'); ?></span>
    </header>
    <div class="hy-amt"><?php echo format_bdt($s['accrued']); ?><em><?php echo ame_pair('পাওনা', 'Accrued'); ?></em></div>
    <div class="hy-amt"><?php echo format_bdt($s['paid']); ?><em><?php echo ame_pair('দেওয়া', 'Paid'); ?></em></div>
    <div class="hy-amt"><?php echo format_bdt($s['due']); ?><em><?php echo ame_pair('বাকি', 'Due'); ?></em></div>
    <div class="hy-amt"><?php echo format_bdt($s['profit']); ?><em><?php echo ame_pair('লাভ', 'Margin'); ?></em></div>
    <div class="hy-amt"><?php echo htmlspecialchars((string)$s['next_due'], ENT_QUOTES, 'UTF-8'); ?><em><?php echo ame_pair('পরবর্তী', 'Next due'); ?></em></div>
    <a class="rnt-btn ghost" href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>"><?php echo ame_pair('স্টেটমেন্ট', 'Statement'); ?></a>
  </article>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
