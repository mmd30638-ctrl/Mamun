<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/invoice_functions.php';
admin_only();
ensure_invoice_designer_schema($db);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'অনুমোদিত অনুরোধ নয়।']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ডাটা সঠিক নয়।']);
    exit;
}

// ================= CSRF যাচাই =================
$submitted_token = (string)($data['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।']);
    exit;
}

$id = (int)($data['id'] ?? 0);
$name = substr(trim((string)($data['name'] ?? '')), 0, 100);
$layout = $data['layout'] ?? null;

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'টেমপ্লেটের নাম দিন।']);
    exit;
}
if (!is_array($layout) || empty($layout)) {
    echo json_encode(['status' => 'error', 'message' => 'কমপক্ষে একটা বক্স যোগ করুন।']);
    exit;
}

// ================= প্রতিটা ব্লকের ডাটা যাচাই ও পরিষ্কার করা =================
$allowed_types = [
    'invoice_info', 'customer_info', 'product_table', 'payment_info',
    'summary_box', 'discount', 'shipping', 'notes', 'terms',
    'button_save', 'button_print',
];
$clean_layout = [];
foreach ($layout as $block) {
    if (!is_array($block)) continue;
    $type = (string)($block['type'] ?? '');
    if (!in_array($type, $allowed_types, true)) continue;

    $clean_layout[] = [
        'id' => (int)($block['id'] ?? 0),
        'type' => $type,
        'x' => max(0, (int)($block['x'] ?? 0)),
        'y' => max(0, (int)($block['y'] ?? 0)),
        'w' => max(30, (int)($block['w'] ?? 100)),
        'h' => max(20, (int)($block['h'] ?? 50)),
        'bg' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($block['bg'] ?? '')) ? $block['bg'] : '#ffffff',
        'border' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($block['border'] ?? '')) ? $block['border'] : '#90caf9',
        'z' => max(1, (int)($block['z'] ?? 1)),
    ];
}

if (empty($clean_layout)) {
    echo json_encode(['status' => 'error', 'message' => 'বৈধ কোনো বক্স পাওয়া যায়নি।']);
    exit;
}

$layout_json = json_encode($clean_layout, JSON_UNESCAPED_UNICODE);
$user_id = (int)($_SESSION['user_id'] ?? 0);

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE invoice_layout_templates SET name = ?, layout_json = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssi', $name, $layout_json, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            // হয়তো id ভুল বা টেমপ্লেট মুছে গেছে — নতুন হিসেবে সংরক্ষণ
            $id = 0;
        }
    }

    if ($id <= 0) {
        $stmt = $db->prepare("INSERT INTO invoice_layout_templates (name, layout_json, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $name, $layout_json, $user_id);
        $stmt->execute();
        $id = $stmt->insert_id;
    }

    log_activity('Invoice Template Saved', 'Settings', $name . ' (ID: ' . $id . ')');

    echo json_encode(['status' => 'success', 'id' => $id]);
} catch (Throwable $e) {
    error_log('Invoice template save failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।']);
}
