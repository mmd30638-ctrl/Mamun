<?php
/**
 * SM40 — হোমপেজ হেডার থেকে বাইরের লগইন (কোনো ক্রেডেনশিয়াল Aponshop-এ পাঠানো হয় না)।
 */

function ame_sm40_entry_url(): string
{
    return 'https://sm40.com/welcome?lang=bengali';
}

function ame_sm40_show_home_chip(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    return function_exists('ame_if_is_home_page') && ame_if_is_home_page();
}

function ame_sm40_home_chip_css(): void
{
    if (!ame_sm40_show_home_chip()) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.947';
    echo '<link rel="stylesheet" href="/assets/css/sm40-home-link.css?v='
        . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" id="sm40-home-link-css">' . "\n";
}