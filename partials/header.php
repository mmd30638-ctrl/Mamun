<?php
/**
 * মাস্টার হেডার (V18) - গ্যারান্টিড মোবাইল ও ডেসটপ ভিজিবিলিটি
 */
 require_once __DIR__ . '/../includes/lang_helper.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/account_functions.php';
require_once __DIR__ . '/../includes/stock_alert_functions.php';
require_once __DIR__ . '/../includes/inventory_first_dash.php';

// একটিমাত্র কাস্টমার-বকেয়া ঘণ্টা — ৮ দিন পেমেন্ট নেই, অথবা নির্ধারিত
// পরবর্তী তারিখ আজ/পার হয়ে গেছে এবং এখনো বাকি আছে।
$ame_due_notification_count = 0;
$ame_stock_alert_count = 0;
$ame_stock_alert_can_view = false;
if (isset($db) && $db instanceof mysqli) {
    try {
        $ame_due_notification_count = function_exists('ame_due_notification_count')
            ? ame_due_notification_count($db)
            : 0;
    } catch (Throwable $e) {
        error_log('header due badge: ' . $e->getMessage());
        $ame_due_notification_count = 0;
    }
    $ame_stock_alert_can_view = ame_stock_alert_can_view($db);
    if ($ame_stock_alert_can_view) {
        $ame_stock_alert_count = ame_stock_alert_count($db);
    }
}
$is_admin_hdr = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
$ame_language_return = (string)($_SERVER['REQUEST_URI'] ?? '/index.php');
if (
    $ame_language_return === ''
    || $ame_language_return[0] !== '/'
    || str_starts_with($ame_language_return, '//')
    || preg_match('/[\r\n]/', $ame_language_return)
) {
    $ame_language_return = '/index.php';
}
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang() === 'en' ? 'en' : 'bn'; ?>" class="lang-<?php echo current_lang() === 'en' ? 'en' : 'bn'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php
$ame_script_now = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if (str_contains($ame_script_now, '/modules/customers/hire/')):
?>
    <script>
        window.AME_ERROR_BOUNDARY = true;
        window.addEventListener('error', function (e) {
            if (e && e.stopImmediatePropagation) e.stopImmediatePropagation();
        }, true);
        window.addEventListener('unhandledrejection', function (e) {
            if (e && e.preventDefault) e.preventDefault();
        }, true);
    </script>
