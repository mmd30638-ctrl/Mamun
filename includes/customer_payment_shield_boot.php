<?php
/**
 * payment-receive / collect ইত্যাদি — includes/config.php-এর পরে একবার require।
 * লাইভ ফাইলে থাকলে customer_ledger_functions.php শুরুতে:
 *   require_once __DIR__ . '/../../includes/customer_payment_shield_boot.php';
 */
if (!function_exists('ame_party_customer_payment_arm')) {
    function ame_party_customer_payment_arm(?mysqli $db = null): void
    {
        if (!($db instanceof mysqli)) {
            global $db;
        }
        if ($db instanceof mysqli && function_exists('ame_party_balance_allow_write')) {
            ame_party_balance_allow_write($db);
        }
    }
}
ame_party_customer_payment_arm(isset($db) && $db instanceof mysqli ? $db : null);