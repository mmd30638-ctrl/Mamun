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
    header('Location: list.php');
    exit();
}

csrf_verify();

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('পণ্য পাওয়া যায়নি।');
    }

    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$existing) {
        throw new InvalidArgumentException('পণ্য পাওয়া যায়নি।');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException(
            $is_english ? 'Product name is required.' : 'পণ্যের নাম অবশ্যই লিখতে হবে।'
        );
    }

    $sku_raw = trim((string)($_POST['sku'] ?? $existing['sku'] ?? ''));
    $product_code = $sku_raw !== ''
        ? normalize_product_code($sku_raw)
        : (string)$existing['sku'];
    if (product_code_exists($db, $product_code, $id)) {
        throw new InvalidArgumentException(
            $is_english ? 'This SKU is already in use.' : 'এই পণ্য আইডি ইতিমধ্যে ব্যবহার করা হয়েছে।'
        );
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

    $purchase_price = product_studio_require_number($_POST, 'purchase_price', $is_english ? 'Purchase price' : 'ক্রয় মূল্য');
    $wholesale_price = product_studio_require_number($_POST, 'wholesale_price', $is_english ? 'Wholesale price' : 'পাইকারি মূল্য');
    $dealer_price = product_studio_require_number($_POST, 'dealer_price', $is_english ? 'Re-dealer price' : 'রি-ডিলার মূল্য');
    $sale_price = product_studio_require_number($_POST, 'sale_price', $is_english ? 'Retail price' : 'খুচরা মূল্য');
    $stock = product_studio_require_number($_POST, 'stock', $is_english ? 'Current stock' : 'বর্তমান স্টক');
    $min_stock = product_studio_require_number($_POST, 'min_stock', $is_english ? 'Stock alert limit' : 'স্টক সতর্কতা সীমা');

    $sql = 'UPDATE products SET sku=?, name=?, category_id=?, brand_id=?, unit_id=?, shelf_location=?, oem_code=?, supplier_code=?, purchase_price=?, wholesale_price=?, dealer_price=?, sale_price=?, stock=?, min_stock=? WHERE id=?';
    $upd = $db->prepare($sql);
    $upd->bind_param(
        'ssiiisssddddddi',
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
        $min_stock,
        $id
    );
    $upd->execute();
    $upd->close();

    if (function_exists('ame_store_stock_set') && current_store_id() > 0) {
        ame_store_stock_set($db, current_store_id(), $id, (float)$stock);
    }

    product_studio_apply_extra($db, $id, $_POST, $existing['image_path'] ?? null);
    if (function_exists('ame_apply_exchange_category_to_product')) {
        ame_apply_exchange_category_to_product($db, $id, $category_id);
    }

    if (function_exists('log_activity')) {
        $oldStock = (string)($existing['stock'] ?? '');
        $oldName = (string)($existing['name'] ?? '');
        log_activity(
            'Product Updated',
            'Inventory',
            'Product ID: ' . $product_code . ', Name: ' . $name,
            [
                'action_type' => 'edit',
                'entity_type' => 'product',
                'entity_id' => (string)$id,
                'reference' => $product_code,
                'product_id' => $id,
                'product_name' => $name,
                'old' => [
                    'name' => $oldName,
                    'sku' => (string)($existing['sku'] ?? ''),
                    'stock' => $oldStock,
                    'sale_price' => (string)($existing['sale_price'] ?? ''),
                    'purchase_price' => (string)($existing['purchase_price'] ?? ''),
                ],
                'new' => [
                    'name' => $name,
                    'sku' => $product_code,
                    'stock' => (string)$stock,
                    'sale_price' => (string)$sale_price,
                    'purchase_price' => (string)$purchase_price,
                ],
            ]
        );
    }

    $respond(
        $is_english ? 'Product updated.' : 'সাফল্য! পণ্য তথ্য আপডেট হয়েছে।',
        'list.php'
    );
} catch (InvalidArgumentException $error) {
    $id = (int)($_POST['id'] ?? 0);
    $respond($error->getMessage(), $id > 0 ? ('edit.php?id=' . $id) : 'list.php');
} catch (Throwable $error) {
    error_log('Product update failed: ' . $error->getMessage());
    $id = (int)($_POST['id'] ?? 0);
    $respond(
        $is_english ? 'The product could not be updated.' : 'পণ্য আপডেট করা যায়নি। আবার চেষ্টা করুন।',
        $id > 0 ? ('edit.php?id=' . $id) : 'list.php'
    );
}
