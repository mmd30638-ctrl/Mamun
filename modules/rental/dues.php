<?php
require_once __DIR__ . '/_boot.php';
$dues = rental_owner_due_rows($db);
$rental_page_title = rental_t('dues');
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
$groups = ['overdue' => [], 'today' => [], 'upcoming' => [], 'clear' => []];
foreach ($dues as $o) {
    $groups[$o['bucket'] ?? 'upcoming'][] = $o;
}
$labels = [
    'overdue' => '🔴 ' . rental_t('overdue'),
    'today' => '🟡 ' . rental_t('due_today'),
    'upcoming' => '🟢 ' . rental_t('upcoming'),
];
?>
<?php foreach ($labels as $key => $lab): $list = $groups[$key] ?? []; ?>
<div class="rnt-card" style="margin-bottom:12px">
    <strong><?php echo esc_html($lab); ?></strong>
    <?php if (!$list): ?>
        <p class="rnt-muted"><?php echo esc_html(rental_t('empty')); ?></p>
    <?php else: ?>
        <div class="rnt-scroll">
        <table class="rnt-table">
            <tr><th>মালিক</th><th>বাকি</th><th>পরবর্তী তারিখ</th><th></th></tr>
            <?php foreach ($list as $o): ?>
            <tr>
                <td><?php echo esc_html($o['name']); ?><div class="rnt-muted"><?php echo esc_html($o['phone'] ?? ''); ?></div></td>
                <td><?php echo format_bdt($o['summary']['due']); ?></td>
                <td><?php echo esc_html((string)$o['summary']['next_due']); ?></td>
                <td><a href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>">স্টেটমেন্ট</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/_foot.php'; ?>
