<?php
/**
 * Global Responsive & Viewport Master — permanent contract.
 * Included from config.php; do not bypass on new pages.
 */
declare(strict_types=1);

if (!defined('AME_GLOBAL_RESPONSIVE_VERSION')) {
    define('AME_GLOBAL_RESPONSIVE_VERSION', '10.98.939');
}

if (!defined('AME_VIEWPORT_META')) {
    define(
        'AME_VIEWPORT_META',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">'
    );
}

/**
 * Echo master responsive CSS (must load after all other stylesheets).
 * Tier JS loads in header.php / responsive_boot.php — not duplicated here.
 */
function ame_global_responsive_assets_echo(): void
{
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : AME_GLOBAL_RESPONSIVE_VERSION;
    $qv = htmlspecialchars($ver, ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/ame-global-responsive-master.css?v=' . $qv
        . '" id="ame-global-responsive-master">' . "\n";
}

/**
 * Early head: tier script immediately after ame-desktop-site.js.
 */
function ame_global_responsive_tier_script_echo(): void
{
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : AME_GLOBAL_RESPONSIVE_VERSION;
    $qv = htmlspecialchars($ver, ENT_QUOTES, 'UTF-8');
    echo '<script src="/assets/js/ame-viewport-tier.js?v=' . $qv . '" id="ame-viewport-tier"></script>' . "\n";
}