<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../customer_identity.php';
ensure_customer_tables($db);
if (function_exists('ame_customer_status_boot')) {
    ame_customer_status_boot($db);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$id              = (int)($_POST['id'] ?? 0);
$customer_code   = trim($_POST['customer_code'] ?? '');
$name            = trim($_POST['name'] ?? '');
$phone_raw       = trim($_POST['phone'] ?? '');
$phone           = $phone_raw !== '' ? $phone_raw : null;
$nid             = trim($_POST['nid'] ?? '');
$father_name     = trim($_POST['father_name'] ?? '');
$address         = trim($_POST['address'] ?? '');
$group_id        = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
$area_id         = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;
$guarantor_name  = trim($_POST['guarantor_name'] ?? '');
$guarantor_phone = trim($_POST['guarantor_phone'] ?? '');
$add_due         = (float)($_POST['add_due'] ?? 0);
$add_deposit     = (float)($_POST['add_deposit'] ?? 0);
$is_customer     = isset($_POST['is_customer']) ? 1 : 0;
$is_supplier     = isset($_POST['is_supplier']) ? 1 : 0;
$is_installment  = isset($_POST['is_installment']) ? 1 : 0;
$is_rental       = isset($_POST['is_rental']) ? 1 : 0;
$credit_limit    = (float)($_POST['credit_limit'] ?? 0);
$additional_notes = trim($_POST['additional_notes'] ?? '');
$custom_requirement = trim($_POST['custom_requirement'] ?? '');

$prev_watch = 0;
$prev_watch_on = null;
$prevRow = null;
$prevStmt = $db->prepare('SELECT customer_code, name, phone, nid, father_name, address, group_id, area_id, guarantor_name, guarantor_phone, is_customer, is_supplier, is_installment, is_rental, credit_limit, additional_notes, custom_requirement, due_watch, due_watch_on, status, agreement_signature FROM customers WHERE id = ? LIMIT 1');
if ($prevStmt) {
    $prevStmt->bind_param('i', $id);
    $prevStmt->execute();
    $prevRow = $prevStmt->get_result()->fetch_assoc();
    $prevStmt->close();
    if ($prevRow) {
        $prev_watch = (int)($prevRow['due_watch'] ?? 0);
        $prev_watch_on = $prevRow['due_watch_on'] ?? null;
    }
}
[$due_watch, $due_watch_on] = ame_due_watch_from_post(isset($_POST['due_watch']), $prev_watch, $prev_watch_on);
$customer_on = function_exists('ame_customer_status_enabled')
    ? ame_customer_status_enabled($prevRow['status'] ?? 1)
    : ((int)($prevRow['status'] ?? 1) === 1);
if (array_key_exists('customer_status', $_POST)) {
    $customer_on = ((string)$_POST['customer_status'] !== '0');
}
$customer_status = function_exists('ame_customer_status_store')
    ? ame_customer_status_store($db, $customer_on)
    : ($customer_on ? '1' : '0');

if ($id <= 0 || empty($customer_code) || empty($name)) {
    die('❌ আইডি ও নাম আবশ্যক। <a href="../index.php">ফিরে যান</a>');
}

$taken = ame_customer_identity_taken($db, $customer_code, (string) $phone, $nid, $id);
if ($taken !== null) {
    die('❌ ' . htmlspecialchars($taken, ENT_QUOTES, 'UTF-8') . ' <a href="../edit_customer.php?id=' . $id . '">ফিরে যান</a>');
}
$phone = ame_customer_phone_store((string) $phone);
$nid = ame_customer_nid_store($nid);

$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    // নিরাপত্তা: ফাইলের আসল MIME টাইপ যাচাই (ইউজারের দেওয়া নাম/এক্সটেনশন বিশ্বাস করা হয় না)
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $max_bytes = 5 * 1024 * 1024; // 5MB সীমা

    if ($_FILES['photo']['size'] > 0 && $_FILES['photo']['size'] <= $max_bytes) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (isset($allowed_mimes[$real_mime])) {
            $upload_dir = __DIR__ . '/../../../uploads/customers/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            $filename = 'cus_' . time() . '_' . random_int(1000, 9999) . '.' . $allowed_mimes[$real_mime];
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                $photo_path = 'uploads/customers/' . $filename;
            }
        }
    }
}

