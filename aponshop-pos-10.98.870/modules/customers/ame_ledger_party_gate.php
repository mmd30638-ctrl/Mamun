<?php
/**
 * কাস্টমার লেজার থেকে ব্যালেন্স আপডেটের ঠিক আগে এক লাইনে কল করুন:
 *   ame_party_ledger_begin($db);
 * customer_ledger_functions.php-এর sync/post ফাংশনগুলোর শুরুতে।
 */
if (!function_exists('ame_party_ledger_begin')) {
    function ame_party_ledger_begin(?mysqli $db = null): void
    {
        if ($db instanceof mysqli && function_exists('ame_party_balance_allow_write')) {
            ame_party_balance_allow_write($db);
        }
    }
}