<?php endif; ?>
    <script src="/assets/js/ame-desktop-site.js?v=<?php echo htmlspecialchars((string)($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
    if (function_exists('ame_global_responsive_tier_script_echo')) {
        ame_global_responsive_tier_script_echo();
    }
    ?>
    <title><?php echo htmlspecialchars(shop_name(), ENT_QUOTES, 'UTF-8'); ?> · <?php echo te('ds_app'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(shop_name() . ' — দোকান ও ওয়ার্কশপ ব্যবসা অ্যাপ। হোম স্ক্রিনে বসিয়ে ফোন অ্যাপের মতো খুলুন।', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <script>
        window.AME_CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
        window.AME_LANG = <?php echo json_encode(current_lang()); ?>;
        window.AME_SHOP_NAME = <?php echo json_encode(shop_name(), JSON_UNESCAPED_UNICODE); ?>;
        window.AME_SHOP_TAGLINE = <?php echo json_encode(shop_tagline(), JSON_UNESCAPED_UNICODE); ?>;
        window.AME_VD_ENGINE = 1;
        window.AME_SELL_WITHOUT_STOCK = <?php echo !empty($_SESSION['ame_sell_without_stock']) ? 'true' : 'false'; ?>;
    </script>
    
    <link rel="stylesheet" href="/assets/css/theme.css?v=<?php echo $sys_version ?? '1.0'; ?>">
    <link rel="stylesheet" href="/assets/css/ame-device.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/ame-master-layout.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (function_exists('ame_if_is_dashboard') && ame_if_is_dashboard()): ?>
    <link rel="stylesheet" href="/assets/css/inventory-first-dash.css?v=<?php echo htmlspecialchars($sys_version ?? '10.95', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php
    $ame_th = $theme ?? ame_theme_defaults();
    $ame_navy = htmlspecialchars($ame_th['header_color'] ?? $ame_th['primary_color'], ENT_QUOTES, 'UTF-8');
    $ame_primary = htmlspecialchars($ame_th['primary_color'] ?? $ame_navy, ENT_QUOTES, 'UTF-8');
    $ame_accent = htmlspecialchars($ame_th['accent_color'] ?? '#0091EA', ENT_QUOTES, 'UTF-8');
    $ame_gold = htmlspecialchars($ame_th['gold_color'] ?? '#F5A623', ENT_QUOTES, 'UTF-8');
    $ame_success = htmlspecialchars($ame_th['secondary_color'] ?? '#0E8F52', ENT_QUOTES, 'UTF-8');
    $ame_bg = htmlspecialchars($ame_th['bg_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $ame_surface = htmlspecialchars($ame_th['surface_color'] ?? '#FFFFFF', ENT_QUOTES, 'UTF-8');
    $ame_text = htmlspecialchars($ame_th['text_color'] ?? '#111111', ENT_QUOTES, 'UTF-8');
    $ame_muted = htmlspecialchars($ame_th['muted_color'] ?? '#64748B', ENT_QUOTES, 'UTF-8');
    $ame_sidebar = htmlspecialchars($ame_th['sidebar_color'] ?? '#071534', ENT_QUOTES, 'UTF-8');
    $ame_border = htmlspecialchars($ame_th['border_color'] ?? '#CFE3F7', ENT_QUOTES, 'UTF-8');
    $ame_danger = htmlspecialchars($ame_th['danger_color'] ?? '#D32F2F', ENT_QUOTES, 'UTF-8');
    $ame_clock = htmlspecialchars(
        function_exists('ame_resolved_clock_color') ? ame_resolved_clock_color($ame_th) : ($ame_th['clock_color'] ?? '#2DD4BF'),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
    <style id="ame-live-theme">
        :root {
            --dark-blue: <?php echo $ame_navy; ?>;
            --brand-navy: <?php echo $ame_navy; ?>;
            --brand-navy-deep: <?php echo $ame_sidebar; ?>;
            --brand-accent: <?php echo $ame_accent; ?>;
            --brand-gold: <?php echo $ame_gold; ?>;
            --brand-success: <?php echo $ame_success; ?>;
            --dark-green: <?php echo $ame_success; ?>;
            --brand-bg: <?php echo $ame_bg; ?>;
            --brand-bg-soft: <?php echo $ame_surface; ?>;
            --brand-white: <?php echo $ame_surface; ?>;
            --brand-black: <?php echo $ame_text; ?>;
            --brand-navy-dark: <?php echo $ame_primary; ?>;
            --brand-muted: <?php echo $ame_muted; ?>;
            --brand-border: <?php echo $ame_border; ?>;
            --brand-sidebar: <?php echo $ame_sidebar; ?>;
            --brand-danger: <?php echo $ame_danger; ?>;
            --red-alert: <?php echo $ame_danger; ?>;
            --brand-surface-soft: <?php echo $ame_bg; ?>;
            --brand-success-soft: #E7F6EC;
            --brand-gold-soft: <?php echo $ame_surface; ?>;
            --hdr-clock: <?php echo $ame_clock; ?>;
            --brand-gradient: linear-gradient(135deg, <?php echo $ame_navy; ?>, <?php echo $ame_accent; ?>);
        }
        html, body { color: <?php echo $ame_text; ?>; background: <?php echo $ame_bg; ?>; }
    </style>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $ame_sale_fresh = str_ends_with(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/modules/sales/pos.php');
    if ($ame_sale_fresh):
    ?>
    <style id="ame-sale-fresh">
        html.ame-sale-wait body.ame-pos-terminal-page #main-content .ame-pos-page {
            visibility: hidden;
        }
    </style>
    <script>document.documentElement.classList.add('ame-sale-wait');</script>
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/device-fit.css?v=<?php echo $sys_version ?? '1.0'; ?>">
    <link rel="stylesheet" href="/assets/css/fulldisplay-fix.css?v=<?php echo $sys_version ?? '1.0'; ?>">
    <link rel="stylesheet" href="/assets/css/submit-spinner.css?v=<?php echo $sys_version ?? '1.0'; ?>">
    <!-- পারফরম্যান্স ফিক্স (v32): এই দুটো স্ক্রিপ্ট শুধু রেসপন্সিভ সাইজ মাপা ও
         ফর্ম-সাবমিট স্পিনার সেটআপ করে — পেজ পার্স হওয়ার আগে ব্লক করার দরকার
         নেই, তাই defer যোগ করা হলো (এতে প্রথম রেন্ডার দ্রুত হবে)। core-shield.js
         ইচ্ছাকৃতভাবে defer করা হয়নি — নিচের ইনলাইন স্ক্রিপ্টের সাথে লোড-অর্ডার
         সংঘর্ষ এড়াতে (openNav() ফাংশন দুই জায়গায় সংজ্ঞায়িত, ক্রম বদলালে
         সাইডবার মেনু বাটন ভেঙে যেতে পারত)। -->
    <script src="/assets/js/device-display.js?v=<?php echo $sys_version ?? '1.0'; ?>" defer></script>
    <script src="/assets/js/core-shield.js?v=<?php echo $sys_version ?? '1.0'; ?>"></script>
    <script src="/assets/js/double-submit-guard.js?v=<?php echo $sys_version ?? '1.0'; ?>" defer></script>

    <?php include __DIR__ . '/sidebar-style.php'; ?>

    <!-- ⭐ PWA সাপোর্ট (Add to Home Screen / অ্যাপের মতো আচরণ) — প্রতিটি দোকানের জন্য
         ডাইনামিক: নাম ও আইকন settings টেবিল থেকে আসে, manifest.php দেখুন -->
    <link rel="manifest" href="/manifest.php">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="application-name" content="<?php echo htmlspecialchars(shop_name(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(shop_short_name(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars(shop_app_icon_url(192), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" href="/assets/css/app-shell.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    
    <link rel="stylesheet" href="/assets/css/master-header.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/premium-saas.css?v=<?php echo htmlspecialchars($sys_version ?? '10.87.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/pos-skins.css?v=<?php echo htmlspecialchars($sys_version ?? '10.94.0', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="/assets/js/premium-saas.710.js?v=<?php echo htmlspecialchars($sys_version ?? '10.87.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php if (function_exists('ame_overlay_head_tags')) { ame_overlay_head_tags(); } ?>
    <link rel="stylesheet" href="/assets/css/hdr-icons.css?v=<?php echo htmlspecialchars($sys_version ?? '10.94.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/sidebar-icons.css?v=<?php echo htmlspecialchars($sys_version ?? '10.94.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/sidebar-menu-label-colors.css?v=<?php echo htmlspecialchars($sys_version ?? '10.94.0', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="/assets/js/sidebar-label-colors.js?v=<?php echo htmlspecialchars($sys_version ?? '10.94.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <link rel="stylesheet" href="/assets/css/ame-confirm.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="/assets/js/ame-confirm.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php
    $ame_head_script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_contains($ame_head_script, '/modules/hrm/') && !str_contains($ame_head_script, '/modules/hrm/employee_list.php')):
    ?>
    <link rel="stylesheet" href="/assets/css/hrm-cream.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/purchase/') && !str_contains($ame_head_script, '/modules/purchase/add.php')): ?>
    <link rel="stylesheet" href="/assets/css/purchase-cream.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php
    $ame_users_css_script = strtolower(basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''))));
    if (in_array($ame_users_css_script, ['users.php', 'user_add.php', 'user_view.php', 'user_reset.php', 'reset_password.php', 'role_permissions.php', 'super_admin.php'], true)):
    ?>
    <link rel="stylesheet" href="/assets/css/users-cream.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/sales/pos.php')): ?>
    <link rel="stylesheet" href="/assets/css/sale-cream.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/reports/sales_report.php')): ?>
    <link rel="stylesheet" href="/assets/css/sales-report-cream.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/reports/invoice_edit.php')): ?>
    <link rel="stylesheet" href="/assets/css/sales-pages.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/sales/history.php')): ?>
    <link rel="stylesheet" href="/assets/css/sales-pages.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/customers/withdraw.php')): ?>
    <link rel="stylesheet" href="/assets/css/withdraw-desk.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (str_contains($ame_head_script, '/modules/returns/v2/view.php')): ?>
    <link rel="stylesheet" href="/assets/css/memo-clear.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/sale-return-memo.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
  <script>
  (function () {
    document.documentElement.setAttribute('data-ame-memo-standalone', '1');
    var m = document.querySelector('meta[name="viewport"]');
    if (m) {
      m.setAttribute('content', 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover');
    }
  })();
  </script>
    <?php endif; ?>
    <?php
    if (!empty($_SESSION['user_id']) && is_file(__DIR__ . '/../includes/sm40_portal.php')) {
        require_once __DIR__ . '/../includes/sm40_portal.php';
        if (function_exists('ame_sm40_global_dock_assets')) {
            ame_sm40_global_dock_assets();
        }
    }
    ?>
    <!-- রেসপন্সিভ ফিট: responsive-fix.js (ফুটার / config boot) -->
</head>
<body<?php
    $ame_body_class = trim((string)($ame_body_class ?? ''));
    $ame_current_script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $ame_body_class = trim('ame-premium-ui ' . $ame_body_class);
    if (!empty($_SESSION['user_id'])) {
        $ame_body_class = trim($ame_body_class . ' ame-user-logged-in');
    }
    if (str_contains($ame_body_class, 'ame-purchase-desk') || str_contains($ame_body_class, 'ame-pos-terminal-page')) {
        $ame_body_class = trim($ame_body_class . ' ame-master-shell');
    }
    if (str_contains($ame_current_script, '/modules/returns/')) {
        $ame_body_class = trim($ame_body_class . ' ame-return-desk');
    }
    if (str_contains($ame_current_script, '/modules/returns/v2/view.php')) {
        echo ' data-ame-memo-standalone="1"';
    }
    if (str_contains($ame_current_script, '/modules/hrm/')) {
        $ame_body_class = trim($ame_body_class . ' ame-hrm-page');
        if (str_contains($ame_current_script, '/modules/hrm/employee_list.php')) {
            $ame_body_class = trim($ame_body_class . ' ame-emp-hub');
        }
    }
    if (str_contains($ame_current_script, '/modules/reports/sales_report.php')) {
        $ame_body_class = trim($ame_body_class . ' ame-srep-page');
    }
    if (str_contains($ame_current_script, '/modules/sales/history.php')) {
        $ame_body_class = trim($ame_body_class . ' ame-sales-history-page');
    }
    if (str_contains($ame_current_script, '/modules/customers/withdraw.php')) {
        $ame_body_class = trim($ame_body_class . ' ame-withdraw-page');
    }
    if (str_contains($ame_current_script, '/modules/suppliers/pay.php')
        || str_contains($ame_current_script, '/modules/suppliers/payments-list.php')
        || str_contains($ame_current_script, '/modules/suppliers/receipt.php')) {
        $ame_body_class = trim($ame_body_class . ' ame-spay-page');
    }
    if (str_contains($ame_current_script, '/modules/purchase/') && !str_contains($ame_current_script, '/modules/purchase/add.php')) {
        $ame_body_class = trim($ame_body_class . ' ame-purchase-cream');
    }
    if (in_array(basename($ame_current_script), ['users.php', 'user_add.php', 'user_view.php', 'user_reset.php', 'reset_password.php', 'role_permissions.php', 'super_admin.php'], true)) {
        $ame_body_class = trim($ame_body_class . ' ame-users-page');
    }
    if (str_contains($ame_current_script, '/modules/bloan/')) {
        $ame_body_class = trim($ame_body_class . ' ame-bloan-page');
    }
    if (function_exists('ame_if_is_home_page') && ame_if_is_home_page()) {
        $ame_body_class = trim($ame_body_class . ' ame-home-gallery');
    }
    if (function_exists('ame_if_is_dashboard') && ame_if_is_dashboard()) {
        $ame_body_class = trim($ame_body_class . ' ame-if-dash');
    }
    echo $ame_body_class !== ''
        ? ' class="' . htmlspecialchars($ame_body_class, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $GLOBALS['ame_body_class'] = $ame_body_class;
    $vd_page_attr = function_exists('vd_page_key') ? vd_page_key() : 'home';
    echo ' data-vd-page="' . htmlspecialchars($vd_page_attr, ENT_QUOTES, 'UTF-8') . '"';
?>>

<!-- সাইডবার মেনু -->
<?php include __DIR__ . '/sidebar.php'; ?>
<?php include __DIR__ . '/sidebar-script.php'; ?>

<!-- ১০০% দৃশ্যমান মাস্টার হেডার -->
<style id="ameHdrBadgeLock">
html body .master-header-fixed{overflow:visible!important}
html body .master-header-fixed .hdr-icon-btn .hdr-badge{
position:absolute!important;top:-3px!important;right:-3px!important;z-index:30!important;
min-width:18px!important;height:18px!important;padding:0 4px!important;border-radius:999px!important;
border:2px solid #fff!important;background:#b91c1c!important;color:#fff!important;-webkit-text-fill-color:#fff!important;
font-size:11px!important;font-weight:900!important;line-height:14px!important;
display:inline-flex!important;align-items:center!important;justify-content:center!important;
opacity:1!important;visibility:visible!important;overflow:visible!important}
html body .master-header-fixed .hdr-icon-btn.hdr-i-duty .hdr-badge{background:#166534!important}
html body .master-header-fixed .hdr-icon-btn.hdr-i-stock .hdr-badge,
html body .master-header-fixed .hdr-icon-btn .hdr-badge.hdr-badge-stock{background:#1d4ed8!important}
</style>
<header class="master-header-fixed" data-vd="header">
    <div class="hdr-btn-group">
        <button type="button" class="hdr-icon-btn hdr-i-menu" onclick="openNav()" title="<?php echo te('open_menu'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4.2" y="5.6" width="15.6" height="2.6" rx="1.3" fill="currentColor"/><rect x="4.2" y="10.7" width="10.4" height="2.6" rx="1.3" fill="currentColor"/><rect x="4.2" y="15.8" width="13.2" height="2.6" rx="1.3" fill="currentColor"/></svg>
        </button>

        <?php
        $ame_hdr_sm40 = false;
        $ame_hdr_sm40_url = '';
        if (is_file(__DIR__ . '/../includes/sm40_portal.php')) {
            require_once __DIR__ . '/../includes/sm40_portal.php';
            if (function_exists('ame_sm40_show_home_chip') && ame_sm40_show_home_chip()) {
                $ame_hdr_sm40 = true;
                $ame_hdr_sm40_url = function_exists('ame_sm40_entry_url') ? ame_sm40_entry_url() : 'https://sm40.com/welcome?lang=bengali';
                if (function_exists('ame_sm40_home_chip_assets')) {
                    ame_sm40_home_chip_assets();
                } elseif (function_exists('ame_sm40_home_chip_css')) {
                    ame_sm40_home_chip_css();
                }
            }
        }
        ?>
        <button type="button" class="hdr-icon-btn hdr-i-home" onclick="window.location.href='/index.php'" title="<?php echo te('go_home'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.2 3.6 10.4h2.2V20h5.1v-5.4h2.2V20h5.1v-9.6h2.2L12 3.2z"/></svg>
        </button>
        <?php if ($ame_hdr_sm40): ?>
        <a
            id="ameSm40HomeLink"
            href="<?php echo htmlspecialchars($ame_hdr_sm40_url, ENT_QUOTES, 'UTF-8'); ?>"
            class="hdr-icon-btn hdr-i-sm40 hdr-i-sm40--digital-life"
            data-sm40-url="<?php echo htmlspecialchars($ame_hdr_sm40_url, ENT_QUOTES, 'UTF-8'); ?>"
            target="_blank"
            rel="noopener noreferrer"
            title="SM40 — আলাদা উইন্ডো/ট্যাবে খুলবে; অডিও চালু রেখে বিক্রয়·পেমেন্ট পেজে কাজ করুন"
        >
            <span class="hdr-i-sm40-life-dot" aria-hidden="true"></span>
            <span class="hdr-i-sm40-label" aria-hidden="true">SM40</span>
        </a>
        <?php endif; ?>

        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="/modules/returns/index.php" class="hdr-icon-btn hdr-i-return" title="<?php echo current_lang() === 'en' ? 'Sales return' : 'বিক্রয় ফেরত'; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7H4.5v2.5M17 17h2.5V14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8.8A7.2 7.2 0 0 1 18.4 9.2M19 15.2A7.2 7.2 0 0 1 5.6 14.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <?php endif; ?>

        <?php if ($is_admin_hdr || has_permission($db, 'm-sales')): ?>
        <a href="/modules/sales/pos.php" class="hdr-icon-btn hdr-i-sales" title="<?php echo te('sales_title'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.4 7.4h11.8l-1.1 8.4a1.8 1.8 0 0 1-1.8 1.5H9.4A1.8 1.8 0 0 1 7.6 17L6 4.8H3.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10.2" cy="20.2" r="1.15" fill="currentColor"/><circle cx="16.6" cy="20.2" r="1.15" fill="currentColor"/></svg>
        </a>
        <?php if (!empty($ame_body_class) && str_contains((string)$ame_body_class, 'ame-pos-terminal-page')): ?>
        <button type="button" class="hdr-icon-btn hdr-i-keys" id="ameSaleKeysBtn" title="<?php echo current_lang() === 'en' ? 'Keyboard on the sales page' : 'বিক্রয় পাতার কীবোর্ড'; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M7 10h.01M11 10h.01M15 10h.01M8 14h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <?php endif; ?>
        <?php endif; ?>

        <?php
        $ame_duty_en = current_lang() === 'en';
        $ame_duty_roster = [];
        if (isset($db) && $db instanceof mysqli && !empty($_SESSION['user_id'])) {
            if (is_file(__DIR__ . '/../includes/staff_duty.php')) {
                require_once __DIR__ . '/../includes/attendance_functions.php';
                require_once __DIR__ . '/../includes/staff_duty.php';
                if (function_exists('ame_staff_duty_roster')) {
                    $ame_duty_roster = ame_staff_duty_roster($db);
                }
            }
        }
        $ame_duty_off = 0;
        foreach ($ame_duty_roster as $ame_duty_row) {
            if (empty($ame_duty_row['present'])) {
                $ame_duty_off++;
            }
        }
        $ame_duty_admin = function_exists('ame_staff_duty_actor_is_admin')
            ? ame_staff_duty_actor_is_admin()
            : (function_exists('ame_worker_can_manage') ? ame_worker_can_manage() : $is_admin_hdr);
        $ame_duty_self_emp = (isset($db) && $db instanceof mysqli && function_exists('ame_staff_duty_actor_employee_id'))
            ? ame_staff_duty_actor_employee_id($db)
            : (int) ($_SESSION['employee_id'] ?? 0);
        $ame_duty_self_user = function_exists('ame_staff_duty_actor_user_id')
            ? ame_staff_duty_actor_user_id()
            : (int) ($_SESSION['user_id'] ?? 0);
        $ame_duty_self_on = false;
        foreach ($ame_duty_roster as $ame_duty_row) {
            if (empty($ame_duty_row['present'])) {
                continue;
            }
            $ame_row_emp = (int) ($ame_duty_row['employee_id'] ?? 0);
            $ame_row_user = (int) ($ame_duty_row['user_id'] ?? 0);
            if (($ame_duty_self_emp > 0 && $ame_row_emp === $ame_duty_self_emp)
                || ($ame_duty_self_user > 0 && $ame_row_user === $ame_duty_self_user)) {
                $ame_duty_self_on = true;
                break;
            }
        }
        $ame_duty_can_others = $ame_duty_admin || $ame_duty_self_on;
        $ame_duty_can = $ame_duty_can_others;
        ?>
        <button type="button" class="hdr-icon-btn hdr-i-duty<?php echo $ame_duty_off > 0 ? ' has-alert' : ''; ?>" id="ameDutyBtn" title="<?php echo $ame_duty_en ? 'Daily duty — activate a worker or engineer' : 'আজকের ডিউটি — কর্মী বা ইঞ্জিনিয়ার Active করুন'; ?>" aria-expanded="false" aria-controls="ameDutyPanel">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="7.2" fill="currentColor"/><circle cx="12" cy="12" r="3.1" fill="#166534"/></svg>
            <?php if ($ame_duty_off > 0): ?>
            <span class="hdr-badge"><?php echo $ame_duty_off > 99 ? '99+' : (int)$ame_duty_off; ?></span>
            <?php endif; ?>
        </button>

        <?php if ($is_admin_hdr || has_permission($db, 'm-customers')): ?>
        <a href="/modules/customers/add_customer.php" class="hdr-icon-btn hdr-i-custadd" title="<?php echo te('hdr_add_customer'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10" cy="9" r="3.2" stroke="currentColor" stroke-width="2"/><path d="M4.4 19c.8-3 3-4.6 5.6-4.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.6 8.4v6.4M13.4 11.6h6.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <a href="/modules/customers/due-reminder.php" class="hdr-icon-btn hdr-i-bell<?php echo ((int)$ame_due_notification_count > 0) ? ' has-alert' : ''; ?>" title="<?php echo te('hdr_pay_note'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.6 9.2a5.4 5.4 0 0 1 10.8 0c0 3.2 1.2 4.4 1.2 4.4H5.4s1.2-1.2 1.2-4.4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 18.4a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <?php if ((int)$ame_due_notification_count > 0): ?>
            <span class="hdr-badge"><?php echo ((int)$ame_due_notification_count > 99) ? '99+' : (int)$ame_due_notification_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="/modules/customers/payment-receive.php" class="hdr-icon-btn hdr-i-pay" title="<?php echo te('take_payment'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.4" y="6.2" width="13.4" height="8.4" rx="1.6" stroke="currentColor" stroke-width="2"/><rect x="7.2" y="10.2" width="13.2" height="7.2" rx="1.4" stroke="currentColor" stroke-width="2"/><path d="M10.4 13.8h4.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <?php endif; ?>

        <?php if ($is_admin_hdr || has_permission($db, 'm-acc') || has_permission($db, 'm-transactions') || has_permission($db, 'm-sales-report')): ?>
        <a href="/modules/accounts/daily_audit.php" class="hdr-icon-btn hdr-i-audit" title="<?php echo te('nav_daily_audit'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="3.6" width="16" height="16.8" rx="2.2" stroke="currentColor" stroke-width="2"/><path d="M8 9.2h8M8 12.6h8M8 16h5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($is_admin_hdr || has_permission($db, 'm-acc')): ?>
        <a href="/modules/accounts/add_transaction.php" class="hdr-icon-btn hdr-i-txn" title="<?php echo te('hdr_general_txn'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7h11l-2.6-2.6M17 17H6l2.6 2.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <?php endif; ?>

        <?php if ($is_admin_hdr || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')): ?>
        <a href="/modules/reports/live_work.php" class="hdr-icon-btn hdr-i-live" title="<?php echo current_lang() === 'en' ? 'Live dashboard' : 'লাইভ ড্যাশবোর্ড'; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3.1" fill="currentColor"/><path d="M5.2 12a6.8 6.8 0 0 1 13.6 0M2.6 12a9.4 9.4 0 0 1 18.8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <?php endif; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?php echo !empty($ame_duty_can) ? '/modules/hrm/employee_list.php' : '/modules/hrm/worker_ledger.php'; ?>" class="hdr-icon-btn hdr-i-staffpay" title="<?php echo current_lang() === 'en' ? 'Staff' : 'কর্মী'; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="7.6" r="2.6" stroke="currentColor" stroke-width="2"/><path d="M3.8 18.6c0-3 2.3-5.2 5.2-5.2s5.2 2.2 5.2 5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.6 8.6v6.4M14 11.8h5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
        <?php endif; ?>

        <?php if ($ame_stock_alert_can_view || !empty($_SESSION['user_id'])): ?>
        <span class="hdr-stock-wrap">
        <?php if ($ame_stock_alert_can_view): ?>
        <a href="/modules/inventory/restock_invoice.php" class="hdr-icon-btn hdr-i-stock<?php echo ((int)$ame_stock_alert_count > 0) ? ' has-alert' : ''; ?>" title="<?php echo te('stock_alert'); ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.2 8.4 12 4.2l7.8 4.2M4.2 8.4v7.2L12 19.8l7.8-4.2V8.4M4.2 8.4 12 12.6l7.8-4.2M12 12.6v7.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php if ((int)$ame_stock_alert_count > 0): ?>
            <span class="hdr-badge hdr-badge-stock"><?php echo ((int)$ame_stock_alert_count > 99) ? '99+' : (int)$ame_stock_alert_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="hdr-stock-free<?php echo !empty($_SESSION['ame_sell_without_stock']) ? ' is-on' : ''; ?>" id="ameSellWithoutStock" aria-pressed="<?php echo !empty($_SESSION['ame_sell_without_stock']) ? 'true' : 'false'; ?>" title="<?php echo current_lang() === 'en' ? 'Sell even when stock is zero. Press again to turn it off.' : 'চালু করলে স্টক না থাকলেও বিক্রি হবে। আবার চাপলে বন্ধ।'; ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 12.5 10.2 16 17 8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <?php endif; ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- ব্র্যান্ড PNG -->
    <div class="hdr-brand-box">
        <img class="hdr-brand-png" src="/assets/img/header-logo.png?v=<?php echo htmlspecialchars((string)($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" width="80" height="48" alt="<?php echo htmlspecialchars($shop['shop_name'] ?? 'Aponshop', ENT_QUOTES, 'UTF-8'); ?>">
        <h1><?php echo htmlspecialchars($shop['shop_name'] ?? 'Aponshop', ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <!-- লাইভ ঘড়ি: বার + তারিখ/মাস/বছর + সময়; ক্লিক করলে এডিট -->
    <div class="hdr-clock" id="hdr-live-clock" data-vd="clock" role="button" tabindex="0" title="<?php echo te('vd_clock_title'); ?>">
        <small id="hdr-clock-date">--</small>
        <span id="hdr-clock-time">--:--:--</span>
    </div>
</header>

<div id="ameDutyPanel" hidden>
    <div class="ame-duty-card" role="dialog" aria-modal="true" aria-labelledby="ameDutyTitle">
        <button type="button" class="ame-duty-x" id="ameDutyClose" aria-label="<?php echo $ame_duty_en ? 'Close' : 'বন্ধ'; ?>">×</button>
        <h2 id="ameDutyTitle"><?php echo $ame_duty_en ? 'Daily duty' : 'আজকের ডিউটি'; ?></h2>
        <p class="ame-duty-hint"><?php echo $ame_duty_en
            ? 'The user who is ON today can turn other unique users ON. If you are off, check yourself in first. At 12:00 AM Bangladesh time everyone goes off duty. Manager and Super Admin turn on automatically at 9:00 AM and can be turned off manually.'
            : 'যে ইউজার আজ ডিউটিতে অন আছে, সে অন্য ইউজারদেরও অন করতে পারবে। নিজে অন না থাকলে আগে নিজের নামে এন্ট্রি দিন। রাত ১২টায় সবাই ছুটি হয়ে যায়। ম্যানেজার ও সুপার অ্যাডমিন সকাল ৯টায় নিজে নিজে অন হয়। ইচ্ছে করলে অফ করা যাবে।'; ?></p>
        <p class="ame-duty-empty" id="ameDutyErr" hidden></p>
        <?php if (!$ame_duty_roster): ?>
        <p class="ame-duty-empty"><?php echo $ame_duty_en ? 'No staff found.' : 'কোন কর্মী পাওয়া যায়নি।'; ?></p>
        <?php else: ?>
        <ul class="ame-duty-list" id="ameDutyList">
            <?php foreach ($ame_duty_roster as $ame_duty_row):
                $ame_row_emp = (int) ($ame_duty_row['employee_id'] ?? 0);
                $ame_row_user = (int) ($ame_duty_row['user_id'] ?? 0);
                $ame_row_on = !empty($ame_duty_row['present']);
                $ame_row_self = ($ame_duty_self_emp > 0 && $ame_row_emp === $ame_duty_self_emp)
                    || ($ame_duty_self_user > 0 && $ame_row_user === $ame_duty_self_user);
                $ame_row_can = !$ame_row_on && ($ame_duty_can_others || $ame_row_self);
                ?>
            <li class="ame-duty-row<?php echo $ame_row_on ? ' on' : ''; ?><?php echo $ame_row_self ? ' is-self' : ''; ?>" data-eng="<?php echo (int) $ame_duty_row['id']; ?>" data-emp="<?php echo $ame_row_emp; ?>" data-user="<?php echo $ame_row_user; ?>" data-can-wake="<?php echo $ame_row_can ? '1' : '0'; ?>">
                <span>
                    <strong><?php echo htmlspecialchars((string) $ame_duty_row['name'], ENT_QUOTES, 'UTF-8'); ?><?php echo $ame_row_self ? ($ame_duty_en ? ' (you)' : ' (আপনি)') : ''; ?></strong>
                    <small><?php
                        $ame_duty_meta = [];
                        if (trim((string) ($ame_duty_row['emp_code'] ?? '')) !== '') {
                            $ame_duty_meta[] = ($ame_duty_en ? 'ID ' : 'আইডি ') . $ame_duty_row['emp_code'];
                        }
                        $ame_duty_meta[] = '#' . ((int) $ame_duty_row['id'] > 0 ? (int) $ame_duty_row['id'] : ($ame_row_emp > 0 ? $ame_row_emp : $ame_row_user));
                        if ($ame_row_on) {
                            $ame_duty_meta[] = $ame_duty_en ? 'Active today' : 'আজ Active';
                        } elseif ($ame_row_can) {
                            $ame_duty_meta[] = $ame_duty_en ? 'Tap to activate' : 'চাপলে Active';
                        } else {
                            $ame_duty_meta[] = $ame_duty_en ? 'ON user can activate' : 'অন ইউজার অন করবে';
                        }
                        echo htmlspecialchars(implode(' · ', $ame_duty_meta), ENT_QUOTES, 'UTF-8');
                    ?></small>
                </span>
                <span class="ame-duty-acts">
                    <button type="button" class="ame-duty-sleep" <?php echo ($ame_row_on && ($ame_duty_admin || $ame_row_self)) ? '' : 'hidden'; ?> title="<?php echo $ame_duty_en ? 'Turn off' : 'অফ'; ?>"><?php echo $ame_duty_en ? 'Off' : 'অফ'; ?></button>
                    <button type="button" class="ame-duty-wake" <?php echo $ame_row_can ? '' : 'disabled'; ?> title="<?php echo $ame_row_on ? ($ame_duty_en ? 'Active today' : 'আজ Active') : ($ame_row_can ? ($ame_duty_en ? 'Check in' : 'এন্ট্রি') : ($ame_duty_en ? 'Only an ON user can activate others' : 'যে অন আছে সে অন্যকে অন করবে')); ?>"><?php echo $ame_row_on ? '🟢' : '⚪'; ?></button>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('ameDutyBtn');
    var panel = document.getElementById('ameDutyPanel');
    if (!btn || !panel) return;
    var closeBtn = document.getElementById('ameDutyClose');
    var csrf = (typeof window.AME_CSRF_TOKEN === 'string') ? window.AME_CSRF_TOKEN : '';
    var en = <?php echo $ame_duty_en ? 'true' : 'false'; ?>;
    var dutyCanOthers = <?php echo !empty($ame_duty_can_others) ? 'true' : 'false'; ?>;
    var dutyAdmin = <?php echo !empty($ame_duty_admin) ? 'true' : 'false'; ?>;
    var selfEmp = <?php echo (int) $ame_duty_self_emp; ?>;
    var selfUser = <?php echo (int) $ame_duty_self_user; ?>;
    function openDuty() {
        panel.hidden = false;
        panel.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
    }
    function closeDuty() {
        panel.hidden = true;
        panel.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (panel.classList.contains('is-open')) closeDuty();
        else openDuty();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeDuty);
    document.addEventListener('click', function (ev) {
        if (!panel.classList.contains('is-open')) return;
        if (panel.contains(ev.target) || btn.contains(ev.target)) return;
        closeDuty();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeDuty();
    });
    window.ameOpenDutyPanel = openDuty;
    function rowIsSelf(row) {
        if (!row) return false;
        var emp = Number(row.getAttribute('data-emp') || 0);
        var user = Number(row.getAttribute('data-user') || 0);
        return (selfEmp > 0 && emp === selfEmp) || (selfUser > 0 && user === selfUser);
    }
    function rowCanWake(row) {
        if (!row || row.classList.contains('on')) return false;
        return dutyCanOthers || rowIsSelf(row) || row.getAttribute('data-can-wake') === '1';
    }
    function unlockOthers() {
        dutyCanOthers = true;
        Array.prototype.forEach.call(panel.querySelectorAll('.ame-duty-row:not(.on)'), function (row) {
            row.setAttribute('data-can-wake', '1');
            var b = row.querySelector('.ame-duty-wake');
            if (b) {
                b.disabled = false;
                b.title = en ? 'Check in' : 'এন্ট্রি';
            }
            var small = row.querySelector('small');
            if (small) {
                small.textContent = small.textContent.replace(en ? 'ON user can activate' : 'অন ইউজার অন করবে', en ? 'Tap to activate' : 'চাপলে Active');
            }
        });
    }
    function showSleep(row, on) {
        if (!row) return;
        var sleep = row.querySelector('.ame-duty-sleep');
        if (!sleep) return;
        var allow = on && (dutyAdmin || rowIsSelf(row));
        sleep.hidden = !allow;
    }
    panel.addEventListener('click', function (ev) {
        var sleepBtn = ev.target.closest('.ame-duty-sleep');
        if (sleepBtn) {
            ev.preventDefault();
            ev.stopPropagation();
            var offRow = sleepBtn.closest('.ame-duty-row');
            if (!offRow) return;
            var offBody = new FormData();
            offBody.append('csrf_token', csrf);
            offBody.append('employee_id', offRow.getAttribute('data-emp') || '0');
            offBody.append('user_id', offRow.getAttribute('data-user') || '0');
            fetch('/modules/hrm/process_staff_sleep.php', {
                method: 'POST',
                body: offBody,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
            }).then(function (r) { return r.json().catch(function () { return { ok: false, err: '' }; }); }).then(function (data) {
                if (!data || !data.ok) {
                    var errBox = document.getElementById('ameDutyErr');
                    if (errBox) {
                        errBox.hidden = false;
                        errBox.textContent = (data && data.err) ? data.err : (en ? 'Could not turn off.' : 'অফ হয়নি। আবার চাপুন।');
                    }
                    return;
                }
                var errBox = document.getElementById('ameDutyErr');
                if (errBox) errBox.hidden = true;
                offRow.classList.remove('on');
                var wakeBtn = offRow.querySelector('.ame-duty-wake');
                if (wakeBtn) {
                    wakeBtn.textContent = '⚪';
                    wakeBtn.disabled = !(dutyAdmin || rowIsSelf(offRow));
                    wakeBtn.title = en ? 'Check in' : 'এন্ট্রি';
                }
                showSleep(offRow, false);
                offRow.setAttribute('data-can-wake', wakeBtn && !wakeBtn.disabled ? '1' : '0');
            }).catch(function () {});
            return;
        }
        var rowHit = ev.target.closest('.ame-duty-row');
        var wake = ev.target.closest('.ame-duty-wake');
        if (!wake && rowHit && rowCanWake(rowHit)) {
            wake = rowHit.querySelector('.ame-duty-wake');
        }
        var row = wake ? wake.closest('.ame-duty-row') : null;
        if (!wake || !row || wake.disabled || !rowCanWake(row)) return;
        ev.preventDefault();
        if (row.classList.contains('on')) return;
        var body = new FormData();
        body.append('csrf_token', csrf);
        body.append('engineer_id', row.getAttribute('data-eng') || '0');
        body.append('employee_id', row.getAttribute('data-emp') || '0');
        body.append('user_id', row.getAttribute('data-user') || '0');
        body.append('source', 'header_icon');
        fetch('/modules/hrm/process_staff_wake.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
        }).then(function (r) { return r.json().catch(function () { return { ok: false, err: '' }; }); }).then(function (data) {
            if (!data || !data.ok) {
                var errBox = document.getElementById('ameDutyErr');
                if (errBox) {
                    errBox.hidden = false;
                    errBox.textContent = (data && data.err) ? data.err : (en ? 'Check-in did not save.' : 'ডিউটিতে এন্ট্রি হয়নি। আবার চাপুন।');
                }
                return;
            }
            var errBox = document.getElementById('ameDutyErr');
            if (errBox) errBox.hidden = true;
            if (data.employee_id) {
                row.setAttribute('data-emp', String(data.employee_id));
                if (rowIsSelf(row) && Number(data.employee_id) > 0) {
                    selfEmp = Number(data.employee_id);
                }
            }
            row.classList.add('on');
            row.setAttribute('data-can-wake', '0');
            wake.textContent = '🟢';
            wake.disabled = true;
            wake.title = en ? 'Active today' : 'আজ Active';
            showSleep(row, true);
            var small = row.querySelector('small');
            if (small) {
                small.textContent = small.textContent.replace(en ? 'Tap to activate' : 'চাপলে Active', en ? 'Active today' : 'আজ Active');
            }
            if (rowIsSelf(row)) {
                unlockOthers();
            }
            var left = panel.querySelectorAll('.ame-duty-row:not(.on)').length;
            var badge = btn.querySelector('.hdr-badge');
            if (left <= 0) {
                btn.classList.remove('has-alert');
                if (badge) badge.remove();
            } else if (badge) {
                badge.textContent = left > 99 ? '99+' : String(left);
            }
            document.dispatchEvent(new CustomEvent('ame-staff-woke', {
                detail: {
                    id: Number(row.getAttribute('data-eng') || 0),
                    employee_id: Number(row.getAttribute('data-emp') || 0),
                    activated_at: data.activated_at || ''
                }
            }));
        }).catch(function () {});
    });
})();
</script>

<?php if (!empty($ame_body_class) && str_contains((string)$ame_body_class, 'ame-pos-terminal-page')): ?>
<?php $ame_keys_en = current_lang() === 'en'; ?>
<div id="ameSaleKeys" class="ame-sale-keys" hidden>
    <div class="ame-sale-keys-card" role="dialog" aria-modal="true" aria-labelledby="ameSaleKeysTitle">
        <button type="button" class="ame-sale-keys-x" data-sale-keys-close aria-label="<?php echo $ame_keys_en ? 'Close' : 'বন্ধ'; ?>">×</button>
        <h2 id="ameSaleKeysTitle"><?php echo $ame_keys_en ? 'Sales page keyboard' : 'বিক্রয় পাতা কীবোর্ড দিয়ে'; ?></h2>
        <ul>
            <li><kbd>F2</kbd><span><?php echo $ame_keys_en ? 'Customer name' : 'গ্রাহকের নাম'; ?></span></li>
            <li><kbd>↑ ↓</kbd><span><?php echo $ame_keys_en ? 'Pick a saved customer or a product' : 'সেভ করা গ্রাহক বা পণ্য বেছে নিন'; ?></span></li>
            <li><kbd>F3</kbd><span><?php echo $ame_keys_en ? 'Find a product' : 'পণ্য খুঁজুন'; ?></span></li>
            <li><kbd>Enter</kbd><span><?php echo $ame_keys_en ? 'Add the product, or move to the next box' : 'পণ্য যোগ, নাহলে পরের ঘরে যান'; ?></span></li>
            <li><kbd>Alt+K</kbd><span><?php echo $ame_keys_en ? 'Retail price' : 'খুচরা দাম'; ?></span></li>
            <li><kbd>Alt+P</kbd><span><?php echo $ame_keys_en ? 'Wholesale price' : 'পাইকারি দাম'; ?></span></li>
            <li><kbd>Alt+D</kbd><span><?php echo $ame_keys_en ? 'Dealer price' : 'ডিলার দাম'; ?></span></li>
            <li><kbd>F4</kbd><span><?php echo $ame_keys_en ? 'Engineer and repair' : 'ইঞ্জিনিয়ার ও মেরামত'; ?></span></li>
            <li><kbd>F6</kbd><span><?php echo $ame_keys_en ? 'Add a new product' : 'নতুন পণ্য যোগ'; ?></span></li>
            <li><kbd>F7</kbd><span><?php echo $ame_keys_en ? 'Discount' : 'ছাড়'; ?></span></li>
            <li><kbd>F8</kbd><span><?php echo $ame_keys_en ? 'Payment account' : 'পেমেন্টের অ্যাকাউন্ট'; ?></span></li>
            <li><kbd>Alt+2</kbd><span><?php echo $ame_keys_en ? 'Open or close the second account' : 'দ্বিতীয় অ্যাকাউন্ট খুলুন বা বন্ধ করুন'; ?></span></li>
            <li><kbd>▸</kbd><span><?php echo $ame_keys_en ? 'The arrow inside the payment box does the same' : 'পেমেন্ট বক্সের ভিতরের তীরও একই কাজ করে'; ?></span></li>
            <li><kbd>F9</kbd><span><?php echo $ame_keys_en ? 'Complete the sale' : 'বিক্রয় সম্পন্ন'; ?></span></li>
            <li><kbd>Ctrl+Enter</kbd><span><?php echo $ame_keys_en ? 'Complete the sale' : 'বিক্রয় সম্পন্ন'; ?></span></li>
            <li><kbd>Esc</kbd><span><?php echo $ame_keys_en ? 'Close the open box' : 'খোলা বক্স বন্ধ'; ?></span></li>
        </ul>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('ameSaleKeys');
    var btn = document.getElementById('ameSaleKeysBtn');
    if (!box || !btn) {
        return;
    }
    function openKeys() {
        box.hidden = false;
    }
    function closeKeys() {
        box.hidden = true;
    }
    window.ameOpenSaleKeys = openKeys;
    window.ameCloseSaleKeys = closeKeys;
    btn.addEventListener('click', function (event) {
        event.preventDefault();
        if (box.hidden) {
            openKeys();
        } else {
            closeKeys();
        }
    });
    box.addEventListener('click', function (event) {
        if (event.target === box || (event.target.closest && event.target.closest('[data-sale-keys-close]'))) {
            closeKeys();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (box.hidden) {
            return;
        }
        if (event.key === 'Escape' || /^F\d+$/.test(event.key) || event.altKey) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (event.key === 'Escape') {
                closeKeys();
            }
        }
    });
})();
</script>
<?php endif; ?>

<div id="ameClockEdit" hidden style="display:none;position:fixed;inset:0;z-index:4000000;align-items:center;justify-content:center;background:rgba(15,23,42,.55);">
    <form id="ameClockEditBox" style="width:min(420px,94vw);background:#fff;color:#0B1E4D;border-radius:14px;padding:18px 16px;box-shadow:0 18px 50px rgba(0,0,0,.28);font-weight:700;max-height:92vh;overflow:auto;">
        <h3 style="margin:0 0 6px;font-size:17px;"><?php echo te('vd_clock_title'); ?></h3>
        <p style="margin:0 0 10px;font-size:13px;color:#64748B;font-weight:600;"><?php echo current_lang() === 'en' ? 'Choose what the header clock shows. Time still follows this phone or computer.' : 'হেডারের ঘড়িতে কী দেখাবে তা বেছে নিন। সময় এই ফোন বা কম্পিউটারের ঘড়ি অনুসারে চলবে।'; ?></p>
        <label style="display:flex;gap:8px;align-items:center;margin:10px 0;">
            <input type="checkbox" id="ameClockShowDate" checked>
            <?php echo current_lang() === 'en' ? 'Show date, month and year' : 'তারিখ, মাস ও বছর দেখাবে'; ?>
        </label>
        <label style="display:flex;gap:8px;align-items:center;margin:10px 0;">
            <input type="checkbox" id="ameClockShowWeekday" checked>
            <?php echo current_lang() === 'en' ? 'Show weekday' : 'বারের নাম দেখাবে (সোমবার, মঙ্গলবার…)'; ?>
        </label>
        <label style="display:flex;gap:8px;align-items:center;margin:10px 0;">
            <input type="checkbox" id="ameClockNumericDate">
            <?php echo current_lang() === 'en' ? 'Numeric date (07/09/2026)' : 'সংখ্যায় তারিখ (০৭/০৯/২০২৬)'; ?>
        </label>
        <label style="display:flex;gap:8px;align-items:center;margin:10px 0;">
            <input type="checkbox" id="ameClockHour12" checked>
            <?php echo current_lang() === 'en' ? '12-hour clock' : '১২ ঘণ্টার ঘড়ি'; ?>
        </label>
        <p style="margin:14px 0 6px;font-size:13px;color:#0B1E4D;"><?php echo te('vd_clock_note'); ?></p>
        <div class="ame-clock-swatches" id="ameClockSwatches" hidden style="display:none;">
            <?php
            $ame_clock_swatches = function_exists('ame_clock_presets') ? ame_clock_presets() : [['hex' => '#2DD4BF', 'bn' => 'টিল', 'en' => 'Teal']];
            foreach ($ame_clock_swatches as $sw):
                $hex = (string)$sw['hex'];
                $lab = current_lang() === 'en' ? ($sw['en'] ?? $hex) : ($sw['bn'] ?? $hex);
            ?>
            <button type="button" class="ame-clock-swatch" data-clock-color="<?php echo htmlspecialchars($hex, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>" style="width:28px;height:28px;border-radius:999px;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1;background:<?php echo htmlspecialchars($hex, ENT_QUOTES, 'UTF-8'); ?>;cursor:pointer;padding:0;"></button>
            <?php endforeach; ?>
        </div>
        <div id="ameClockSliders" hidden style="display:none;">
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700;">
                <span style="display:flex;justify-content:space-between;align-items:center;">
                    <?php echo current_lang() === 'en' ? 'Hue' : 'রং (হিউ)'; ?>
                    <span>
                        <button type="button" class="ame-clock-step" data-clock-axis="h" data-clock-delta="-12" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">−</button>
                        <button type="button" class="ame-clock-step" data-clock-axis="h" data-clock-delta="12" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">+</button>
                    </span>
                </span>
                <input type="range" id="ameClockHue" min="0" max="360" step="1" style="width:100%;accent-color:#0B1E4D;">
            </label>
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700;">
                <span style="display:flex;justify-content:space-between;align-items:center;">
                    <?php echo current_lang() === 'en' ? 'Colour amount' : 'কালার বেশি/কম'; ?>
                    <span>
                        <button type="button" class="ame-clock-step" data-clock-axis="s" data-clock-delta="-8" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">−</button>
                        <button type="button" class="ame-clock-step" data-clock-axis="s" data-clock-delta="8" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">+</button>
                    </span>
                </span>
                <input type="range" id="ameClockSat" min="0" max="100" step="1" style="width:100%;accent-color:#0B1E4D;">
            </label>
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700;">
                <span style="display:flex;justify-content:space-between;align-items:center;">
                    <?php echo current_lang() === 'en' ? 'Darker / brighter' : 'গাঢ় / উজ্জ্বল'; ?>
                    <span>
                        <button type="button" class="ame-clock-step" data-clock-axis="l" data-clock-delta="-6" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">−</button>
                        <button type="button" class="ame-clock-step" data-clock-axis="l" data-clock-delta="6" style="min-width:36px;min-height:36px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;">+</button>
                    </span>
                </span>
                <input type="range" id="ameClockLight" min="8" max="92" step="1" style="width:100%;accent-color:#0B1E4D;">
            </label>
        </div>
        <label hidden style="display:none;">
            <?php echo current_lang() === 'en' ? 'Hex' : 'হেক্স'; ?>
            <input type="text" id="ameClockHex" maxlength="7" spellcheck="false" inputmode="text" autocomplete="off" style="flex:1;min-width:0;height:36px;border:1px solid #c5ced8;border-radius:8px;padding:0 8px;font-weight:800;letter-spacing:.04em;">
            <input type="color" id="ameClockColor" value="<?php echo htmlspecialchars($ame_clock ?? '#2DD4BF', ENT_QUOTES, 'UTF-8'); ?>" style="width:46px;height:36px;border:0;background:transparent;cursor:pointer;">
        </label>
        <p id="ameClockPreview" style="margin:12px 0;padding:10px;background:#0B1E4D;color:var(--hdr-clock, #2DD4BF);border-radius:8px;text-align:center;font-weight:800;line-height:1.45;white-space:pre-line;"></p>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
            <button type="button" id="ameClockEditClose" style="min-height:38px;padding:8px 14px;border:1px solid #c5ced8;border-radius:8px;background:#fff;font-weight:800;cursor:pointer;"><?php echo current_lang() === 'en' ? 'Close' : 'বন্ধ'; ?></button>
            <button type="submit" style="min-height:38px;padding:8px 14px;border:0;border-radius:8px;background:#0B1E4D;color:#fff;font-weight:800;cursor:pointer;"><?php echo current_lang() === 'en' ? 'Save' : 'সেভ'; ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/display_bars.php'; ?>

<!-- মেইন কন্টেন্ট এরিয়া -->
<div id="main-content" class="ame-print-root">
<nav class="ds-crumb" aria-label="<?php echo te('ds_breadcrumb'); ?>">
    <a href="/index.php"><?php echo te('ds_home'); ?></a>
    <span class="ds-crumb-here" data-ds-crumb></span>
</nav>
<?php if (function_exists('ame_if_dash_welcome')) { ame_if_dash_welcome(); } ?>

<script>
    /* ১. সাইডবার টগল ফাংশন */
    function openNav() {
        const sidebar = document.getElementById("mySidebar");
        const backdrop = document.getElementById("sidebarBackdrop");
        if (sidebar) {
            const isActive = sidebar.classList.toggle("active");
            if (backdrop) {
                backdrop.classList.toggle("active", isActive);
            }
            document.body.style.overflow = isActive ? "hidden" : "";
        } else {
            alert(<?php echo json_encode(t('sidebar_missing'), JSON_UNESCAPED_UNICODE); ?>);
        }
    }

    /* ২. রিয়েল-টাইম ডিজিটাল ঘড়ি — বার, তারিখ, মাস, বছর + সময় */
    function ameClockPrefs() {
        try {
            return JSON.parse(localStorage.getItem('ameHdrClock') || '{}') || {};
        } catch (e) {
            return {};
        }
    }
    function ameClockSavePrefs(prefs) {
        try {
            localStorage.setItem('ameHdrClock', JSON.stringify(prefs));
        } catch (e) {}
    }
    function ameClockNormHex(hex) {
        hex = String(hex || '').trim();
        if (/^#[0-9a-fA-F]{3}$/.test(hex)) {
            hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
        }
        return /^#[0-9a-fA-F]{6}$/.test(hex) ? hex.toUpperCase() : '';
    }
    function ameClockHexToHsl(hex) {
        hex = ameClockNormHex(hex) || '#2DD4BF';
        var r = parseInt(hex.slice(1, 3), 16) / 255;
        var g = parseInt(hex.slice(3, 5), 16) / 255;
        var b = parseInt(hex.slice(5, 7), 16) / 255;
        var max = Math.max(r, g, b);
        var min = Math.min(r, g, b);
        var h = 0;
        var s = 0;
        var l = (max + min) / 2;
        var d = max - min;
        if (d > 0.0001) {
            s = d / (1 - Math.abs(2 * l - 1));
            if (max === r) {
                h = ((g - b) / d) % 6;
            } else if (max === g) {
                h = (b - r) / d + 2;
            } else {
                h = (r - g) / d + 4;
            }
            h *= 60;
            if (h < 0) {
                h += 360;
            }
        }
        return { h: h, s: s * 100, l: l * 100 };
    }
    function ameClockHslToHex(h, s, l) {
        h = ((Number(h) % 360) + 360) % 360;
        s = Math.max(0, Math.min(100, Number(s))) / 100;
        l = Math.max(0, Math.min(100, Number(l))) / 100;
        var c = (1 - Math.abs(2 * l - 1)) * s;
        var x = c * (1 - Math.abs((h / 60) % 2 - 1));
        var m = l - c / 2;
        var r = 0;
        var g = 0;
        var b = 0;
        if (h < 60) {
            r = c; g = x;
        } else if (h < 120) {
            r = x; g = c;
        } else if (h < 180) {
            g = c; b = x;
        } else if (h < 240) {
            g = x; b = c;
        } else if (h < 300) {
            r = x; b = c;
        } else {
            r = c; b = x;
        }
        function toHex(n) {
            var v = Math.round((n + m) * 255);
            var hx = v.toString(16);
            return hx.length === 1 ? '0' + hx : hx;
        }
        return ameClockNormHex('#' + toHex(r) + toHex(g) + toHex(b)) || '#2DD4BF';
    }
    function ameClockSyncSliders(hex) {
        var hsl = ameClockHexToHsl(hex);
        var hue = document.getElementById('ameClockHue');
        var sat = document.getElementById('ameClockSat');
        var light = document.getElementById('ameClockLight');
        var hexEl = document.getElementById('ameClockHex');
        if (hue) hue.value = String(Math.round(hsl.h));
        if (sat) sat.value = String(Math.round(hsl.s));
        if (light) light.value = String(Math.round(hsl.l));
        if (hexEl && hexEl.value.toUpperCase() !== hex) {
            hexEl.value = hex;
        }
    }
    function ameClockColorFromSliders() {
        var hue = document.getElementById('ameClockHue');
        var sat = document.getElementById('ameClockSat');
        var light = document.getElementById('ameClockLight');
        return ameClockHslToHex(
            hue ? hue.value : 168,
            sat ? sat.value : 70,
            light ? light.value : 50
        );
    }
    function ameClockSavedColor() {
        var root = getComputedStyle(document.documentElement).getPropertyValue('--hdr-clock');
        return ameClockNormHex(root) || '#111111';
    }
    window.ameApplyClockColor = function (hex, persist) {
        hex = ameClockNormHex(hex);
        if (!hex) {
            return;
        }
        document.documentElement.style.setProperty('--hdr-clock', hex);
        var clockEl = document.getElementById('hdr-live-clock');
        if (clockEl) {
            clockEl.style.setProperty('color', hex, 'important');
        }
        var dateEl = document.getElementById('hdr-clock-date');
        var timeEl = document.getElementById('hdr-clock-time');
        if (dateEl) dateEl.style.setProperty('color', hex, 'important');
        if (timeEl) timeEl.style.setProperty('color', hex, 'important');
        var preview = document.getElementById('ameClockPreview');
        if (preview) {
            preview.style.color = hex;
        }
        var picker = document.getElementById('ameClockColor');
        if (picker && picker.value.toUpperCase() !== hex) {
            picker.value = hex;
        }
        ameClockSyncSliders(hex);
        document.querySelectorAll('[data-clock-color]').forEach(function (btn) {
            var on = ameClockNormHex(btn.getAttribute('data-clock-color')) === hex;
            btn.style.boxShadow = on ? '0 0 0 2px #0B1E4D' : '0 0 0 1px #cbd5e1';
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (persist === false) {
            return;
        }
        /* Clock colour is stored by the visual design engine, not a second cookie. */
    };
    function ameClockReadForm() {
        var showDateEl = document.getElementById('ameClockShowDate');
        var weekdayEl = document.getElementById('ameClockShowWeekday');
        var numericEl = document.getElementById('ameClockNumericDate');
        var hourEl = document.getElementById('ameClockHour12');
        var colorEl = document.getElementById('ameClockColor');
        return {
            showDate: showDateEl ? showDateEl.checked : true,
            showWeekday: weekdayEl ? weekdayEl.checked : true,
            numericDate: numericEl ? numericEl.checked : false,
            hour12: hourEl ? hourEl.checked : true,
            color: colorEl ? ameClockNormHex(colorEl.value) : ameClockSavedColor()
        };
    }
    function ameFormatClock(now, prefs) {
        var useEn = (window.AME_LANG === 'en');
        var hour12 = typeof prefs.hour12 === 'boolean' ? prefs.hour12 : true;
        var showDate = prefs.showDate !== false;
        var showWeekday = prefs.showWeekday !== false;
        var numericDate = prefs.numericDate === true;
        var locale = useEn ? 'en-GB' : 'bn-BD';
        var dateOpts = numericDate
            ? { day: '2-digit', month: '2-digit', year: 'numeric' }
            : { day: 'numeric', month: 'long', year: 'numeric' };
        if (showWeekday) {
            dateOpts.weekday = 'long';
        }
        var dateText = now.toLocaleDateString(locale, dateOpts);
        var timeText = now.toLocaleTimeString(locale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: hour12
        });
        return { dateText: dateText, timeText: timeText, showDate: showDate };
    }
    function updateHdrClock() {
        var now = new Date();
        var prefs = ameClockPrefs();
        var overlay = document.getElementById('ameClockEdit');
        if (overlay && !overlay.hidden) {
            prefs = ameClockReadForm();
        }
        var parts = ameFormatClock(now, prefs);
        var dateEl = document.getElementById('hdr-clock-date');
        var timeEl = document.getElementById('hdr-clock-time');
        var clockEl = document.getElementById('hdr-live-clock');
        if (dateEl) {
            dateEl.textContent = parts.showDate ? parts.dateText : '';
            dateEl.hidden = !parts.showDate;
        }
        if (timeEl) {
            timeEl.textContent = parts.timeText;
        } else if (clockEl) {
            clockEl.textContent = parts.showDate ? (parts.dateText + ' · ' + parts.timeText) : parts.timeText;
        }
        var preview = document.getElementById('ameClockPreview');
        if (preview && overlay && !overlay.hidden) {
            preview.textContent = parts.showDate
                ? (parts.dateText + '\n' + parts.timeText)
                : parts.timeText;
        }
        if (clockEl) {
            clockEl.setAttribute('aria-label', parts.showDate
                ? (parts.dateText + ' ' + parts.timeText)
                : parts.timeText);
        }
    }
    function ameOpenClockEdit() {
        var box = document.getElementById('ameClockEdit');
        var prefs = ameClockPrefs();
        if (!box) {
            return;
        }
        document.getElementById('ameClockShowDate').checked = prefs.showDate !== false;
        document.getElementById('ameClockShowWeekday').checked = prefs.showWeekday !== false;
        document.getElementById('ameClockNumericDate').checked = prefs.numericDate === true;
        document.getElementById('ameClockHour12').checked = typeof prefs.hour12 === 'boolean'
            ? prefs.hour12
            : true;
        var colorEl = document.getElementById('ameClockColor');
        if (colorEl) {
            colorEl.value = ameClockSavedColor();
        }
        ameClockSyncSliders(ameClockSavedColor());
        window.ameApplyClockColor(ameClockSavedColor(), false);
        box.hidden = false;
        box.style.display = 'flex';
        updateHdrClock();
    }
    function ameCloseClockEdit() {
        var box = document.getElementById('ameClockEdit');
        if (!box) {
            return;
        }
        box.hidden = true;
        box.style.display = 'none';
        window.ameApplyClockColor(ameClockSavedColor(), false);
        updateHdrClock();
    }
    setInterval(updateHdrClock, 1000);
    updateHdrClock();
    (function () {
        var clockEl = document.getElementById('hdr-live-clock');
        var form = document.getElementById('ameClockEditBox');
        var closeBtn = document.getElementById('ameClockEditClose');
        var overlay = document.getElementById('ameClockEdit');
        if (clockEl) {
            clockEl.addEventListener('click', ameOpenClockEdit);
            clockEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    ameOpenClockEdit();
                }
            });
        }
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var formPrefs = ameClockReadForm();
                ameClockSavePrefs(formPrefs);
                window.ameApplyClockColor(formPrefs.color, true);
                ameCloseClockEdit();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', ameCloseClockEdit);
        }
        if (overlay) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    ameCloseClockEdit();
                }
            });
        }
        ['ameClockShowDate', 'ameClockShowWeekday', 'ameClockNumericDate', 'ameClockHour12'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', updateHdrClock);
            }
        });
        var colorEl = document.getElementById('ameClockColor');
        if (colorEl) {
            colorEl.addEventListener('input', function () {
                window.ameApplyClockColor(colorEl.value, true);
            });
        }
        var hexEl = document.getElementById('ameClockHex');
        if (hexEl) {
            hexEl.addEventListener('input', function () {
                var typed = ameClockNormHex(hexEl.value);
                if (typed) {
                    window.ameApplyClockColor(typed, true);
                }
            });
        }
        ['ameClockHue', 'ameClockSat', 'ameClockLight'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function () {
                    window.ameApplyClockColor(ameClockColorFromSliders(), true);
                });
            }
        });
        document.querySelectorAll('.ame-clock-step').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var axis = btn.getAttribute('data-clock-axis');
                var delta = Number(btn.getAttribute('data-clock-delta')) || 0;
                var hsl = ameClockHexToHsl(ameClockSavedColor());
                if (axis === 'h') {
                    hsl.h = ((hsl.h + delta) % 360 + 360) % 360;
                } else if (axis === 's') {
                    hsl.s = Math.max(0, Math.min(100, hsl.s + delta));
                } else {
                    hsl.l = Math.max(8, Math.min(92, hsl.l + delta));
                }
                window.ameApplyClockColor(ameClockHslToHex(hsl.h, hsl.s, hsl.l), true);
            });
        });
        document.querySelectorAll('#ameClockSwatches [data-clock-color]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.ameApplyClockColor(btn.getAttribute('data-clock-color'), true);
            });
        });
        window.ameApplyClockColor(ameClockSavedColor(), false);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay && !overlay.hidden) {
                ameCloseClockEdit();
            }
        });
    })();

    /* ৩. ফুলস্ক্রিন ফাংশন */
    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(()=>{});
        } else {
            if (document.exitFullscreen) document.exitFullscreen();
        }
    }