try {
    if ($photo_path) {
        $stmt = $db->prepare("UPDATE customers SET 
            customer_code=?, name=?, phone=?, nid=?, father_name=?, address=?, group_id=?, area_id=?,
            guarantor_name=?, guarantor_phone=?, is_customer=?, is_supplier=?, is_installment=?, is_rental=?,
            credit_limit=?, additional_notes=?, custom_requirement=?, photo=?, due_watch=?, due_watch_on=?, status=? WHERE id=?");
        $stmt->bind_param(
            "ssssssiissiiiidsssissi",
            $customer_code, $name, $phone, $nid, $father_name, $address, $group_id, $area_id,
            $guarantor_name, $guarantor_phone, $is_customer, $is_supplier, $is_installment, $is_rental,
            $credit_limit, $additional_notes, $custom_requirement, $photo_path, $due_watch, $due_watch_on, $customer_status, $id
        );
    } else {
        $stmt = $db->prepare("UPDATE customers SET 
            customer_code=?, name=?, phone=?, nid=?, father_name=?, address=?, group_id=?, area_id=?,
            guarantor_name=?, guarantor_phone=?, is_customer=?, is_supplier=?, is_installment=?, is_rental=?,
            credit_limit=?, additional_notes=?, custom_requirement=?, due_watch=?, due_watch_on=?, status=? WHERE id=?");
        $stmt->bind_param(
            "ssssssiissiiiidssissi",
            $customer_code, $name, $phone, $nid, $father_name, $address, $group_id, $area_id,
            $guarantor_name, $guarantor_phone, $is_customer, $is_supplier, $is_installment, $is_rental,
            $credit_limit, $additional_notes, $custom_requirement, $due_watch, $due_watch_on, $customer_status, $id
        );
    }
    if (!$stmt->execute()) {
        die('❌ আপডেটে সমস্যা হয়েছে। Enable/Disable সেভ হয়নি।');
    }
    $signRaw = trim((string)($_POST['signature_png'] ?? ''));
    $signClear = (string)($_POST['signature_clear'] ?? '') === '1';
    if ($signRaw !== '') {
        $signPath = ame_customer_save_signature($signRaw);
        if ($signPath !== '') {
            ame_customer_drop_signature_file($prevRow['agreement_signature'] ?? '');
            $signedAt = date('Y-m-d H:i:s');
            $sig = $db->prepare('UPDATE customers SET agreement_signature = ?, agreement_signed_at = ? WHERE id = ?');
            if ($sig) {
                $sig->bind_param('ssi', $signPath, $signedAt, $id);
                $sig->execute();
                $sig->close();
            }
        }
    } elseif ($signClear) {
        ame_customer_drop_signature_file($prevRow['agreement_signature'] ?? '');
        $sig = $db->prepare('UPDATE customers SET agreement_signature = NULL, agreement_signed_at = NULL WHERE id = ?');
        if ($sig) {
            $sig->bind_param('i', $id);
            $sig->execute();
            $sig->close();
        }
    }
    if ($due_watch === 1 && function_exists('ame_customer_notify_allow')
        && !ame_customer_notify_allow($db, $id)) {
        die('❌ বকেয়া নোটিফিকেশনের অনুমতি সংরক্ষণ করা যায়নি।');
    }

    // অতিরিক্ত বকেয়া যোগ করলে লেজারে ডেবিট এন্ট্রি
    if ($add_due > 0) {
        add_ledger_entry($db, $id, 'অতিরিক্ত বকেয়া', 'ADJ-DUE-' . time(), 'এডিট থেকে অতিরিক্ত বকেয়া যোগ করা হয়েছে', $add_due, 0);
    }

    // অতিরিক্ত জমা যোগ করলে লেজারে ক্রেডিট এন্ট্রি
    if ($add_deposit > 0) {
        add_ledger_entry($db, $id, 'অতিরিক্ত জমা', 'ADJ-DEP-' . time(), 'এডিট থেকে অতিরিক্ত জমা যোগ করা হয়েছে', 0, $add_deposit);
    }

    if (function_exists('log_activity')) {
        $oldSnap = is_array($prevRow) ? [
            'customer_code' => (string)($prevRow['customer_code'] ?? ''),
            'name' => (string)($prevRow['name'] ?? ''),
            'phone' => (string)($prevRow['phone'] ?? ''),
            'nid' => (string)($prevRow['nid'] ?? ''),
            'father_name' => (string)($prevRow['father_name'] ?? ''),
            'address' => (string)($prevRow['address'] ?? ''),
            'credit_limit' => (string)($prevRow['credit_limit'] ?? ''),
            'due_watch' => (string)($prevRow['due_watch'] ?? ''),
            'status' => (string)($prevRow['status'] ?? ''),
        ] : [];
        $newSnap = [
            'customer_code' => $customer_code,
            'name' => $name,
            'phone' => (string)($phone ?? ''),
            'nid' => $nid,
            'father_name' => $father_name,
            'address' => $address,
            'credit_limit' => (string)$credit_limit,
            'due_watch' => (string)$due_watch,
            'status' => (string)$customer_status,
        ];
        if ($add_due > 0) {
            $newSnap['add_due'] = (string)$add_due;
        }
        if ($add_deposit > 0) {
            $newSnap['add_deposit'] = (string)$add_deposit;
        }
        log_activity('Customer Updated', 'Customers', $name . ' #' . $id, [
            'action_type' => 'edit',
            'entity_type' => 'customer',
            'entity_id' => (string)$id,
            'reference' => $customer_code,
            'party_type' => 'customer',
            'party_id' => $id,
            'party_name' => $name,
            'old' => $oldSnap,
            'new' => $newSnap,
            'amount_due' => $add_due > 0 ? (float)$add_due : null,
            'amount_paid' => $add_deposit > 0 ? (float)$add_deposit : null,
        ]);
    }

    header('Location: ../profile.php?id=' . $id . '&updated=1');
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('process_customer_update failed: ' . $e->getMessage()); die('❌ আপডেটে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।');
}