<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/hrm_functions.php';
admin_only();
ensure_user_schema($db);
ensure_hrm_schema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = clean_input($_POST['role'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $address = clean_input($_POST['address'] ?? '');
    $status = in_array((string)($_POST['status'] ?? '1'), ['1', 'active', 'on'], true) ? 1 : 0;
    $manual_id_raw = trim((string) ($_POST['user_id'] ?? ''));
    $manual_id = $manual_id_raw === '' ? 0 : (int) $manual_id_raw;

    $fail = static function (string $msg): void {
        $_SESSION['user_flash'] = $msg;
        $_SESSION['user_flash_ok'] = false;
        header('Location: user_add.php');
        exit();
    };

    if ($username === '' || $role === '' || $phone === '') {
        $fail('সব আবশ্যক ঘর পূরণ করুন (মোবাইল বাধ্যতামূলক)। / Fill every required field (mobile is required).');
    }
    if ($manual_id_raw !== '' && $manual_id <= 0) {
        $fail('ইউজার আইডি ১ বা তার বেশি হতে হবে। / User ID must be 1 or more.');
    }
    if ($manual_id > 0 && !ame_user_id_free($db, $manual_id)) {
        $fail('এই আইডি অন্যজন ব্যবহার করছে। খালি নম্বর দিন। / That ID is taken. Pick a free number.');
    }
    $pw_err = ame_validate_password($password);
    if ($pw_err !== null) {
        $fail($pw_err);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('সঠিক ইমেইল ঠিকানা দিন। / Enter a valid email.');
    }
    if ($password !== $password_confirm) {
        $fail('দুটো পাসওয়ার্ড মিলছে না। / The two passwords do not match.');
    }

    // ইউজারনেম আগে থেকে আছে কিনা চেক
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $fail('এই ইউজারনেম আগে থেকেই ব্যবহৃত হয়েছে। / That username is already taken.');
    }

    // রোল আসলে user_roles টেবিলে আছে কিনা যাচাই (নিরাপত্তার জন্য)
    $role_check = $db->prepare("SELECT id FROM user_roles WHERE role_key = ?");
    $role_check->bind_param("s", $role);
    $role_check->execute();
    if ($role_check->get_result()->num_rows === 0) {
        $fail('ভুল রোল নির্বাচিত হয়েছে। / That role is not valid.');
    }

    $photo_target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/";
    $doc_target_dir    = __DIR__ . "/../../uploads/users_docs/"; // web-root এর বাইরে, সরাসরি এক্সিকিউট হবে না
    $max_bytes         = 5 * 1024 * 1024; // 5MB সীমা

    // ⭐ ছবি আপলোড (ঐচ্ছিক) — আসল MIME টাইপ যাচাই করা হয়, ইউজারের দেওয়া এক্সটেনশন বিশ্বাস করা হয় না
    $photo_name = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $allowed_photo_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($_FILES['photo']['size'] > 0 && $_FILES['photo']['size'] <= $max_bytes) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);
            if (isset($allowed_photo_mimes[$real_mime])) {
                $photo_name = "user_photo_" . time() . "_" . random_int(1000, 9999) . "." . $allowed_photo_mimes[$real_mime];
                move_uploaded_file($_FILES['photo']['tmp_name'], $photo_target_dir . $photo_name);
            }
        }
    }

    // ⭐ ডকুমেন্ট আপলোড (ঐচ্ছিক) — শুধু PDF/ছবি, MIME যাচাই সহ, web-root এর বাইরে সংরক্ষণ
    $document_name = null;
    if (!empty($_FILES['document']['name']) && $_FILES['document']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['document']['tmp_name'])) {
        $allowed_doc_mimes = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($_FILES['document']['size'] > 0 && $_FILES['document']['size'] <= (10 * 1024 * 1024)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $_FILES['document']['tmp_name']);
            finfo_close($finfo);
            if (isset($allowed_doc_mimes[$real_mime])) {
                if (!is_dir($doc_target_dir)) { mkdir($doc_target_dir, 0755, true); }
                $doc_filename = "user_doc_" . time() . "_" . random_int(1000, 9999) . "." . $allowed_doc_mimes[$real_mime];
                move_uploaded_file($_FILES['document']['tmp_name'], $doc_target_dir . $doc_filename);
                $document_name = "uploads/users_docs/" . $doc_filename; // পরে খুঁজে বের করার জন্য পূর্ণ আপেক্ষিক পাথ সংরক্ষণ
            }
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    if ($manual_id > 0) {
        $stmt = $db->prepare("INSERT INTO users (id, username, password, role, phone, email, address, photo, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issssssssi", $manual_id, $username, $hashed_password, $role, $phone, $email, $address, $photo_name, $document_name, $status);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, role, phone, email, address, photo, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssssssi", $username, $hashed_password, $role, $phone, $email, $address, $photo_name, $document_name, $status);
        }
    }

    if ($stmt && $stmt->execute()) {
        $new_user_id = $manual_id > 0 ? $manual_id : (int)$stmt->insert_id;
        ame_sync_users_autoincrement($db);
        $store_ids = $_POST['store_ids'] ?? [];
        if (is_array($store_ids) && function_exists('ame_store_assign_user')) {
            foreach ($store_ids as $store_id) {
                ame_store_assign_user($db, $new_user_id, (int)$store_id);
            }
        }
        ensure_employee_for_user($db, $new_user_id);
        if (function_exists('ame_remember_user_name')) {
            ame_remember_user_name($db, $new_user_id, $username);
        }
        log_activity("User Added", "Settings", "নতুন ইউজার যোগ করা হলো: $username ($role) #" . $new_user_id);
        $_SESSION['user_flash'] = 'ইউজার তৈরি হয়েছে — আইডি #' . $new_user_id . '। বেতন কর্মী পেজ থেকে। / User created — ID #' . $new_user_id . '. Salary is on the employee page.';
        $_SESSION['user_flash_ok'] = true;
        header('Location: users.php');
        exit();
    }
    $fail('ইউজার তৈরি হয়নি। / Could not create the user.');
} else {
    header("Location: user_add.php");
    exit();
}
?>
