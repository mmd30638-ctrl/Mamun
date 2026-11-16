<?php
require_once '../../includes/config.php';
admin_only();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $shop_name = clean_input($_POST['shop_name'] ?? '');
    $shop_tagline = clean_input($_POST['shop_tagline'] ?? '');
    $shop_phone = clean_input($_POST['shop_phone'] ?? '');
    $shop_address = clean_input($_POST['shop_address'] ?? '');

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $max_bytes   = 2 * 1024 * 1024;

    function ame_save_upload(array $file, string $prefix, array $allowed_ext, int $max_bytes): ?string {
        if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > $max_bytes) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) return null;
        if (!getimagesize($file['tmp_name'])) return null;

        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/";
        $safe_name  = $prefix . '_' . bin2hex(random_bytes(6)) . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $target_dir . $safe_name)) return null;
        return $safe_name;
    }

    $logo_name   = ame_save_upload($_FILES['shop_logo']   ?? [], 'shop_logo',   $allowed_ext, $max_bytes);
    $banner_name = ame_save_upload($_FILES['shop_banner'] ?? [], 'shop_banner', $allowed_ext, $max_bytes);

    $check = $db->query("SELECT id FROM settings LIMIT 1");

    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();

        $sql = "UPDATE settings SET shop_name=?, shop_tagline=?, shop_phone=?, shop_address=?";
        $types = "ssss";
        $params = [$shop_name, $shop_tagline, $shop_phone, $shop_address];

        if ($banner_name !== null) { $sql .= ", shop_banner=?"; $types .= "s"; $params[] = $banner_name; }
        if ($logo_name   !== null) { $sql .= ", shop_logo=?";   $types .= "s"; $params[] = $logo_name; }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $row['id'];

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    } else {
        $stmt = $db->prepare("INSERT INTO settings (shop_name, shop_tagline, shop_phone, shop_address, shop_banner, shop_logo) VALUES (?, ?, ?, ?, ?, ?)");
        $banner_val = $banner_name ?? '';
        $logo_val = $logo_name ?? '';
        $stmt->bind_param("ssssss", $shop_name, $shop_tagline, $shop_phone, $shop_address, $banner_val, $logo_val);
        $stmt->execute();
    }

    $bt = dirname(__DIR__, 2) . '/includes/brand_type.php';
    if (is_file($bt)) {
        require_once $bt;
        ame_brand_type_save($db, $_POST);
    }

    header("Location: dashboard_settings.php?updated=1");
    exit();
}

header("Location: dashboard_settings.php");
exit();
