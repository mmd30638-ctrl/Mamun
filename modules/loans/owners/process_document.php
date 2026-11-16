<?php
/**
 * সাধারণ ডকুমেন্ট আপলোড হ্যান্ডলার — owner_documents টেবিলে doc_category
 * ('owner'/'loan'/'bank'/'payment') ও ref_id দিয়ে যেকোনো সত্তার সাথে
 * ডকুমেন্ট যুক্ত করা যায়। প্রাধান্য: owner-linked ডকুমেন্ট (স্পেক সেকশন ১৮)।
 */
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$owner_id = (int)($_POST['owner_id'] ?? 0);
$doc_category = clean_input($_POST['doc_category'] ?? 'owner');
$ref_id = isset($_POST['ref_id']) && $_POST['ref_id'] !== '' ? (int)$_POST['ref_id'] : null;
$action = (string)($_POST['action'] ?? 'upload');
$redirect_back = 'profile.php?id=' . $owner_id . '#documents';

if ($owner_id <= 0 || !ame_oloan_owner_fetch($db, $owner_id)) {
    die('❌ ' . ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}

if ($action === 'delete') {
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    $del = $db->prepare("DELETE FROM owner_documents WHERE id = ? AND owner_id = ?");
    $del->bind_param('ii', $doc_id, $owner_id);
    $del->execute();
    $del->close();
    log_activity('Owner Document Deleted', 'owner_loans', "ডকুমেন্ট #$doc_id মুছে ফেলা হলো (owner #$owner_id)");
    redirect($redirect_back);
}

$title = trim((string)($_POST['title'] ?? ''));
if ($title === '') {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('ডকুমেন্টের নাম দিন।', 'Enter a document title.')));
}
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['document']['tmp_name'])) {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('ফাইলটি আপলোড করা যায়নি।', 'File upload failed.')));
}

$max_bytes = 8 * 1024 * 1024;
if ($_FILES['document']['size'] <= 0 || $_FILES['document']['size'] > $max_bytes) {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('ফাইলটি ৮ মেগাবাইটের মধ্যে দিন।', 'File must be under 8MB.')));
}

$allowed_mimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$real_mime = finfo_file($finfo, $_FILES['document']['tmp_name']);
finfo_close($finfo);
if (!isset($allowed_mimes[$real_mime])) {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('শুধু PDF, JPG, PNG বা WEBP ফাইল আপলোড করা যাবে।', 'Only PDF, JPG, PNG or WEBP files are allowed.')));
}

$upload_dir = ame_oloan_upload_dir() . '/' . $owner_id;
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('ফোল্ডার তৈরি করা যায়নি।', 'Could not create folder.')));
}

$ext = $allowed_mimes[$real_mime];
$filename = 'doc_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . '/' . $filename)) {
    redirect($redirect_back . '&err=' . rawurlencode(ame_l('ফাইলটি সেভ করা যায়নি।', 'Could not save the file.')));
}

$rel_path = 'uploads/owners/' . $owner_id . '/' . $filename;
$size = (int)$_FILES['document']['size'];
$uploaded_by = (int)($_SESSION['user_id'] ?? 0);

$stmt = $db->prepare("INSERT INTO owner_documents (owner_id, doc_category, ref_id, title, file_path, file_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('isisssii', $owner_id, $doc_category, $ref_id, $title, $rel_path, $ext, $size, $uploaded_by);
$stmt->execute();
$stmt->close();

log_activity('Owner Document Uploaded', 'owner_loans', "মালিক #$owner_id — ডকুমেন্ট আপলোড: {$title}");
redirect($redirect_back);
