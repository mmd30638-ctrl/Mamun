<?php
/**
 * বিক্রয় ফেরত মডিউল — একমাত্র সাইডবার এন্ট্রি পয়েন্ট।
 */
require_once __DIR__ . '/sales_return_module.php';

header('Location: ' . ame_sales_return_hub_url(), true, 302);
exit;