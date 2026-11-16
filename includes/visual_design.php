<?php
/**
 * Central visual design engine.
 * Page custom style wins over the global theme, which wins over the built-in CSS.
 * Old palette cookies and the settings colour form do not write a second theme.
 */

function vd_valid_page_key(string $key): string
{
    $key = strtolower(trim($key));
    if ($key === '' || $key === 'index') {
        return 'home';
    }
    if (!preg_match('/^[a-z0-9_\/-]{1,80}$/', $key)) {
        return '';
    }
    return $key;
}

function vd_page_key(): string
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
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

function vd_can_edit(?mysqli $db): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    if (strtolower((string)$_SESSION['role']) === 'admin') {
        return true;
    }
    return $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-visual-design');
}

function vd_elements(): array
{
    return [
        'page' => 'body',
        'header' => '.master-header-fixed',
        'sidebar' => '#mySidebar',
        'nav' => '#mySidebar a',
        'section' => '.menu-section, .studio-panel, .ame-pos-heading',
        'card' => '.card, .studio-panel',
        'product-card' => '.pos-product, .product-card, .ame-product-card',
        'product-name' => '.pos-product-name, .product-name, .pname',
        'product-price' => '.pos-price, .product-price, .price',
        'input' => 'input[type="text"], input[type="number"], input[type="search"], input[type="tel"], input[type="email"], textarea',
        'select' => 'select',
        'search' => 'input[type="search"], .pos-search',
        'button' => 'button, .btn, a.hdr-icon-btn',
        'button-text' => 'button, .btn',
        'table' => 'table',
        'table-header' => 'th',
        'table-row' => 'tbody tr',
        'table-cell' => 'td',
        'badge' => '.hdr-badge, .badge',
        'label' => 'label',
        'icon' => '.hdr-icon-btn, #mySidebar a',
        'heading' => 'h1, h2, h3',
        'text' => '#main-content p',
        'alert' => '.alert, .flash-ok, .flash-err',
        'modal' => '#ameClockEditBox, .modal',
        'popup' => '.dropdown-menu, .ame-pop',
        'total' => '.ame-pos-total, .pos-total, .grand-total',
        'payment' => '.pos-payment, .payment-box',
        'customer' => '.pos-customer, .customer-box',
        'footer' => 'footer',
        'clock' => '#hdr-live-clock',
    ];
}

function vd_properties(): array
{
    return [
        'background-color' => 'color',
        'color' => 'color',
        'border-color' => 'color',
        'icon-color' => 'color',
        'hover-background' => 'color',
        'hover-color' => 'color',
        'active-background' => 'color',
        'focus-color' => 'color',
        'placeholder-color' => 'color',
        'shadow-color' => 'color',
        'font-family' => 'font',
        'font-size' => 'length',
        'font-weight' => 'weight',
        'line-height' => 'leading',
        'letter-spacing' => 'track',
        'text-align' => 'align',
        'border-width' => 'length',
        'border-style' => 'border-style',
        'border-radius' => 'length',
        'margin' => 'box',
        'padding' => 'box',
        'gap' => 'box',
        'box-shadow' => 'shadow',
        'opacity' => 'opacity',
        'background' => 'gradient',
        'transition' => 'transition',
    ];
}

function vd_fonts(): array
{
    return [
        'inherit',
        'system-ui, sans-serif',
        'Noto Sans Bengali, sans-serif',
        'Georgia, serif',
        'ui-monospace, monospace',
    ];
}

function vd_label(string $prefix, string $key): string
{
    $id = $prefix . '_' . str_replace('-', '_', $key);
    return function_exists('t') ? t($id) : $key;
}

function vd_is_color(string $value): bool
{
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
        return true;
    }
    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(,\s*(0|1|0?\.\d{1,2})\s*)?\)$/', $value, $m)) {
        return (int)$m[1] <= 255 && (int)$m[2] <= 255 && (int)$m[3] <= 255;
    }
    if (preg_match('/^hsla?\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%\s*(,\s*(0|1|0?\.\d{1,2})\s*)?\)$/', $value, $m)) {
        return (int)$m[1] <= 360 && (int)$m[2] <= 100 && (int)$m[3] <= 100;
    }
    return false;
}

function vd_is_length(string $value): bool
{
    return (bool)preg_match('/^(0|[0-9]{1,3}(\.\d{1,2})?)(px|rem|em|%)$/', $value);
}

function vd_is_box(string $value): bool
{
    return (bool)preg_match('/^((0|[0-9]{1,3}(\.\d{1,2})?(px|rem|em|%))\s*){1,4}$/', $value);
}

