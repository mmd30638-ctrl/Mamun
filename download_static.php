<?php
/**
 * /downloads/*.zip সরাসরি পাবলিক লিংকে খোলা থাকত (কোনো লগইন ছাড়াই পুরো সোর্স কোড
 * নামানো যেত)। এখন এই ফাইলটাই একমাত্র পথ — admin_only() পাশ করলে তবেই ZIP স্ট্রিম হবে।
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/pack_zip.php';
admin_only();

$file = basename((string)($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.zip$/', $file)) {
    http_response_code(400);
    exit('❌ ফাইল নাম সঠিক নয়।');
}

$path = __DIR__ . '/downloads/' . $file;
$real = realpath($path);
$downloads_dir = realpath(__DIR__ . '/downloads');
if ($real === false || $downloads_dir === false || !str_starts_with($real, $downloads_dir . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('❌ ফাইল পাওয়া যায়নি।');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ame_stream_zip_file($real, $file);
