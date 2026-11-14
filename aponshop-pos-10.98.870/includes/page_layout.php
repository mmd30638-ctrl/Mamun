<?php
/**
 * Full-page layout studio: front colors, boxes, icons.
 * Designer lives in Settings. Optional 📐 icon can be placed on every page.
 */

function ame_page_layout_store_path(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/page_layout.json';
}

function ame_page_layout_defaults(): array
{
    return [
        'iconOnAllPages' => true,
        'front' => [
            'navy' => '#003F8F',
            'blue' => '#0057B8',
            'green' => '#04933A',
            'canvas' => '#F4F7FB',
            'text' => '#111827',
            'muted' => '#667085',
            'surface' => '#F8FAFC',
            'line' => '#D7DEE8',
            'danger' => '#DC2626',
        ],
        'pages' => [],
    ];
}

function ame_page_layout_load(): array
{
    $path = ame_page_layout_store_path();
    $base = ame_page_layout_defaults();
    if (!is_file($path)) {
        return $base;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        return $base;
    }
    if (isset($raw['front']) && is_array($raw['front'])) {
        $base['front'] = array_merge($base['front'], $raw['front']);
    }
    if (array_key_exists('iconOnAllPages', $raw)) {
        $base['iconOnAllPages'] = (bool) $raw['iconOnAllPages'];
    }
    if (isset($raw['pages']) && is_array($raw['pages'])) {
        $base['pages'] = $raw['pages'];
    }
    return $base;
}

function ame_page_layout_save(array $data): bool
{
    $base = ame_page_layout_defaults();
    $out = [
        'iconOnAllPages' => !empty($data['iconOnAllPages']),
        'front' => array_merge($base['front'], is_array($data['front'] ?? null) ? $data['front'] : []),
        'pages' => is_array($data['pages'] ?? null) ? $data['pages'] : [],
    ];
    $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(ame_page_layout_store_path(), $json) !== false;
}

function ame_page_layout_can_edit(?mysqli $db = null): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    if (strtolower((string) $_SESSION['role']) === 'admin') {
        return true;
    }
    if ($db instanceof mysqli && function_exists('has_permission')) {
        return has_permission($db, 'm-settings') || has_permission($db, 'm-visual-design');
    }
    return false;
}

function ame_page_layout_key(): string
{
    $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $script = preg_replace('#^/modules/#', '', $script) ?? $script;
    $script = preg_replace('#\.php$#', '', $script) ?? $script;
    $script = trim($script, '/');
    if ($script === '' || $script === 'index') {
        return 'home';
    }
    if (!preg_match('/^[a-z0-9_\/-]{1,80}$/', $script)) {
        return 'page';
    }
    return $script;
}

