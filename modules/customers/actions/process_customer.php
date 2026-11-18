<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../customer_identity.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../add_customer.php');
    exit;
}
csrf_verify();

$customer_code    = trim($_POST['customer_code'] ?? '');
$name             = trim($_POST['name'] ?? '');
$phone_raw        = trim($_POST['phone'] ?? '');
$phone            = $phone_raw !== '' ? $phone_raw : null;
$nid              = trim($_POST['nid'] ?? '');
$father_name      = trim($_POST['father_name'] ?? '');
$address          = trim($_POST['address'] ?? '');
$group_id         = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
$area_id          = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;
$guarantor_name   = trim($_POST['guarantor_name'] ?? '');
$guarantor_phone  = trim($_POST['guarantor_phone'] ?? '');
$opening_due      = (float)($_POST['opening_due'] ?? 0);
$advance_deposit  = (float)($_POST['advance_deposit'] ?? 0);
$advance_account_raw = trim((string)($_POST['advance_account_id'] ?? ''));
$is_customer      = isset($_POST['is_customer']) ? 1 : 0;
$is_supplier      = isset($_POST['is_supplier']) ? 1 : 0;
$is_installment   = isset($_POST['is_installment']) ? 1 : 0;
$is_rental        = isset($_POST['is_rental']) ? 1 : 0;
$credit_limit     = (float)($_POST['credit_limit'] ?? 0);
$additional_notes = trim($_POST['additional_notes'] ?? '');
$custom_requirement = trim($_POST['custom_requirement'] ?? '');

$next_due_date_raw = trim($_POST['next_due_date'] ?? '');
$next_due_date      = $next_due_date_raw !== '' ? $next_due_date_raw : null;
$due_note           = trim($_POST['due_note'] ?? '');
$due_note           = $due_note !== '' ? $due_note : null;
[$due_watch, $due_watch_on] = ame_due_watch_from_post(isset($_POST['due_watch']));

if (empty($customer_code) || empty($name)) {
    die('❌ আইডি ও নাম আবশ্যক। <a href="../add_customer.php">ফিরে যান</a>');
}

$advance_account_id = 0;
if ($advance_deposit > 0) {
    $advance_account_id = ame_posted_shop_account_id($db, $advance_account_raw);
    if ($advance_account_id <= 0) {
        die('❌ অগ্রিম জমা দিলে খোলা ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট নির্বাচন করা আবশ্যক। <a href="../add_customer.php">ফিরে যান</a>');
    }
}

$taken = ame_customer_identity_taken($db, $customer_code, (string) $phone, $nid, 0);
if ($taken !== null) {
    die('❌ ' . htmlspecialchars($taken, ENT_QUOTES, 'UTF-8') . ' <a href="../add_customer.php">ফিরে যান</a>');
}
$phone = ame_customer_phone_store((string) $phone);
$nid = ame_customer_nid_store($nid);

$photo_path = null;
$photo_jpeg = trim((string)($_POST['photo_jpeg'] ?? ''));
if ($photo_jpeg !== '') {
    if (preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $photo_jpeg)) {
        $photo_jpeg = substr($photo_jpeg, strpos($photo_jpeg, ',') + 1);
    }
    $binary = base64_decode($photo_jpeg, true);
    if ($binary !== false && strlen($binary) > 32 && strlen($binary) <= 6 * 1024 * 1024) {
        $converted = ame_customer_save_converted_image($binary);
        if ($converted !== '') {
            $photo_path = $converted;
        }
    }
}
if ($photo_path === null && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $max_bytes = 5 * 1024 * 1024;
    if ($_FILES['photo']['size'] > 0 && $_FILES['photo']['size'] <= $max_bytes) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
        if (isset($allowed_mimes[$real_mime])) {
            $binary = file_get_contents($_FILES['photo']['tmp_name']);
            $converted = $binary ? ame_customer_save_converted_image($binary) : '';
            if ($converted !== '') {
                $photo_path = $converted;
            } else {
                $upload_dir = __DIR__ . '/../../../uploads/customers/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $filename = 'cus_' . time() . '_' . random_int(1000, 9999) . '.jpg';
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    $photo_path = 'uploads/customers/' . $filename;
                }
            }
        }
    }
}

