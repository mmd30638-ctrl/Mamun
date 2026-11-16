<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
/**
 * Inventory First chrome — mint CSS on dashboards.
 * Shop home is a photo gallery (no KPI board). Pulse keeps the welcome strip.
 */
function ame_if_dash_script(): string
{
    return strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
}

function ame_if_is_home_page(): bool
{
    $script = ame_if_dash_script();
    if (str_contains($script, '/modules/')) {
        return false;
    }
    $base = basename($script);
    return $base === 'index.php' || $base === 'index.html' || $script === '/' || $script === '';
}

function ame_if_is_dashboard(): bool
{
    if (ame_if_is_home_page()) {
        return false;
    }
    $script = ame_if_dash_script();
    $needles = [
        '/modules/pulse/index.php',
        '/modules/smart/index.php',
        '/modules/returns/v2/dashboard.php',
        '/modules/purchase/returns/dashboard.php',
        '/modules/rental/dashboard.php',
        '/modules/accounts/finance_dashboard.php',
        '/modules/loans/dashboard.php',
        '/modules/partners/dashboard.php',
        '/modules/finance/dashboard.php',
        '/modules/hrm/index.php',
        '/modules/agents/index.php',
        '/modules/workshop/index.php',
        '/modules/reports/index.php',
        '/modules/family/index.php',
        '/modules/personal/index.php',
    ];
    foreach ($needles as $needle) {
        if (str_contains($script, $needle)) {
            return true;
        }
    }
    if (str_contains($script, '/modules/')) {
        return false;
    }
    return str_ends_with($script, '/index.php') || str_ends_with($script, '/dashboard.php');
}

function ame_if_is_home_welcome(): bool
{
    $script = ame_if_dash_script();
    return str_contains($script, '/modules/pulse/index.php');
}

function ame_if_dash_welcome(): void
{
    if (!ame_if_is_home_welcome()) {
        return;
    }
    $en = function_exists('current_lang') && current_lang() === 'en';
    $name = trim((string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? ''));
    if ($name === '') {
        $name = $en ? 'Admin' : 'অ্যাডমিন';
    }
    $hello = $en ? 'Welcome Back, ' . $name . '!' : 'ফিরে আসুন, ' . $name . '!';
    $sub = $en
        ? "Here's what's happening with your inventory today."
        : 'আজ দোকানের স্টক ও হিসাবের অবস্থা এখানে।';
    $grow = $en ? 'Grow Today for a Better Tomorrow' : 'আজই বেড়ে উঠুন, কালকের জন্য';
    echo '<div class="if-dash-welcome">';
    echo '<div><h2>' . htmlspecialchars($hello, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p>' . htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') . '</p></div>';
    echo '<em>' . htmlspecialchars($grow, ENT_QUOTES, 'UTF-8') . '</em>';
    echo '</div>';
}