function ame_page_layout_css(array $data): string
{
    $f = $data['front'] ?? [];
    $navy = htmlspecialchars((string) ($f['navy'] ?? '#003F8F'), ENT_QUOTES, 'UTF-8');
    $blue = htmlspecialchars((string) ($f['blue'] ?? '#0057B8'), ENT_QUOTES, 'UTF-8');
    $green = htmlspecialchars((string) ($f['green'] ?? '#04933A'), ENT_QUOTES, 'UTF-8');
    $canvas = htmlspecialchars((string) ($f['canvas'] ?? '#F4F7FB'), ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars((string) ($f['text'] ?? '#111827'), ENT_QUOTES, 'UTF-8');
    $muted = htmlspecialchars((string) ($f['muted'] ?? '#667085'), ENT_QUOTES, 'UTF-8');
    $surface = htmlspecialchars((string) ($f['surface'] ?? '#F8FAFC'), ENT_QUOTES, 'UTF-8');
    $line = htmlspecialchars((string) ($f['line'] ?? '#D7DEE8'), ENT_QUOTES, 'UTF-8');
    $danger = htmlspecialchars((string) ($f['danger'] ?? '#DC2626'), ENT_QUOTES, 'UTF-8');

    $css = ':root{--desk-navy:' . $navy . ';--desk-blue:' . $blue . ';--desk-green:' . $green
        . ';--desk-canvas:' . $canvas . ';--desk-text:' . $text . ';--desk-muted:' . $muted
        . ';--desk-surface:' . $surface . ';--desk-line:' . $line . ';--desk-danger:' . $danger . ';}';

    foreach (($data['pages'] ?? []) as $pageKey => $page) {
        if (!is_array($page) || !preg_match('/^[a-z0-9_\/-]{1,80}$/', (string) $pageKey)) {
            continue;
        }
        $safeKey = htmlspecialchars((string) $pageKey, ENT_QUOTES, 'UTF-8');
        if ($pageKey === 'sales/pos') {
            $scope = 'html body.ame-sales-pro.ame-pos-terminal-page[data-ame-layout-page="' . $safeKey . '"]';
        } else {
            $scope = 'body[data-ame-layout-page="' . $safeKey . '"]';
        }
        foreach (($page['boxes'] ?? []) as $box) {
            if (!is_array($box)) {
                continue;
            }
            $sel = ame_page_layout_safe_selector((string) ($box['selector'] ?? ''));
            if ($sel === '') {
                continue;
            }
            $rules = [];
            if (!empty($box['hidden'])) {
                $rules[] = 'display:none';
            }
            if (isset($box['bg']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $box['bg'])) {
                $rules[] = 'background:' . $box['bg'];
            }
            if (isset($box['color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $box['color'])) {
                $rules[] = 'color:' . $box['color'];
            }
            foreach (['width', 'height', 'left', 'top', 'radius'] as $prop) {
                if (!isset($box[$prop]) || !is_numeric($box[$prop])) {
                    continue;
                }
                $cssProp = $prop === 'radius' ? 'border-radius' : $prop;
                $rules[] = $cssProp . ':' . (int) $box[$prop] . 'px';
            }
            if (!empty($box['moved'])) {
                $rules[] = 'position:relative';
            }
            if ($rules) {
                $css .= $scope . ' ' . $sel . '{' . implode(';', $rules) . ' !important;}';
            }
        }
        foreach (($page['icons'] ?? []) as $icon) {
            if (!is_array($icon)) {
                continue;
            }
            $sel = ame_page_layout_safe_selector((string) ($icon['selector'] ?? ''));
            if ($sel === '') {
                continue;
            }
            $rules = [];
            if (!empty($icon['hidden'])) {
                $rules[] = 'display:none';
            }
            if (isset($icon['size']) && is_numeric($icon['size'])) {
                $rules[] = 'width:' . (int) $icon['size'] . 'px';
                $rules[] = 'height:' . (int) $icon['size'] . 'px';
                $rules[] = 'font-size:' . (int) $icon['size'] . 'px';
            }
            if (isset($icon['color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $icon['color'])) {
                $rules[] = 'color:' . $icon['color'];
            }
            if ($rules) {
                $css .= $scope . ' ' . $sel . '{' . implode(';', $rules) . ' !important;}';
            }
        }
    }
    return $css;
}

function ame_page_layout_safe_selector(string $sel): string
{
    $sel = trim($sel);
    if ($sel === '' || strlen($sel) > 180) {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9\s\.\#\[\]=":_-]{1,180}$/', $sel)) {
        return '';
    }
    return $sel;
}

function ame_page_layout_catalog(): array
{
    return [
        ['key' => 'sales/pos', 'url' => '/modules/sales/pos.php', 'label' => 'বিক্রয়'],
        ['key' => 'purchase/add', 'url' => '/modules/purchase/add.php', 'label' => 'ক্রয়'],
        ['key' => 'purchase/history', 'url' => '/modules/purchase/history.php', 'label' => 'ক্রয় ইতিহাস'],
        ['key' => 'inventory/list', 'url' => '/modules/inventory/list.php', 'label' => 'স্টক'],
        ['key' => 'workshop/index', 'url' => '/modules/workshop/index.php', 'label' => 'ওয়ার্কশপ'],
        ['key' => 'customers/list', 'url' => '/modules/customers/list.php', 'label' => 'কাস্টমার'],
        ['key' => 'hrm/list', 'url' => '/modules/hrm/employee_list.php', 'label' => 'কর্মী'],
        ['key' => 'hrm/archive', 'url' => '/modules/hrm/employee_list.php?archive=1', 'label' => 'কর্মী আর্কাইভ'],
        ['key' => 'settings/super', 'url' => '/modules/settings/super_admin.php', 'label' => 'সুপার অ্যাডমিন'],
        ['key' => 'reports/live_work', 'url' => '/modules/reports/live_work.php', 'label' => 'লাইভ ড্যাশবোর্ড'],
        ['key' => 'settings/speed', 'url' => '/modules/settings/speed_system.php', 'label' => 'স্পিড সিস্টেম'],
        ['key' => 'reports/sales_summary', 'url' => '/modules/reports/sales_summary.php', 'label' => 'রিপোর্ট'],
        ['key' => 'settings/tools', 'url' => '/modules/settings/tools.php', 'label' => 'সেটিংস'],
        ['key' => 'home', 'url' => '/index.php', 'label' => 'হোম'],
    ];
}

function ame_page_layout_boot(): void
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, 'layout_designer.php')) {
        return;
    }
    $db = $GLOBALS['db'] ?? null;
    $can = ame_page_layout_can_edit($db instanceof mysqli ? $db : null);
    $data = ame_page_layout_load();
    $ver = '10.98.324';
    $verFile = dirname(__DIR__) . '/VERSION.txt';
    if (is_file($verFile)) {
        $fromFile = trim((string) file_get_contents($verFile));
        if ($fromFile !== '') {
            $ver = $fromFile;
        }
    } elseif (defined('AME_ASSET_VERSION')) {
        $ver = (string) AME_ASSET_VERSION;
    }
    $page = ame_page_layout_key();
    $cfg = [
        'page' => $page,
        'path' => $script,
        'canEdit' => $can,
        'iconOnAllPages' => !empty($data['iconOnAllPages']),
        'designer' => '/modules/settings/layout_designer.php',
        'embed' => isset($_GET['ame_layout_embed']),
    ];
    echo '<style id="amePageLayoutCss">' . ame_page_layout_css($data) . '</style>' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/page-layout-global.css?v=' . htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script>window.AME_PAGE_LAYOUT=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';</script>' . "\n";
    echo '<script src="/assets/js/page-layout-global.js?v=' . htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    if ($page === 'sales/pos') {
        echo '<link rel="stylesheet" href="/assets/css/page-layout-pos-override.css?v=' . htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    echo '<script>document.body && document.body.setAttribute("data-ame-layout-page",' . json_encode($page) . ');</script>' . "\n";
}

function ame_page_layout_echo_styles_after_lock(): void
{
    if (!function_exists('ame_page_layout_load') || !function_exists('ame_page_layout_css')) {
        return;
    }
    $data = ame_page_layout_load();
    echo '<style id="amePageLayoutCssAfterLock">' . ame_page_layout_css($data) . '</style>' . "\n";
}