function vd_sanitize_value(string $property, string $value): ?string
{
    $props = vd_properties();
    if (!isset($props[$property])) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || strlen($value) > 160) {
        return null;
    }
    if (preg_match('/url\s*\(|expression|javascript:|@import|<|>/i', $value)) {
        return null;
    }
    $kind = $props[$property];
    $ok = false;
    if ($kind === 'color') {
        $ok = vd_is_color($value);
    } elseif ($kind === 'length') {
        $ok = vd_is_length($value);
    } elseif ($kind === 'box') {
        $ok = vd_is_box($value);
    } elseif ($kind === 'font') {
        $ok = in_array($value, vd_fonts(), true);
    } elseif ($kind === 'weight') {
        $ok = (bool)preg_match('/^(normal|bold|[1-9]00)$/', $value);
    } elseif ($kind === 'leading') {
        $ok = (bool)preg_match('/^(0\.[89]|[12](\.\d{1,2})?|3)$/', $value) || vd_is_length($value);
    } elseif ($kind === 'track') {
        $ok = $value === 'normal' || (bool)preg_match('/^-?(0|[0-9]{1,2}(\.\d{1,2})?)(px|em)$/', $value);
    } elseif ($kind === 'align') {
        $ok = in_array($value, ['left', 'right', 'center', 'justify'], true);
    } elseif ($kind === 'border-style') {
        $ok = in_array($value, ['none', 'solid', 'dashed', 'dotted'], true);
    } elseif ($kind === 'opacity') {
        $ok = (bool)preg_match('/^(0|1|0\.\d{1,2})$/', $value);
    } elseif ($kind === 'shadow') {
        $ok = $value === 'none' || (bool)preg_match('/^0 [0-9]{1,2}px [0-9]{1,2}px( [0-9]{1,2}px)? rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d{1,2})\s*)?\)$/', $value);
    } elseif ($kind === 'gradient') {
        $ok = (bool)preg_match('/^linear-gradient\(\s*(to (top|right|bottom|left)|[0-9]{1,3}deg)\s*,\s*#[0-9a-fA-F]{3,8}\s*,\s*#[0-9a-fA-F]{3,8}\s*\)$/', $value);
    } elseif ($kind === 'transition') {
        $ok = $value === 'none' || (bool)preg_match('/^0\.[0-9]{1,2}s ease$/', $value);
    }
    return $ok ? $value : null;
}

function vd_normalize_scope(string $scope): string
{
    return $scope === 'global' ? 'global' : 'page';
}

function vd_normalize_device(string $device): string
{
    return in_array($device, ['all', 'mobile', 'tablet', 'desktop'], true) ? $device : 'all';
}

function ensure_vd_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('vd_styles')) {
        $done = true;
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS vd_styles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(12) NOT NULL,
        page_key VARCHAR(80) NOT NULL DEFAULT '',
        element_key VARCHAR(40) NOT NULL,
        device VARCHAR(12) NOT NULL DEFAULT 'all',
        property VARCHAR(40) NOT NULL,
        value VARCHAR(180) NOT NULL,
        updated_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vd_style (scope, page_key, element_key, device, property),
        KEY idx_vd_page (page_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('vd_styles', true);
    }
}

function vd_rows(mysqli $db, string $pageKey): array
{
    ensure_vd_schema($db);
    $stmt = $db->prepare("SELECT scope, page_key, element_key, device, property, value
        FROM vd_styles
        WHERE scope='global' OR (scope='page' AND page_key=?)
        ORDER BY id ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $pageKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function vd_selector(string $element, string $pageKey, string $property): string
{
    $elements = vd_elements();
    $raw = $elements[$element] ?? '';
    if ($raw === '') {
        return '';
    }
    $parts = array_map('trim', explode(',', $raw));
    $out = [];
    $prefix = $pageKey !== '' ? 'body[data-vd-page="' . $pageKey . '"] ' : '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if ($part === 'body') {
            $sel = $pageKey !== '' ? 'body[data-vd-page="' . $pageKey . '"]' : 'body';
        } else {
            $sel = $prefix . $part . ':not(.vd-ui)';
        }
        if ($property === 'icon-color') {
            $sel .= ' svg';
        } elseif ($property === 'hover-background' || $property === 'hover-color') {
            $sel .= ':hover';
        } elseif ($property === 'active-background') {
            $sel .= ':active';
        } elseif ($property === 'focus-color') {
            $sel .= ':focus';
        } elseif ($property === 'placeholder-color') {
            $sel .= '::placeholder';
        }
        $out[] = $sel;
    }
    return implode(', ', $out);
}

