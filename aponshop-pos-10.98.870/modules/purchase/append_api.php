<?php
/**
 * 10.98.346 — ক্রয় ডেস্ক API: খোঁজ/যোগ/আপডেট/বাদ/হেডার/সাপ্লায়ার।
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = [
    __DIR__ . '/../../includes/config.php',
    __DIR__ . '/../../../includes/config.php',
];
$loaded = false;
foreach ($config as $cfg) {
    if (is_file($cfg)) {
        require_once $cfg;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'কনফিগ পাওয়া যায়নি'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../includes/purchase_append.php';
require_once __DIR__ . '/../../includes/purchase_edit.php';

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'ডাটাবেজ নেই'], JSON_UNESCAPED_UNICODE);
    exit;
}

function ame_purchase_api_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ame_purchase_logged_in()) {
    ame_purchase_api_fail('লগইন লাগবে।', 401);
}

$do = trim((string) ($_GET['do'] ?? $_POST['do'] ?? 'search'));

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $do === 'search' || $do === 'ping' || $do === 'invoice' || $do === 'list' || $do === 'bundle' || $do === 'items' || $do === 'revisions' || $do === 'suppliers') {
    if ($do === 'ping') {
        $map = ame_purchase_map($db);
        echo json_encode([
            'ok' => true,
            'version' => is_file(__DIR__ . '/../../VERSION.txt')
                ? trim((string) file_get_contents(__DIR__ . '/../../VERSION.txt'))
                : '10.98.346',
            'head' => $map['head'],
            'item' => $map['item'],
            'product' => $map['product'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'invoice') {
        $id = (int) ($_GET['id'] ?? $_GET['invoice_id'] ?? 0);
        $row = ame_purchase_find($db, $id);
        if (!$row) {
            ame_purchase_api_fail('মেমো পাওয়া যায়নি।', 404);
        }
        echo json_encode(['ok' => true, 'invoice' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'list') {
        echo json_encode(['ok' => true, 'invoices' => ame_purchase_list_invoices($db)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'suppliers') {
        $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
        echo json_encode(['ok' => true, 'suppliers' => ame_purchase_list_suppliers($db, $q)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'items' || $do === 'revisions' || $do === 'bundle') {
        $id = (int) ($_GET['id'] ?? $_GET['invoice_id'] ?? 0);
        if ($id <= 0) {
            ame_purchase_api_fail('মেমো নম্বর নেই।');
        }
        $invoice = ame_purchase_find($db, $id);
        if (!$invoice) {
            ame_purchase_api_fail('মেমো পাওয়া যায়নি।', 404);
        }
        echo json_encode([
            'ok' => true,
            'invoice' => $invoice,
            'items' => ame_purchase_list_items($db, $id),
            'revisions' => ame_purchase_list_revisions($db, $id),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $q = trim((string) ($_GET['q'] ?? $_GET['query'] ?? $_POST['q'] ?? ''));
    echo json_encode([
        'ok' => true,
        'products' => $q === '' ? [] : ame_purchase_search_products($db, $q),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ame_purchase_api_fail('Invalid request method', 405);
}

$session_token = (string) ($_SESSION['csrf_token'] ?? '');
$posted_token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token !== '' && ($posted_token === '' || !hash_equals($session_token, $posted_token))) {
    ame_purchase_api_fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে।', 403);
}

$id = (int) ($_POST['invoice_id'] ?? $_POST['purchase_id'] ?? $_POST['id'] ?? $_POST['pid'] ?? 0);
if ($id <= 0) {
    ame_purchase_api_fail('মেমো নম্বর নেই।');
}

try {
    if ($do === 'update') {
        $patch = [];
        if (isset($_POST['qty']) || isset($_POST['quantity'])) {
            $patch['qty'] = (float) ($_POST['qty'] ?? $_POST['quantity']);
        }
        if (isset($_POST['price']) || isset($_POST['rate'])) {
            $patch['price'] = (float) ($_POST['price'] ?? $_POST['rate']);
        }
        if (isset($_POST['discount'])) {
            $patch['discount'] = (float) $_POST['discount'];
        }
        $result = ame_purchase_update_item($db, $id, (int) ($_POST['item_id'] ?? $_POST['line_id'] ?? 0), $patch);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'remove') {
        $result = ame_purchase_remove_item($db, $id, (int) ($_POST['item_id'] ?? $_POST['line_id'] ?? 0));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'header') {
        $result = ame_purchase_update_header($db, $id, [
            'number' => $_POST['number'] ?? $_POST['invoice_no'] ?? null,
            'date' => $_POST['date'] ?? null,
            'supplier_id' => $_POST['supplier_id'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do !== 'add' && $do !== 'append') {
        ame_purchase_api_fail('অজানা কাজ');
    }
    $result = ame_purchase_append_item($db, $id, [
        'product_id' => (int) ($_POST['product_id'] ?? $_POST['pid'] ?? 0),
        'name' => (string) ($_POST['name'] ?? $_POST['product_name'] ?? ''),
        'code' => (string) ($_POST['code'] ?? $_POST['sku'] ?? ''),
        'qty' => (float) ($_POST['qty'] ?? $_POST['quantity'] ?? 1),
        'price' => (float) ($_POST['price'] ?? $_POST['purchase_price'] ?? $_POST['rate'] ?? 0),
    ]);
    if (function_exists('ame_purchase_log_revision')) {
        ame_purchase_log_revision($db, $id, 'add', 'পণ্য যোগ', $result);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('purchase append: ' . $e->getMessage());
    ame_purchase_api_fail($e->getMessage(), 400);
}
