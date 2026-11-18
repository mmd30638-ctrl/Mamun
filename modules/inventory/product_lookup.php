<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/product_code_functions.php';
require_once '../../includes/product_studio.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'অনুমোদিত অনুরোধ নয়।'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_verify();
ensure_inventory_schema($db);

$field = strtolower(trim((string)($_POST['field'] ?? 'sku')));
$value = (string)($_POST['value'] ?? '');
$exclude_id = max(0, (int)($_POST['exclude_id'] ?? 0));

try {
    if ($field === 'barcode') {
        $code = product_studio_normalize_barcode($value);
        if ($code === null) {
            echo json_encode([
                'success' => true,
                'exists' => false,
                'value' => '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'success' => true,
            'exists' => product_studio_barcode_exists($db, $code, $exclude_id),
            'value' => $code,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $code = normalize_product_code($value);
    $holder = product_code_holder($db, $code, $exclude_id);
    echo json_encode([
        'success' => true,
        'exists' => $holder !== null,
        'value' => $code,
        'holder' => $holder,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    echo json_encode([
        'success' => false,
        'code' => 'invalid',
        'message' => $error->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Product lookup failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'পরীক্ষা করা যায়নি।',
    ], JSON_UNESCAPED_UNICODE);
}
