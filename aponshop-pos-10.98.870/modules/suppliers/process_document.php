<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';

require_post();
csrf_verify();

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if ($supplier_id <= 0 || !ame_supplier_fetch($db, $supplier_id)) {
    die('❌ সাপ্লায়ার খুঁজে পাওয়া যায়নি। <a href="list.php">তালিকায় ফিরে যান</a>');
}

if ($action === 'delete') {
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    ame_supplier_document_delete($db, $doc_id, $supplier_id);
    redirect('profile.php?id=' . $supplier_id . '#documents');
    exit;
}

if ($action === 'upload') {
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ডকুমেন্টের নাম দিন।') . '#documents');
        exit;
    }
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['document']['tmp_name'])) {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ফাইলটি আপলোড করা যায়নি। আবার চেষ্টা করুন।') . '#documents');
        exit;
    }

    $max_bytes = 8 * 1024 * 1024;
    if ($_FILES['document']['size'] <= 0 || $_FILES['document']['size'] > $max_bytes) {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ফাইলটি ৮ মেগাবাইটের মধ্যে দিন।') . '#documents');
        exit;
    }

    $allowed_mimes = [
        'application/pdf' => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $_FILES['document']['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed_mimes[$real_mime])) {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('শুধু PDF, JPG, PNG বা WEBP ফাইল আপলোড করা যাবে।') . '#documents');
        exit;
    }

    $upload_dir = ame_supplier_upload_dir() . '/' . $supplier_id;
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ফোল্ডার তৈরি করা যায়নি।') . '#documents');
        exit;
    }

    $ext = $allowed_mimes[$real_mime];
    $filename = 'doc_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . '/' . $filename)) {
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ফাইলটি সেভ করা যায়নি।') . '#documents');
        exit;
    }

    $rel_path = 'uploads/suppliers/' . $supplier_id . '/' . $filename;
    $size = (int)$_FILES['document']['size'];
    $uploaded_by = (int)($_SESSION['user_id'] ?? 0);

    try {
        ame_supplier_document_add($db, $supplier_id, $title, $rel_path, $ext, $size, $uploaded_by);
        if (function_exists('log_activity')) {
            log_activity('Supplier Document Uploaded', 'Suppliers', $title);
        }
    } catch (Throwable $e) {
        error_log('Supplier document save failed: ' . $e->getMessage());
        @unlink($upload_dir . '/' . $filename);
        redirect('profile.php?id=' . $supplier_id . '&err=' . rawurlencode('ডকুমেন্ট সংরক্ষণ করা যায়নি।') . '#documents');
        exit;
    }

    redirect('profile.php?id=' . $supplier_id . '#documents');
    exit;
}

redirect('profile.php?id=' . $supplier_id);
