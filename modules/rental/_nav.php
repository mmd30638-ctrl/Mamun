<?php
$rental_here = basename((string)($_SERVER['PHP_SELF'] ?? ''));
if (in_array($rental_here, ['owner_view.php', 'statement.php'], true)) {
    $rental_here = 'owners.php';
}
$flash = rental_flash();
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
$nav = [
    'dashboard.php' => ['bn' => 'ড্যাশবোর্ড', 'en' => 'Dashboard', 'ico' => '⌂'],
    'owners.php' => ['bn' => 'মালিক / সোর্স', 'en' => 'Owners', 'ico' => '◇'],
    'dues.php' => ['bn' => 'মালিকের বকেয়া', 'en' => 'Owner dues', 'ico' => '⏰'],
    'reports.php' => ['bn' => 'রিপোর্ট', 'en' => 'Reports', 'ico' => '▤'],
];
?>
<link rel="stylesheet" href="/assets/css/hire-yard.808.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.94.0', ENT_QUOTES, 'UTF-8'); ?>">
<script src="/assets/js/device-display.js?v=8.08"></script>
<div class="rnt-wrap">
<nav class="rnt-nav no-print" aria-label="<?php echo htmlspecialchars(rental_t('module'), ENT_QUOTES, 'UTF-8'); ?>">
<?php foreach ($nav as $file => $meta): ?>
    <a class="<?php echo $rental_here === $file ? 'on' : ''; ?>" href="/modules/rental/<?php echo $file; ?>">
        <span aria-hidden="true"><?php echo $meta['ico']; ?></span>
        <?php echo ame_pair($meta['bn'], $meta['en']); ?>
    </a>
<?php endforeach; ?>
</nav>
<h1 class="rnt-h"><?php echo htmlspecialchars($rental_page_title ?? rental_t('module'), ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if ($flash): ?>
<div class="rnt-flash <?php echo ($flash['type'] ?? '') === 'err' ? 'err' : 'ok'; ?>">
    <?php echo htmlspecialchars((string)$flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