function vd_css_property(string $property, string $value): string
{
    if ($property === 'hover-background' || $property === 'active-background') {
        return 'background-color:' . $value;
    }
    if ($property === 'hover-color' || $property === 'focus-color' || $property === 'placeholder-color' || $property === 'icon-color') {
        return 'color:' . $value;
    }
    if ($property === 'shadow-color') {
        return 'box-shadow:0 4px 14px ' . $value;
    }
    return $property . ':' . $value;
}

function vd_media_wrap(string $device, string $css): string
{
    if ($css === '') {
        return '';
    }
    if ($device === 'mobile') {
        return '@media (max-width:767px){' . $css . '}';
    }
    if ($device === 'tablet') {
        return '@media (min-width:768px) and (max-width:1023px){' . $css . '}';
    }
    if ($device === 'desktop') {
        return '@media (min-width:1024px){' . $css . '}';
    }
    return $css;
}

function vd_build_css(array $rows, string $pageKey): string
{
    $buckets = [
        'global|all' => '',
        'global|mobile' => '',
        'global|tablet' => '',
        'global|desktop' => '',
        'page|all' => '',
        'page|mobile' => '',
        'page|tablet' => '',
        'page|desktop' => '',
    ];
    $elements = vd_elements();
    foreach ($rows as $row) {
        $scope = (string)($row['scope'] ?? '');
        $element = (string)($row['element_key'] ?? '');
        $device = vd_normalize_device((string)($row['device'] ?? 'all'));
        $property = (string)($row['property'] ?? '');
        $value = vd_sanitize_value($property, (string)($row['value'] ?? ''));
        if ($value === null || !isset($elements[$element])) {
            continue;
        }
        if ($scope !== 'global' && $scope !== 'page') {
            continue;
        }
        $targetPage = $scope === 'page' ? $pageKey : '';
        if ($scope === 'page' && (string)($row['page_key'] ?? '') !== $pageKey) {
            continue;
        }
        $selector = vd_selector($element, $targetPage, $property);
        if ($selector === '') {
            continue;
        }
        $decl = vd_css_property($property, $value);
        $buckets[$scope . '|' . $device] .= $selector . '{' . $decl . ' !important}';
    }
    $css = '';
    foreach ($buckets as $key => $chunk) {
        $device = explode('|', $key)[1];
        $css .= vd_media_wrap($device, $chunk);
    }
    return $css;
}

function vd_stylesheet_html(?mysqli $db = null): string
{
    if (!($db instanceof mysqli)) {
        return '';
    }
    try {
        $page = vd_page_key();
        $css = vd_build_css(vd_rows($db, $page), $page);
    } catch (Throwable $e) {
        error_log('visual design css skipped: ' . $e->getMessage());
        return '';
    }
    if ($css === '') {
        return '';
    }
    return '<style id="vd-engine">' . $css . '</style>';
}

function vd_brand_field(string $element, string $property): ?string
{
    $map = [
        'page|background-color' => 'bg_color',
        'page|color' => 'text_color',
        'header|background-color' => 'header_color',
        'sidebar|background-color' => 'sidebar_color',
        'button|background-color' => 'accent_color',
        'card|background-color' => 'surface_color',
        'heading|color' => 'primary_color',
        'alert|color' => 'danger_color',
        'clock|color' => 'clock_color',
        'badge|background-color' => 'gold_color',
        'label|color' => 'muted_color',
    ];
    return $map[$element . '|' . $property] ?? null;
}

function vd_sync_brand(mysqli $db, string $element, string $property, ?string $value): void
{
    if (!function_exists('ame_load_theme') || !function_exists('ame_save_theme') || !function_exists('ame_theme_hex')) {
        return;
    }
    $field = vd_brand_field($element, $property);
    if ($field === null || $value === null || !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
        return;
    }
    $theme = ame_load_theme($db, '');
    $theme[$field] = ame_theme_hex($value, $theme[$field] ?? $value);
    ame_save_theme($db, $theme, '');
}

function vd_upsert(mysqli $db, string $scope, string $pageKey, string $element, string $device, string $property, string $value, int $userId): bool
{
    $clean = vd_sanitize_value($property, $value);
    if ($clean === null || !isset(vd_elements()[$element])) {
        return false;
    }
    $scope = vd_normalize_scope($scope);
    $device = vd_normalize_device($device);
    $pageKey = $scope === 'global' ? '' : $pageKey;
    ensure_vd_schema($db);
    $stmt = $db->prepare('INSERT INTO vd_styles (scope, page_key, element_key, device, property, value, updated_by)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE value=VALUES(value), updated_by=VALUES(updated_by)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssssssi', $scope, $pageKey, $element, $device, $property, $clean, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $scope === 'global' && $device === 'all') {
        vd_sync_brand($db, $element, $property, $clean);
    }
    return $ok;
}

