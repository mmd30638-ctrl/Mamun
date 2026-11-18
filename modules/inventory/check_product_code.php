<?php
require_once '../../includes/config.php';
require_once '../../includes/product_code_functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'অনুমোদিত অনুরোধ নয়।'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $product_code = normalize_product_code($_POST['sku'] ?? '');
    $exclude_id = max(0, (int)($_POST['exclude_id'] ?? 0));
    $holder = product_code_holder($db, $product_code, $exclude_id);
    $exists = $holder !== null;

    echo json_encode([
        'success' => true,
        'exists' => $exists,
        'product_code' => $product_code,
        'holder' => $holder
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Product code check failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'পণ্য আইডি পরীক্ষা করা যায়নি।'
    ], JSON_UNESCAPED_UNICODE);
}
