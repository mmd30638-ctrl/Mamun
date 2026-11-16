<?php
/**
 * PWA manifest — Home Screen app name/icon from shop settings.
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');

$icon_192 = shop_app_icon_url(192);
$icon_512 = shop_app_icon_url(512);
$name = shop_name();
$short = shop_short_name();
if (function_exists('mb_strlen') && mb_strlen($short, 'UTF-8') > 12) {
    $short = mb_substr($short, 0, 12, 'UTF-8');
}

$manifest = [
    'id'               => '/',
    'name'             => $name . ' অ্যাপ',
    'short_name'       => $short,
    'description'      => $name . ' — দোকান ও ওয়ার্কশপ ব্যবসা অ্যাপ',
    'lang'             => 'bn',
    'dir'              => 'ltr',
    'start_url'        => '/index.php',
    'scope'            => '/',
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'      => 'portrait',
    'background_color' => '#FFFFFF',
    'theme_color'      => '#FFFFFF',
    'categories'       => ['business', 'finance', 'productivity'],
    'icons' => [
        ['src' => $icon_192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icon_512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
