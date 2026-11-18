<?php
/**
 * ব্যবসায়িক ডাটা ক্লিয়ার — টেবিলের কাঠামো ও লগইন রেখে
 * সব রিপোর্ট/লেনদেন/মাস্টার ডাটা মুছে দেয়।
 * DROP TABLE নেই। অন্য ডাটাবেজ থেকে ইমপোর্টের আগে চালান।
 */

function ame_factory_reset_keep_tables(): array
{
    return [
        'users',
        'settings',
        'theme_settings',
        'pos_title_line',
        'invoice_settings',
        'invoice_layout_templates',
        'dashboard_banners',
        'display_widgets',
        'sms_settings',
        'sms_sims',
        'whatsapp_settings',
        'user_roles',
        'role_permissions',
        'user_permissions',
        'system_flags',
        'system_routes',
        'app_settings',
        'worker_earnings_settings',
        'loan_settings',
        'accounts',
        'stores',
    ];
}

function ame_factory_reset_table_groups(): array
{
    return [
        'children' => [
            'sale_return_items', 'sale_returns_v2', 'sale_returns',
            'sale_payments', 'sale_services', 'sale_items', 'sale_warranties',
            'sale_exchange_items',
            'purchase_return_items', 'purchase_return_logs', 'purchase_returns', 'purchase_expenses',
            'profit_allocation_details',
            'loan_repayment_schedule', 'loan_fees', 'loan_recovery_actions',
            'loan_restructure_log', 'loan_settlements',
            'loan_installments', 'loan_transactions',
            'partner_acct_audit',
            'workshop_job_parts', 'workshop_quote_items',
            'store_transfer_items', 'stock_count_items',
            'service_commissions',
            'employee_advances',
            'employee_shop_deposits',
            'stock_movements',
            'worker_payment_allocations',
            'family_book_folds', 'family_book_pages', 'family_med_buys',
        ],
        'transactions' => [
            'sales', 'purchases', 'purchase_drafts',
            'customer_ledger', 'customer_payments', 'customer_withdrawals', 'customer_call_log',
            'installment_contracts', 'rental_contracts',
            'rental_owner_payments', 'rental_audit',
            'expenses', 'cash_closing', 'bill_collections',
            'account_ledger', 'ledger_provenance', 'ledger_provenance_meta',
            'loan_applications', 'loans', 'bank_loans',
            'partnership_adjustments', 'partner_withdrawals', 'partnership_openings',
            'partner_capital_transactions',
            'partner_acct_expenses', 'partner_acct_payments', 'partner_acct_months',
            'profit_allocations',
            'workshop_jobs', 'workshop_quotes',
            'store_transfers', 'stock_counts', 'product_serials', 'store_stock',
            'worker_payouts', 'worker_earnings', 'worker_payments', 'worker_audit_log',
            'attendance', 'leave_requests', 'salaries',
            'activity_logs', 'sms_log', 'sms_outbox', 'idempotency_keys',
            'login_attempts', 'password_resets', 'password_reset_otps', 'critical_action_otps',
            'family_shopping', 'family_inventory', 'family_expenses', 'family_events',
            'family_documents', 'family_notes', 'family_access', 'family_books',
        ],
        'master' => [
            'products', 'customers', 'suppliers',
            'categories', 'brands', 'product_units',
            'customer_groups', 'customer_areas',
            'contacts', 'contact_groups',
            'merchants', 'engineers', 'employees', 'departments', 'positions',
            'partners', 'loan_products',
            'rental_owners', 'rental_vehicles',
            'workshop_machine_parts', 'workshop_machines',
            'bill_partners', 'personal_categories',
            'expense_categories', 'pf_categories', 'pf_family_members', 'pf_merchants',
            'family_groups', 'family_contacts', 'family_members', 'family_doctors', 'family_medicines',
            'user_stores', 'worker_payment_rules',
        ],
        'staging' => [
            'customer', 'product', 'product_type', 'company', 'sale', 'sale_item',
            'sale_registered', 'sale_retail', 'purchase', 'purchase_item', 'pro_branch',
            'shop_info', 'user_info', 'emp_info',
            'ms_categories', 'ms_customers', 'ms_ledger', 'ms_products', 'ms_purchases',
            'ms_purchase_items', 'ms_sales', 'ms_sale_items', 'ms_settings',
            'ms_suppliers', 'ms_users',
        ],
    ];
}

function ame_factory_reset_mae_staging_tables(): array
{
    $out = [];
    foreach (['customer', 'product', 'product_type', 'company', 'sale', 'sale_item',
        'sale_registered', 'sale_retail', 'purchase', 'purchase_item', 'pro_branch',
        'shop_info', 'user_info', 'emp_info'] as $t) {
        $out[] = 'mae_' . $t;
    }
    return $out;
}

function ame_table_exists(mysqli $db, string $table): bool
{
    $safe = $db->real_escape_string($table);
    $r = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $r && $r->num_rows > 0;
}

function ame_list_base_tables(mysqli $db): array
{
    $out = [];
    $r = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    if (!$r) {
        return $out;
    }
    while ($row = $r->fetch_row()) {
        $out[] = (string)$row[0];
    }
    sort($out);
    return $out;
}

