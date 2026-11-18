<?php
/**
 * পণ্য যোগ/এডিট পেজ থেকে ক্যাটেগরি/ব্র্যান্ড/ইউনিট — এই তিনটার যেকোনোটা
 * পেজ ছেড়ে না গিয়ে সরাসরি "+" বাটন থেকে নতুন তৈরি করার জন্য (AJAX)।
 * সাব-ক্যাটেগরি ও প্রস্তুতকারক এখানে নেই — কারণ products টেবিলে সেগুলো
 * সরাসরি লেখার (ফ্রি-টেক্সট) ফিল্ড, আলাদা কোনো তালিকা/টেবিল নেই।
 */
require_once '../../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'অনুমোদিত অনুরোধ নয়।'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_verify();

$map = [
    'category' => ['table' => 'categories', 'col' => 'name'],
    'brand' => ['table' => 'brands', 'col' => 'name'],
    'unit' => ['table' => 'product_units', 'col' => 'unit_name'],
];

$type = strtolower(trim((string)($_POST['type'] ?? '')));
$name = trim(clean_input($_POST['name'] ?? ''));

if (!isset($map[$type])) {
    echo json_encode(['success' => false, 'message' => 'অজানা ধরন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'নাম লিখুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($name, 'UTF-8') > 120) {
    $name = mb_substr($name, 0, 120, 'UTF-8');
}

$table = $map[$type]['table'];
$col = $map[$type]['col'];

try {
    // আগে থেকে একই নামে থাকলে সেটাই ব্যবহার করা হবে (ডুপ্লিকেট তৈরি হবে না)
    $stmt = $db->prepare("SELECT id FROM `{$table}` WHERE `{$col}` = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'id' => (int)$existing['id'],
            'name' => $name,
            'duplicate' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO `{$table}` (`{$col}`) VALUES (?)");
    $stmt->bind_param('s', $name);
    if (!$stmt->execute()) {
        throw new RuntimeException($db->error);
    }
    $newId = (int)$db->insert_id;
    $stmt->close();

    if (function_exists('log_activity')) {
        $labelMap = ['category' => 'ক্যাটেগরি', 'brand' => 'ব্র্যান্ড', 'unit' => 'ইউনিট'];
        log_activity('create', 'inventory', 'নতুন ' . ($labelMap[$type] ?? $type) . ' যোগ: ' . $name);
    }

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'name' => $name,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('quick_add_taxonomy failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'সেভ করা যায়নি।',
    ], JSON_UNESCAPED_UNICODE);
}
