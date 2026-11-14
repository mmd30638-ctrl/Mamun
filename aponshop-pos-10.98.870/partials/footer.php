<?php
/**
 * সিরিয়াল ১১১: ক্লিন সিস্টেম ফুটার
 * কাজ: মেইন কন্টেন্ট বন্ধ করা এবং ঘড়ি সচল রাখা।
 */
?>
</div> <!-- #main-content ডিভ বন্ধ করা হলো যা header.php তে শুরু হয়েছিল -->

<!-- ⭐ রেসপন্সিভ সেফটি-নেট — সব পেজের সব CSS/JS-এর পরে, একদম শেষে লোড হয় (এখানেই থাকতে
     হবে, কারণ responsive-fix.css-এর !important রুলগুলো আগে থেকে ছিল কিন্তু কোথাও
     লিংক করা ছিল না — ফলে এতদিন এই সুরক্ষা আসলেই কার্যকরই হচ্ছিল না) -->
<link rel="stylesheet" href="/assets/css/ame-account-select.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/responsive-fix.css?v=<?php echo $sys_version ?? '1.0'; ?>">
<link rel="stylesheet" href="/assets/css/invoice-print-fix.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/memo-clear.css?v=10.98.847">
<link rel="stylesheet" href="/assets/css/pos-pay-dock.css?v=10.98.847">
<link rel="stylesheet" href="/assets/css/design-system.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/line-hover.630.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
<script src="/assets/js/line-hover.630.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/ame-account-select.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/responsive-fix.js?v=<?php echo $sys_version ?? '1.0'; ?>" defer></script>
<script src="/assets/js/design-system.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/ame-idle-lock.js?v=10.98.847" defer></script>
<script src="/assets/js/pos-pay-dock.js?v=10.98.847" defer></script>
<script src="/assets/js/error-boundary.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/qa-api-log.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include __DIR__ . '/page_fit.php'; ?>
<?php if (str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/modules/accounts/partner_')): ?>
<link rel="stylesheet" href="/assets/css/partner-desk.css?v=10.98.847">
<script src="/assets/js/partner-desk.js?v=10.98.847" defer></script>
<?php endif; ?>


<script>
    // ডিজিটাল ঘড়ি আপডেট ফাংশন (বাংলা ফরমেট)
    function startClock() {
        const clockEl = document.getElementById('clock-display');
        if(clockEl) {
            clockEl.innerText = new Date().toLocaleString('bn-BD', {
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true
            });
        }
    }
    setInterval(startClock, 1000); 
    startClock();

    (function () {
        var token = window.AME_CSRF_TOKEN || '';
        if (!token) return;
        document.querySelectorAll('form').forEach(function (form) {
            var method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'post') return;
            if (form.querySelector('input[name="csrf_token"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = token;
            form.appendChild(input);
        });
    })();
</script>

<?php
try {
    $ame_acc_share = __DIR__ . '/../includes/accounts_print_share.php';
    if (is_file($ame_acc_share)) {
        require_once $ame_acc_share;
        if (function_exists('ame_accounts_print_share_render')) {
            ame_accounts_print_share_render();
        }
    }
} catch (Throwable $e) {
    error_log('accounts print share bar skipped: ' . $e->getMessage());
}
?>
<?php
/* নিচের অ্যাপ-ডক সব পেজ থেকে সরানো — হেডার মেনুই নেভিগেশন। */
?>
<style id="ame-app-dock-hide">
/* PWA "install app" ডক/প্রম্পট সব পেজ থেকে বাদ — হেডার মেনুই নেভিগেশন। */
.ame-app-dock,
.ame-app-install,
.ame-app-install.show {
    display: none !important;
}
</style>
<?php if (function_exists('ame_if_is_home_page') && ame_if_is_home_page()): ?>
<script src="/assets/js/app-install.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endif; ?>
<?php
if (isset($db) && $db instanceof mysqli && function_exists('vd_stylesheet_html')) {
    echo vd_stylesheet_html($db);
}
?>
<link rel="stylesheet" href="/assets/css/hdr-top-lock.css?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>">
<?php
$ame_lock_ver = htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8');
$ame_lock_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$ame_is_pos = function_exists('ame_is_sales_desk_page') && ame_is_sales_desk_page();
if (stripos($ame_lock_uri, '/modules/settings/activity_logs.php') !== false
    || stripos($ame_lock_uri, '/modules/reports/audit_trail.php') !== false) {
    echo '<link rel="stylesheet" href="/assets/css/audit-desk.819.css?v=' . $ame_lock_ver . '">' . "\n";
}
/* SPEED.txt §২ — POS CSS শুধু বিক্রয় ডেস্কে */
if ($ame_is_pos && is_file(__DIR__ . '/../includes/page_layout.php')) {
    require_once __DIR__ . '/../includes/page_layout.php';
}
if ($ame_is_pos) {
    echo '<link rel="stylesheet" href="/assets/css/ame-sale-desk-lock.css?v=' . $ame_lock_ver . '" id="ameSaleLock">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/sale-charge-popups.css?v=' . $ame_lock_ver . '">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/pos-charge-line.css?v=' . $ame_lock_ver . '" id="amePosChargeLine">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/pos-round-hide.css?v=' . $ame_lock_ver . '" id="amePosRoundHide">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/pos-cash-paid.css?v=' . $ame_lock_ver . '" id="amePosCashPaidCss">' . "\n";
    if (function_exists('ame_page_layout_echo_styles_after_lock')) {
        ame_page_layout_echo_styles_after_lock();
    }
}
?>
<script>
    (function () {
        if (!document.documentElement.classList.contains('ame-sale-wait')) {
            return;
        }
        function showSale() {
            document.documentElement.classList.remove('ame-sale-wait');
        }
        var lock = document.getElementById('ameSaleLock');
        if (lock && lock.sheet) {
            showSale();
        } else if (lock) {
            lock.addEventListener('load', showSale);
        }
        setTimeout(showSale, 1800);
    })();
</script>
<script src="/assets/js/enter-submit.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/pay-security-autofill.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/module-navigation.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/ame-keep-warm.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/ame-memo-file.js?v=10.98.847" defer></script>
<?php if (!empty($ame_is_pos)): ?>
<script src="/assets/js/sell-without-stock.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/pos-cash-paid.js?v=<?php echo htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endif; ?>

<?php
if (isset($db) && $db instanceof mysqli) {
    if (is_file(__DIR__ . '/../includes/super_admin.php')) {
        require_once __DIR__ . '/../includes/super_admin.php';
        ame_super_admin_boot($db);
    }
    if (is_file(__DIR__ . '/../includes/staff_archive.php')) {
        require_once __DIR__ . '/../includes/staff_archive.php';
        ame_staff_archive_boot($db);
    }
}
$ame_staff_ver = htmlspecialchars($sys_version ?? '1.0', ENT_QUOTES, 'UTF-8');
if (function_exists('ame_is_super_admin')) {
    echo '<script>window.AME_IS_SUPER_ADMIN=' . (ame_is_super_admin() ? 'true' : 'false') . ';</script>' . "\n";
}
echo '<link rel="stylesheet" href="/assets/css/staff-archive.css?v=' . $ame_staff_ver . '">' . "\n";
echo '<script src="/assets/js/staff-archive.js?v=' . $ame_staff_ver . '" defer></script>' . "\n";
$ame_hrm_script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
if (str_contains($ame_hrm_script, '/modules/hrm/') && !str_contains($ame_hrm_script, '/modules/hrm/employee_list.php')) {
    echo '<link rel="stylesheet" href="/assets/css/hrm-cream.css?v=' . $ame_staff_ver . '">' . "\n";
}
if (str_contains($ame_hrm_script, '/modules/purchase/') && !str_contains($ame_hrm_script, '/modules/purchase/add.php')) {
    echo '<link rel="stylesheet" href="/assets/css/purchase-cream.css?v=' . $ame_staff_ver . '">' . "\n";
}
$ame_users_script = basename($ame_hrm_script);
if (in_array($ame_users_script, ['users.php', 'user_add.php', 'user_view.php', 'user_reset.php', 'reset_password.php', 'role_permissions.php', 'super_admin.php'], true)) {
    echo '<link rel="stylesheet" href="/assets/css/users-cream.css?v=' . $ame_staff_ver . '">' . "\n";
}
if (str_contains($ame_hrm_script, '/modules/reports/sales_report.php')
    || str_contains($ame_hrm_script, '/modules/reports/invoice_edit.php')) {
    echo '<link rel="stylesheet" href="/assets/css/sales-report-cream.css?v=' . $ame_staff_ver . '">' . "\n";
    echo '<link rel="stylesheet" href="/assets/css/sales-pages.css?v=' . $ame_staff_ver . '">' . "\n";
}
if (str_contains($ame_hrm_script, '/modules/sales/history.php')) {
    echo '<link rel="stylesheet" href="/assets/css/sales-pages.css?v=' . $ame_staff_ver . '">' . "\n";
}
?>
<?php
if (is_file(__DIR__ . '/../includes/page_layout.php')) {
    require_once __DIR__ . '/../includes/page_layout.php';
    if (function_exists('ame_page_layout_boot')) {
        ame_page_layout_boot();
    }
}
if (is_file(__DIR__ . '/../includes/page_text.php')) {
    require_once __DIR__ . '/../includes/page_text.php';
    if (function_exists('ame_page_text_boot')) {
        ame_page_text_boot();
    }
}
?>
<?php if (!empty($ame_is_pos)): ?>
<script>
(function () {
    function placeUnder(box, cssVar, liftWhenLow) {
        if (!box) return;
        var view = window.visualViewport;
        var viewTop = view ? view.offsetTop : 0;
        var viewHeight = view ? view.height : window.innerHeight;
        var top = Math.max(viewTop + 8, Math.round(box.getBoundingClientRect().bottom + 10));
        if (liftWhenLow) {
            var highPoint = Math.round(viewTop + viewHeight * 0.42);
            if (top > highPoint) {
                top = highPoint;
            }
        }
        document.documentElement.style.setProperty(cssVar, Math.round(top) + 'px');
    }
    function placeChargePops() {
        placeUnder(document.getElementById('p-search'), '--ame-product-pop-top', true);
        placeUnder(document.getElementById('posCustomerInfoField'), '--ame-customer-pop-top', false);
    }
    function watch(el) {
        if (!el || !window.MutationObserver) return;
        new MutationObserver(placeChargePops).observe(el, { attributes: true, childList: true, subtree: true });
    }
    function bootChargePops() {
        placeChargePops();
        watch(document.getElementById('search-res'));
        watch(document.getElementById('posCustomerSearchList'));
        window.addEventListener('resize', placeChargePops);
        window.addEventListener('scroll', placeChargePops, true);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', placeChargePops);
            window.visualViewport.addEventListener('scroll', placeChargePops);
        }
        ['p-search', 'posCustomerInfoField'].forEach(function (id) {
            var box = document.getElementById(id);
            if (!box) return;
            box.addEventListener('input', placeChargePops);
            box.addEventListener('focus', placeChargePops);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootChargePops);
    } else {
        bootChargePops();
    }
})();
</script>
<?php endif; ?>
<?php
$ame_pur_uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? '') . ' ' . (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (
    !str_contains($ame_pur_uri, 'restock_invoice')
    && (
    str_contains($ame_pur_uri, '/modules/purchase/')
    || str_contains($ame_pur_uri, '/modules/workshop/')
    || preg_match('#(^|/)m(/|$|\\?)#', $ame_pur_uri)
    || str_contains($ame_pur_uri, '/m/')
    || str_contains($ame_pur_uri, 'm.php')
    || str_contains($ame_pur_uri, 'invoice')
    || str_contains($ame_pur_uri, 'memo')
    )
) {
    $ame_inject = dirname(__DIR__) . '/includes/purchase_desk_inject.php';
    if (is_file($ame_inject)) {
        require_once $ame_inject;
        echo ame_purchase_desk_tags();
    } else {
        $ame_pur_ver = htmlspecialchars('10.98.351', ENT_QUOTES, 'UTF-8');
        echo '<script src="/assets/js/ame-pdesk-349.php?v=' . $ame_pur_ver . '" defer></script>' . "\n";
    }
}
?>
<?php
try {
    $ame_hire_due_lib = __DIR__ . '/../includes/hire_due_notify.php';
    if (is_file($ame_hire_due_lib)) {
        require_once $ame_hire_due_lib;
        if (function_exists('hire_due_footer_inject')) {
            hire_due_footer_inject();
        }
    }
} catch (Throwable $e) {
    error_log('hire due notice skipped: ' . $e->getMessage());
}
?>
<?php if (function_exists('ame_operator_stamp_html') && !empty($_SESSION['user_id'])): ?>
<style id="ame-op-stamp-css">
.ame-op-stamp { margin: 8px 0 0; font-size: 12px; font-weight: 800; color: #1e293b; line-height: 1.35; }
.ame-op-stamp p { margin: 0; }
.ame-op-stamp-page { display: none; }
@media print {
    .ame-op-stamp, .ame-op-stamp-page { display: block !important; }
    .ame-op-stamp-page { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center; font-size: 11px; color: #111; }
}
</style>
<?php echo ame_operator_stamp_html(isset($db) && $db instanceof mysqli ? $db : null, 0, ['class' => 'ame-op-stamp ame-op-stamp-page']); ?>
<script>
(function () {
    var stamp = document.querySelector('.ame-op-stamp-page');
    if (!stamp) return;
    var ids = ['memo-container', 'receiptCard', 'spayMemo', 'ame-slip', 'invoiceBox', 'wsSlip', 'wsShareSlip'];
    ids.forEach(function (id) {
        var el = document.getElementById(id);
        if (!el || el.querySelector('.ame-op-stamp')) return;
        var clone = stamp.cloneNode(true);
        clone.classList.remove('ame-op-stamp-page');
        clone.style.display = 'block';
        el.appendChild(clone);
    });
})();
</script>
<?php endif; ?>
<?php
$ame_purchase_lock = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
if (str_contains($ame_purchase_lock, '/modules/purchase/add.php')) {
    $ame_plock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/purchase-desk-lock.css?v=' . $ame_plock_ver . '" id="amePurchaseLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/customers/due-reminder.php')) {
    $ame_dlock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/due-reminder.css?v=' . $ame_dlock_ver . '" id="ameDueReminderLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/customers/index.php')) {
    $ame_dlock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/directory.811.css?v=' . $ame_dlock_ver . '" id="ameDirectoryLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/customers/ledger.php')) {
    $ame_dlock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/cust-desk.css?v=' . $ame_dlock_ver . '" id="ameCustomerLedgerLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/suppliers/statement.php') || str_contains($ame_purchase_lock, '/modules/suppliers/ledger.php')) {
    $ame_dlock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/supplier-statement.css?v=' . $ame_dlock_ver . '" id="ameSupplierStatementLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/family/')) {
    $ame_flock_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/family-desk.520.css?v=' . $ame_flock_ver . '" id="ameFamilyDeskLock">' . "\n";
}
if (str_contains($ame_purchase_lock, '/modules/bloan/')) {
    $ame_block_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1.0'), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/bloan-desk.521.css?v=' . $ame_block_ver . '" id="ameBloanDeskLock">' . "\n";
}
?>
<style id="ame-ink-lock">
html { --ame-ink: #111827 !important; --brand-black: #111827 !important; }
html body .custom-sidebar a:not(.active),
html body .direct-link:not(.active),
html body .menu-list a:not(.active),
html body .menu-head:not(.active) {
  color: #111827 !important;
  -webkit-text-fill-color: #111827 !important;
}
html body .hm-gallery-cap {
  color: #3d2a12 !important;
  -webkit-text-fill-color: #3d2a12 !important;
}
</style>
<?php if (!empty($ame_pay_list_white)): ?>
<link rel="stylesheet" href="/assets/css/pay-list-white.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.538', ENT_QUOTES, 'UTF-8'); ?>" id="pay-list-white-538">
<?php endif; ?>
<?php if (!empty($_SESSION['user_id']) && function_exists('ame_if_is_home_page') && ame_if_is_home_page()): ?>
<link rel="stylesheet" href="/assets/css/home-dash.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.545', ENT_QUOTES, 'UTF-8'); ?>">
<script src="/assets/js/home-dash.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.545', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endif; ?>
<?php if (function_exists('ame_if_is_home_page') && ame_if_is_home_page() && !empty($_SESSION['user_id'])): ?>
<link rel="stylesheet" href="/assets/css/hand-return.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.540', ENT_QUOTES, 'UTF-8'); ?>" id="hand-return-540">
<script src="/assets/js/hand-return.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.540', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endif; ?>
<?php if (str_contains($ame_hrm_script ?? '', '/modules/hrm/')): ?>
<link rel="stylesheet" href="/assets/css/emp-pay-white.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" id="emp-pay-white">
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/box-icon-white.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" id="box-icon-white">
<?php if (str_contains($ame_hrm_script ?? '', '/modules/returns/')): ?>
<link rel="stylesheet" href="/assets/css/return-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" id="return-desk">
<?php endif; ?>
<?php if (str_contains($ame_hrm_script ?? '', '/modules/purchase/returns/')): ?>
<link rel="stylesheet" href="/assets/css/purchase-return-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : ($sys_version ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" id="purchase-return-desk">
<?php endif; ?>
<?php include __DIR__ . '/confirm_actions.php'; ?>
</body>
</html>