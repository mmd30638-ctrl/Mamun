<?php
/**
 * ক্রয় পেজ থেকে সরাসরি নতুন সাপ্লায়ার যোগ (quick add) — modules/suppliers/process_add.php-এর
 * মতোই সরল INSERT, শুধু JSON রেসপন্স দেয় যাতে ক্রয় পেজেই সাথে সাথে সিলেক্ট করা যায়।
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/lang_helper.php';
require_once __DIR__ . '/../../../includes/supplier_functions.php';
require_once __DIR__ . '/../../../includes/purchase_functions.php';
admin_only();

header('Content-Type: application/json; charset=UTF-8');

$is_english = current_lang() === 'en';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => ame_l('অনুমোদিত অনুরোধ নয়।', 'Invalid request method.')], JSON_UNESCAPED_UNICODE);
    exit;
}
csrf_verify();

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$opening = (float)str_replace([',', '৳'], '', (string)($_POST['opening_balance'] ?? $_POST['previous_balance'] ?? '0'));

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ সাপ্লায়ারের নাম দিন।', '⚠️ Enter the supplier name.')], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($name, 'UTF-8') > 150) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ নাম অনেক বড় হয়ে গেছে।', '⚠️ The name is too long.')], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($phone !== '' && mb_strlen($phone, 'UTF-8') > 30) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ মোবাইল নম্বর সঠিক নয়।', '⚠️ The mobile number is invalid.')], JSON_UNESCAPED_UNICODE);
    exit;
}

// একই নাম আগে থেকে থাকলে সতর্ক করা (ভুলবশত ডুপ্লিকেট সাপ্লায়ার তৈরি ঠেকাতে)
if (!function_exists('ame_supplier_live_sql')) {
    require_once __DIR__ . '/../../../includes/supplier_functions.php';
}
ame_supplier_schema_boot($db);
$check = $db->prepare("SELECT id FROM suppliers WHERE name = ? AND purged_at IS NULL LIMIT 1");
$check->bind_param('s', $name);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ এই নামে একজন সাপ্লায়ার ইতিমধ্যে আছেন। তালিকা থেকে সার্চ করে বেছে নিন।', '⚠️ A supplier already exists with this name. Search the list and pick it.')], JSON_UNESCAPED_UNICODE);
    exit;
}
$check->close();

try {
    $supplier_id = ame_supplier_save($db, [
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'opening_balance' => $opening,
    ], 0);
    if (abs($opening) > 0.004) {
        ame_supplier_set_previous_balance($db, $supplier_id, $opening, 'ক্রয় পেজ থেকে নতুন সাপ্লায়ার');
    }

    if (function_exists('log_activity')) {
        log_activity('Quick Supplier Added', 'Suppliers', $name . ' (ক্রয় পেজ থেকে)');
    }

    echo json_encode([
        'status' => 'success',
        'supplier' => [
            'id' => $supplier_id,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'total_due' => round($opening, 2),
            'opening_balance' => round($opening, 2),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Quick add supplier failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ সাপ্লায়ার যোগ করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।', '⚠️ Could not add the supplier. Please try again.')], JSON_UNESCAPED_UNICODE);
}
