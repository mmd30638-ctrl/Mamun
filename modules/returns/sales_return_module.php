<?php
/**
 * বিক্রয় ফেরত — একীভূত মডিউল (Sales Return Module)
 * সংস্করণ: 10.98.962 | সকল রুট ও নেভ এখান থেকে — অন্য মডিউলে হাত দেওয়া নিষেধ।
 *
 * @see modules/returns/SALES_RETURN_MODULE.md
 */
declare(strict_types=1);

const AME_SALES_RETURN_MODULE_REV = '10.98.966';

/**
 * @return list<array{id:string,href:string,icon:string,label_key:string,desc_bn:string,desc_en:string}>
 */
function ame_sales_return_sections(): array
{
    return [
        [
            'id' => 'dashboard',
            'href' => '/modules/returns/v2/dashboard.php',
            'icon' => '⊞',
            'label_key' => 'nav_return_dash',
            'desc_bn' => 'KPI, সাম্প্রতিক ফেরত, দ্রুত অ্যাকশন',
            'desc_en' => 'KPIs, recent returns, quick actions',
        ],
        [
            'id' => 'quick',
            'href' => '/modules/returns/v2/quick_return.php',
            'icon' => '⚡',
            'label_key' => 'nav_return_quick',
            'desc_bn' => 'ক্রেতা নাম → পণ্য → ফেরত + মেমো (স্টক ও বকেয়া)',
            'desc_en' => 'Customer → product → instant return memo',
        ],
        [
            'id' => 'register',
            'href' => '/modules/returns/v2/register.php',
            'icon' => '↩',
            'label_key' => 'nav_return_register',
            'desc_bn' => 'সব ফেরত লিজ, ফিল্টার, অনুমোদন',
            'desc_en' => 'Return register, filters, approval',
        ],
        [
            'id' => 'invoice',
            'href' => '/modules/returns/v2/create_invoice_return.php',
            'icon' => '↩',
            'label_key' => 'nav_return_invoice',
            'desc_bn' => 'মেমো/ইনভয়েস দিয়ে পণ্য ফেরত',
            'desc_en' => 'Return against sales invoice',
        ],
        [
            'id' => 'manual',
            'href' => '/modules/returns/v2/create_manual_return.php',
            'icon' => '↩',
            'label_key' => 'nav_return_manual',
            'desc_bn' => 'ইনভয়েস ছাড়া হাতে লেখা ফেরত',
            'desc_en' => 'Manual return without invoice',
        ],
        [
            'id' => 'exchange',
            'href' => '/modules/returns/v2/create_exchange.php',
            'icon' => '↩',
            'label_key' => 'nav_return_exchange',
            'desc_bn' => 'পণ্য বদল ও পার্থক্য',
            'desc_en' => 'Product exchange',
        ],
        [
            'id' => 'report',
            'href' => '/modules/returns/return_report.php',
            'icon' => '↩',
            'label_key' => 'nav_return_report',
            'desc_bn' => 'সারসংক্ষেপ ও তারিখ রেঞ্জ',
            'desc_en' => 'Aggregated return report',
        ],
        [
            'id' => 'legacy',
            'href' => '/modules/returns/history.php',
            'icon' => '↩',
            'label_key' => 'nav_return_old_hist',
            'desc_bn' => 'পুরনো v1 ফেরত লেজার',
            'desc_en' => 'Legacy return history',
        ],
    ];
}

function ame_sales_return_hub_url(): string
{
    return '/modules/returns/v2/dashboard.php';
}

function ame_sales_return_active_from_request(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, 'quick_return.php')) {
        return 'quick';
    }
    if (str_contains($script, 'dashboard.php') || str_ends_with($script, '/modules/returns/index.php')) {
        return 'dashboard';
    }
    if (str_contains($script, 'register.php')) {
        return 'register';
    }
    if (str_contains($script, 'create_invoice_return.php')) {
        return 'invoice';
    }
    if (str_contains($script, 'create_manual_return.php')) {
        return 'manual';
    }
    if (str_contains($script, 'create_exchange.php')) {
        return 'exchange';
    }
    if (str_contains($script, 'return_report.php')) {
        return 'report';
    }
    if (str_contains($script, 'history.php')) {
        return 'legacy';
    }
    if (str_contains($script, '/modules/returns/v2/view.php')) {
        return 'register';
    }

    return 'dashboard';
}

function ame_sales_return_include_nav(?string $activeId = null): void
{
  $activeId = $activeId ?? ame_sales_return_active_from_request();
    $GLOBALS['ame_sales_return_nav_active'] = $activeId;
    include __DIR__ . '/v2/partials/module_nav.php';
}

function ame_sales_return_label(array $section): string
{
    $key = (string)($section['label_key'] ?? '');
    if ($key !== '' && function_exists('te')) {
        $t = te($key);
        if ($t !== $key) {
            return $t;
        }
    }
    if ($key !== '' && function_exists('t')) {
        $t = t($key);
        if ($t !== $key) {
            return $t;
        }
    }

    return $key;
}