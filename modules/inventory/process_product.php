<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/product_code_functions.php';
require_once '../../includes/product_studio.php';

ensure_inventory_schema($db);

$is_english = function_exists('current_lang') && current_lang() === 'en';

$respond = static function ($message, $target) {
    echo '<script>';
    echo 'alert(' . json_encode($message, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ');';
    echo 'window.location.href=' . json_encode($target) . ';';
    echo '</script>';
    exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_product.php');
    exit();
}

csrf_verify();

$transaction_started = false;

try {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException(
            $is_english ? 'Product name is required.' : 'পণ্যের নাম অবশ্যই লিখতে হবে।'
        );
    }

    $posted_sku = trim((string)($_POST['sku'] ?? ''));
    $code_mode = ($_POST['code_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
    if ($posted_sku !== '') {
        $code_mode = 'manual';
    }
    if ($code_mode === 'manual') {
        $product_code = normalize_product_code($posted_sku);
        $code_holder = product_code_holder($db, $product_code);
        if ($code_holder !== null) {
            $holder_text = ' #' . $code_holder['id'] . ' ' . $code_holder['name']
                . ($code_holder['status'] !== '' && $code_holder['status'] !== 'active' ? ' (' . $code_holder['status'] . ')' : '');
            throw new InvalidArgumentException(
                ($is_english ? 'This SKU is already in use by:' : 'এই পণ্য আইডি ইতিমধ্যে ব্যবহার করছে:') . $holder_text
            );
        }
    } else {
        $product_code = 'TMP-' . strtoupper(bin2hex(random_bytes(8)));
    }

    $category_id = max(0, (int)($_POST['category_id'] ?? 0));
    $brand_id = max(0, (int)($_POST['brand_id'] ?? 0));
    $unit_id = max(0, (int)($_POST['unit_id'] ?? 0));

    $shelf_location = trim((string)($_POST['shelf_location'] ?? ''));
    $shelf_location = $shelf_location === '' ? null : $shelf_location;
    $oem_code = trim((string)($_POST['oem_code'] ?? ''));
    $oem_code = $oem_code === '' ? null : $oem_code;
    $supplier_code = trim((string)($_POST['supplier_code'] ?? ''));
    $supplier_code = $supplier_code === '' ? null : $supplier_code;

    $purchase_price = product_studio_require_number(
        $_POST,
        'purchase_price',
        $is_english ? 'Purchase price' : 'ক্রয় মূল্য'
    );
    $wholesale_price = product_studio_require_number(
        $_POST,
        'wholesale_price',
        $is_english ? 'Wholesale price' : 'পাইকারি মূল্য'
    );
    $dealer_price = product_studio_require_number(
        $_POST,
        'dealer_price',
        $is_english ? 'Re-dealer price' : 'রি-ডিলার মূল্য'
    );
    $sale_price = product_studio_require_number(
        $_POST,
        'sale_price',
        $is_english ? 'Retail price' : 'খুচরা মূল্য'
    );
    $stock = product_studio_require_number(
        $_POST,
        'stock',
        $is_english ? 'Opening stock' : 'প্রারম্ভিক স্টক'
    );
    $min_stock = product_studio_require_number(
        $_POST,
        'min_stock',
        $is_english ? 'Stock alert limit' : 'স্টক সতর্কতা সীমা'
    );

    $extra_preview = product_studio_collect_extra($_POST);
    if (!empty($extra_preview['barcode']) && product_studio_barcode_exists($db, (string)$extra_preview['barcode'], 0)) {
        throw new InvalidArgumentException(
            $is_english ? 'This barcode is already in use.' : 'এই বারকোড আগে ব্যবহার করা হয়েছে।'
        );
    }

    $db->begin_transaction();
    $transaction_started = true;

    $stmt = $db->prepare(
        'INSERT INTO products (
            sku, name, category_id, brand_id, unit_id,
            shelf_location, oem_code, supplier_code, purchase_price, wholesale_price,
            dealer_price, sale_price, stock, min_stock
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ssiiisssdddddd',
        $product_code,
        $name,
        $category_id,
        $brand_id,
        $unit_id,
        $shelf_location,
        $oem_code,
        $supplier_code,
        $purchase_price,
        $wholesale_price,
        $dealer_price,
        $sale_price,
        $stock,
        $min_stock
    );
    $stmt->execute();
    $product_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($product_id > 0 && function_exists('ame_store_stock_set') && current_store_id() > 0) {
        ame_store_stock_set($db, current_store_id(), $product_id, (float)$stock);
    }

    if ($code_mode === 'auto') {
        $product_code = generate_product_code($db, $product_id);
        $update = $db->prepare('UPDATE products SET sku = ? WHERE id = ?');
        $update->bind_param('si', $product_code, $product_id);
        $update->execute();
        $update->close();
    }

    product_studio_apply_extra($db, $product_id, $_POST, null);
    if (function_exists('ame_apply_exchange_category_to_product')) {
        ame_apply_exchange_category_to_product($db, $product_id, $category_id);
    }

    if (function_exists('log_activity')) {
        log_activity('Product Added', 'Inventory', 'Product ID: ' . $product_code . ', Name: ' . $name);
    }

    $db->commit();
    $transaction_started = false;

    $respond(
        $is_english
            ? 'Product saved. ID: ' . $product_code
            : 'পণ্য সফলভাবে সংরক্ষণ হয়েছে। আইডি: ' . $product_code,
        'list.php'
    );
} catch (InvalidArgumentException $error) {
    if ($transaction_started) {
        $db->rollback();
    }
    $respond($error->getMessage(), 'add_product.php');
} catch (Throwable $error) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Product save failed: ' . $error->getMessage());
    $respond(
        $is_english ? 'The product could not be saved.' : 'পণ্য সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।',
        'add_product.php'
    );
}
