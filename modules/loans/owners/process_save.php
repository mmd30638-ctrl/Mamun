<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$owner_name = clean_input($_POST['owner_name'] ?? '');
$parent_name = clean_input($_POST['parent_name'] ?? '');
$nid_no = clean_input($_POST['nid_no'] ?? '');
$mobile = clean_input($_POST['mobile'] ?? '');
$address = clean_input($_POST['address'] ?? '');
$shop_name = clean_input($_POST['shop_name'] ?? '');
$shop_type = clean_input($_POST['shop_type'] ?? '');
$business_address = clean_input($_POST['business_address'] ?? '');
$trade_license_no = clean_input($_POST['trade_license_no'] ?? '');
$status = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($owner_name === '' || $mobile === '' || $shop_name === '') {
    $back = $id > 0 ? 'add.php?id=' . $id : 'add.php';
    redirect($back . '&err=' . rawurlencode(ame_l('মালিকের নাম, মোবাইল ও দোকানের নাম আবশ্যক।', 'Owner name, mobile and shop name are required.')));
}

// ছবি আপলোড (ঐচ্ছিক) — মিমি-টাইপ ভ্যালিডেটেড, সাপ্লায়ার ডকুমেন্ট প্যাটার্নের অনুরূপ
$photo_rel = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $max_bytes = 5 * 1024 * 1024;
    if ($_FILES['photo']['size'] > 0 && $_FILES['photo']['size'] <= $max_bytes) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
        if (isset($allowed[$mime])) {
            $dir = ame_oloan_upload_dir();
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $fname = 'owner_' . time() . '_' . random_int(1000, 9999) . '.' . $allowed[$mime];
            if (@move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) {
                $photo_rel = 'uploads/owners/' . $fname;
            }
        }
    }
}

if ($id > 0) {
    $existing = ame_oloan_owner_fetch($db, $id);
    if (!$existing) {
        redirect('list.php?err=' . rawurlencode(ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.')));
    }
    $photo_final = $photo_rel ?? $existing['photo'];
    $stmt = $db->prepare("UPDATE shop_owners SET owner_name=?, parent_name=?, nid_no=?, mobile=?, address=?, shop_name=?, shop_type=?, business_address=?, trade_license_no=?, photo=?, status=? WHERE id=?");
    // টাইপ: s(name) s(parent) s(nid) s(mobile) s(address) s(shop) s(type) s(baddr) s(license) s(photo) i(status) i(id) = "ssssssssssii" (১২টি)
    $stmt->bind_param('ssssssssssii', $owner_name, $parent_name, $nid_no, $mobile, $address, $shop_name, $shop_type, $business_address, $trade_license_no, $photo_final, $status, $id);
    $stmt->execute();
    $stmt->close();
    log_activity('Owner Updated', 'owner_loans', "মালিক #$id ({$owner_name}) আপডেট করা হলো");
    redirect('profile.php?id=' . $id . '&saved=1');
} else {
    $stmt = $db->prepare("INSERT INTO shop_owners (owner_name, parent_name, nid_no, mobile, address, shop_name, shop_type, business_address, trade_license_no, photo, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    // টাইপ: s*10 + i(status) + i(created_by) = "ssssssssssii" (১২টি)
    $stmt->bind_param('ssssssssssii', $owner_name, $parent_name, $nid_no, $mobile, $address, $shop_name, $shop_type, $business_address, $trade_license_no, $photo_rel, $status, $user_id);
    $stmt->execute();
    $new_id = $db->insert_id;
    $stmt->close();
    log_activity('Owner Created', 'owner_loans', "নতুন মালিক তৈরি: #$new_id ({$owner_name})");
    redirect('profile.php?id=' . $new_id . '&saved=1');
}