$pdf_path = null;
if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
    $pdf_ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    $max_bytes = 10 * 1024 * 1024; // 10MB সীমা
    if ($pdf_ext === 'pdf' && $_FILES['pdf_file']['size'] > 0 && $_FILES['pdf_file']['size'] <= $max_bytes) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['pdf_file']['tmp_name']);
        finfo_close($finfo);

        if ($real_mime === 'application/pdf') {
            $pdf_dir = __DIR__ . '/../../../uploads/customers_docs/';
            if (!is_dir($pdf_dir)) { mkdir($pdf_dir, 0755, true); }
            $pdf_filename = 'doc_' . time() . '_' . random_int(1000, 9999) . '.pdf';
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_dir . $pdf_filename)) {
                $pdf_path = 'uploads/customers_docs/' . $pdf_filename;
            }
        }
    }
}

$db->begin_transaction();
try {
    $stmt = $db->prepare("INSERT INTO customers 
        (customer_code, name, photo, phone, nid, father_name, address, group_id, area_id,
         guarantor_name, guarantor_phone, is_customer, is_supplier, is_installment, is_rental,
         credit_limit, additional_notes, pdf_file, custom_requirement, next_due_date, due_note,
         due_watch, due_watch_on) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException('প্রস্তুতিতে সমস্যা: ' . $db->error);
    }
    $stmt->bind_param(
        "sssssssiissiiiidsssssis",
        $customer_code, $name, $photo_path, $phone, $nid, $father_name, $address,
        $group_id, $area_id, $guarantor_name, $guarantor_phone,
        $is_customer, $is_supplier, $is_installment, $is_rental,
        $credit_limit, $additional_notes, $pdf_path, $custom_requirement,
        $next_due_date, $due_note, $due_watch, $due_watch_on
    );
    $stmt->execute();
    $new_id = $db->insert_id;

    if ($opening_due > 0) {
        add_ledger_entry($db, $new_id, 'সাবেক বকেয়া', 'OPEN-' . $new_id, 'প্রারম্ভিক বকেয়া (এন্ট্রির সময় যোগ করা)', $opening_due, 0);
    }

    if ($advance_deposit > 0) {
        add_ledger_entry($db, $new_id, 'অগ্রিম জমা', 'ADV-' . $new_id, 'প্রারম্ভিক অগ্রিম জমা (এন্ট্রির সময় যোগ করা)', 0, $advance_deposit);
        account_credit($advance_account_id, $advance_deposit, 'customer_advance', $new_id, 'নতুন কাস্টমার এন্ট্রির অগ্রিম জমা: ' . $name);
    }

    $signRaw = trim((string)($_POST['signature_png'] ?? ''));
    if ($signRaw !== '') {
        $signPath = ame_customer_save_signature($signRaw);
        if ($signPath !== '') {
            $signedAt = date('Y-m-d H:i:s');
            $sig = $db->prepare('UPDATE customers SET agreement_signature = ?, agreement_signed_at = ? WHERE id = ?');
            if ($sig) {
                $sig->bind_param('ssi', $signPath, $signedAt, $new_id);
                $sig->execute();
                $sig->close();
            }
        }
    }

    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('Customer Added', 'Customers', $name . ' #' . $new_id, [
            'action_type' => 'add',
            'entity_type' => 'customer',
            'entity_id' => (string)$new_id,
            'reference' => $customer_code !== '' ? $customer_code : ('C-' . $new_id),
            'party_type' => 'customer',
            'party_id' => (int)$new_id,
            'party_name' => $name,
            'amount_due' => $opening_due > 0 ? (float)$opening_due : null,
        ]);
    }
    header('Location: ../profile.php?id=' . $new_id . '&success=1');
    exit;
} catch (Throwable $e) {
    $db->rollback();
    error_log('Customer creation failed: ' . $e->getMessage());
    die('❌ সংরক্ষণে সমস্যা হয়েছে, কোনো তথ্য সেভ হয়নি। <a href="../add_customer.php">ফিরে যান</a>');
}