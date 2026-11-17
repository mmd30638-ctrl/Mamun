<?php
/**
 * Secure Permission System (mysqli only)
 */
function ensure_permission_schema($db) {
    // পারফরম্যান্স ফিক্স: has_permission() প্রায় প্রতিটা পেজে (সাইডবারের
    // প্রতিটা মেনু-আইটেমের জন্য আলাদাভাবে, একটা পেজে ২০-৩০ বার পর্যন্ত)
    // কল হয়, আর এতদিন এই ফাংশনটা প্রতিবার কল হলেই ২টা CREATE TABLE IF NOT
    // EXISTS + ১টা SHOW COLUMNS FROM users চালাত — অর্থাৎ একটা পেজে
    // শুধু এই একটা ফাংশনের জন্যই ৬০-৯০টা অতিরিক্ত ডাটাবেজ কোয়েরি হতো,
    // যেটা পুরো সাইট ধীর হয়ে যাওয়ার একটা বড় কারণ ছিল। এই টেবিল/কলাম
    // একবার তৈরি হয়ে গেলে বারবার চেক করার দরকার নেই, তাই এখন এই পুরো
    // কাজটা প্রতি রিকোয়েস্টে (পেজ-লোডে) শুধু একবারই চলবে।
    static $ensured = false;
    if ($ensured) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('permission_schema')) {
        $ensured = true;
        return;
    }
    $ensured = true;

    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(50) NOT NULL,
        module_key VARCHAR(50) NOT NULL,
        UNIQUE KEY unique_role_module (role_key, module_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->query($sql);

    $db->query("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_key VARCHAR(50) NOT NULL,
        UNIQUE KEY unique_user_module (user_id, module_key),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = [];
    $res = @$db->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    if ($cols && !in_array('use_custom_perms', $cols, true)) {
        $db->query("ALTER TABLE users ADD COLUMN use_custom_perms TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('permission_schema', true);
    }
}

function get_all_modules()
{
    return [
        'm-sales' => '🧾 বিক্রয় ও POS',
        'm-sales-report' => '📊 বিক্রয় রিপোর্ট',
        'm-sales-returns' => '🔄 বিক্রয় ফেরত মডিউল',
        'm-purchase' => '🚚 ক্রয়',
        'm-purchase-returns' => '↩️ ক্রয় ফেরত',
        'm-inv' => '📦 ইনভেন্টরি',
        'm-workshop' => '🔧 ওয়ার্কশপ',
        'm-customers' => '👤 ক্রেতা',
        'm-hire' => '🤝 কাস্টমার ভাড়া',
        'm-hire-rate' => 'ভাড়া হার পরিবর্তন',
        'm-hire-product' => 'ভাড়া পণ্য পরিবর্তন',
        'm-hire-deposit' => 'জামানত ব্যবহার',
        'm-hire-refund' => 'জামানত ফেরত',
        'm-hire-adjust' => 'ভাড়া আর্থিক সমন্বয়',
        'm-hire-cancel' => 'ভাড়া চুক্তি বাতিল',
        'm-rental' => '📦 ভাড়া স্টেটমেন্ট',
        'm-shop-rent' => '🏪 দোকান ভাড়া',
        'm-suppliers' => '🤝 সাপ্লায়ার',
        'm-transactions' => '💱 লেনদেন',
        'm-acc' => '💼 হিসাব-নিকাশ',
        'm-hrm' => '👷 কর্মী',
        'm-warranty' => '🛡️ ওয়ারেন্টি',
        'm-loans' => '🏦 লোন/ঋণ',
        'm-reports' => '📈 রিপোর্ট',
        'm-pulse' => '◉ দোকানের অবস্থা',
        'm-agents' => '🤖 এজেন্টস',
        'm-smart' => 'স্মার্ট ব্যবসা হাব',
        'm-family' => 'পারিবারিক ব্যবস্থাপনা',
        'm-others' => '📁 অন্যান্য',
        'm-ticker' => '📢 চলমান লেখা',
        'm-countdown' => '⏳ কাউন্টডাউন',
        'm-settings' => '⚙️ সিস্টেম সেটিংস',
        'm-visual-design' => '🎨 ভিজ্যুয়াল ডিজাইন',
    ];
}

function ame_user_permission_keys(mysqli $db, int $user_id): array
{
    ensure_permission_schema($db);
    $keys = [];
    // BUG FIX: prepare() ব্যর্থ (false) হলে আগে সরাসরি bind_param() কল করে
    // ফ্যাটাল এরর হতো (দেখুন role_permissions.php-এর নোট) — এখন গার্ড করা হলো।
    $stmt = $db->prepare("SELECT module_key FROM user_permissions WHERE user_id = ?");
    if (!$stmt) {
        error_log('ame_user_permission_keys: prepare failed — ' . $db->error);
        return $keys;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $keys[] = $row['module_key'];
    }
    $stmt->close();
    return $keys;
}

function has_permission($db, $module_key) {
    if (!isset($_SESSION['role'])) return false;
    if (strtolower((string)$_SESSION['role']) === 'admin') return true;

    // পারফরম্যান্স ফিক্স: একই পেজে (যেমন সাইডবার রেন্ডার করার সময়)
    // has_permission() বারবার একই module_key দিয়ে কল হয় না ঠিকই, কিন্তু
    // একটা পেজে ২০-৩০টা ভিন্ন module_key-এর জন্য এটা কল হয়, আর প্রতিটা
    // কলে "use_custom_perms" ফ্ল্যাগ চেক করতে আলাদা করে users টেবিলে
    // কোয়েরি চলত — এই ফ্ল্যাগ পুরো রিকোয়েস্ট জুড়ে একই ইউজারের জন্য
    // একই থাকে, তাই এখন এটা একবার লোড করে ক্যাশ করে রাখা হচ্ছে। এছাড়া
    // একই module_key নিয়ে যদি একাধিকবার জিজ্ঞাসা করা হয় (বিভিন্ন
    // ফাংশন/পেজ-অংশ থেকে), সেই ফলাফলও রিকোয়েস্ট জুড়ে ক্যাশ করা থাকছে।
    static $resultCache = [];
    static $customFlag = null;
    static $customFlagUserId = null;

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $cacheKey = $user_id . '|' . $_SESSION['role'] . '|' . $module_key;
    if (array_key_exists($cacheKey, $resultCache)) {
        return $resultCache[$cacheKey];
    }

    ensure_permission_schema($db);

    // BUG FIX: নিচের দুটো prepare()-এও আগে গার্ড ছিল না — এই ফাংশনটা প্রায়
    // প্রতিটা পেজে চলে (মেনু/এক্সেস চেক), তাই এখানে একটা prepare() ব্যর্থ
    // হলে সাইটজুড়ে পেজ ভেঙে পড়ার ঝুঁকি ছিল। এখন ব্যর্থ হলে নিরাপদে false
    // রিটার্ন করে (অ্যাক্সেস না দিয়ে), সাইট ভাঙবে না।
    if ($user_id > 0) {
        if ($customFlag === null || $customFlagUserId !== $user_id) {
            $flag = $db->query("SELECT use_custom_perms FROM users WHERE id = " . $user_id . " LIMIT 1");
            $row = $flag ? $flag->fetch_assoc() : null;
            $customFlag = $row ? (int)$row['use_custom_perms'] : 0;
            $customFlagUserId = $user_id;
        }
        if ($customFlag === 1) {
            $stmt = $db->prepare("SELECT id FROM user_permissions WHERE user_id = ? AND module_key = ?");
            if (!$stmt) {
                error_log('has_permission: prepare(user_permissions) failed — ' . $db->error);
                return $resultCache[$cacheKey] = false;
            }
            $stmt->bind_param('is', $user_id, $module_key);
            $stmt->execute();
            $has = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            return $resultCache[$cacheKey] = $has;
        }
    }

    $stmt = $db->prepare("SELECT id FROM role_permissions WHERE role_key = ? AND module_key = ?");
    if (!$stmt) {
        error_log('has_permission: prepare(role_permissions) failed — ' . $db->error);
        return $resultCache[$cacheKey] = false;
    }
    $stmt->bind_param("ss", $_SESSION['role'], $module_key);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $resultCache[$cacheKey] = $has;
}

function ame_module_for_request(): ?string
{
    $path = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $skip = ['login.php', 'process_login.php', 'logout.php', 'index.php', 'forgot_password.php', 'process_forgot_password.php', 'process_password_reset.php', 'reset_password.php', 'process_new_password.php'];
    if (in_array(basename($path), $skip, true)) {
        return null;
    }
    // বিক্রয় ইনভয়েস দেখা, প্রিন্ট ও শেয়ার যেকোনো লগইন করা ইউজার করতে পারবে
    if (in_array(basename($path), ['invoice.php', 'invoice_digital.php', 'invoice_a4.php', 'invoice_custom.php'], true)) {
        return null;
    }
    if (str_contains($path, 'switch_language.php')) {
        return null;
    }
    if (str_contains($path, 'restock_invoice.php')) {
        return null;
    }
    if (preg_match('#/modules/settings/(profile|change_password|process_password|edit_profile|process_profile|file_atlas)#', $path)) {
        return null;
    }
    if (str_contains($path, '/modules/hrm/work_ledger')) {
        return null;
    }
    if (str_contains($path, '/modules/hrm/pay_salary')
        || str_contains($path, '/modules/hrm/process_salary.php')
        || str_contains($path, '/modules/hrm/salary_receipt')) {
        return null;
    }
    if (str_contains($path, '/modules/hrm/process_staff_wake')) {
        return null;
    }
    if (str_contains($path, '/modules/sales/sell_without_stock')) {
        return null;
    }
    if (str_contains($path, '/modules/returns/v2/actions/hand_return.php')) {
        return null;
    }

    $rules = [
        '/modules/purchase/returns' => 'm-purchase-returns',
        '/modules/settings/scrolling_text' => 'm-ticker',
        '/modules/settings/countdown' => 'm-countdown',
        '/modules/reports/warranty' => 'm-warranty',
        '/modules/reports/cashier_performance' => 'm-hrm',
        '/modules/reports/engineer' => 'm-hrm',
        '/modules/reports/employee_report' => 'm-hrm',
        '/modules/reports/sales_report' => 'm-sales-report',
        '/modules/reports/invoice_edit' => 'm-sales-report',
        '/modules/reports/process_invoice_edit' => 'm-sales-report',
        '/modules/reports/sales_summary' => 'm-sales-report',
        '/modules/reports/sales_detailed' => 'm-sales-report',
        '/modules/reports/items_report' => 'm-sales-report',
        '/modules/reports/day_closing' => 'm-sales-report',
        '/modules/sales/' => 'm-sales',
        '/modules/returns/' => 'm-sales-returns',
        '/modules/purchase/' => 'm-purchase',
        '/modules/inventory/' => 'm-inv',
        '/modules/workshop/' => 'm-workshop',
        '/modules/pulse/' => 'm-pulse',
        '/modules/agents/' => 'm-agents',
        '/modules/smart/' => 'm-smart',
        '/modules/family/' => 'm-family',
        '/inventory/' => 'm-inv',
        '/modules/customers/hire/' => 'm-customers',
        '/modules/customers/' => 'm-customers',
        '/modules/shop_rent/' => 'm-shop-rent',
        '/modules/rental/' => 'm-rental',
        '/modules/suppliers/' => 'm-suppliers',
        '/modules/accounts/' => 'm-transactions',
        '/modules/partners/' => 'm-acc',
        '/modules/expenses/' => 'm-acc',
        '/modules/bloan/' => 'm-bloan',
        '/modules/borrow/' => 'm-oloans',
        '/modules/hrm/' => 'm-hrm',
        '/modules/loans/owners/' => 'm-oloans',
        '/modules/loans/' => 'm-loans',
        '/modules/reports/' => 'm-reports',
        '/modules/contacts/' => 'm-others',
        '/modules/personal/' => 'm-others',
        '/modules/utilities/' => 'm-others',
        '/modules/settings/' => 'm-settings',
    ];
    foreach ($rules as $needle => $mod) {
        if (str_contains($path, $needle)) {
            return $mod;
        }
    }
    return null;
}

function ame_enforce_page_permission(mysqli $db): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    $path = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_contains($path, '/modules/loans/') || str_contains($path, '/modules/borrow/')) {
        header('Location: /index.php');
        exit;
    }
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return;
    }
    $mod = ame_module_for_request();
    if ($mod === 'm-sales-returns' && !empty($_SESSION['user_id'])) {
        return;
    }
    if ($mod && !has_permission($db, $mod)) {
        if ($mod === 'm-hrm' && str_contains($path, '/modules/reports/employee_report') && has_permission($db, 'm-reports')) {
            return;
        }
        http_response_code(403);
        die('❌ এই অংশ ব্যবহারের অনুমতি নেই। অ্যাডমিন পারমিশন দিলে খুলবে।');
    }
}

function require_permission($db, string $module_key): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    if (!has_permission($db, $module_key)) {
        http_response_code(403);
        die('❌ এই মডিউল ব্যবহারের অনুমতি নেই।');
    }
}