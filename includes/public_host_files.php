<?php
/**
 * Hostinger যে পাবলিক ফাইল খোঁজে — ডিস্কে রাখে, ডাটাবেজে নয়।
 */

function ame_sale_cream_css_text(): string
{
    return "/* APONSHOP sale-cream — https://mavo12.online */\n"
        . "html.ame-sale-cream,body.ame-sale-cream{background-color:#efe6d4;}\n";
}

function ame_robots_txt_text(): string
{
    return "User-agent: *\nAllow: /login.php\nAllow: /favicon.ico\nAllow: /favicon.png\nDisallow: /\n";
}

function ame_public_host_root(): string
{
    return dirname(__DIR__);
}

function ame_ensure_public_host_files(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $root = ame_public_host_root();
    $cssDir = $root . '/assets/css';
    if (!is_dir($cssDir)) {
        @mkdir($cssDir, 0755, true);
    }
    $css = $cssDir . '/sale-cream.css';
    $cssBody = is_file($css) ? (string) @file_get_contents($css) : '';
    if ($cssBody === '' || (int) @filesize($css) < 20 || !str_contains($cssBody, '{')) {
        @file_put_contents($css, ame_sale_cream_css_text());
    }
    $robots = $root . '/robots.txt';
    if (!is_file($robots) || (int) @filesize($robots) < 10) {
        @file_put_contents($robots, ame_robots_txt_text());
    }
    $favicon = $root . '/favicon.png';
    if (!is_file($favicon) || (int) @filesize($favicon) < 32) {
        foreach ([$root . '/assets/img/icon-192.png', $root . '/assets/img/ma-logo.png'] as $src) {
            if (is_file($src) && (int) @filesize($src) > 32) {
                @copy($src, $favicon);
                break;
            }
        }
    }
}
