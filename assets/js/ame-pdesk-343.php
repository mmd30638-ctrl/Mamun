<?php
/**
 * 10.98.346 — পুরনো ৩৪৩ পাথও নতুন ডেস্ক দেয় (ক্যাশড HTML থাকলে)।
 */
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$js = __DIR__ . '/ame-purchase-desk.js';
if (!is_file($js)) {
    http_response_code(404);
    echo '/* missing ame-purchase-desk.js */';
    exit;
}
readfile($js);
