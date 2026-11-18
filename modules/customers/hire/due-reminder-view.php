<?php
$overdue_count = $overdue_count ?? 0;
$today_count = $today_count ?? 0;
$upcoming_count = $upcoming_count ?? 0;
$watch_count = $watch_count ?? 0;
$date_from = $date_from ?? '';
$date_to = $date_to ?? '';
$search = $search ?? '';
$kind = $kind ?? '';
$customers = $customers ?? [];
$today = $today ?? date('Y-m-d');
$chip = static function (string $key, string $label, string $kind, string $search, string $date_from, string $date_to): string {
    $on = $kind === $key;
    $qs = http_build_query(array_filter([
        'kind' => $key !== '' ? $key : null,
        'search' => $search !== '' ? $search : null,
        'date_from' => $date_from !== '' ? $date_from : null,
        'date_to' => $date_to !== '' ? $date_to : null,
    ], static fn ($v) => $v !== null));
    $href = 'due-reminder.php' . ($qs !== '' ? ('?' . $qs) : '');
    return '<a class="' . ($on ? 'is-on' : '') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};
?>
<section class="hire-cards hire-cards-simple hire-cards-four">
  <article class="hire-card"><span>মেয়াদ পার</span><b><?php echo (int) $overdue_count; ?></b></article>
  <article class="hire-card"><span>আজ</span><b><?php echo (int) $today_count; ?></b></article>
  <article class="hire-card"><span>আগামী</span><b><?php echo (int) $upcoming_count; ?></b></article>
  <article class="hire-card"><span>বকেয়া চুক্তি</span><b><?php echo (int) $watch_count; ?></b></article>
</section>

<nav class="hire-view-tabs no-print">
  <?php
    echo $chip('', 'সব', $kind, $search, $date_from, $date_to);
    echo $chip('overdue', 'মেয়াদ পার', $kind, $search, $date_from, $date_to);
    echo $chip('today', 'আজ', $kind, $search, $date_from, $date_to);
    echo $chip('upcoming', 'আগামী', $kind, $search, $date_from, $date_to);
  ?>
</nav>

<form class="hire-panel hire-contract-search" method="get">
  <?php if ($kind !== ''): ?><input type="hidden" name="kind" value="<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
  <div class="hire-grid hire-contract-search-grid">
    <label>নাম / মোবাইল / চুক্তি
      <input type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="কাস্টমার বা পণ্য" autocomplete="off">
    </label>
    <label>শুরু<input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>শেষ<input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>"></label>
  </div>
  <div class="hire-actions">
    <button class="hire-btn hire-btn-primary" type="submit">খুঁজুন</button>
    <a class="hire-btn" href="due-reminder.php">রিসেট</a>
  </div>
</form>

<?php if (!$customers): ?>
<section class="hire-panel">
  <p class="hire-empty">এখন কোনো বকেয়া বা নির্ধারিত তারিখ নেই।</p>
</section>
<?php else: ?>
<section class="hire-live-list">
  <?php foreach ($customers as $c):
      $meta = $c['badge'] ?? (function_exists('hire_due_row_badge') ? hire_due_row_badge($c, $today) : ['class' => '', 'text' => '', 'date' => '']);
      $cid = (int) ($c['id'] ?? 0);
      $due = (float) ($c['due'] ?? 0);
      $phone = trim((string) ($c['customer_phone'] ?? ''));
      $cls = (string) ($meta['class'] ?? '');
  ?>
  <article class="hire-live-card hire-due-item">
    <div class="hire-live-top">
      <div>
        <p class="hire-live-no"><?php echo htmlspecialchars((string) ($c['rental_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($c['item_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <h3><a href="contract.php?id=<?php echo $cid; ?>"><?php echo htmlspecialchars((string) ($c['customer_name'] ?? 'কাস্টমার'), ENT_QUOTES, 'UTF-8'); ?></a></h3>
        <?php if ($phone !== ''): ?><p class="hire-muted"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      </div>
      <span class="hire-badge <?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(trim(($meta['text'] ?? '') . ' ' . ($meta['date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="hire-live-nums">
      <div class="<?php echo $due > 0.009 ? 'is-due' : ''; ?>"><span>ভাড়া বকেয়া</span><b>৳<?php echo number_format($due, 0); ?></b></div>
      <div><span>বকেয়া দিন</span><b><?php echo (int) ($c['unpaid_days'] ?? 0); ?></b></div>
      <div><span>প্রত্যাশিত</span><b><?php
        $fa = $c['follow_amount'] ?? '';
        echo ($fa !== '' && $fa !== null) ? '৳' . number_format((float) $fa, 0) : '—';
      ?></b></div>
    </div>
    <?php if (trim((string) ($c['follow_note'] ?? '')) !== ''): ?>
    <p class="hire-muted"><?php echo htmlspecialchars((string) $c['follow_note'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div class="hire-live-links no-print">
      <?php if ($phone !== ''): ?>
      <a class="hire-btn" href="tel:<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">কল</a>
      <?php endif; ?>
      <button type="button" class="hire-btn hire-btn-money" data-hire-pay="<?php echo $cid; ?>">টাকা নিন</button>
      <button type="button" class="hire-btn" data-hire-edit="<?php echo $cid; ?>">তারিখ</button>
      <a class="hire-btn" href="contract.php?id=<?php echo $cid; ?>">খুলুন</a>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
