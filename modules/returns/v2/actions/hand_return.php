<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$out = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (empty($_SESSION['user_id'])) {
    $out(['ok' => false, 'error' => 'লগইন করা নেই।'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $q = str_replace(['%', '_'], '', $q);
    $products = [];
    $customers = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $ps = $db->prepare('SELECT id, name, stock, sale_price FROM products WHERE ' . ame_product_option_sql('status') . ' AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?) ORDER BY name ASC LIMIT 12');
        if ($ps) {
            $ps->bind_param('sss', $like, $like, $like);
            $ps->execute();
            $res = $ps->get_result();
            while ($row = $res->fetch_assoc()) {
                $products[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'stock' => (float)$row['stock'],
                    'price' => (float)$row['sale_price'],
                ];
            }
            $ps->close();
        }
        $pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
        $cs = $db->prepare('SELECT id, name, phone FROM customers WHERE (' . $pick . ') AND (name LIKE ? OR phone LIKE ?) ORDER BY name ASC LIMIT 8');
        if ($cs) {
            $cs->bind_param('ss', $like, $like);
            $cs->execute();
            $res = $cs->get_result();
            while ($row = $res->fetch_assoc()) {
                $customers[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'phone' => (string)($row['phone'] ?? ''),
                ];
            }
            $cs->close();
        }
    }
    $out(['ok' => true, 'products' => $products, 'customers' => $customers]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $out(['ok' => false, 'error' => 'শুধু ফর্ম জমা দিয়ে করা যায়।'], 405);
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $csrf === '' || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    $out(['ok' => false, 'error' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করুন।'], 403);
}

try {
    $out(ame_post_hand_product_return($db, $_POST));
} catch (Throwable $e) {
    $out(['ok' => false, 'error' => $e->getMessage()], 400);
}
