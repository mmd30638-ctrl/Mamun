<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/critical_action_otp.php';
ensure_user_schema($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: profile.php');
    exit();
}

csrf_verify();

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: /login.php');
    exit();
}

$phone = trim((string)($_POST['phone'] ?? ''));
$whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if (strlen($phone) > 20 || strlen($whatsapp) > 20 || strlen($address) > 255) {
    die('❌ মোবাইল বা ঠিকানার দৈর্ঘ্য বেশি হয়েছে। <a href="edit_profile.php">ফিরে যান</a>');
}

if ($whatsapp !== '' && !ame_validate_bd_phone($whatsapp)) {
    die('❌ সঠিক WhatsApp নম্বর দিন (01XXXXXXXXX)। <a href="edit_profile.php">ফিরে যান</a>');
}
if ($whatsapp !== '') {
    $whatsapp = ame_normalize_bd_phone_display($whatsapp);
}

$photo_name = null;
if (!empty($_FILES['photo']['name'])) {
    $photo_name = save_user_image($_FILES['photo']);
    if ($photo_name === null) {
        die('❌ ছবি আপলোড করা যায়নি। JPG, PNG বা WebP ছবি সর্বোচ্চ ২ MB হতে হবে। <a href="edit_profile.php">ফিরে যান</a>');
    }
}

if ($photo_name !== null) {
    $stmt = $db->prepare("UPDATE users SET phone = ?, whatsapp = ?, address = ?, photo = ? WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ssssi", $phone, $whatsapp, $address, $photo_name, $user_id);
    }
} else {
    $stmt = $db->prepare("UPDATE users SET phone = ?, whatsapp = ?, address = ? WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("sssi", $phone, $whatsapp, $address, $user_id);
    }
}

if (!$stmt || !$stmt->execute()) {
    if ($stmt) {
        $stmt->close();
    }
    die('❌ প্রোফাইল আপডেট করা যায়নি। <a href="edit_profile.php">আবার চেষ্টা করুন</a>');
}
$stmt->close();

if (function_exists('log_activity')) {
    log_activity('Profile Updated', 'Settings', 'নিজের প্রোফাইল আপডেট করা হয়েছে');
}

header('Location: profile.php?updated=1');
exit();