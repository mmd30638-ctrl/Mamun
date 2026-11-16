<?php
/**
 * Explicit overlay CSS/JS for pages that render HTML through header.php.
 * Do not inject via output buffering — that corrupts JSON APIs.
 */

function ame_asset_ver(): string
{
    return htmlspecialchars(
        defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.17',
        ENT_QUOTES,
        'UTF-8'
    );
}

function ame_overlay_head_tags(): void
{
    $v = ame_asset_ver();
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    echo '<link rel="stylesheet" href="/assets/css/saas-os.806.css?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/brand-type.php?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/ame-runtime.812.css?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/shell-in.825.css?v=' . $v . '">' . "\n";
    $is_shop_home = function_exists('ame_if_is_home_page') && ame_if_is_home_page();
    echo '<link rel="stylesheet" href="/assets/css/fresh-home.810.css?v=' . $v . '">' . "\n";
    if (!$is_shop_home) {
        echo '<link rel="stylesheet" href="/assets/css/inventory-first-home.710.css?v=' . $v . '">' . "\n";
    }
    if (stripos($uri, '/modules/family/') !== false) {
        echo '<link rel="stylesheet" href="/assets/css/family-if.css?v=' . $v . '">' . "\n";
        $kitab = stripos($uri, '/modules/family/library.php') !== false
            || stripos($uri, '/modules/family/read.php') !== false;
        if ($kitab) {
            echo '<link rel="stylesheet" href="/assets/css/family-os.817.css?v=' . $v . '">' . "\n";
        }
        echo '<link rel="stylesheet" href="/assets/css/family-desks.818.css?v=' . $v . '">' . "\n";
        echo '<script src="/assets/js/family-desks.812.js?v=' . $v . '" defer></script>' . "\n";
        if (stripos($uri, '/modules/family/library.php') !== false) {
            echo '<script src="/assets/js/kitab-studio.816.js?v=' . $v . '" defer></script>' . "\n";
            echo '<script src="/assets/js/kitab-assist.817.js?v=' . $v . '" defer></script>' . "\n";
        } elseif (stripos($uri, '/modules/family/read.php') !== false) {
            echo '<script src="/assets/js/kitab-assist.817.js?v=' . $v . '" defer></script>' . "\n";
        }
    }
    echo '<script src="/assets/js/saas-os.806.js?v=' . $v . '" defer></script>' . "\n";
    echo '<script src="/assets/js/pay-mark.php?v=' . $v . '" defer></script>' . "\n";
    echo '<script src="/assets/js/fresh-home.810.js?v=' . $v . '" defer></script>' . "\n";
    echo '<script src="/assets/js/ame-runtime.812.js?v=' . $v . '" defer></script>' . "\n";
    echo '<script src="/assets/js/shell-in.825.js?v=' . $v . '" defer></script>' . "\n";
    if (!$is_shop_home) {
        echo '<script src="/assets/js/inventory-first-home.710.js?v=' . $v . '" defer></script>' . "\n";
    }
    echo '<script src="/assets/js/ticker-voice.827.js?v=' . $v . '" defer></script>' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/ticker-voice.827.css?v=' . $v . '">' . "\n";
    if (stripos($uri, '/modules/accounts/') !== false) {
        echo '<link rel="stylesheet" href="/assets/css/txn-desk.css?v=' . $v . '">' . "\n";
    }
    if (stripos($uri, '/modules/settings/activity_logs.php') !== false
        || stripos($uri, '/modules/reports/audit_trail.php') !== false) {
        echo '<link rel="stylesheet" href="/assets/css/audit-desk.819.css?v=' . $v . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="/assets/css/front-desk-lock.819.css?v=' . $v . '">' . "\n";
}