</script>

<!-- ⭐ PWA সার্ভিস ওয়ার্কার — ক্যাশ মুছে নতুন ভার্সন নেয় -->
<script src="/assets/js/pos-skins.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.17', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/sw-boot.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.17', ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<!-- ⭐ অফলাইন বিক্রয় কিউ (ব্যাকগ্রাউন্ডে কাজ করে) — এই ৩টা স্ক্রিপ্টে
     (offline-queue.js, offline-payment-queue.js, offline-pay-nav.js) ইচ্ছাকৃতভাবে
     defer যোগ করা হয়নি — ঠিক নিচেই ইনলাইন স্ক্রিপ্ট আছে যেগুলো এগুলোর
     window.AmeOfflineQueue/AmeOfflinePay সাথে সাথেই (synchronously) ব্যবহার
     করে; defer করলে লোড-অর্ডার ভেঙে অফলাইন বিক্রয়/পেমেন্ট কিউ কাজ নাও করতে পারত। -->
<script src="/assets/js/offline-queue.js?v=<?php echo $sys_version ?? '1.0'; ?>"></script>
<script src="/assets/js/offline-payment-queue.js?v=<?php echo $sys_version ?? '1.0'; ?>"></script>
<script src="/assets/js/offline-pay-nav.js?v=<?php echo $sys_version ?? '1.0'; ?>"></script>
<script>
    /* কাস্টমার তালিকা প্রতি পাতায় নয় — পেমেন্ট পাতা নিজে নেয়। */
