<?php
/** @var array{page:int,pages:int,total:int,per:int} $pa_slice */
/** @var string $bounds_ym */
/** @var int $pa_partner_id */
/** @var string $pa_pager_script basename of current script */
$pages = (int)($pa_slice['pages'] ?? 1);
if ($pages < 2) {
    return;
}
$script = $pa_pager_script ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$page = (int)($pa_slice['page'] ?? 1);
$total = (int)($pa_slice['total'] ?? 0);
$per = (int)($pa_slice['per'] ?? pa_stmt_days_per_page());
$unit = (string)($pa_slice['unit'] ?? 'day');
$unitBn = $unit === 'day' ? 'দিন' : 'সারি';
?>
<nav class="pa-pager no-print" aria-label="পৃষ্ঠা">
    <span class="pa-pager-meta">মোট <?php echo $total; ?> <?php echo $unitBn; ?> · প্রতি পৃষ্ঠায় <?php echo $per; ?> <?php echo $unitBn; ?>-এর হিসাব</span>
    <div class="pa-pager-btns">
        <?php if ($page > 1): ?>
            <a class="pa-pager-link pa-btn ghost" href="<?php echo htmlspecialchars(pa_stmt_nav_href($script, $bounds_ym, $pa_partner_id, $page - 1), ENT_QUOTES, 'UTF-8'); ?>">← আগের <?php echo $per; ?> দিন</a>
        <?php endif; ?>
        <span class="pa-pager-cur">পৃষ্ঠা <?php echo $page; ?> / <?php echo $pages; ?></span>
        <?php if ($page < $pages): ?>
            <a class="pa-pager-link pa-btn ghost" href="<?php echo htmlspecialchars(pa_stmt_nav_href($script, $bounds_ym, $pa_partner_id, $page + 1), ENT_QUOTES, 'UTF-8'); ?>">পরের <?php echo $per; ?> দিন →</a>
        <?php endif; ?>
    </div>
</nav>