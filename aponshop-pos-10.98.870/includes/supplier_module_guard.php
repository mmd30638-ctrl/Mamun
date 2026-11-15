<?php
/**
 * সাপ্লায়ার মডিউল লক — ভিউ পেজ `total_due` লেখে না।
 *
 * অনুমোদিত লেখা (আপনার অনুমতি / ব্যবসায়িক ইভেন্ট):
 * · ক্রয় (purchase process)
 * · সাপ্লায়ার পেমেন্ট (process_pay)
 * · অ্যাডমিন প্রোফাইল সংশোধন (process_save + নিশ্চিত টিক)
 * · অ্যাডমিন টুল: ব্যালেন্স গার্ড, সাইন-চেক সিঙ্ক (Party Shield দিয়ে)
 *
 * পেজ লোড, Easy Update ZIP, তালিকা/স্টেটমেন্ট খোলা — ব্যালেন্স বদলায় না।
 *
 * @version 1.0.0
 */
if (!defined('AME_SUPPLIER_MODULE_GUARD_VER')) {
    define('AME_SUPPLIER_MODULE_GUARD_VER', '1.0.0');
}

if (!function_exists('ame_supplier_module_view_boot')) {
    /** প্রদর্শন-পেজে — শুধু পড়া */
    function ame_supplier_module_view_boot(): void
    {
        if (!defined('AME_SUPPLIER_VIEW_ONLY')) {
            define('AME_SUPPLIER_VIEW_ONLY', true);
        }
    }
}

if (!function_exists('ame_supplier_module_write_boot')) {
    /** প্রক্রিয়া পেজে — Shield-অনুমোদিত লেখা */
    function ame_supplier_module_write_boot(mysqli $db): void
    {
        if (function_exists('ame_party_balance_write_enable')) {
            ame_party_balance_write_enable($db);
        }
    }
}

if (!function_exists('ame_supplier_module_admin_write_boot')) {
    /** অ্যাডমিন ম্যানুয়াল সংশোধন */
    function ame_supplier_module_admin_write_boot(mysqli $db): void
    {
        if (function_exists('ame_party_admin_manual_enable')) {
            ame_party_admin_manual_enable($db);
        }
        ame_supplier_module_write_boot($db);
    }
}

if (!function_exists('ame_supplier_amount_policy_note')) {
    function ame_supplier_amount_policy_note(): string
    {
        return '<p class="sup-policy-lock" role="note">'
            . 'লক: বকেয়া শুধু <strong>ক্রয়</strong>, <strong>পেমেন্ট</strong> বা <strong>অ্যাডমিন সংশোধন</strong> এ বদলায়। '
            . 'আপডেট/তালিকা খুললে অটো পরিবর্তন হয় না (Party Amount Shield + ' . htmlspecialchars(AME_SUPPLIER_MODULE_GUARD_VER, ENT_QUOTES, 'UTF-8') . ').'
            . '</p>';
    }
}