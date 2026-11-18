<?php
require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/error_monitor.php';

admin_only();

$is_english = current_lang() === 'en';

$words = $is_english
    ? [
        'title' => 'System Error Diagnostics',
        'subtitle' => 'Check application files, database structure and recent errors.',
        'recent' => 'Recent System Errors',
        'empty' => 'No recorded system errors were found.',
        'back' => 'Back to Dashboard',
        'clear' => 'Clear log',
        'clear_confirm' => 'Clear the entire error log? This only clears the log text, no code or data is touched.'
    ]
    : [
        'title' => 'সিস্টেম সমস্যা শনাক্তকরণ',
        'subtitle' => 'ফাইল, Database গঠন এবং সাম্প্রতিক সমস্যা পরীক্ষা করুন।',
        'recent' => 'সাম্প্রতিক সিস্টেম সমস্যা',
        'empty' => 'কোনো সংরক্ষিত সিস্টেম সমস্যা পাওয়া যায়নি।',
        'back' => 'ড্যাশবোর্ডে ফিরে যান',
        'clear' => 'লগ খালি করুন',
        'clear_confirm' => 'পুরো এরর লগ খালি করে দেওয়া হবে? এতে শুধু লগের লেখা মুছবে, কোনো কোড বা ডেটা বদলাবে না।'
    ];

$clear_message = $_SESSION['error_log_cleared'] ?? '';
unset($_SESSION['error_log_cleared']);

$escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$checks = [];

$add_check = static function (
    $name,
    $success,
    $detail
) use (&$checks) {
    $checks[] = [
        'name' => $name,
        'success' => (bool)$success,
        'detail' => $detail
    ];
};

$log_file = ma_diagnostic_log_path();
$log_directory = dirname($log_file);
$legacy_log = ame_legacy_jsonl_log_path();

$add_check(
    'Error Monitor',
    defined('MA_ERROR_MONITOR_LOADED'),
    defined('MA_ERROR_MONITOR_LOADED')
        ? 'Error Monitor চালু আছে।'
        : 'Error Monitor চালু হয়নি।'
);

$htaccess_text = (string)@file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
$party_ok = str_contains($htaccess_text, '!/pos_party_search\\.php$')
    || !str_contains($htaccess_text, 'pos_[A-Za-z0-9_-]+');
$add_check(
    'Sales party search',
    $party_ok,
    $party_ok
        ? 'বিক্রয়ের ক্রেতা/সাপ্লায়ার সার্চ খোলা আছে। Apache ৪০৩ PHP লগে আসে না।'
        : 'pos_party_search.php সার্ভারে ৪০৩। ক্রেতা/সাপ্লায়ার খোঁজা বন্ধ। এই ত্রুটি PHP এরর লগে আসে না।'
);

$add_check(
    'Private Log Folder',
    is_dir($log_directory) && is_writable($log_directory),
    $log_directory
);

$product_code_file = dirname(__DIR__, 2)
    . '/includes/product_code_functions.php';

$add_check(
    'Product Code Functions',
    is_file($product_code_file),
    is_file($product_code_file)
        ? 'প্রয়োজনীয় ফাইল পাওয়া গেছে।'
        : 'ফাইল পাওয়া যায়নি: ' . $product_code_file
);

$process_file = dirname(__DIR__)
    . '/inventory/process_product.php';

$process_content = is_file($process_file)
    ? (string)file_get_contents($process_file)
    : '';

$add_check(
    'Product Processing File',
    is_file($process_file),
    is_file($process_file)
        ? 'process_product.php পাওয়া গেছে।'
        : 'process_product.php পাওয়া যায়নি।'
);

$add_check(
    'Product Code Include',
    strpos(
        $process_content,
        'product_code_functions.php'
    ) !== false,
    'Product Code helper সংযুক্ত আছে কি না পরীক্ষা।'
);

