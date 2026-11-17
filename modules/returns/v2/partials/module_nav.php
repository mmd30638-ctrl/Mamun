<?php
/**
 * @var string $ame_sales_return_nav_active
 */
if (!function_exists('ame_sales_return_sections')) {
    require_once dirname(__DIR__, 2) . '/sales_return_module.php';
}
$active = (string)($GLOBALS['ame_sales_return_nav_active'] ?? 'dashboard');
$sections = ame_sales_return_sections();
$isEn = function_exists('current_lang') && current_lang() === 'en';
$moduleTitle = function_exists('te') ? te('nav_sale_return') : 'বিক্রয় ফেরত';
$rev = AME_SALES_RETURN_MODULE_REV;
?>
<link rel="stylesheet" href="/assets/css/sales-return-module.css?v=<?php echo htmlspecialchars($rev, ENT_QUOTES, 'UTF-8'); ?>">
<nav class="sr-mod" aria-label="<?php echo htmlspecialchars($moduleTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <h2 class="sr-mod-title">
        <span class="sr-mod-badge">v<?php echo htmlspecialchars($rev, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php echo htmlspecialchars($moduleTitle, ENT_QUOTES, 'UTF-8'); ?>
    </h2>
    <p class="sr-mod-sub"><?php echo $isEn
        ? 'Sales return, exchange, register, and reports — one module.'
        : 'ফেরত, বদল, রেজিস্টার ও রিপোর্ট — এক মডিউলের ভিতরে।'; ?></p>
    <ul class="sr-mod-tabs">
        <?php foreach ($sections as $sec): ?>
            <?php
            $id = (string)($sec['id'] ?? '');
            $on = $id === $active;
            $label = ame_sales_return_label($sec);
            ?>
            <li>
                <a href="<?php echo htmlspecialchars((string)$sec['href'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="sr-mod-tab<?php echo $on ? ' is-active' : ''; ?>"
                   <?php echo $on ? ' aria-current="page"' : ''; ?>>
                    <span class="sr-mod-tab-ico" aria-hidden="true"><?php echo htmlspecialchars((string)($sec['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="sr-mod-tab-txt"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>