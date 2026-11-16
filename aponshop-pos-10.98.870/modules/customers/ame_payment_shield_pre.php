<?php
/**
 * payment-receive.php / collect পেজে config লোডের পর এক লাইনে যোগ করুন (লাইভ):
 *   require_once __DIR__ . '/ame_payment_shield_pre.php';
 * কাস্টমার ব্যালেন্স আপডেটের আগে শিল্ড অনুমোদন।
 */
if (isset($db) && $db instanceof mysqli && function_exists('ame_party_balance_allow_write')) {
    ame_party_balance_allow_write($db);
}