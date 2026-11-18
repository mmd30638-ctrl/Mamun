<?php
/* 10.98.739 */
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/workshop_functions.php';
require_once '../../includes/lang_helper.php';

ensure_inventory_schema($db);
ensure_workshop_schema($db);

$is_english = current_lang() === 'en';

/**
 * একই লেখার বিকল্প রূপ: য়/ড়/ঢ় এক অক্ষরে (U+09DF…) বা য+়/ড+়/ঢ+় দুই অক্ষরে লেখা,
 * এবং বাংলা ↔ ইংরেজি অঙ্ক। পুরোনো POS থেকে আসা নামে এক রূপ, ফোনের কিবোর্ডে অন্য
 * রূপ — কোলেশন utf8mb4_general_ci হলে এরা LIKE-এ মেলে না, তাই সব রূপে খোঁজা হয়।
 */
$ame_term_variants = static function (string $term): array {
    $out = [$term];
    $candidates = [
        strtr($term, ["\u{09AF}\u{09BC}" => "\u{09DF}", "\u{09A1}\u{09BC}" => "\u{09DC}", "\u{09A2}\u{09BC}" => "\u{09DD}"]),
        strtr($term, ["\u{09DF}" => "\u{09AF}\u{09BC}", "\u{09DC}" => "\u{09A1}\u{09BC}", "\u{09DD}" => "\u{09A2}\u{09BC}"]),
        strtr($term, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']),
        strtr($term, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']),
    ];
    foreach ($candidates as $candidate) {
        if (!in_array($candidate, $out, true)) {
            $out[] = $candidate;
        }
    }
    return $out;
};

$ame_match_ids = static function (mysqli $db, string $term) use ($ame_term_variants): array {
    $ids = [];
    if ($term === '' || mb_strlen($term, 'UTF-8') > 80) {
        return $ids;
    }
    $columns = [];
    $colRes = @$db->query('SHOW COLUMNS FROM products');
    if ($colRes instanceof mysqli_result) {
        while ($colRow = $colRes->fetch_assoc()) {
            $columns[(string)$colRow['Field']] = true;
        }
        $colRes->free();
    }
    $where = [];
    $types = '';
    $params = [];
    foreach ($ame_term_variants($term) as $variant) {
        $like = '%' . $variant . '%';
        $where[] = 'p.name LIKE ?';
        $types .= 's';
        $params[] = $like;
        if (!empty($columns['sku'])) {
            $where[] = 'p.sku LIKE ?';
            $types .= 's';
            $params[] = $like;
        }
        foreach (['oem_code', 'supplier_code', 'barcode'] as $column) {
            if (empty($columns[$column])) {
                continue;
            }
            $where[] = 'p.' . $column . ' LIKE ?';
            $where[] = 'p.' . $column . ' = ?';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $variant;
        }
    }
    if (ctype_digit($term)) {
        $where[] = 'p.id = ?';
        $types .= 'i';
        $params[] = (int)$term;
    }
    if ($where === []) {
        return $ids;
    }
    $matchStmt = $db->prepare(
        'SELECT p.id FROM products p
         WHERE (' . implode(' OR ', $where) . ')
         LIMIT 2000'
    );
    if ($matchStmt) {
        $matchStmt->bind_param($types, ...$params);
        $matchStmt->execute();
        $matchRes = $matchStmt->get_result();
        while ($matchRes && ($row = $matchRes->fetch_assoc())) {
            $ids[] = (int)$row['id'];
        }
        $matchStmt->close();
    }
    return $ids;
};

if (isset($_GET['match'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $term = trim((string)$_GET['match']);
    echo json_encode(['term' => $term, 'ids' => $ame_match_ids($db, $term)], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $term = trim((string)$_GET['lookup']);
    $found = [];
    if ($term !== '' && mb_strlen($term, 'UTF-8') <= 80) {
        $pid = ctype_digit($term) ? (int)$term : 0;
        $variants = $ame_term_variants($term);
        $variantWhere = [];
        $variantTypes = '';
        $variantParams = [];
        foreach ($variants as $variant) {
            $like = '%' . $variant . '%';
            $variantWhere[] = '(name LIKE ? OR sku LIKE ? OR barcode LIKE ? OR oem_code LIKE ? OR supplier_code LIKE ?)';
            $variantTypes .= 'sssss';
            array_push($variantParams, $like, $like, $like, $like, $like);
        }
        $lookup = $db->prepare(
            'SELECT id, name, sku, status, stock, sale_price
             FROM products
             WHERE ' . implode(' OR ', $variantWhere) . ' OR id = ?
             ORDER BY (sku = ?) DESC, name ASC
             LIMIT 15'
        );
        if ($lookup) {
            $variantTypes .= 'is';
            $variantParams[] = $pid;
            $variantParams[] = $term;
            $lookup->bind_param($variantTypes, ...$variantParams);
            $lookup->execute();
            $res = $lookup->get_result();
            while ($row = $res->fetch_assoc()) {
                $found[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'sku' => (string)($row['sku'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'stock' => (float)($row['stock'] ?? 0),
                    'price' => (float)($row['sale_price'] ?? 0),
                ];
            }
            $lookup->close();
        }
    }
    echo json_encode(['products' => $found], JSON_UNESCAPED_UNICODE);
    exit;
}

$words = $is_english
    ? [
        'title' => 'Inventory Master List',
        'subtitle' => 'Search and monitor products, stock and prices.',
        'search' => 'Search by name, product code, OEM or supplier code...',
        'add' => 'Add Product',
        'category' => 'Categories',
        'brand' => 'Brands',
        'unit' => 'Units',
        'product' => 'Product Details',
        'stock' => 'Stock (this store)',
        'store' => 'Store',
        'price' => 'K / P / D prices',
        'action' => 'Action',
        'general' => 'General',
        'edit' => 'Edit',
        'empty' => 'No active products were found.',
        'error' => 'The stock list could not be loaded. Please contact the administrator.'
    ]
    : [
        'title' => 'ইনভেন্টরি মাস্টার তালিকা',
        'subtitle' => 'পণ্য, স্টক ও মূল্য অনুসন্ধান এবং পর্যবেক্ষণ করুন।',
        'search' => 'নাম, পণ্য কোড, OEM বা সাপ্লায়ার কোড দিয়ে খুঁজুন...',
        'add' => 'নতুন পণ্য',
        'category' => 'ক্যাটেগরি',
        'brand' => 'ব্র্যান্ড',
        'unit' => 'ইউনিট',
        'product' => 'পণ্যের বিবরণ',
        'stock' => 'মজুদ (এই স্টোর)',
        'store' => 'স্টোর',
        'price' => 'K / P / D মূল্য',
        'action' => 'কার্যক্রম',
        'general' => 'সাধারণ',
        'edit' => 'সম্পাদনা',
        'empty' => 'কোনো সক্রিয় পণ্য পাওয়া যায়নি।',
        'error' => 'স্টক তালিকা লোড করা যায়নি। প্রশাসকের সঙ্গে যোগাযোগ করুন।'
    ];

$escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$products = false;
$load_error = false;
$store_id = 0;
$stock_select = 'p.stock AS store_qty';
$stock_join = '';
$reserved_map = [];

try {
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    $stock_select = $store_id > 0
        ? 'COALESCE(ss.qty, p.stock) AS store_qty'
        : 'p.stock AS store_qty';
    $stock_join = $store_id > 0
        ? ' LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ' . (int)$store_id . ' '
        : '';
    $reserved_map = function_exists('ame_workshop_reserved_map')
        ? ame_workshop_reserved_map($db, (int)$store_id)
        : [];
} catch (Throwable $error) {
    error_log('Inventory list context failed: ' . $error->getMessage());
}

$product_select_sql = "SELECT
            p.*,
            {$stock_select},
            c.name AS category_name,
            u.unit_name,
            p.id AS id
         FROM products p
         LEFT JOIN categories c
            ON p.category_id = c.id
         LEFT JOIN product_units u
            ON p.unit_id = u.id
         {$stock_join}";

/**
 * একটি পণ্যের সারি (tr) — মূল তালিকা এবং ডাটাবেস-মিলের বাড়তি সারি, দুটোই এই এক ছাঁচে।
 */
$render_row = static function (array $product) use ($escape, $words, $is_english, &$reserved_map): string {
    $product_code = trim(
        (string)($product['sku'] ?? '')
    );

    if ($product_code === '') {
        $product_code = 'PRD-' . str_pad(
            (string)$product['id'],
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    $stock = (float)($product['store_qty'] ?? $product['stock'] ?? 0);
    $reserved = (float)($reserved_map[(int)$product['id']] ?? 0);
    $sellable = ame_stock_sellable($stock, $reserved);
    $minimum_stock = (float)($product['min_stock'] ?? 0);
    $is_negative = $stock < 0;
    $is_low = !$is_negative && $sellable <= $minimum_stock;

    $search_text = mb_strtolower(
        '#' . $product['id'] . ' ' . $product['name'] . ' ' . $product_code
        . ' ' . ($product['oem_code'] ?? '')
        . ' ' . ($product['supplier_code'] ?? '')
        . ' ' . ($product['barcode'] ?? '')
        . ' ' . ($product['tags'] ?? '')
        . ' ' . ($product['category_name'] ?? '')
        . ' ' . ($product['unit_name'] ?? '')
        . ' ' . ($product['shelf_location'] ?? ''),
        'UTF-8'
    );
    $thumb = trim((string)($product['image_path'] ?? ''));
    $thumb_src = $thumb !== ''
        ? '/' . ltrim($thumb, '/')
        : 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . rawurlencode($product_code);
    $row_status = strtolower(trim((string)($product['status'] ?? 'active')));
    if ($row_status === '') {
        $row_status = 'active';
    }
    ob_start();
    ?>
                            <tr data-id="<?php echo (int)$product['id']; ?>" data-search="<?php echo $escape($search_text); ?>">
                                <td data-label="<?php echo $escape($words['product']); ?>">
                                    <div class="stock-product">
                                        <img
                                            class="stock-qr"
                                            src="<?php echo $escape($thumb_src); ?>"
                                            alt=""
                                            loading="lazy"
                                        >

                                        <div>
                                            <div class="stock-name">
                                                <?php echo $escape($product['name']); ?>
                                                <?php if ($row_status !== 'active'): ?>
                                                    <em class="stock-status"><?php echo $escape($row_status); ?></em>
                                                <?php endif; ?>
                                                <?php if (!empty($product['_pinned'])): ?>
                                                    <em class="stock-status db"><?php echo $is_english ? 'from sales' : 'বিক্রয়ের সাথে মিল'; ?></em>
                                                <?php endif; ?>
                                                <?php if (!empty($product['is_old_battery'])): ?>
                                                    <em class="stock-status old">old</em>
                                                <?php endif; ?>
                                            </div>

                                            <span class="stock-code">
                                                ID: <?php echo $escape($product_code); ?>
                                                <?php if (!empty($product['barcode'])): ?>
                                                    · <?php echo $escape($product['barcode']); ?>
                                                <?php endif; ?>
                                            </span>

                                            <small class="stock-meta">
                                                📂 <?php echo $escape(
                                                    $product['category_name']
                                                    ?: $words['general']
                                                ); ?>

                                                <?php if (!empty($product['unit_name'])): ?>
                                                    | ⚖️ <?php echo $escape($product['unit_name']); ?>
                                                <?php endif; ?>

                                                <?php if (!empty($product['shelf_location'])): ?>
                                                    | 📍 <?php echo $escape($product['shelf_location']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($product['oem_code'])): ?>
                                                    | OEM <?php echo $escape($product['oem_code']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($product['supplier_code'])): ?>
                                                    | সাপ <?php echo $escape($product['supplier_code']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td data-label="<?php echo $escape($words['stock']); ?>">
                                    <span class="stock-badge <?php echo $is_negative ? 'negative' : ($is_low ? 'low' : ''); ?>">
                                        <?php echo $escape($sellable); ?>
                                        <?php echo $escape($product['unit_name'] ?? ''); ?>
                                    </span>
                                    <?php if ($is_negative || $reserved > 0.0001): ?>
                                        <div class="stock-meta">
                                            <?php
                                            $hand_text = rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.');
                                            $reserve_text = rtrim(rtrim(number_format($reserved, 3, '.', ''), '0'), '.');
                                            if ($is_english) {
                                                echo $escape('On hand ' . $hand_text . ($reserved > 0.0001 ? ' · job reserved ' . $reserve_text : ''));
                                            } else {
                                                echo $escape('হাতে ' . $hand_text . ($reserved > 0.0001 ? ' · জব রিজার্ভ ' . $reserve_text : ''));
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td data-label="<?php echo $escape($words['price']); ?>">
                                    <div class="stock-kpd">
                                        <span>K ৳<?php echo number_format((float)$product['sale_price'], 2); ?></span>
                                        <span>P ৳<?php echo number_format((float)$product['wholesale_price'], 2); ?></span>
                                        <span>D ৳<?php echo number_format((float)($product['dealer_price'] ?? 0), 2); ?></span>
                                    </div>
                                </td>

                                <td data-label="<?php echo $escape($words['action']); ?>">
                                    <a
                                        class="stock-edit"
                                        href="edit.php?id=<?php echo (int)$product['id']; ?>"
                                    >
                                        ⚙️ <?php echo $escape($words['edit']); ?>
                                    </a>
                                </td>
                            </tr>
    <?php
    return (string)ob_get_clean();
};

if (isset($_GET['rows'])) {
    // ডাটাবেস যে পণ্য মেলায় কিন্তু পাতায় যার সারি নেই (যেমন আর্কাইভ করা),
    // সেই পণ্যের সারি একই ছাঁচে বানিয়ে পাঠানো হয়; ব্রাউজার তালিকায় বসিয়ে দেখায়।
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $ids = [];
    foreach (explode(',', (string)$_GET['rows']) as $piece) {
        $piece = trim($piece);
        if ($piece !== '' && ctype_digit($piece) && (int)$piece > 0) {
            $ids[(int)$piece] = true;
        }
        if (count($ids) >= 50) {
            break;
        }
    }
    $rows = [];
    $info = [];
    if ($ids !== []) {
        try {
            $rowRes = $db->query(
                $product_select_sql
                . ' WHERE p.id IN (' . implode(',', array_keys($ids)) . ')'
                . ' ORDER BY p.name ASC'
            );
            while ($rowRes && ($product = $rowRes->fetch_assoc())) {
                $key = (string)(int)$product['id'];
                $rows[$key] = $render_row($product);
                $status = strtolower(trim((string)($product['status'] ?? '')));
                // মূল তালিকার কুয়েরি (status <> 'archived') এই পণ্যকে নেয় কি না —
                // পাতায় সারি না থাকলে এ তথ্যেই বোঝা যায় ডাটাবেস বাদ দিয়েছে, না পাতা।
                $info[$key] = [
                    'status' => $status === '' ? 'active' : $status,
                    'in_main' => true,
                ];
            }
        } catch (Throwable $error) {
            error_log('Inventory list rows failed: ' . $error->getMessage());
        }
    }
    echo json_encode(['rows' => $rows, 'info' => $info], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (isset($_GET['search_html'])) {
    // সার্চের উত্তর সার্ভারেই আঁকা সারি — পাতার পুরোনো তালিকা ফিল্টার করা হয় না।
    // বিক্রয়/ক্রয় যে আইডি মেলায়, এখানে সেগুলোর HTML-ই ফেরত যায়।
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $term = trim((string)$_GET['search_html']);
    $ids = $ame_match_ids($db, $term);
    $html = '';
    if ($ids !== []) {
        try {
            $rowRes = $db->query(
                $product_select_sql
                . ' WHERE p.id IN (' . implode(',', $ids) . ')'
                . ' ORDER BY p.name ASC'
            );
            while ($rowRes && ($product = $rowRes->fetch_assoc())) {
                $html .= $render_row($product);
            }
        } catch (Throwable $error) {
            error_log('Inventory search_html failed: ' . $error->getMessage());
        }
    }
    echo json_encode(
        ['term' => $term, 'ids' => $ids, 'count' => count($ids), 'html' => $html],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

$listed = [];
$listed_ids = [];
$ame_list_push = static function (array $product, bool $pinned = false) use (&$listed, &$listed_ids): void {
    $id = (int)($product['id'] ?? 0);
    if ($id <= 0 || isset($listed_ids[$id])) {
        return;
    }
    if ($pinned) {
        $product['_pinned'] = true;
    }
    $listed_ids[$id] = true;
    $listed[] = $product;
};

try {
    // কোনো WHERE/স্ট্যাটাস/ক্যাটাগরি/টাইপ/স্টক ছাঁকনি নেই — products টেবিলের
    // প্রতিটি সারিই আসে (LEFT JOIN, তাই ক্যাটাগরি/ইউনিট/স্টোর-স্টক না থাকলেও সারি বাদ যায় না)।
    // p.id AS id শেষে রাখা: JOIN-এর অন্য id কলাম p.id কে মুছে ফেললেও data-id ঠিক থাকে।
    $products = $db->query(
        $product_select_sql
        . ' ORDER BY p.name ASC'
    );
} catch (Throwable $error) {
    $load_error = true;

    error_log(
        'Inventory list load failed: '
        . $error->getMessage()
    );
}

// বিক্রয়ে যে ২–৩টি পণ্য আসে (100/ HP বাবুল পিনিয়ম, 402/ ইলং ব্যাটারী) — নতুন রেকর্ড না
// বানিয়ে একই আইডি/স্টক দিয়ে তালিকার ওপরে আলাদা করে বসানো হয়।
try {
    $pinRes = $db->query(
        $product_select_sql
        . " WHERE TRIM(IFNULL(p.sku,'')) IN ('100/','402/','100.','402')
             OR p.name LIKE 'HP বাবুল%'
             OR p.name LIKE '%ইলং ব্যাটার%'
         ORDER BY p.name ASC"
    );
    while ($pinRes && ($pinRow = $pinRes->fetch_assoc())) {
        $ame_list_push($pinRow, true);
    }
} catch (Throwable $pinError) {
    error_log('Inventory pin rows failed: ' . $pinError->getMessage());
}
if ($products instanceof mysqli_result) {
    while ($row = $products->fetch_assoc()) {
        $ame_list_push($row, false);
    }
}

include '../../partials/header.php';
include __DIR__ . '/stock_list_style.php';
?>

<div class="stock-page">
    <section class="stock-shell">
        <div class="stock-color-bar">
            <span></span><span></span><span></span><span></span>
        </div>

        <header class="stock-heading">
            <h2>📋 <?php echo $escape($words['title']); ?></h2>
            <p><span id="stockListVer">তালিকা 10.98.739</span>
                · <?php echo $escape($words['subtitle']); ?>
                <?php if (function_exists('current_store_name')): ?>
                    · <?php echo $escape($words['store']); ?>: <?php echo $escape(current_store_name()); ?>
                <?php endif; ?>
            </p>
        </header>

        <div class="stock-toolbar">
            <input
                type="search"
                id="stockSearch"
                class="stock-search"
                placeholder="<?php echo $escape($words['search']); ?>"
                value="<?php echo $escape(mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80, 'UTF-8')); ?>"
            >

            <div class="stock-actions">
                <a href="add_product.php" class="stock-action green">
                    ➕ <?php echo $escape($words['add']); ?>
                </a>
                <a href="categories.php" class="stock-action blue">
                    🏷️ <?php echo $escape($words['category']); ?>
                </a>
                <a href="brands.php" class="stock-action black">
                    🏭 <?php echo $escape($words['brand']); ?>
                </a>
                <a href="units.php" class="stock-action red">
                    📏 <?php echo $escape($words['unit']); ?>
                </a>
            </div>
        </div>

        <?php if ($load_error): ?>
            <div class="stock-message error">
                <?php echo $escape($words['error']); ?>
            </div>
        <?php elseif ($listed === []): ?>
            <div class="stock-message empty">
                <?php echo $escape($words['empty']); ?>
            </div>
        <?php else: ?>
            <div class="stock-table-wrap">
                <table class="stock-table" data-ame-no-page="1">
                    <thead>
                        <tr>
                            <th><?php echo $escape($words['product']); ?></th>
                            <th><?php echo $escape($words['stock']); ?></th>
                            <th><?php echo $escape($words['price']); ?></th>
                            <th><?php echo $escape($words['action']); ?></th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        <?php foreach ($listed as $product): ?>
                            <?php echo $render_row($product); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p id="stockMatchInfo" class="stock-match-info" style="display:none;"></p>
            <div id="stockPager" class="ame-auto-pager" style="display:none;"></div>
            <p id="stockNoMatch" class="stock-message empty" style="display:none;"></p>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    const search = document.getElementById('stockSearch');
    const rows = Array.from(document.querySelectorAll('#stockTableBody tr'));
    const noMatch = document.getElementById('stockNoMatch');
    const english = <?php echo $is_english ? 'true' : 'false'; ?>;

    if (!search) {
        return;
    }

    // ডাটাবেসের utf8mb4_unicode_ci কোলেশনের মতো: বাংলা অঙ্ক = ইংরেজি অঙ্ক,
    // ং / ঃ / ় উপেক্ষা। বিক্রয় ডেস্কের SQL সার্চ যেভাবে মেলায়, এখানেও তাই।
    function fold(text) {
        return String(text || '')
            .normalize('NFC')
            .toLowerCase()
            .replace(/[\u09e6-\u09ef]/g, function (digit) {
                return String.fromCharCode(digit.charCodeAt(0) - 0x09e6 + 48);
            })
            .replace(/[\u200b-\u200d\u2060\ufeff]/g, '')
            .replace(/\u09df/g, '\u09af')
            .replace(/\u09dc/g, '\u09a1')
            .replace(/\u09dd/g, '\u09a2')
            .replace(/[\u0982\u0983\u09bc]/g, '')
            .replace(/[\u09c0]/g, '\u09bf')
            .replace(/[\u09c2]/g, '\u09c1')
            .replace(/[\s,;:.\-_/\\()\[\]{}"'|+*#=]+/g, ' ')
            .trim();
    }

    const rowById = new Map();
    function registerRow(row, injected) {
        row._id = parseInt(row.dataset.id || '0', 10) || 0;
        row._folded = ' ' + fold(row.dataset.search) + ' ';
        row._compact = row._folded.replace(/ /g, '');
        if (injected) {
            row._injected = true;
        }
        if (row._id > 0) {
            rowById.set(row._id, row);
        }
    }
    rows.forEach(function (row) { registerRow(row, false); });
    function rowForId(id) {
        id = parseInt(id, 10) || 0;
        if (id <= 0) {
            return null;
        }
        let row = rowById.get(id);
        if (row && row.parentNode) {
            return row;
        }
        if (!tableBody) {
            return null;
        }
        row = tableBody.querySelector('tr[data-id="' + id + '"]');
        if (!row) {
            return null;
        }
        if (rows.indexOf(row) === -1) {
            rows.push(row);
        }
        registerRow(row, false);
        return row;
    }

    // ডাটাবেস যে পণ্য মেলায় কিন্তু এই পাতায় যার সারি নেই (যেমন আর্কাইভ করা),
    // সেই সারি list.php?rows= থেকে এনে তালিকায় বসানো হয় — স্ট্যাটাস ব্যাজসহ।
    const tableBody = document.getElementById('stockTableBody');
    const originalHTML = tableBody ? tableBody.innerHTML : '';
    let usingServerList = false;
    const matchInfo = document.getElementById('stockMatchInfo');
    // সার্চের নিচে ছোট হিসাব: ডাটাবেস কয়টি মেলাল, তার কয়টি পাতায় ছিল, কয়টি আনা হলো —
    // কোনো পণ্য না দেখালে এই লাইনেই বোঝা যায় কোন ধাপে আটকেছে।
    function showMatchInfo(text, isError) {
        if (!matchInfo) {
            return;
        }
        if (!text) {
            matchInfo.style.display = 'none';
            matchInfo.textContent = '';
            return;
        }
        const pageRows = rows.length;
        matchInfo.textContent = text + (english
            ? ' (page list: ' + pageRows + ' rows)'
            : ' (পাতার তালিকায় মোট ' + pageRows + 'টি সারি)');
        matchInfo.classList.toggle('is-error', !!isError);
        matchInfo.style.display = '';
    }
    function injectMissingRows(ids, term, seq) {
        const missing = ids.filter(function (id) { return id > 0 && !rowForId(id); }).slice(0, 50);
        const onPage = ids.length - missing.length;
        if (!missing.length || !tableBody) {
            return Promise.resolve();
        }
        return fetch('list.php?rows=' + encodeURIComponent(missing.join(',')), { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (seq !== serverSeq) {
                    return;
                }
                if (!data || !data.rows) {
                    throw new Error('empty');
                }
                const holder = document.createElement('tbody');
                let added = 0;
                Object.keys(data.rows).forEach(function (key) {
                    if (rowById.has(parseInt(key, 10))) {
                        return;
                    }
                    holder.innerHTML = data.rows[key];
                    const row = holder.querySelector('tr');
                    if (!row) {
                        return;
                    }
                    row.classList.add('is-db-row');
                    const nameBox = row.querySelector('.stock-name');
                    if (nameBox) {
                        const badge = document.createElement('em');
                        badge.className = 'stock-status db';
                        badge.textContent = english ? 'from database' : 'ডাটাবেস থেকে';
                        nameBox.appendChild(badge);
                    }
                    // পাতায় না-থাকা পণ্য তালিকার শুরুতে বসে — স্ক্রল না করেই চোখে পড়ে।
                    tableBody.insertBefore(row, tableBody.firstChild);
                    rows.unshift(row);
                    registerRow(row, true);
                    added += 1;
                });
                const detail = missing.map(function (id) {
                    const meta = (data.info && data.info[String(id)]) || null;
                    if (!meta) {
                        return '#' + id;
                    }
                    return '#' + id + ' (' + (english ? 'status ' : 'স্ট্যাটাস ') + meta.status + ', '
                        + (english ? 'in page query: ' : 'পাতার কুয়েরিতে পড়ে: ')
                        + (meta.in_main ? (english ? 'yes' : 'হ্যাঁ') : (english ? 'no' : 'না')) + ')';
                }).join(', ');
                showMatchInfo(english
                    ? 'Database matched ' + ids.length + ' product(s): ' + onPage + ' on this page, ' + added + ' fetched and shown at the top. Not in the page list: ' + detail + '.'
                    : 'ডাটাবেস ' + ids.length + 'টি পণ্য মেলাল: ' + onPage + 'টি এই পাতায় ছিল, ' + added + 'টি ডাটাবেস থেকে এনে ওপরে বসানো হলো। পাতার তালিকায় ছিল না: ' + detail + '।', false);
            })
            .catch(function (error) {
                if (seq !== serverSeq) {
                    return;
                }
                showMatchInfo(english
                    ? 'Database matched product #' + missing.join(', #') + ' but the row could not be fetched (list.php?rows=): ' + (error && error.message ? error.message : 'error')
                    : 'ডাটাবেস পণ্য #' + missing.join(', #') + ' মেলাল, কিন্তু সারি আনা যায়নি (list.php?rows=): ' + (error && error.message ? error.message : 'ত্রুটি'), true);
            });
    }

    // ডাটাবেস থেকে আসা মিল (list.php?match=) — বিক্রয়/ক্রয়ের সার্চের হুবহু শর্ত।
    let serverTerm = '';
    let serverIds = null;
    let serverTimer = null;
    let serverSeq = 0;

    function matches(row, tokens, compactQuery, idQuery) {
        if (idQuery > 0 && row._id === idQuery) {
            return true;
        }
        if (serverIds && serverIds.has(Number(row._id))) {
            return true;
        }
        if (tokens.every(function (token) { return row._folded.indexOf(token) !== -1; })) {
            return true;
        }
        return compactQuery !== '' && row._compact.indexOf(compactQuery) !== -1;
    }

    function resetRowsFromBody(injected) {
        rowById.clear();
        rows.length = 0;
        if (!tableBody) {
            return;
        }
        Array.prototype.forEach.call(tableBody.querySelectorAll(':scope > tr'), function (row) {
            rows.push(row);
            registerRow(row, !!injected);
        });
    }
    function restoreOriginal() {
        serverTerm = '';
        serverIds = null;
        if (usingServerList && tableBody) {
            tableBody.innerHTML = originalHTML;
            usingServerList = false;
            resetRowsFromBody(false);
        }
        matched = rows.filter(function (row) { return !row._injected; });
        page = 1;
        paint();
        showMatchInfo('');
        if (noMatch) {
            noMatch.style.display = 'none';
        }
    }
    function applyServerHtml(html, ids, term) {
        if (!tableBody) {
            return;
        }
        usingServerList = true;
        tableBody.innerHTML = html || '';
        resetRowsFromBody(true);
        matched = rows.slice();
        page = 1;
        paint();
        const shown = matched.length;
        showMatchInfo(english
            ? 'Database matched ' + ids.length + ': #' + ids.join(', #') + '. Showing ' + shown + '.'
            : 'ডাটাবেস ' + ids.length + 'টি মেলাল: #' + ids.join(', #') + '। দেখাচ্ছি ' + shown + 'টি।', shown !== ids.length);
        if (noMatch) {
            if (ids.length === 0) {
                noMatch.textContent = english
                    ? 'No product matched "' + term + '".'
                    : '«' + term + '» নামে কোনো পণ্য পাওয়া যায়নি।';
                noMatch.style.display = '';
                scheduleLookup(term);
            } else {
                noMatch.style.display = 'none';
            }
        }
    }
    function scheduleServerMatch(term) {
        clearTimeout(serverTimer);
        const seq = ++serverSeq;
        if (term === '') {
            restoreOriginal();
            return;
        }
        if (term === serverTerm && serverIds) {
            return;
        }
        showMatchInfo(english ? 'Searching…' : 'খুঁজছি…', false);
        serverTimer = setTimeout(function () {
            fetch('list.php?search_html=' + encodeURIComponent(term), { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (seq !== serverSeq || search.value.trim() !== term) {
                        return;
                    }
                    if (!data || !Array.isArray(data.ids)) {
                        throw new Error('empty');
                    }
                    serverTerm = term;
                    serverIds = new Set();
                    data.ids.forEach(function (id) {
                        id = parseInt(id, 10);
                        if (id > 0) {
                            serverIds.add(id);
                        }
                    });
                    applyServerHtml(data.html || '', Array.from(serverIds), term);
                })
                .catch(function (error) {
                    if (seq !== serverSeq || search.value.trim() !== term) {
                        return;
                    }
                    showMatchInfo(english
                        ? 'Database search failed: ' + (error && error.message ? error.message : 'error')
                        : 'ডাটাবেস-সার্চ আনা যায়নি: ' + (error && error.message ? error.message : 'ত্রুটি'), true);
                });
        }, 200);
    }

    const pager = document.getElementById('stockPager');
    const PAGE_SIZE = 50;
    let page = 1;
    let matched = rows.slice();

    function paint() {
        const pages = Math.max(1, Math.ceil(matched.length / PAGE_SIZE));
        if (page > pages) {
            page = pages;
        }
        const from = (page - 1) * PAGE_SIZE;
        const to = from + PAGE_SIZE;
        const visible = new Set(matched.slice(from, to));
        rows.forEach(function (row) {
            if (visible.has(row)) {
                row.style.removeProperty('display');
            } else {
                row.style.setProperty('display', 'none', 'important');
            }
        });
        if (!pager) {
            return;
        }
        if (pages <= 1) {
            pager.style.display = 'none';
            pager.textContent = '';
            return;
        }
        pager.style.display = '';
        pager.innerHTML = '<button type="button"' + (page <= 1 ? ' disabled' : '') + '>' + (english ? 'Prev' : 'আগে') + '</button>'
            + '<span>' + (english ? 'Page ' : 'পৃষ্ঠা ') + page + ' / ' + pages + ' · ' + matched.length + (english ? ' products' : ' পণ্য') + '</span>'
            + '<button type="button"' + (page >= pages ? ' disabled' : '') + '>' + (english ? 'Next' : 'পরে') + '</button>';
        const buttons = pager.querySelectorAll('button');
        buttons[0].onclick = function () { if (page > 1) { page -= 1; paint(); } };
        buttons[1].onclick = function () { if (page < pages) { page += 1; paint(); } };
    }

    function run(fromServer) {
        const term = search.value.trim();
        if (term === '') {
            restoreOriginal();
            return;
        }
        if (!fromServer) {
            page = 1;
            scheduleServerMatch(term);
        }
    }

    let lookupTimer = null;
    let lookupSeq = 0;
    function scheduleLookup(term) {
        clearTimeout(lookupTimer);
        if (term.length < 2 && !/^\d+$/.test(term)) {
            return;
        }
        const seq = ++lookupSeq;
        lookupTimer = setTimeout(function () {
            fetch('list.php?lookup=' + encodeURIComponent(term), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (seq !== lookupSeq || !noMatch || noMatch.style.display === 'none') {
                        return;
                    }
                    const items = (data && data.products) || [];
                    if (!items.length) {
                        return;
                    }
                    noMatch.textContent = '';
                    const head = document.createElement('div');
                    head.textContent = english
                        ? 'Not in this list, but found in the database:'
                        : 'এই তালিকায় নেই, কিন্তু ডাটাবেসে পাওয়া গেছে:';
                    head.style.fontWeight = '800';
                    noMatch.appendChild(head);
                    items.forEach(function (item) {
                        const line = document.createElement('div');
                        const link = document.createElement('a');
                        link.href = 'edit.php?id=' + encodeURIComponent(item.id);
                        link.textContent = '#' + item.id + ' · ' + item.name + ' · ID ' + (item.sku || '—') +
                            (item.status && item.status !== 'active' ? ' · ' + item.status : '');
                        line.appendChild(link);
                        noMatch.appendChild(line);
                    });
                })
                .catch(function () {});
        }, 350);
    }

    search.addEventListener('input', function () { run(false); });
    search.addEventListener('search', function () { run(false); });
    run(false);
})();
</script>

<?php include '../../partials/footer.php'; ?>