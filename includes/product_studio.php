<?php
/**
 * APONSHOP Product Studio — schema, extra fields, barcode, image.
 * POS prices stay: sale_price (K), wholesale_price (P), dealer_price (D).
 */

function product_studio_columns(mysqli $db, bool $refresh = false): array
{
    static $cache = null;
    if ($refresh) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];
    $res = @$db->query('SHOW COLUMNS FROM products');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cache[$row['Field']] = true;
        }
    }
    return $cache;
}

function product_studio_has_col(mysqli $db, string $column): bool
{
    $cols = product_studio_columns($db);
    return isset($cols[$column]);
}

function ensure_product_studio_schema(mysqli $db): void
{
    $needed = [
        'barcode' => "VARCHAR(64) DEFAULT NULL",
        'manufacturer_name' => "VARCHAR(150) DEFAULT NULL",
        'sub_category' => "VARCHAR(100) DEFAULT NULL",
        'product_type' => "VARCHAR(32) NOT NULL DEFAULT 'part'",
        'short_description' => "TEXT NULL",
        'description' => "TEXT NULL",
        'highlights' => "TEXT NULL",
        'tags' => "VARCHAR(255) DEFAULT NULL",
        'internal_notes' => "TEXT NULL",
        'reference_no' => "VARCHAR(80) DEFAULT NULL",
        'vat_percent' => "DECIMAL(8,2) NOT NULL DEFAULT 0",
        'discount_percent' => "DECIMAL(8,2) NOT NULL DEFAULT 0",
        'moq' => "DECIMAL(10,2) NOT NULL DEFAULT 1",
        'warranty_months' => "INT DEFAULT NULL",
        'warranty_type' => "VARCHAR(80) DEFAULT NULL",
        'return_policy' => "TEXT NULL",
        'model_name' => "VARCHAR(120) DEFAULT NULL",
        'size_label' => "VARCHAR(80) DEFAULT NULL",
        'color_label' => "VARCHAR(80) DEFAULT NULL",
        'weight_kg' => "DECIMAL(10,3) DEFAULT NULL",
        'country_origin' => "VARCHAR(80) DEFAULT NULL",
        'hs_code' => "VARCHAR(32) DEFAULT NULL",
        'image_path' => "VARCHAR(255) DEFAULT NULL",
        'seo_title' => "VARCHAR(180) DEFAULT NULL",
        'seo_keywords' => "VARCHAR(255) DEFAULT NULL",
        'is_old_battery' => "TINYINT(1) NOT NULL DEFAULT 0",
        'shelf_location' => "VARCHAR(100) DEFAULT NULL",
        'oem_code' => "VARCHAR(80) DEFAULT NULL",
        'supplier_code' => "VARCHAR(80) DEFAULT NULL",
    ];

    $cols = product_studio_columns($db, true);
    foreach ($needed as $column => $ddl) {
        if (isset($cols[$column])) {
            continue;
        }
        try {
            @$db->query("ALTER TABLE products ADD COLUMN `{$column}` {$ddl}");
        } catch (Throwable $error) {
            error_log('Product studio schema ' . $column . ': ' . $error->getMessage());
        }
    }
    product_studio_columns($db, true);

    $dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht) && is_file(dirname(__DIR__) . '/uploads/.htaccess')) {
        @copy(dirname(__DIR__) . '/uploads/.htaccess', $ht);
    }
}

function product_studio_null_text($value, int $max): ?string
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, $max);
    } elseif (strlen($text) > $max) {
        $text = substr($text, 0, $max);
    }
    return $text;
}

function product_studio_normalize_type($type): string
{
    $type = strtolower(trim((string)$type));
    $ok = ['part', 'new_battery', 'old_battery', 'accessory'];
    return in_array($type, $ok, true) ? $type : 'part';
}

function product_studio_normalize_status($status): string
{
    $status = strtolower(trim((string)$status));
    $ok = ['draft', 'active', 'inactive'];
    return in_array($status, $ok, true) ? $status : 'active';
}

