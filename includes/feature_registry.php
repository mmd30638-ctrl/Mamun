<?php
/**
 * Permission-aware feature launcher for the shop-status dashboard.
 *
 * Current module homes are mapped here. Future modules also appear
 * automatically when they add a safe top-level dashboard.php, index.php, or
 * list.php entry point under /modules/<module>/.
 */
require_once __DIR__ . '/permission_catalog.php';

function ame_feature_routes(): array
{
    return [
        'm-sales' => '/modules/sales/pos.php',
        'm-sales-report' => '/modules/reports/sales_summary.php',
        'm-sales-returns' => '/modules/returns/v2/dashboard.php',
        'm-purchase' => '/modules/purchase/add.php',
        'm-purchase-returns' => '/modules/purchase/returns/dashboard.php',
        'm-inv' => '/modules/inventory/list.php',
        'm-customers' => '/modules/customers/index.php',
        'm-hire' => '/modules/customers/hire/index.php',
        'm-suppliers' => '/modules/suppliers/dashboard.php',
        'm-hrm' => '/modules/hrm/index.php',
        'm-rental' => '/modules/rental/dashboard.php',
        'm-shop-rent' => '/modules/shop_rent/index.php',
        'm-family' => '/modules/family/index.php',
        'm-transactions' => '/modules/accounts/finance_dashboard.php',
        'm-acc' => '/modules/partners/index.php',
        'm-bloan' => '/modules/bloan/dashboard.php',
        'm-warranty' => '/modules/reports/warranty.php',
        'm-reports' => '/modules/reports/index.php',
        'm-pulse' => '/modules/pulse/index.php',
        'm-smart' => '/modules/smart/index.php',
        'm-ticker' => '/modules/settings/scrolling_text.php',
        'm-countdown' => '/modules/settings/countdown.php',
        'm-settings' => '/modules/settings/dashboard_settings.php',
    ];
}

function ame_feature_allowed(mysqli $db, string $permission): bool
{
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return true;
    }
    return $permission !== '' && has_permission($db, $permission);
}

function ame_feature_catalog_index(): array
{
    $index = [];
    foreach (ame_permission_catalog() as $groupKey => $group) {
        foreach (($group['items'] ?? []) as $permission => $item) {
            $index[$permission] = [
                'permission' => $permission,
                'group' => $groupKey,
                'group_title' => is_en() ? (string)($group['en'] ?? $groupKey) : (string)($group['bn'] ?? $groupKey),
                'group_lead' => (string)($group['lead'] ?? ''),
                'title' => (string)($item['title'] ?? $permission),
                'desc' => (string)($item['desc'] ?? ''),
                'icon' => (string)($item['icon'] ?? 'folder'),
            ];
        }
    }
    return $index;
}

function ame_feature_registry(mysqli $db): array
{
    $catalog = ame_feature_catalog_index();
    $routes = ame_feature_routes();
    $features = [];
    $seen = [];

    foreach ($routes as $permission => $href) {
        if (!isset($catalog[$permission]) || !ame_feature_allowed($db, $permission)) {
            continue;
        }
        $item = $catalog[$permission];
        $item['href'] = $href;
        $features[] = $item;
        $seen[$href] = true;
    }

    // Safe auto-discovery: only module landing pages are eligible. Mutation
    // handlers, process files, APIs, and nested pages can never be discovered.
    $root = dirname(__DIR__) . '/modules';
    $skip = ['borrow', 'loans', 'pulse', 'settings'];
    $permissionAliases = [
        'accounts' => 'm-transactions',
        'partners' => 'm-acc',
        'returns' => 'm-sales-returns',
        'dashboard' => 'm-pulse',
    ];
    if (is_dir($root)) {
        foreach (new DirectoryIterator($root) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }
            $slug = $dir->getFilename();
            if (!preg_match('/^[a-z0-9_-]+$/', $slug) || in_array($slug, $skip, true)) {
                continue;
            }
            $href = '';
            foreach (['dashboard.php', 'index.php', 'list.php'] as $entry) {
                if (is_file($dir->getPathname() . '/' . $entry)) {
                    $href = '/modules/' . $slug . '/' . $entry;
                    break;
                }
            }
            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $permission = $permissionAliases[$slug] ?? ('m-' . $slug);
            if (!ame_feature_allowed($db, $permission)) {
                continue;
            }
            $item = $catalog[$permission] ?? [
                'permission' => $permission,
                'group' => 'operations',
                'group_title' => is_en() ? 'New features' : 'নতুন ফিচার',
                'group_lead' => is_en() ? 'Automatically discovered module' : 'স্বয়ংক্রিয়ভাবে যুক্ত নতুন মডিউল',
                'title' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                'desc' => is_en() ? 'Open this module' : 'এই মডিউল খুলুন',
                'icon' => 'folder',
            ];
            $item['href'] = $href;
            $features[] = $item;
            $seen[$href] = true;
        }
    }

    $groups = [];
    foreach ($features as $feature) {
        $key = (string)$feature['group'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'title' => (string)$feature['group_title'],
                'lead' => (string)$feature['group_lead'],
                'items' => [],
            ];
        }
        $groups[$key]['items'][] = $feature;
    }
    return $groups;
}