</script>

<script>
(function () {
    var dot = document.getElementById('ame-net-dot');
    var btn = document.getElementById('ame-net-icon');
    if (!dot || !btn) return;

    function render() {
        var online = navigator.onLine;
        var pendingSales = (window.AmeOfflineQueue && window.AmeOfflineQueue.pendingCount())
            ? window.AmeOfflineQueue.pendingCount()
            : 0;
        var pendingPay = (window.AmeOfflinePay && window.AmeOfflinePay.pendingCount())
            ? window.AmeOfflinePay.pendingCount()
            : 0;
        var pending = pendingSales + pendingPay;

        btn.classList.remove('is-off', 'is-sync');
        if (!online) {
            dot.style.background = '#c62828';
            btn.classList.add('is-off');
            btn.title = <?php echo json_encode(t('net_offline'), JSON_UNESCAPED_UNICODE); ?> + (pending ? ' (' + pending + ')' : '');
        } else if (pending > 0) {
            dot.style.background = '#1D4ED8';
            btn.classList.add('is-sync');
            btn.title = <?php echo json_encode(t('net_syncing'), JSON_UNESCAPED_UNICODE); ?> + ' (' + pending + ')';
        } else {
            dot.style.background = '#0B8043';
            btn.title = <?php echo json_encode(t('net_online'), JSON_UNESCAPED_UNICODE); ?>;
        }
    }

    if (window.AmeOfflineQueue) {
        window.AmeOfflineQueue.onQueueChange(render);
    }
    window.addEventListener('online', render);
    window.addEventListener('offline', render);
    render();
})();
</script>

