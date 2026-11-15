<?php
/** @var array{page:int,pages:int,total:int,per:int} $pa_slice */
/** @var string $bounds_ym */
/** @var int $pa_partner_id */
/** @var string $pa_pager_script basename of current script */
if (($pa_slice['pages'] ?? 1) < 2) {
    return;
}
$script = $pa_pager_script ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$page = (int)($pa_slice['page'] ?? 1);
$pages = (int)($pa_slice['pages'] ?? 1);
$total = (int)($pa_slice['total'] ?? 0);
$per = (int)($pa_slice['per'] ?? 15);
?>
<nav class="pa-pager no-print" aria-label="পৃষ্ঠা">
    <span class="pa-pager-meta">মোট <?php echo $total; ?> সারি · প্রতি পৃষ্ঠায় <?php echo $per; ?> (ক্রমিক)</span>
    <div class="pa-pager-btns">
        <?php if ($page > 1): ?>
            <a class="pa-btn ghost" href="<?php echo htmlspecialchars(pa_stmt_nav_href($script, $bounds_ym, $pa_partner_id, $page - 1), ENT_QUOTES, 'UTF-8'); ?>">← আগে</a>
        <?php endif; ?>
        <span class="pa-pager-cur"><?php echo $page; ?> / <?php echo $pages; ?></span>
        <?php if ($page < $pages): ?>
            <a class="pa-btn ghost" href="<?php echo htmlspecialchars(pa_stmt_nav_href($script, $bounds_ym, $pa_partner_id, $page + 1), ENT_QUOTES, 'UTF-8'); ?>">পরে →</a>
        <?php endif; ?>
    </div>
</nav>