// আগে এখানে পুরনো একটা নির্দিষ্ট bind_param স্ট্রিং ('ssiiisdddddd', ১২ কলামের
// সময়কার) সরাসরি হার্ডকোড করে খোঁজা হতো — পরে shelf_location/oem_code/
// supplier_code কলাম যোগ হয়ে ১৪ কলাম হয়ে যাওয়ায় (bind_param এখন
// 'ssiiisssdddddd') এই পুরনো চেকটা সবসময় ❌ দেখাতো, যদিও আসল কোড ঠিকই কাজ
// করছিল (নিচের "Product Insert Query" চেক সেটা আলাদাভাবে যাচাই করে)। এখন
// থেকে হার্ডকোড স্ট্রিং না খুঁজে, VALUES(...)-এর ভেতরের "?" সংখ্যা এবং
// bind_param-এ দেওয়া টাইপ-স্ট্রিং-এর দৈর্ঘ্য মিলছে কিনা যাচাই করা হচ্ছে —
// ভবিষ্যতে নতুন কলাম যোগ হলেও এই চেক নিজে থেকেই ঠিক থাকবে।
$insert_placeholder_count = 0;
if (preg_match('/INSERT\s+INTO\s+products[^;]*?VALUES\s*\(([^)]*)\)/is', $process_content, $insert_match)) {
    $insert_placeholder_count = substr_count($insert_match[1], '?');
}
$bind_type_length = null;
if (preg_match('/bind_param\s*\(\s*\'([a-z]+)\'/i', $process_content, $bind_match)) {
    $bind_type_length = strlen($bind_match[1]);
}
$param_type_ok = $insert_placeholder_count > 0
    && $bind_type_length !== null
    && $bind_type_length === $insert_placeholder_count;

$add_check(
    'Parameter Type Count',
    $param_type_ok,
    $param_type_ok
        ? $insert_placeholder_count . 'টি পণ্য তথ্যের bind_param ধরন ঠিক আছে।'
        : 'অমিল পাওয়া গেছে: INSERT কোয়েরিতে ' . $insert_placeholder_count
            . 'টি "?" কিন্তু bind_param-এ ' . ($bind_type_length ?? 0) . 'টি ধরন দেওয়া আছে।'
);

try {
    $columns_result = $db->query(
        'SHOW COLUMNS FROM products'
    );

    $columns = [];

    while ($column = $columns_result->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    $add_check(
        'Product SKU Column',
        in_array('sku', $columns, true),
        in_array('sku', $columns, true)
            ? 'products.sku কলাম পাওয়া গেছে।'
            : 'products.sku কলাম পাওয়া যায়নি।'
    );

    $required_columns = [
        'id',
        'sku',
        'name',
        'category_id',
        'brand_id',
        'unit_id',
        'shelf_location',
        'oem_code',
        'supplier_code',
        'purchase_price',
        'wholesale_price',
        'dealer_price',
        'sale_price',
        'stock',
        'min_stock'
    ];

    $missing_columns = array_diff(
        $required_columns,
        $columns
    );

    $add_check(
        'Product Database Structure',
        empty($missing_columns),
        empty($missing_columns)
            ? 'সব প্রয়োজনীয় কলাম আছে।'
            : 'অনুপস্থিত: ' . implode(', ', $missing_columns)
    );
} catch (Throwable $error) {
    $add_check(
        'Product Database Structure',
        false,
        $error->getMessage()
    );
}

try {
    $test_statement = $db->prepare(
        'INSERT INTO products (
            sku, name, category_id, brand_id, unit_id,
            shelf_location, oem_code, supplier_code, purchase_price, wholesale_price,
            dealer_price, sale_price, stock, min_stock
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $test_statement->close();

    $add_check(
        'Product Insert Query',
        true,
        'Insert query প্রস্তুত করা সম্ভব হয়েছে; কোনো তথ্য লেখা হয়নি।'
    );
} catch (Throwable $error) {
    $add_check(
        'Product Insert Query',
        false,
        $error->getMessage()
    );
}

$entries = ame_parse_php_error_log($log_file, 40);
$legacy = ame_parse_jsonl_error_log($legacy_log, 10);
foreach ($legacy as $row) {
    if (function_exists('ame_error_entry_is_noise') && ame_error_entry_is_noise($row)) {
        continue;
    }
    $row['type'] = ($row['type'] ?? 'OLD') . ' (পুরনো লগ)';
    $entries[] = $row;
}
if (function_exists('ame_error_entry_is_noise')) {
    $entries = array_values(array_filter($entries, static function ($row) {
        return is_array($row) && !ame_error_entry_is_noise($row);
    }));
}

include '../../partials/header.php';
include __DIR__ . '/error_diagnostics_style.php';
include __DIR__ . '/error_diagnostics_view.php';
include '../../partials/footer.php';