function product_studio_normalize_barcode($code): ?string
{
    $code = strtoupper(trim((string)$code));
    $code = preg_replace('/\s+/', '', $code);
    if ($code === '') {
        return null;
    }
    if (
        strlen($code) < 3
        || strlen($code) > 64
        || !preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $code)
    ) {
        throw new InvalidArgumentException(
            'বারকোডে শুধু ইংরেজি অক্ষর, সংখ্যা, ড্যাশ, ডট ও আন্ডারস্কোর ব্যবহার করুন।'
        );
    }
    return $code;
}

function product_studio_barcode_exists(mysqli $db, string $code, int $exclude_id = 0): bool
{
    if (!product_studio_has_col($db, 'barcode')) {
        return false;
    }
    $stmt = $db->prepare(
        'SELECT id FROM products WHERE barcode = ? AND id != ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $code, $exclude_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function product_studio_generate_barcode(mysqli $db, int $product_id): string
{
    $base = 'APN' . str_pad((string)$product_id, 9, '0', STR_PAD_LEFT);
    $code = $base;
    $suffix = 1;
    while (product_studio_barcode_exists($db, $code, $product_id)) {
        $code = $base . $suffix;
        $suffix++;
    }
    return $code;
}

function product_studio_require_number(array $post, string $field, string $label): float
{
    $value = $post[$field] ?? null;
    if ($value === null || $value === '' || !is_numeric($value)) {
        throw new InvalidArgumentException($label . ' সঠিকভাবে লিখুন।');
    }
    $value = (float)$value;
    if ($value < 0) {
        throw new InvalidArgumentException($label . ' শূন্যের কম হতে পারবে না।');
    }
    return $value;
}

function product_studio_collect_extra(array $post): array
{
    $type = product_studio_normalize_type($post['product_type'] ?? 'part');
    if (!empty($post['is_old_battery'])) {
        $type = 'old_battery';
    }
    $status = product_studio_normalize_status($post['status'] ?? 'active');

    $barcode = product_studio_normalize_barcode($post['barcode'] ?? '');

    $weight_raw = trim((string)($post['weight_kg'] ?? ''));
    $weight = ($weight_raw === '' || !is_numeric($weight_raw))
        ? null
        : max(0, (float)$weight_raw);

    $warranty_raw = trim((string)($post['warranty_months'] ?? ''));
    $warranty = ($warranty_raw === '' || !is_numeric($warranty_raw))
        ? null
        : max(0, (int)$warranty_raw);

    return [
        'barcode' => $barcode,
        'manufacturer_name' => product_studio_null_text($post['manufacturer_name'] ?? '', 150),
        'sub_category' => product_studio_null_text($post['sub_category'] ?? '', 100),
        'product_type' => $type,
        'status' => $status,
        'short_description' => product_studio_null_text($post['short_description'] ?? '', 2000),
        'description' => product_studio_null_text($post['description'] ?? '', 8000),
        'highlights' => product_studio_null_text($post['highlights'] ?? '', 2000),
        'tags' => product_studio_null_text($post['tags'] ?? '', 255),
        'internal_notes' => product_studio_null_text($post['internal_notes'] ?? '', 2000),
        'reference_no' => product_studio_null_text($post['reference_no'] ?? '', 80),
        'vat_percent' => max(0, (float)($post['vat_percent'] ?? 0)),
        'discount_percent' => max(0, (float)($post['discount_percent'] ?? 0)),
        'moq' => max(0, (float)($post['moq'] ?? 1)),
        'warranty_months' => $warranty,
        'warranty_type' => product_studio_null_text($post['warranty_type'] ?? '', 80),
        'return_policy' => product_studio_null_text($post['return_policy'] ?? '', 2000),
        'model_name' => product_studio_null_text($post['model_name'] ?? '', 120),
        'size_label' => product_studio_null_text($post['size_label'] ?? '', 80),
        'color_label' => product_studio_null_text($post['color_label'] ?? '', 80),
        'weight_kg' => $weight,
        'country_origin' => product_studio_null_text($post['country_origin'] ?? '', 80),
        'hs_code' => product_studio_null_text($post['hs_code'] ?? '', 32),
        'seo_title' => product_studio_null_text($post['seo_title'] ?? '', 180),
        'seo_keywords' => product_studio_null_text($post['seo_keywords'] ?? '', 255),
        'is_old_battery' => $type === 'old_battery' ? 1 : 0,
    ];
}

function product_studio_update_fields(mysqli $db, int $product_id, array $data): void
{
    if ($product_id <= 0 || $data === []) {
        return;
    }
    $existing = [];
    $res = @$db->query('SHOW COLUMNS FROM products');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
    }
    $set = [];
    $types = '';
    $values = [];
    foreach ($data as $column => $value) {
        if (!isset($existing[$column])) {
            continue;
        }
        $set[] = '`' . str_replace('`', '', $column) . '` = ?';
        $decimals = ['vat_percent', 'discount_percent', 'moq', 'weight_kg'];
        if ($value === null) {
            $types .= 's';
        } elseif (in_array($column, $decimals, true)) {
            $types .= 'd';
            $value = (float)$value;
        } elseif (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $value;
    }
    if ($set === []) {
        return;
    }
    $types .= 'i';
    $values[] = $product_id;
    $sql = 'UPDATE products SET ' . implode(', ', $set) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $refs = [];
    $refs[] = &$types;
    foreach ($values as $i => $_) {
        $refs[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();
}

function product_studio_optimize_jpeg(string $source, string $dest): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return copy($source, $dest);
    }
    $raw = @file_get_contents($source);
    if ($raw === false) {
        return false;
    }
    $img = @imagecreatefromstring($raw);
    if (!$img) {
        return copy($source, $dest);
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $max = 1600;
    if ($w > $max || $h > $max) {
        $scale = min($max / $w, $max / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }
    $ok = imagejpeg($img, $dest, 82);
    imagedestroy($img);
    return $ok;
}

function product_studio_upload_image(int $product_id, ?string $existing_path = null): ?string
{
    if (!empty($_POST['remove_image']) && $existing_path) {
        $full = dirname(__DIR__) . '/' . ltrim($existing_path, '/');
        if (is_file($full)) {
            @unlink($full);
        }
        return '';
    }

    $info = $_FILES['product_image'] ?? null;
    if (!is_array($info) || (int)($info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $err = (int)($info['error'] ?? 1);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('ছবি বড়। ৪ এমবির নিচে দিন।');
    }
    if ($err !== UPLOAD_ERR_OK || empty($info['tmp_name']) || !is_uploaded_file($info['tmp_name'])) {
        throw new InvalidArgumentException('ছবি আপলোড হয়নি। আবার চেষ্টা করুন।');
    }
    if ((int)($info['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new InvalidArgumentException('ছবি বড়। ৪ এমবির নিচে দিন।');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = (string)finfo_file($f, $info['tmp_name']);
            finfo_close($f);
        }
    }
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($map[$mime])) {
        throw new InvalidArgumentException('শুধু JPG, PNG বা WEBP ছবি দিন।');
    }

    $dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new InvalidArgumentException('ছবির ফোল্ডার তৈরি হয়নি।');
    }

    $name = 'prd_' . $product_id . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $dest = $dir . '/' . $name;
    if (!product_studio_optimize_jpeg($info['tmp_name'], $dest) || !is_file($dest)) {
        throw new InvalidArgumentException('ছবি সেভ হয়নি।');
    }

    if ($existing_path) {
        $old = dirname(__DIR__) . '/' . ltrim($existing_path, '/');
        if (is_file($old) && realpath($old) !== realpath($dest)) {
            @unlink($old);
        }
    }

    return 'uploads/products/' . $name;
}

function product_studio_apply_extra(mysqli $db, int $product_id, array $post, ?string $existing_image = null): void
{
    $extra = product_studio_collect_extra($post);

    if (empty($extra['barcode'])) {
        $extra['barcode'] = product_studio_generate_barcode($db, $product_id);
    } elseif (product_studio_barcode_exists($db, (string)$extra['barcode'], $product_id)) {
        throw new InvalidArgumentException('এই বারকোড আগে ব্যবহার করা হয়েছে।');
    }

    $image = product_studio_upload_image($product_id, $existing_image);
    if ($image !== null) {
        $extra['image_path'] = $image === '' ? null : $image;
    }

    product_studio_update_fields($db, $product_id, $extra);
}

function product_studio_image_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if ($path[0] === '/') {
        return $path;
    }
    return '/' . ltrim($path, '/');
}
