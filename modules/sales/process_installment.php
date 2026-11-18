<?php
/**
 * পুরনো হায়ার-পারচেজ এন্ডপয়েন্ট। ইঞ্জিন ফাইল নেই, তাই এখানে সেভ হয় না।
 * কিস্তি এখন কাস্টমার প্রোফাইলের process_installment দিয়ে চলে।
 */
require_once '../../includes/config.php';


require_post();
csrf_verify();
header('Content-Type: application/json; charset=UTF-8');
$engine = __DIR__ . '/../../includes/updates/12_hire_purchase_engine.php';
if (!is_file($engine)) {
    http_response_code(501);
    echo json_encode([
        'status' => 'error',
        'message' => 'এই কিস্তি ফিচার এখন চালু নেই। কাস্টমার প্রোফাইল থেকে কিস্তি নিন।',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once '../../includes/account_functions.php';
require_once $engine;

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'ডাটা পাওয়া যায়নি'], JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'এই কিস্তি ফিচার এখন চালু নেই।'], JSON_UNESCAPED_UNICODE);
