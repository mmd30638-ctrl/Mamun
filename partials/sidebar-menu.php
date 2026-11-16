<?php
/* 10.98.711 */
/**
 * Aponshop Complete Sidebar Menu (Accordion)
 * Path: public_html/partials/sidebar-menu.php
 */
include_once __DIR__ . '/../includes/permission_functions.php';
include_once __DIR__ . '/sidebar-icons.php';

if (is_file(__DIR__ . '/../includes/super_admin.php')) {
    require_once __DIR__ . '/../includes/super_admin.php';
    $ame_db_now = $GLOBALS['db'] ?? (isset($db) && $db instanceof mysqli ? $db : null);
    if ($ame_db_now instanceof mysqli && function_exists('ame_super_admin_boot')) {
        ame_super_admin_boot($ame_db_now);
    }
}
$is_admin = function_exists('ame_is_admin_like')
    ? ame_is_admin_like()
    : (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin');
$script_now = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$englishAtlas = function_exists('current_lang') && current_lang() === 'en';
?>

<?php include __DIR__ . '/sidebar-shortcuts.php'; ?>
<a href="/index.php" class="direct-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('home'); ?> <?php echo te('nav_home'); ?>
</a>
<?php if (!empty($_SESSION['user_id'])): ?>
<a href="/modules/clipboard/index.php" class="direct-link <?php echo str_contains($script_now, '/modules/clipboard/') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('transactions'); ?> কপি-পোস্ট
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-hrm')): ?>
<a href="/modules/hrm/employee_list.php" class="direct-link <?php echo str_contains($script_now, '/modules/hrm/employee_list.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('hrm'); ?> <?php echo htmlspecialchars(t('nav_hrm') === 'nav_hrm' ? 'কর্মী' : t('nav_hrm'), ENT_QUOTES, 'UTF-8'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-sales')): ?>
<a href="/modules/sales/pos.php" class="direct-link <?php echo str_contains($script_now, '/modules/sales/pos.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('sales'); ?> <?php echo te('nav_smart_sales'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-workshop')): ?>
<a href="/modules/workshop/index.php" class="direct-link <?php echo str_contains($script_now, '/modules/workshop/index.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('workshop'); ?> <?php echo te('nav_workshop'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-inv')): ?>
<a href="/modules/inventory/list.php" class="direct-link <?php echo str_contains($script_now, '/modules/inventory/list.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('inventory'); ?> <?php echo te('nav_inventory'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-purchase')): ?>
<a href="/modules/purchase/add.php" class="direct-link <?php echo str_contains($script_now, '/modules/purchase/add.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('purchase'); ?> <?php echo te('nav_stock_buy'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-pulse')): ?>
<a href="/modules/pulse/index.php" class="direct-link <?php echo (str_contains($script_now, '/modules/pulse/') ? 'active' : ''); ?>">
    <?php echo ame_sb_icon('pulse'); ?> <?php echo te('nav_pulse'); ?>
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')): ?>
<a href="/modules/reports/live_work.php" class="direct-link <?php echo str_contains($script_now, '/modules/reports/live_work.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('reports'); ?> লাইভ ড্যাশবোর্ড
</a>
<?php endif; ?>
<?php if ($is_admin || has_permission($db, 'm-agents')): ?>
<a href="/modules/agents/index.php" class="direct-link <?php echo (str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/modules/agents/') ? 'active' : ''); ?>">
    <?php echo ame_sb_icon('agents'); ?> <?php echo te('nav_agents'); ?>
</a>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-smart')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-smart')">
        <span><?php echo ame_sb_icon('smart'); ?> <?php echo te('nav_smart'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-smart" class="menu-list hidden">
        <a href="/modules/smart/index.php"><?php echo ame_sb_dot('smart', 'nav_smart_dash'); ?> <?php echo te('nav_smart_dash'); ?></a>
        <a href="/modules/smart/assistant.php"><?php echo ame_sb_dot('smart', 'nav_smart_assistant'); ?> <?php echo te('nav_smart_assistant'); ?></a>
        <a href="/modules/smart/stock.php"><?php echo ame_sb_dot('smart', 'nav_smart_stock'); ?> <?php echo te('nav_smart_stock'); ?></a>
        <a href="/modules/smart/purchase.php"><?php echo ame_sb_dot('smart', 'nav_smart_purchase'); ?> <?php echo te('nav_smart_purchase'); ?></a>
        <a href="/modules/smart/customers.php"><?php echo ame_sb_dot('smart', 'nav_smart_customers'); ?> <?php echo te('nav_smart_customers'); ?></a>
        <a href="/modules/smart/profit.php"><?php echo ame_sb_dot('smart', 'nav_smart_profit'); ?> <?php echo te('nav_smart_profit'); ?></a>
        <a href="/modules/smart/calendar.php"><?php echo ame_sb_dot('smart', 'nav_smart_calendar'); ?> <?php echo te('nav_smart_calendar'); ?></a>
        <a href="/modules/smart/map.php"><?php echo ame_sb_dot('smart', 'nav_smart_map'); ?> <?php echo te('nav_smart_map'); ?></a>
        <a href="/modules/smart/privacy.php"><?php echo ame_sb_dot('smart', 'nav_smart_privacy'); ?> <?php echo te('nav_smart_privacy'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-sales')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-sales')">
        <span><?php echo ame_sb_icon('sales'); ?> <?php echo te('nav_sales'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-sales" class="menu-list hidden">
        <a href="/modules/sales/pos.php">🛒 <?php echo te('nav_pos') ?? 'বিক্রয় কাউন্টার'; ?></a>
        <a href="/modules/sales/history.php"><?php echo ame_sb_dot('sales', 'nav_sales_history'); ?> <?php echo te('nav_sales_history'); ?></a>
        <?php /* v34: এই আলাদা "বিক্রয় কাউন্টার" লুকস লিংকটা সরানো হলো — এখন
                 কাউন্টারের রং সবসময় নিচের একটাই "রং পরিবর্তন" পেজ থেকে
                 নিয়ন্ত্রিত হয়, আলাদা কোনো কাউন্টার-নির্দিষ্ট রং-সেটিংস আর
                 নেই (v33 দেখুন)। */ ?>
        <?php if ($is_admin || has_permission($db, 'm-sales-report')): ?>
        <a href="/modules/reports/sales_report.php"><?php echo ame_sb_dot('sales', 'nav_sales_report_pro'); ?> <?php echo te('nav_sales_report_pro'); ?></a>
        <a href="/modules/reports/live_work.php"><?php echo ame_sb_dot('sales', 'nav_day_closing'); ?> লাইভ ড্যাশবোর্ড</a>
        <a href="/modules/reports/day_closing.php"><?php echo ame_sb_dot('sales', 'nav_day_closing'); ?> <?php echo te('nav_day_closing'); ?></a>
        <a href="/modules/reports/day_closing.php"><?php echo ame_sb_dot('sales', 'nav_day_closing'); ?> <?php echo te('nav_day_closing'); ?></a>
        <a href="/modules/reports/sales_detailed.php"><?php echo ame_sb_dot('sales', 'nav_sales_detailed'); ?> <?php echo te('nav_sales_detailed'); ?></a>
        <a href="/modules/reports/items_report.php"><?php echo ame_sb_dot('sales', 'nav_items_report'); ?> <?php echo te('nav_items_report'); ?></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-sales-returns')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-sales-returns')">
        <span><?php echo ame_sb_icon('sale_return'); ?> <?php echo te('nav_sale_return'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-sales-returns" class="menu-list hidden">
        <a href="/modules/returns/v2/dashboard.php"><?php echo ame_sb_dot('sale_return', 'nav_return_dash'); ?> <?php echo te('nav_return_dash'); ?></a>
        <a href="/modules/returns/v2/register.php"><?php echo ame_sb_dot('sale_return', 'nav_return_register'); ?> <?php echo te('nav_return_register'); ?></a>
        <a href="/modules/returns/v2/create_invoice_return.php"><?php echo ame_sb_dot('sale_return', 'nav_return_invoice'); ?> <?php echo te('nav_return_invoice'); ?></a>
        <a href="/modules/returns/v2/create_manual_return.php"><?php echo ame_sb_dot('sale_return', 'nav_return_manual'); ?> <?php echo te('nav_return_manual'); ?></a>
        <a href="/modules/returns/v2/create_exchange.php"><?php echo ame_sb_dot('sale_return', 'nav_return_exchange'); ?> <?php echo te('nav_return_exchange'); ?></a>
        <a href="/modules/returns/return_report.php"><?php echo ame_sb_dot('sale_return', 'nav_return_report'); ?> <?php echo te('nav_return_report'); ?></a>
        <a href="/modules/returns/history.php"><?php echo ame_sb_dot('sale_return', 'nav_return_old_hist'); ?> <?php echo te('nav_return_old_hist'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-purchase')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-purchase')">
        <span><?php echo ame_sb_icon('purchase'); ?> <?php echo te('nav_purchase'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-purchase" class="menu-list hidden">
        <a href="/modules/purchase/add.php"><?php echo ame_sb_dot('purchase', 'nav_stock_buy'); ?> <?php echo te('nav_stock_buy'); ?></a>
        <a href="/modules/inventory/restock_invoice.php"><?php echo ame_sb_dot('purchase', 'nav_restock_invoice'); ?> <?php echo te('nav_restock_invoice'); ?></a>
        <a href="/modules/purchase/history.php"><?php echo ame_sb_dot('purchase', 'nav_purchase_hist'); ?> <?php echo te('nav_purchase_hist'); ?></a>
        <a href="/modules/reports/purchase_summary.php"><?php echo ame_sb_dot('purchase', 'nav_purchase_report'); ?> <?php echo te('nav_purchase_report'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-purchase-returns')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-purchase-returns')">
        <span><?php echo ame_sb_icon('purchase_return'); ?> <?php echo te('nav_purchase_return'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-purchase-returns" class="menu-list hidden">
        <a href="/modules/purchase/returns/dashboard.php"><?php echo ame_sb_dot('purchase_return', 'nav_pr_dash'); ?> <?php echo te('nav_pr_dash'); ?></a>
        <a href="/modules/purchase/returns/register.php"><?php echo ame_sb_dot('purchase_return', 'nav_pr_register'); ?> <?php echo te('nav_pr_register'); ?></a>
        <a href="/modules/purchase/returns/create.php"><?php echo ame_sb_dot('purchase_return', 'nav_pr_new'); ?> <?php echo te('nav_pr_new'); ?></a>
        <a href="/modules/purchase/returns/analytics.php"><?php echo ame_sb_dot('purchase_return', 'nav_pr_report'); ?> <?php echo te('nav_pr_report'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-inv')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-inv')">
        <span><?php echo ame_sb_icon('inventory'); ?> <?php echo te('nav_inventory'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-inv" class="menu-list hidden">
        <a href="/modules/inventory/list.php"><?php echo ame_sb_dot('inventory', 'nav_stock_list'); ?> <?php echo te('nav_stock_list'); ?></a>
        <a href="/modules/inventory/unit_setup.php"><?php echo ame_sb_dot('inventory', 'nav_units'); ?> <?php echo ame_l('ইউনিট ও রূপান্তর', 'Units and conversion'); ?></a>
        <a href="/modules/inventory/unit_stock.php"><?php echo ame_sb_dot('inventory', 'nav_unit_stock'); ?> <?php echo ame_l('ইউনিট স্টক', 'Unit stock'); ?></a>
        <a href="/modules/inventory/restock_invoice.php"><?php echo ame_sb_dot('inventory', 'nav_restock_invoice'); ?> <?php echo te('nav_restock_invoice'); ?></a>
        <a href="/modules/inventory/old_battery.php"><?php echo ame_sb_dot('inventory', 'nav_old_battery'); ?> <?php echo te('nav_old_battery'); ?></a>
        <a href="/modules/inventory/add_product.php"><?php echo ame_sb_dot('inventory', 'nav_new_product'); ?> <?php echo te('nav_new_product'); ?></a>
        <a href="/modules/inventory/categories.php"><?php echo ame_sb_dot('inventory', 'nav_category'); ?> <?php echo te('nav_category'); ?></a>
        <a href="/modules/inventory/brands.php"><?php echo ame_sb_dot('inventory', 'nav_brand'); ?> <?php echo te('nav_brand'); ?></a>
        <a href="/modules/inventory/barcode.php"><?php echo ame_sb_dot('inventory', 'nav_barcode'); ?> <?php echo te('nav_barcode'); ?></a>
        <a href="/modules/inventory/damage.php"><?php echo ame_sb_dot('inventory', 'nav_damaged'); ?> <?php echo te('nav_damaged'); ?></a>
        <a href="/modules/inventory/stock_report.php"><?php echo ame_sb_dot('inventory', 'nav_stock_now'); ?> <?php echo te('nav_stock_now'); ?></a>
        <a href="/modules/reports/valuation.php"><?php echo ame_sb_dot('inventory', 'nav_stock_value'); ?> <?php echo te('nav_stock_value'); ?></a>
        <a href="/modules/reports/stock_ledger.php"><?php echo ame_sb_dot('inventory', 'nav_stock_ledger'); ?> <?php echo te('nav_stock_ledger'); ?></a>
        <a href="/modules/reports/stock_movement_analysis.php"><?php echo ame_sb_dot('inventory', 'nav_stock_move'); ?> <?php echo te('nav_stock_move'); ?></a>
        <a href="/modules/reports/product_performance.php"><?php echo ame_sb_dot('inventory', 'nav_product_perf'); ?> <?php echo te('nav_product_perf'); ?></a>
        <a href="/modules/inventory/transfer.php"><?php echo ame_sb_dot('inventory', 'nav_stock_transfer'); ?> <?php echo te('nav_stock_transfer'); ?></a>
        <a href="/modules/inventory/stock_count.php"><?php echo ame_sb_dot('inventory', 'nav_stock_count'); ?> <?php echo te('nav_stock_count'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-workshop')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-workshop')">
        <span><?php echo ame_sb_icon('workshop'); ?> <?php echo te('nav_workshop'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-workshop" class="menu-list hidden">
        <a href="/modules/workshop/index.php"><?php echo ame_sb_dot('workshop', 'nav_ws_dash'); ?> <?php echo te('nav_ws_dash'); ?></a>
        <a href="/modules/workshop/job_new.php"><?php echo ame_sb_dot('workshop', 'nav_ws_new'); ?> <?php echo te('nav_ws_new'); ?></a>
        <a href="/modules/workshop/machines.php"><?php echo ame_sb_dot('workshop', 'nav_ws_machines'); ?> <?php echo te('nav_ws_machines'); ?></a>
        <a href="/modules/workshop/parts_finder.php"><?php echo ame_sb_dot('workshop', 'nav_ws_finder'); ?> <?php echo te('nav_ws_finder'); ?></a>
        <a href="/modules/workshop/needs.php"><?php echo ame_sb_dot('workshop', 'nav_ws_needs'); ?> <?php echo te('nav_ws_needs'); ?></a>
        <a href="/modules/workshop/dues.php"><?php echo ame_sb_dot('workshop', 'nav_ws_dues'); ?> <?php echo te('nav_ws_dues'); ?></a>
        <a href="/modules/workshop/quotes.php"><?php echo ame_sb_dot('workshop', 'nav_ws_quotes'); ?> <?php echo te('nav_ws_quotes'); ?></a>
        <a href="/modules/workshop/serials.php"><?php echo ame_sb_dot('workshop', 'nav_ws_serials'); ?> <?php echo te('nav_ws_serials'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-customers')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-customers')">
        <span><?php echo ame_sb_icon('customers'); ?> <?php echo te('nav_customers'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-customers" class="menu-list hidden">
        <a href="/modules/customers/index.php"><?php echo ame_sb_dot('customers', 'nav_cust_list'); ?> <?php echo te('nav_cust_list'); ?></a>
        <a href="/modules/customers/add_customer.php"><?php echo ame_sb_dot('customers', 'nav_cust_new'); ?> <?php echo te('nav_cust_new'); ?></a>
        <a href="/modules/customers/payment-receive.php"><?php echo ame_sb_dot('customers', 'nav_cust_pay'); ?> <?php echo te('nav_cust_pay'); ?></a>
        <a href="/modules/customers/withdraw.php"><?php echo ame_sb_dot('customers', 'nav_cust_withdraw'); ?> <?php echo te('nav_cust_withdraw'); ?></a>
        <a href="/modules/customers/actions/payments-list.php"><?php echo ame_sb_dot('customers', 'nav_cust_pay_list'); ?> <?php echo te('nav_cust_pay_list'); ?></a>
        <a href="/modules/customers/ledger.php"><?php echo ame_sb_dot('customers', 'nav_cust_ledger'); ?> <?php echo te('nav_cust_ledger'); ?></a>
        <a href="/modules/customers/hire/index.php"><?php echo ame_sb_dot('customers', 'nav_cust_hire'); ?> <?php echo te('nav_cust_hire'); ?></a>
        <a href="/modules/customers/due-reminder.php"><?php echo ame_sb_dot('customers', 'nav_cust_due'); ?> বকেয়া নোটিফিকেশন · অন/অফ</a>
        <a href="/modules/customers/actions/payment-status.php"><?php echo ame_sb_dot('customers', 'nav_cust_status'); ?> <?php echo te('nav_cust_status'); ?></a>
        <a href="/modules/customers/customer-areas.php"><?php echo ame_sb_dot('customers', 'nav_cust_area'); ?> <?php echo te('nav_cust_area'); ?></a>
        <a href="/modules/customers/customer-groups.php"><?php echo ame_sb_dot('customers', 'nav_cust_group'); ?> <?php echo te('nav_cust_group'); ?></a>
        <a href="/modules/reports/receivable_ageing.php"><?php echo ame_sb_dot('customers', 'nav_cust_ageing'); ?> <?php echo te('nav_cust_ageing'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-shop-rent')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-shop-rent')">
        <span><?php echo ame_sb_icon('shop_rent'); ?> <?php echo te('nav_shop_rent'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-shop-rent" class="menu-list hidden">
        <a href="/modules/shop_rent/index.php"><?php echo ame_sb_dot('shop_rent', 'nav_shop_rent_desk'); ?> <?php echo te('nav_shop_rent_desk'); ?></a>
        <a href="/modules/shop_rent/shop.php"><?php echo ame_sb_dot('shop_rent', 'nav_shop_rent_new'); ?> <?php echo te('nav_shop_rent_new'); ?></a>
        <a href="/modules/shop_rent/pay.php"><?php echo ame_sb_dot('shop_rent', 'nav_shop_rent_pay'); ?> <?php echo te('nav_shop_rent_pay'); ?></a>
        <a href="/modules/shop_rent/locker.php"><?php echo ame_sb_dot('shop_rent', 'nav_shop_rent_docs'); ?> <?php echo te('nav_shop_rent_docs'); ?></a>
        <a href="/modules/shop_rent/statement.php"><?php echo ame_sb_dot('shop_rent', 'nav_shop_rent_statement'); ?> <?php echo te('nav_shop_rent_statement'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-suppliers')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-suppliers')">
        <span><?php echo ame_sb_icon('suppliers'); ?> <?php echo te('nav_suppliers'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-suppliers" class="menu-list hidden">
        <a href="/modules/suppliers/dashboard.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_dashboard'); ?> <?php echo te('nav_sup_dashboard'); ?></a>
        <a href="/modules/suppliers/list.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_list'); ?> <?php echo te('nav_sup_list'); ?></a>
        <a href="/modules/suppliers/profile.php?id=0"><?php echo ame_sb_dot('suppliers', 'nav_sup_new'); ?> <?php echo te('nav_sup_new'); ?></a>
        <a href="/modules/suppliers/pay.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_pay'); ?> <?php echo te('nav_sup_pay'); ?></a>
        <a href="/modules/suppliers/payments-list.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_pay_list'); ?> <?php echo te('nav_sup_pay_list'); ?></a>
        <a href="/modules/suppliers/ledger.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_ledger'); ?> <?php echo te('nav_sup_ledger'); ?></a>
        <a href="/modules/suppliers/statement.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_statement'); ?> <?php echo te('nav_sup_statement'); ?></a>
        <a href="/modules/reports/payable_ageing.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_ageing'); ?> <?php echo te('nav_sup_ageing'); ?></a>
        <?php if ($is_admin): ?><a href="/modules/suppliers/sign_check.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_ageing'); ?> সাপ্লায়ার চিহ্ন যাচাই</a><a href="/modules/suppliers/balance_guard.php"><?php echo ame_sb_dot('suppliers', 'nav_sup_ageing'); ?> ব্যালেন্স পাহারা (লক)</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-transactions')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-transactions')">
        <span><?php echo ame_sb_icon('transactions'); ?> <?php echo te('nav_transactions'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-transactions" class="menu-list hidden">
        <a href="/modules/accounts/daily_audit.php"><?php echo ame_sb_dot('transactions', 'nav_daily_audit'); ?> <?php echo te('nav_daily_audit'); ?></a>
        <a href="/modules/accounts/categories.php"><?php echo ame_sb_dot('transactions', 'nav_txn_type'); ?> <?php echo te('nav_txn_type'); ?></a>
        <a href="/modules/accounts/finance_dashboard.php"><?php echo ame_sb_dot('transactions', 'nav_my_finance'); ?> <?php echo te('nav_my_finance'); ?></a>
        <a href="/modules/accounts/add_transaction.php"><?php echo ame_sb_dot('transactions', 'nav_txn_general'); ?> <?php echo te('nav_txn_general'); ?></a>
        <a href="/modules/accounts/receive.php"><?php echo ame_sb_dot('transactions', 'nav_txn_receive'); ?> <?php echo te('nav_txn_receive'); ?></a>
        <a href="/modules/accounts/family_members.php"><?php echo ame_sb_dot('transactions', 'nav_txn_family'); ?> <?php echo te('nav_txn_family'); ?></a>
        <a href="/modules/accounts/list.php"><?php echo ame_sb_dot('transactions', 'nav_txn_accounts'); ?> <?php echo te('nav_txn_accounts'); ?></a>
        <a href="/modules/accounts/transfer.php"><?php echo ame_sb_dot('transactions', 'nav_txn_transfer'); ?> <?php echo te('nav_txn_transfer'); ?></a>
        <a href="/modules/accounts/cash_book.php"><?php echo ame_sb_dot('transactions', 'nav_txn_all'); ?> <?php echo te('nav_txn_all'); ?></a>
        <a href="/modules/reports/cash_bank_summary.php"><?php echo ame_sb_dot('transactions', 'nav_cash_bank'); ?> <?php echo te('nav_cash_bank'); ?></a>
        <a href="/modules/reports/finance_summary.php"><?php echo ame_sb_dot('transactions', 'nav_income_exp'); ?> <?php echo te('nav_income_exp'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-acc')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-acc')">
        <span><?php echo ame_sb_icon('accounts'); ?> <?php echo te('nav_accounts'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-acc" class="menu-list hidden">
        <a href="/modules/accounts/daily_audit.php"><?php echo ame_sb_dot('accounts', 'nav_daily_audit'); ?> <?php echo te('nav_daily_audit'); ?></a>
        <a href="/modules/accounts/bank_statement.php"><?php echo ame_sb_dot('accounts', 'nav_bank_statement'); ?> <?php echo te('nav_bank_statement'); ?></a>
        <a href="/modules/accounts/net_profit_report.php"><?php echo ame_sb_dot('accounts', 'nav_net_pl'); ?> <?php echo te('nav_net_pl'); ?></a>
        <a href="/modules/accounts/vat_tax_summary.php"><?php echo ame_sb_dot('accounts', 'nav_vat'); ?> <?php echo te('nav_vat'); ?></a>
    </div>
</div>
<a href="/modules/accounts/partner_central_dashboard.php" class="direct-link <?php echo str_contains($script_now, '/modules/accounts/partner_central_dashboard.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('suppliers'); ?> <?php echo ame_l('পার্টনারশিপ ড্যাশবোর্ড', 'Partnership dashboard'); ?>
</a>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-bloan')): ?>
<a href="/modules/bloan/desk.php" class="direct-link <?php echo str_contains($script_now, '/modules/bloan/desk.php') ? 'active' : ''; ?>">
    <?php echo ame_sb_icon('loans'); ?> <?php echo ame_l('ব্যবসার ঋণ', 'Business Loan'); ?>
</a>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-warranty')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-warranty')">
        <span><?php echo ame_sb_icon('warranty'); ?> <?php echo te('nav_warranty'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-warranty" class="menu-list hidden">
        <a href="/modules/reports/warranty.php"><?php echo ame_sb_dot('warranty', 'nav_warranty_search'); ?> <?php echo te('nav_warranty_search'); ?></a>
        <a href="/modules/reports/warranty_report.php"><?php echo ame_sb_dot('warranty', 'nav_warranty_report'); ?> <?php echo te('nav_warranty_report'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-reports')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-reports')">
        <span><?php echo ame_sb_icon('reports'); ?> <?php echo te('nav_reports'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-reports" class="menu-list hidden">
        <a href="/modules/reports/live_work.php"><?php echo ame_sb_dot('reports', 'nav_report_dash'); ?> লাইভ ড্যাশবোর্ড</a>
        <a href="/modules/reports/index.php"><?php echo ame_sb_dot('reports', 'nav_report_dash'); ?> <?php echo te('nav_report_dash'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-family')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-family')">
        <span><?php echo ame_sb_icon('family'); ?> <?php echo te('nav_family'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-family" class="menu-list hidden">
        <a href="/modules/family/index.php"><?php echo ame_sb_family_icon('dash'); ?> <?php echo te('nav_family_dash'); ?></a>
        <a href="/modules/family/members.php"><?php echo ame_sb_family_icon('members'); ?> <?php echo te('nav_family_members'); ?></a>
        <a href="/modules/family/contacts.php"><?php echo ame_sb_family_icon('contacts'); ?> <?php echo te('nav_family_contacts'); ?></a>
        <a href="/modules/family/doctors.php"><?php echo ame_sb_family_icon('health'); ?> <?php echo te('nav_family_health'); ?></a>
        <a href="/modules/family/shopping.php"><?php echo ame_sb_family_icon('shop'); ?> <?php echo te('nav_family_shop'); ?></a>
        <a href="/modules/family/calendar.php"><?php echo ame_sb_family_icon('cal'); ?> <?php echo te('nav_family_cal'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-others')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-others')">
        <span><?php echo ame_sb_icon('others'); ?> <?php echo te('nav_others'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-others" class="menu-list hidden">
        <a href="/modules/contacts/list.php"><?php echo ame_sb_dot('others', 'nav_contacts'); ?> <?php echo te('nav_contacts'); ?></a>
        <a href="/modules/personal/index.php"><?php echo ame_sb_dot('others', 'nav_personal'); ?> <?php echo te('nav_personal'); ?></a>
        <a href="/modules/utilities/shared_bills.php"><?php echo ame_sb_dot('others', 'nav_shared_bills'); ?> <?php echo te('nav_shared_bills'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-ticker') || has_permission($db, 'm-settings')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-ticker')">
        <span><?php echo ame_sb_icon('ticker'); ?> <?php echo te('nav_ticker'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-ticker" class="menu-list hidden">
        <a href="/modules/settings/scrolling_text.php"><?php echo ame_sb_dot('ticker', 'nav_ticker_edit'); ?> <?php echo te('nav_ticker_edit'); ?></a>
        <a href="/modules/settings/scrolling_text.php"><?php echo ame_sb_dot('ticker', 'nav_icon_pack'); ?> <?php echo te('nav_icon_pack'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-countdown') || has_permission($db, 'm-settings')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-countdown')">
        <span><?php echo ame_sb_icon('countdown'); ?> <?php echo te('nav_countdown'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-countdown" class="menu-list hidden">
        <a href="/modules/settings/countdown.php"><?php echo ame_sb_dot('countdown', 'nav_countdown_edit'); ?> <?php echo te('nav_countdown_edit'); ?></a>
        <a href="/modules/settings/countdown.php"><?php echo ame_sb_dot('countdown', 'nav_icon_pack'); ?> <?php echo te('nav_icon_pack'); ?></a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin || has_permission($db, 'm-settings')): ?>
<div class="menu-section">
    <div class="menu-head" onclick="toggleAccordion('m-settings')">
        <span><?php echo ame_sb_icon('settings'); ?> <?php echo te('nav_settings'); ?></span><span class="arrow">▼</span>
    </div>
    <div id="m-settings" class="menu-list hidden">
        <?php
        $ame_pct_on = 0;
        if (isset($db) && $db instanceof mysqli) {
            if (!function_exists('get_invoice_settings') && is_file(__DIR__ . '/../includes/invoice_functions.php')) {
                require_once __DIR__ . '/../includes/invoice_functions.php';
            }
            if (function_exists('get_invoice_settings')) {
                try {
                    $ame_pct_set = get_invoice_settings($db);
                    $ame_pct_on = !empty($ame_pct_set['pos_discount_auto']) ? 1 : 0;
                } catch (Throwable $e) {
                    $ame_pct_on = 0;
                }
            }
        }
        ?>
        <div class="sb-pct-card<?php echo $ame_pct_on ? ' is-on' : ' is-off'; ?>">
            <span class="sb-sub-icon sb-sub-icon-chip sb-sub-icon-chip-lg" aria-hidden="true" style="background:#ffffff !important;color:#0b5cad !important;border:2px solid #ffffff;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/><path d="M18 6L6 18"/></svg>
            </span>
            <span class="sb-pct-copy">
                <strong>ছাড় পার্সেন্ট</strong>
                <small><?php echo $ame_pct_on ? 'চালু — প্রতি বিক্রয়ে ১২% অটো মাইনাস' : 'বন্ধ — চালু করলে প্রতি বিক্রয়ে ১২% অটো মাইনাস'; ?></small>
            </span>
            <span class="sb-pct-actions" role="group" aria-label="ছাড় পার্সেন্ট চালু বা বন্ধ">
                <form method="post" action="/modules/settings/discount_switch.php">
                    <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                    <input type="hidden" name="pos_discount_auto" value="1">
                    <button type="submit" class="sb-pct-pill<?php echo $ame_pct_on ? ' is-pick' : ''; ?>" aria-pressed="<?php echo $ame_pct_on ? 'true' : 'false'; ?>" title="চালু করলে প্রতি বিক্রয়ে শতকরা ১২% অটো মাইনাস হয়, যতক্ষণ না এখান থেকে বন্ধ করা হয়">চালু</button>
                </form>
                <form method="post" action="/modules/settings/discount_switch.php">
                    <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                    <input type="hidden" name="pos_discount_auto" value="0">
                    <button type="submit" class="sb-pct-pill<?php echo $ame_pct_on ? '' : ' is-pick'; ?>" aria-pressed="<?php echo $ame_pct_on ? 'false' : 'true'; ?>" title="বন্ধ করলে বিক্রয় থেকে লক পার্সেন্ট চলে যাবে">বন্ধ</button>
                </form>
            </span>
        </div>
        <a href="/modules/settings/file_atlas.php" class="sb-atlas-open" title="<?php echo htmlspecialchars($englishAtlas ? 'File atlas — what each file does' : 'ফাইল খাতা — কোন ফাইলে কী কাজ', ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ame_sb_settings_icon('atlas', true); ?>
            <span class="sb-theme-copy">
                <strong><?php echo $englishAtlas ? 'File atlas' : 'ফাইল খাতা'; ?></strong>
                <small><?php echo $englishAtlas ? 'What every folder and file does' : 'কোন ফোল্ডারে কী কাজ, কোন ফাইলে কী কাজ'; ?></small>
            </span>
        </a>
        <?php /* v34: আগে এই বাটনটা একটা আলাদা পপ-আপ (মডাল) খুলত, যেটাতে
                 আবার নিজস্ব "Workspace"/"ঘড়ি"/"কাউন্টার" ৩টা ট্যাব ছিল —
                 এটাই "একাধিক কালার অপশন" এর মূল কারণ ছিল, কারণ এই পপ-আপের
                 "Workspace" ট্যাবটা আসলে নিচের /modules/settings/theme.php
                 পেজেরই একটা ডুপ্লিকেট (একই ডাটাবেজে সেভ হতো)। এখন থেকে এই
                 লিংকটা সরাসরি সেই একটাই ক্যানোনিকাল "রং ও ডিজাইন" পেজে
                 নিয়ে যায় — পুরো সাইটে রং বদলানোর জায়গা এখন এই একটাই। */ ?>
        <a href="/modules/settings/theme.php" class="sb-theme-open" title="<?php echo te('vd_menu_hint'); ?>">
            <?php echo ame_sb_settings_icon('theme', true); ?>
            <span class="sb-theme-copy">
                <strong><?php echo te('nav_theme_color'); ?></strong>
                <small><?php echo te('vd_menu_hint'); ?></small>
            </span>
        </a>
        <a href="/modules/settings/dashboard_settings.php"><?php echo ame_sb_settings_icon('shop_info'); ?> <?php echo te('nav_shop_info'); ?></a>
        <a href="/modules/settings/international_standards.php">আন্তর্জাতিক মান</a>
        <a href="/modules/settings/pos_title.php"><?php echo ame_sb_settings_icon('pos_title'); ?> <?php echo te('nav_pos_title'); ?></a>
        <a href="/modules/settings/layout_designer.php" style="color:#d97706;font-weight:700;">📐 <?php echo current_lang() === 'en' ? 'Layout Design' : 'লেআউট ডিজাইন'; ?></a>
        <a href="/modules/settings/text_style.php" style="color:#0f766e;font-weight:700;">🔤 <?php echo current_lang() === 'en' ? 'Page text size & color' : 'লেখা বড় ছোট ও রঙ'; ?></a>
        <a href="/modules/settings/stores.php"><?php echo ame_sb_settings_icon('stores'); ?> <?php echo te('nav_stores'); ?></a>
        <a href="/modules/settings/scrolling_text.php"><?php echo ame_sb_settings_icon('ticker'); ?> <?php echo te('nav_ticker'); ?></a>
        <a href="/modules/settings/countdown.php"><?php echo ame_sb_settings_icon('countdown'); ?> <?php echo te('nav_countdown'); ?></a>
        <a href="/modules/settings/banners.php"><?php echo ame_sb_settings_icon('banners'); ?> <?php echo te('nav_home_banner'); ?></a>
        <a href="/modules/settings/trust.php"><?php echo ame_sb_settings_icon('trust'); ?> <?php echo te('nav_trust'); ?></a>
        <a href="/modules/provenance/index.php"><?php echo ame_sb_settings_icon('provenance'); ?> <?php echo te('nav_provenance'); ?></a>
        <a href="/modules/settings/invoice_template.php"><?php echo ame_sb_settings_icon('invoice'); ?> <?php echo te('nav_invoice_tpl'); ?></a>
        <a href="/modules/settings/super_admin.php" style="color:#3D2A12;font-weight:800;">★ সুপার অ্যাডমিন</a>
        <a href="/modules/settings/role_control.php" style="color:#047857;font-weight:800;">রোল কন্ট্রোল</a>
        <a href="/modules/settings/users.php"><?php echo ame_sb_settings_icon('user_list'); ?> <?php echo te('nav_user_list'); ?></a>
        <a href="/modules/settings/user_add.php"><?php echo ame_sb_settings_icon('user_new'); ?> <?php echo te('nav_user_new'); ?></a>
        <a href="/modules/settings/role_permissions.php"><?php echo ame_sb_settings_icon('user_perm'); ?> <?php echo te('nav_user_perm'); ?></a>
        <a href="/modules/settings/sms_config.php"><?php echo ame_sb_settings_icon('sms_setup'); ?> <?php echo te('nav_sms_setup'); ?></a>
        <a href="/modules/settings/sms_log.php"><?php echo ame_sb_settings_icon('sms_log'); ?> <?php echo te('nav_sms_log'); ?></a>
        <a href="/modules/reports/live_work.php"><?php echo ame_sb_settings_icon('activity'); ?> লাইভ ড্যাশবোর্ড</a>
        <a href="/modules/settings/activity_logs.php"><?php echo ame_sb_settings_icon('activity'); ?> <?php echo te('nav_activity'); ?></a>
        <a href="/modules/reports/audit_trail.php"><?php echo ame_sb_settings_icon('audit'); ?> <?php echo te('nav_audit'); ?></a>
        <a href="/modules/settings/speed_system.php"><?php echo ame_sb_settings_icon('health'); ?> স্পিড সিস্টেম</a>
        <a href="/modules/settings/project_care.php"><?php echo ame_sb_settings_icon('project_care'); ?> প্রজেক্ট রক্ষা</a>
        <a href="/modules/settings/money_guard.php"><?php echo ame_sb_settings_icon('money_guard'); ?> টাকা মিল</a>
        <a href="/modules/settings/system_health.php"><?php echo ame_sb_settings_icon('health'); ?> <?php echo te('nav_health'); ?></a>
        <a href="/modules/settings/backup.php"><?php echo ame_sb_settings_icon('backup'); ?> <?php echo te('nav_backup'); ?></a>
        <a href="/modules/settings/lang_settings.php"><?php echo ame_sb_settings_icon('lang'); ?> <?php echo te('nav_lang'); ?></a>
        <?php if (env_flag_enabled('AME_ENABLE_MIGRATION_TOOLS')): ?>
        <a href="/import.php"><?php echo ame_sb_settings_icon('db_import'); ?> ডাটাবেজ ইনপুট</a>
        <?php endif; ?>
        <a href="/modules/settings/tools.php"><?php echo ame_sb_settings_icon('tools'); ?> <?php echo te('nav_tools'); ?></a>
        <a href="/modules/settings/change_password.php"><?php echo ame_sb_settings_icon('password'); ?> <?php echo te('nav_password'); ?></a>
        <a href="/modules/settings/profile.php"><?php echo ame_sb_settings_icon('profile'); ?> <?php echo te('nav_profile'); ?></a>
        <a href="/modules/settings/about.php"><?php echo ame_sb_settings_icon('about'); ?> <?php echo te('nav_about'); ?></a>
    </div>
</div>
<?php endif; ?>

<a href="/logout.php" class="direct-link" style="color: #E53935; border-top: 1px solid #E2E8F0; margin-top: 30px; padding-top: 16px;">
    <?php echo ame_sb_icon('logout'); ?> <?php echo te('nav_logout'); ?>
</a>
