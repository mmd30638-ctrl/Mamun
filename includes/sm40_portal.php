<?php
/**
 * SM40 — হোমপেজ হেডার থেকে বাইরের লগইন (কোনো ক্রেডেনশিয়াল Aponshop-এ পাঠানো হয় না)।
 */

function ame_sm40_entry_url(): string
{
    return '/sm40_listen.php';
}

function ame_sm40_show_home_chip(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    return function_exists('ame_if_is_home_page') && ame_if_is_home_page();
}

function ame_sm40_show_global_dock(): bool
{
    return !empty($_SESSION['user_id']);
}

/** লগইন থাকলে সব পেজে SM40 ভাসমান শর্টকাট (মোবাইল + ডেস্কটপ) */
function ame_sm40_global_dock_assets(): void
{
    if (!ame_sm40_show_global_dock()) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.949';
    echo '<link rel="stylesheet" href="/assets/css/sm40-home-link.css?v='
        . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" id="sm40-home-link-css">' . "\n";
    echo '<script src="/assets/js/sm40-home-player.js?v='
        . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer id="sm40-home-player-js"></script>' . "\n";
}

function ame_sm40_home_chip_assets(): void
{
    ame_sm40_global_dock_assets();
}

/** @deprecated alias */
function ame_sm40_home_chip_css(): void
{
    ame_sm40_home_chip_assets();
}