<!-- ============================================================
     প্রফেশনাল ক্যালকুলেটর পপআপ — কীবোর্ড দিয়ে চালানো যায়
     ============================================================ -->
<div id="ame-calc-overlay" style="
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 14, 39, 0.55);
    z-index: 9999999;
    align-items: center;
    justify-content: center;
    padding: 16px;
">
    <div id="ame-calc-box" style="
        width: 100%;
        max-width: 320px;
        background: #ffffff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    ">
        <div style="
            background: linear-gradient(135deg, #0B1E4D, #0091EA);
            color: #fff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        ">
            <strong style="font-size:14px;">🧮 ক্যালকুলেটর</strong>
            <button type="button" onclick="ameCloseCalculator()" style="
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 26px; height: 26px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 16px;
                line-height: 1;
            ">&times;</button>
        </div>

        <div style="padding: 16px 18px 6px;">
            <div id="ame-calc-expr" style="
                min-height: 16px;
                text-align: right;
                color: #93a5c2;
                font-size: 12px;
                font-family: monospace;
                overflow-x: auto;
                white-space: nowrap;
            ">&nbsp;</div>
            <div id="ame-calc-display" style="
                text-align: right;
                font-size: 32px;
                font-weight: 800;
                font-family: monospace;
                color: #0B1E4D;
                padding: 8px 0 14px;
                overflow-x: auto;
                white-space: nowrap;
            ">0</div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 6px 18px 20px;">
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcClear()">AC</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcBackspace()">⌫</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcInput('%')">%</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcInput('/')">÷</button>

            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('7')">7</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('8')">8</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('9')">9</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcInput('*')">×</button>

            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('4')">4</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('5')">5</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('6')">6</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcInput('-')">−</button>

            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('1')">1</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('2')">2</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('3')">3</button>
            <button type="button" class="ame-calc-btn ame-calc-op" onclick="ameCalcInput('+')">+</button>

            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('0')" style="grid-column: span 2;">0</button>
            <button type="button" class="ame-calc-btn" onclick="ameCalcInput('.')">.</button>
            <button type="button" class="ame-calc-btn ame-calc-equal" onclick="ameCalcEquals()">=</button>
        </div>
    </div>
</div>

<style>
    .ame-calc-btn {
        border: none;
        background: #EAF4FF;
        color: #0B1E4D;
        font-size: 18px;
        font-weight: 700;
        padding: 14px 0;
        border-radius: 12px;
        cursor: pointer;
        transition: transform .1s ease, background .15s ease;
    }
    .ame-calc-btn:hover { background: #dcecfd; }
    .ame-calc-btn:active { transform: scale(0.95); }
    .ame-calc-op { background: #CFE3F7; color: #0B1E4D; }
    .ame-calc-equal {
        background: linear-gradient(135deg, #0B1E4D, #0091EA) !important;
        color: #fff !important;
    }
</style>

<script>
(function () {
    var expr = '';

    window.ameOpenCalculator = function () {
        document.getElementById('ame-calc-overlay').style.display = 'flex';
        expr = '';
        ameCalcRender();
    };

    window.ameCloseCalculator = function () {
        document.getElementById('ame-calc-overlay').style.display = 'none';
    };

    window.ameCalcInput = function (val) {
        // % কে .01 গুণকে রূপান্তর — সহজ পার্সেন্টেজ হিসাবের জন্য
        expr += val;
        ameCalcRender();
    };

    window.ameCalcBackspace = function () {
        expr = expr.slice(0, -1);
        ameCalcRender();
    };

    window.ameCalcClear = function () {
        expr = '';
        ameCalcRender();
    };

    window.ameCalcEquals = function () {
        if (!expr) return;
        try {
            // নিরাপদ ইভালুয়েশন: শুধু সংখ্যা ও +-*/%.() অনুমোদিত
            if (!/^[0-9+\-*/%.() ]+$/.test(expr)) {
                throw new Error('invalid');
            }
            var safeExpr = expr.replace(/%/g, '/100');
            // eslint-disable-next-line no-new-func
            var result = Function('"use strict"; return (' + safeExpr + ')')();
            if (!isFinite(result)) throw new Error('invalid');

            document.getElementById('ame-calc-expr').textContent = expr + ' =';
            document.getElementById('ame-calc-display').textContent =
                Number(result.toFixed(8)).toString();
            expr = Number(result.toFixed(8)).toString();
        } catch (e) {
            document.getElementById('ame-calc-display').textContent = 'ভুল হিসাব';
            expr = '';
        }
    };

    function ameCalcRender() {
        document.getElementById('ame-calc-expr').textContent = expr ? expr : '\u00A0';
        document.getElementById('ame-calc-display').textContent = expr || '0';
    }

    // ওভারলে-র বাইরে ক্লিক করলে বন্ধ হয়ে যাবে
    document.getElementById('ame-calc-overlay').addEventListener('click', function (e) {
        if (e.target === this) window.ameCloseCalculator();
    });

    // কীবোর্ড সাপোর্ট — পপআপ খোলা থাকলে সরাসরি কীবোর্ড দিয়ে হিসাব করা যাবে
    document.addEventListener('keydown', function (e) {
        var overlay = document.getElementById('ame-calc-overlay');
        if (overlay.style.display !== 'flex') return;

        if (e.key === 'Escape') {
            window.ameCloseCalculator();
            return;
        }
        if (e.key === 'Enter' || e.key === '=') {
            e.preventDefault();
            window.ameCalcEquals();
            return;
        }
        if (e.key === 'Backspace') {
            e.preventDefault();
            window.ameCalcBackspace();
            return;
        }
        if (/^[0-9+\-*/.%]$/.test(e.key)) {
            e.preventDefault();
            window.ameCalcInput(e.key);
        }
    });
})();
</script>