<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'শুধু পোস্ট করা যাবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$submitted = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || $submitted === '' || !hash_equals((string)$_SESSION['csrf_token'], $submitted)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['customer_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'কাস্টমার পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = $db->prepare('SELECT id, photo FROM customers WHERE id = ?');
$check->bind_param('i', $id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'কাস্টমার পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$photo_path = '';
$photo_jpeg = trim((string)($_POST['photo_jpeg'] ?? ''));
if ($photo_jpeg !== '') {
    if (preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $photo_jpeg)) {
        $photo_jpeg = substr($photo_jpeg, strpos($photo_jpeg, ',') + 1);
    }
    $binary = base64_decode($photo_jpeg, true);
    if ($binary !== false && strlen($binary) > 32 && strlen($binary) <= 6 * 1024 * 1024) {
        $photo_path = ame_customer_save_converted_image($binary);
    }
}

if ($photo_path === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $max_bytes = 5 * 1024 * 1024;
    if ($_FILES['photo']['size'] <= 0 || $_FILES['photo']['size'] > $max_bytes) {
        echo json_encode(['success' => false, 'message' => 'ছবি ৫ মেগাবাইটের মধ্যে দিন।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed_mimes[$real_mime])) {
        echo json_encode(['success' => false, 'message' => 'শুধু JPG, PNG বা WEBP ছবি চলবে।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $binary = file_get_contents($_FILES['photo']['tmp_name']);
    $photo_path = $binary ? ame_customer_save_converted_image($binary) : '';
    if ($photo_path === '') {
        $upload_dir = __DIR__ . '/../../../uploads/customers/';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            echo json_encode(['success' => false, 'message' => 'ছবি ফোল্ডার তৈরি হয়নি।'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $filename = 'cus_' . time() . '_' . random_int(1000, 9999) . '.jpg';
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
            echo json_encode(['success' => false, 'message' => 'ছবি সেভ হয়নি। আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $photo_path = 'uploads/customers/' . $filename;
    }
}

if ($photo_path === '') {
    echo json_encode(['success' => false, 'message' => 'একটা ছবি সিলেক্ট করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $db->prepare('UPDATE customers SET photo = ? WHERE id = ?');
$stmt->bind_param('si', $photo_path, $id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'ডাটাবেসে ছবি বসানো যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'ছবি সেভ হয়েছে।',
    'photo_url' => ame_customer_photo_url($photo_path),
], JSON_UNESCAPED_UNICODE);