function ame_factory_reset_wipe_tables(mysqli $db): array
{
    $keep = array_fill_keys(ame_factory_reset_keep_tables(), true);
    $named = [];
    $groups = ame_factory_reset_table_groups();
    foreach (['children', 'transactions', 'master', 'staging'] as $g) {
        foreach ($groups[$g] as $t) {
            $named[] = $t;
        }
    }
    $named = array_merge($named, ame_factory_reset_mae_staging_tables());

    $live = ame_list_base_tables($db);
    $all = array_values(array_unique(array_merge($named, $live)));
    $wipe = [];
    foreach ($all as $table) {
        if (isset($keep[$table])) {
            continue;
        }
        $wipe[] = $table;
    }
    return $wipe;
}

function ame_safe_truncate(mysqli $db, string $table): bool
{
    if (!ame_table_exists($db, $table)) {
        return false;
    }
    return (bool)$db->query("TRUNCATE TABLE `{$table}`");
}

function ame_factory_reset_run(mysqli $db): array
{
    $targets = ame_factory_reset_wipe_tables($db);
    $stats = ['truncated' => 0, 'skipped' => 0, 'tables' => []];

    $db->query('SET FOREIGN_KEY_CHECKS=0');

    foreach ($targets as $table) {
        if (ame_safe_truncate($db, $table)) {
            $stats['truncated']++;
            $stats['tables'][] = $table;
        } else {
            $stats['skipped']++;
        }
    }

    if (ame_table_exists($db, 'accounts')) {
        $db->query('UPDATE accounts SET balance = 0');
    }
    if (ame_table_exists($db, 'users')) {
        $db->query('UPDATE users SET employee_id = NULL WHERE employee_id IS NOT NULL');
    }
    if (ame_table_exists($db, 'system_flags')) {
        $db->query("DELETE FROM system_flags WHERE flag_name IN ('single_balance_migrated')");
    }
    if (ame_table_exists($db, 'product_units')) {
        $n = $db->query('SELECT COUNT(*) AS c FROM product_units');
        $row = $n ? $n->fetch_assoc() : null;
        if ($row && (int)$row['c'] === 0) {
            $db->query("INSERT INTO product_units (unit_name) VALUES ('পিস')");
        }
    }

    $db->query('SET FOREIGN_KEY_CHECKS=1');

    $jobs_dir = dirname(__DIR__) . '/uploads/migrations/jobs';
    if (is_dir($jobs_dir)) {
        foreach (glob($jobs_dir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }
    $mig_dir = dirname(__DIR__) . '/uploads/migrations';
    if (is_dir($mig_dir)) {
        foreach (glob($mig_dir . '/mae_*.sql') ?: [] as $file) {
            @unlink($file);
        }
    }

    return $stats;
}

function ame_factory_reset_sql(mysqli $db): string
{
    $tables = ame_factory_reset_wipe_tables($db);
    $lines = [
        '-- APONSHOP — সব ব্যবসায়িক ডাটা মুছুন (টেবিল কাঠামো থাকে)',
        '-- phpMyAdmin-এ ইমপোর্ট করুন। ইউজার/সেটিংস/লগইন মুছবে না।',
        '-- তারপর অন্য ডাটাবেজের SQL ইমপোর্ট করতে পারবেন।',
        'SET NAMES utf8mb4;',
        'SET FOREIGN_KEY_CHECKS=0;',
        '',
    ];
    foreach ($tables as $table) {
        if (ame_table_exists($db, $table)) {
            $lines[] = 'TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`;';
        }
    }
    $lines[] = '';
    $lines[] = 'UPDATE `accounts` SET balance = 0;';
    $lines[] = 'UPDATE `users` SET employee_id = NULL WHERE employee_id IS NOT NULL;';
    $lines[] = "INSERT INTO `product_units` (unit_name) SELECT 'পিস' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `product_units` LIMIT 1);";
    $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $lines[] = '';
    return implode("\n", $lines);
}

function ame_factory_reset_keeps(): array
{
    return [
        'ইউজার ও লগইন (এডমিন দিয়ে ঢুকতে পারবেন)',
        'দোকানের সেটিংস (নাম, ঠিকানা, লোগো)',
        'অ্যাকাউন্ট তালিকা (ব্যালেন্স ০ হবে)',
        'স্টোর/শাখা তালিকা',
        'SMS/WhatsApp সেটিংস',
        'থিম, POS শিরোনাম, ইনভয়েস টেমপ্লেট',
        'পারমিশন ও রোল সেটআপ',
        'টেবিলের কাঠামো (স্কিমা) — কোনো টেবিল ড্রপ হয় না',
    ];
}

function ame_factory_reset_deletes(): array
{
    return [
        'সমস্ত কাস্টমার, পণ্য, সাপ্লায়ার, খাত',
        'সমস্ত বিক্রয়, ক্রয়, রিটার্ন, পেমেন্ট, বকেয়া, লেজার',
        'সমস্ত রিপোর্ট, অ্যাক্টিভিটি লগ, স্টক মুভমেন্ট',
        'কর্মী, অ্যাডভান্স, বেতন, হাজিরা',
        'ওয়ার্কশপ, লোন, পার্টনারশিপ, ভাড়া',
        'পারিবারিক খাতা সহ অন্য সব ব্যবসায়িক সারি',
    ];
}