function vd_delete_one(mysqli $db, string $scope, string $pageKey, string $element, string $device, string $property): void
{
    $scope = vd_normalize_scope($scope);
    $device = vd_normalize_device($device);
    $pageKey = $scope === 'global' ? '' : $pageKey;
    if (!isset(vd_elements()[$element]) || !isset(vd_properties()[$property])) {
        return;
    }
    $stmt = $db->prepare('DELETE FROM vd_styles WHERE scope=? AND page_key=? AND element_key=? AND device=? AND property=?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sssss', $scope, $pageKey, $element, $device, $property);
    $stmt->execute();
    $stmt->close();
}

function vd_reset(mysqli $db, string $mode, string $pageKey, string $element = ''): void
{
    ensure_vd_schema($db);
    if ($mode === 'element') {
        if (!isset(vd_elements()[$element])) {
            return;
        }
        $stmt = $db->prepare("DELETE FROM vd_styles WHERE scope='page' AND page_key=? AND element_key=?");
        if ($stmt) {
            $stmt->bind_param('ss', $pageKey, $element);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    if ($mode === 'global-element') {
        if (!isset(vd_elements()[$element])) {
            return;
        }
        $stmt = $db->prepare("DELETE FROM vd_styles WHERE scope='global' AND page_key='' AND element_key=?");
        if ($stmt) {
            $stmt->bind_param('s', $element);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    if ($mode === 'page') {
        $stmt = $db->prepare("DELETE FROM vd_styles WHERE scope='page' AND page_key=?");
        if ($stmt) {
            $stmt->bind_param('s', $pageKey);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    if ($mode === 'all') {
        $db->query("DELETE FROM vd_styles");
        if (function_exists('ensure_theme_schema')) {
            ensure_theme_schema($db);
            $db->query("DELETE FROM theme_settings WHERE module_key<>''");
        }
        if (function_exists('ame_theme_defaults') && function_exists('ame_save_theme')) {
            ame_save_theme($db, ame_theme_defaults(), '');
        }
    }
}

function vd_editor_config(mysqli $db): array
{
    $page = vd_page_key();
    $elements = [];
    foreach (vd_elements() as $key => $selector) {
        $elements[] = [
            'key' => $key,
            'selector' => $selector,
            'label' => vd_label('vd_el', $key),
        ];
    }
    $properties = [];
    foreach (vd_properties() as $key => $kind) {
        $properties[] = [
            'key' => $key,
            'kind' => $kind,
            'label' => vd_label('vd_prop', $key),
            'group' => vd_property_group($key),
        ];
    }
    $i18nKeys = [
        'vd_open', 'vd_close', 'vd_title', 'vd_scope_page', 'vd_scope_global', 'vd_device',
        'vd_device_all', 'vd_device_mobile', 'vd_device_tablet', 'vd_device_desktop', 'vd_pick',
        'vd_save', 'vd_undo', 'vd_redo', 'vd_reset_el', 'vd_reset_page', 'vd_reset_all',
        'vd_saved', 'vd_failed', 'vd_confirm_page', 'vd_confirm_el', 'vd_confirm_all', 'vd_confirm_global_save',
        'vd_recent', 'vd_hex', 'vd_alpha', 'vd_group_color', 'vd_group_type', 'vd_group_border',
        'vd_group_space', 'vd_group_look', 'vd_clear', 'vd_preview_device',
    ];
    $i18n = [];
    foreach ($i18nKeys as $key) {
        $i18n[$key] = function_exists('t') ? t($key) : $key;
    }
    return [
        'page' => $page,
        'api' => '/modules/settings/visual_design_api.php',
        'csrf' => function_exists('csrf_token') ? csrf_token() : '',
        'elements' => $elements,
        'properties' => $properties,
        'fonts' => vd_fonts(),
        'styles' => vd_rows($db, $page),
        'i18n' => $i18n,
    ];
}

function vd_property_group(string $property): string
{
    if (in_array($property, ['font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-align'], true)) {
        return 'type';
    }
    if (in_array($property, ['border-width', 'border-style', 'border-radius', 'border-color'], true)) {
        return 'border';
    }
    if (in_array($property, ['margin', 'padding', 'gap'], true)) {
        return 'space';
    }
    if (in_array($property, ['box-shadow', 'opacity', 'background', 'transition', 'shadow-color'], true)) {
        return 'look';
    }
    return 'color';
}

function vd_editor_html(mysqli $db): string
{
    return '';
}
