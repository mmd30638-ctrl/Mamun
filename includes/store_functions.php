<?php
/**
 * একাধিক স্টোর / শাখা — সেশন, স্টক ও বিক্রয় স্কোপ।
 */

function ensure_store_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS stores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            address VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_store_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS store_stock (
            store_id INT NOT NULL,
            product_id INT NOT NULL,
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            PRIMARY KEY (store_id, product_id),
            INDEX (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS user_stores (
            user_id INT NOT NULL,
            store_id INT NOT NULL,
            PRIMARY KEY (user_id, store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ame_store_add_column($db, 'sales', 'store_id', 'INT NULL');
    ame_store_add_column($db, 'purchases', 'store_id', 'INT NULL');

    $count = 0;
    $res = @$db->query('SELECT COUNT(*) AS c FROM stores');
    if ($res) {
        $count = (int)($res->fetch_assoc()['c'] ?? 0);
    }

    if ($count <= 0) {
        $name = 'মূল স্টোর';
        if (function_exists('shop_name')) {
            $shop = trim((string)shop_name());
            if ($shop !== '') {
                $name = $shop;
            }
        }
        $stmt = $db->prepare(
            'INSERT INTO stores (code, name, is_active, is_default) VALUES (?, ?, 1, 1)'
        );
        $code = 'MAIN';
        $stmt->bind_param('ss', $code, $name);
        $stmt->execute();
        $stmt->close();
    }

    $default_id = ame_default_store_id($db);
    if ($default_id <= 0) {
        return;
    }

    // পারফরম্যান্স ফিক্স: নিচের ৩টা কাজ (নতুন প্রোডাক্টের store_stock সারি
    // তৈরি করা, sales/purchases-এর ফাঁকা store_id ভরাট করা) আগে প্রতিটা
    // পেজ-লোডে চলত — অর্থাৎ প্রোডাক্ট/বিক্রয়/ক্রয় টেবিল যত বড় হতো, প্রতিটা
    // পেজ লোড ততই ধীর হয়ে যেত (পুরো products টেবিলে INSERT চেষ্টা +
    // sales/purchases টেবিলে ফুল-স্ক্যান UPDATE, প্রতি ভিজিটে!)। এখন এই
    // ভারী কাজটা সর্বোচ্চ প্রতি ৫ মিনিটে একবার চলবে (system_flags টেবিলে
    // শেষ রান করা সময় সেভ থাকে) — নতুন বিক্রয়/ক্রয়/প্রোডাক্টের store_id
    // এখনো ঠিকভাবে ভরাট হবে, শুধু প্রতিটা পেজ-লোডে বারবার না।
    ame_store_backfill_if_due($db, $default_id);
}

function ame_store_backfill_if_due($db, int $default_id): void
{
    static $ranThisRequest = false;
    if ($ranThisRequest) {
        return;
    }
    $ranThisRequest = true;

    $throttleSeconds = 300; // ৫ মিনিট
    $flagName = 'store_backfill_last_run';
    $now = time();

    $hasFlags = false;
    $lastRun = 0;
    $flagsCheckOk = @$db->query("SHOW TABLES LIKE 'system_flags'");
    if ($flagsCheckOk && $flagsCheckOk->num_rows > 0) {
        $hasFlags = true;
        $safe = $db->real_escape_string($flagName);
        $r = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = '{$safe}' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc())) {
            $lastRun = (int)($row['flag_value'] ?? 0);
        }
    }

    if ($hasFlags && $lastRun > 0 && ($now - $lastRun) < $throttleSeconds) {
        return;
    }

    $products_exist = @$db->query("SHOW TABLES LIKE 'products'");
    if ($products_exist && $products_exist->num_rows > 0) {
        @$db->query(
            "INSERT IGNORE INTO store_stock (store_id, product_id, qty)
             SELECT {$default_id}, id, COALESCE(stock, 0)
             FROM products"
        );
    }

    @$db->query(
        "UPDATE sales SET store_id = {$default_id} WHERE store_id IS NULL"
    );

    $purchases_exist = @$db->query("SHOW TABLES LIKE 'purchases'");
    if ($purchases_exist && $purchases_exist->num_rows > 0) {
        @$db->query(
            "UPDATE purchases SET store_id = {$default_id} WHERE store_id IS NULL"
        );
    }

    if (!$hasFlags) {
        @$db->query("CREATE TABLE IF NOT EXISTS system_flags (
            flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
            flag_value VARCHAR(50) NOT NULL DEFAULT '1',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $stmt = $db->prepare(
        "INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value), updated_at = NOW()"
    );
    if ($stmt) {
        $nowStr = (string)$now;
        $stmt->bind_param('ss', $flagName, $nowStr);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_store_add_column($db, $table, $column, $definition)
{
    $safe_table = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
    $safe_column = preg_replace('/[^a-z0-9_]/i', '', (string)$column);
    if ($safe_table === '' || $safe_column === '') {
        return;
    }
    $exists = @$db->query("SHOW TABLES LIKE '{$safe_table}'");
    if (!$exists || $exists->num_rows === 0) {
        return;
    }
    $col = @$db->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    if ($col && $col->num_rows > 0) {
        return;
    }
    $db->query("ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$definition}");
}

function ame_default_store_id($db)
{
    $res = @$db->query('SELECT id FROM stores WHERE is_default = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1');
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    $res = @$db->query('SELECT id FROM stores WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return 0;
}

function ame_list_stores($db, $active_only = false)
{
    static $cache = [];
    $key = $active_only ? '1' : '0';
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $sql = 'SELECT * FROM stores';
    if ($active_only) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY is_default DESC, name ASC';
    $rows = [];
    $res = @$db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $cache[$key] = $rows;
}

function ame_user_store_ids($db, $user_id)
{
    $user_id = (int)$user_id;
    $ids = [];
    $stmt = $db->prepare('SELECT store_id FROM user_stores WHERE user_id = ?');
    if (!$stmt) {
        return $ids;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['store_id'];
    }
    $stmt->close();
    return $ids;
}

function ame_user_can_access_store($db, $store_id)
{
    $store_id = (int)$store_id;
    if ($store_id <= 0) {
        return false;
    }
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'admin') {
        return true;
    }
    $assigned = ame_user_store_ids($db, (int)($_SESSION['user_id'] ?? 0));
    if (count($assigned) === 0) {
        return true;
    }
    return in_array($store_id, $assigned, true);
}

function ame_stores_for_user($db)
{
    $all = ame_list_stores($db, true);
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'admin') {
        return $all;
    }
    $assigned = ame_user_store_ids($db, (int)($_SESSION['user_id'] ?? 0));
    if (count($assigned) === 0) {
        return $all;
    }
    return array_values(array_filter($all, static function ($row) use ($assigned) {
        return in_array((int)$row['id'], $assigned, true);
    }));
}

function ame_resolve_current_store($db)
{
    $allowed = ame_stores_for_user($db);
    $allowed_ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $allowed);

    $current = (int)($_SESSION['current_store_id'] ?? 0);
    if ($current > 0 && in_array($current, $allowed_ids, true)) {
        return $current;
    }

    $default = ame_default_store_id($db);
    if ($default > 0 && in_array($default, $allowed_ids, true)) {
        $_SESSION['current_store_id'] = $default;
        return $default;
    }

    if (isset($allowed_ids[0])) {
        $_SESSION['current_store_id'] = $allowed_ids[0];
        return $allowed_ids[0];
    }

    $_SESSION['current_store_id'] = 0;
    return 0;
}

function current_store_id()
{
    return (int)($_SESSION['current_store_id'] ?? 0);
}

function current_store($db = null)
{
    $id = current_store_id();
    if ($id <= 0) {
        return null;
    }
    if (!($db instanceof mysqli) && isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
        $db = $GLOBALS['db'];
    }
    if (!($db instanceof mysqli)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function current_store_name()
{
    $store = current_store();
    if ($store && trim((string)($store['name'] ?? '')) !== '') {
        return (string)$store['name'];
    }
    return function_exists('shop_name') ? shop_name() : 'স্টোর';
}

/**
 * পণ্য তালিকা ও বিক্রয় কাউন্টার একই সংখ্যা দেখায়:
 * এই স্টোরের হাতে থাকা মজুদ থেকে জব রিজার্ভ বাদ, শূন্যের নিচে নামে না।
 */
function ame_stock_sellable(float $on_hand, float $reserved = 0.0): float
{
    if ($reserved < 0) {
        $reserved = 0.0;
    }
    $sellable = round($on_hand - $reserved, 3);
    return $sellable > 0 ? $sellable : 0.0;
}

function ame_store_stock_qty($db, $store_id, $product_id)
{
    $store_id = (int)$store_id;
    $product_id = (int)$product_id;
    if ($store_id <= 0 || $product_id <= 0) {
        return 0.0;
    }
    $stmt = $db->prepare(
        'SELECT qty FROM store_stock WHERE store_id = ? AND product_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('ii', $store_id, $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (float)$row['qty'];
    }
    $legacy = $db->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1');
    if (!$legacy) {
        return 0.0;
    }
    $legacy->bind_param('i', $product_id);
    $legacy->execute();
    $prod = $legacy->get_result()->fetch_assoc();
    $legacy->close();
    return $prod ? (float)$prod['stock'] : 0.0;
}

function ame_store_sync_product_stock($db, $product_id)
{
    $product_id = (int)$product_id;
    if ($product_id <= 0) {
        return;
    }
    $sum = 0.0;
    $read = $db->prepare('SELECT COALESCE(SUM(qty), 0) AS total FROM store_stock WHERE product_id = ?');
    if ($read) {
        $read->bind_param('i', $product_id);
        $read->execute();
        $row = $read->get_result()->fetch_assoc();
        $read->close();
        $sum = (float)($row['total'] ?? 0);
    }
    $stmt = $db->prepare('UPDATE products SET stock = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('di', $sum, $product_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * স্টোর সারি না থাকলে ০ বসিয়ে products.stock মুছে ফেলে না।
 * অন্য কোনো স্টোরে সারি না থাকলে এবং এটাই ডিফল্ট/একমাত্র স্টোর হলে
 * আগের products.stock থেকে শুরু হয় — তালিকা ও বিক্রয় একই মজুদ পায়।
 */
function ame_store_stock_ensure_row($db, $store_id, $product_id): void
{
    $store_id = (int)$store_id;
    $product_id = (int)$product_id;
    if (!($db instanceof mysqli) || $store_id <= 0 || $product_id <= 0) {
        return;
    }

    $check = $db->prepare('SELECT qty FROM store_stock WHERE store_id = ? AND product_id = ? LIMIT 1');
    if (!$check) {
        return;
    }
    $check->bind_param('ii', $store_id, $product_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();
    if ($existing) {
        return;
    }

    $seed = 0.0;
    $others = $db->prepare('SELECT COUNT(*) AS c FROM store_stock WHERE product_id = ?');
    $other_rows = 0;
    if ($others) {
        $others->bind_param('i', $product_id);
        $others->execute();
        $count_row = $others->get_result()->fetch_assoc();
        $others->close();
        $other_rows = (int)($count_row['c'] ?? 0);
    }
    if ($other_rows === 0) {
        $store_count = 0;
        $store_res = @$db->query('SELECT COUNT(*) AS c FROM stores');
        if ($store_res && ($store_row = $store_res->fetch_assoc())) {
            $store_count = (int)($store_row['c'] ?? 0);
        }
        $default_id = function_exists('ame_default_store_id') ? (int)ame_default_store_id($db) : 0;
        if ($store_count <= 1 || $store_id === $default_id) {
            $legacy = $db->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1');
            if ($legacy) {
                $legacy->bind_param('i', $product_id);
                $legacy->execute();
                $prod = $legacy->get_result()->fetch_assoc();
                $legacy->close();
                if ($prod) {
                    $seed = (float)$prod['stock'];
                }
            }
        }
    }

    $insert = $db->prepare('INSERT IGNORE INTO store_stock (store_id, product_id, qty) VALUES (?, ?, ?)');
    if (!$insert) {
        return;
    }
    $insert->bind_param('iid', $store_id, $product_id, $seed);
    $insert->execute();
    $insert->close();
}

function ame_store_stock_change($db, $store_id, $product_id, $delta, $require_enough = true)
{
    $store_id = (int)$store_id;
    $product_id = (int)$product_id;
    $delta = (float)$delta;
    if ($store_id <= 0 || $product_id <= 0 || abs($delta) < 0.0001) {
        return $delta == 0.0;
    }

    ame_store_stock_ensure_row($db, $store_id, $product_id);
    if (!function_exists('ame_tx_open')) {
        require_once __DIR__ . '/stock_lock.php';
    }
    $ownTx = ame_tx_open($db);
    try {
        ame_lock_product_row($db, $product_id);

    if ($require_enough && $delta < 0) {
        $need = abs($delta);
        $upd = $db->prepare(
            'UPDATE store_stock
             SET qty = qty + ?
             WHERE store_id = ? AND product_id = ? AND qty >= ?'
        );
        $upd->bind_param('diid', $delta, $store_id, $product_id, $need);
    } else {
        $upd = $db->prepare(
            'UPDATE store_stock
             SET qty = qty + ?
             WHERE store_id = ? AND product_id = ?'
        );
        $upd->bind_param('dii', $delta, $store_id, $product_id);
    }

    $upd->execute();
    $ok = $upd->affected_rows === 1;
    $upd->close();
    if ($ok) {
        ame_store_sync_product_stock($db, $product_id);
    }
        if ($ownTx) {
            if ($ok) {
                $db->commit();
            } else {
                $db->rollback();
            }
        }
        return $ok;
    } catch (Throwable $error) {
        if ($ownTx) {
            $db->rollback();
        }
        throw $error;
    }
}

function ame_store_stock_set($db, $store_id, $product_id, $qty)
{
    $store_id = (int)$store_id;
    $product_id = (int)$product_id;
    $qty = (float)$qty;
    if ($store_id <= 0 || $product_id <= 0) {
        return false;
    }
    $stmt = $db->prepare(
        'INSERT INTO store_stock (store_id, product_id, qty)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE qty = VALUES(qty)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iid', $store_id, $product_id, $qty);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        ame_store_sync_product_stock($db, $product_id);
    }
    return $ok;
}

function ame_sales_set_store($db, $sale_id, $store_id = null)
{
    $sale_id = (int)$sale_id;
    $store_id = (int)($store_id ?? current_store_id());
    if ($sale_id <= 0 || $store_id <= 0) {
        return;
    }
    $stmt = $db->prepare('UPDATE sales SET store_id = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $store_id, $sale_id);
    $stmt->execute();
    $stmt->close();
}

function ame_public_login_url()
{
    $configured_url = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    $configured_host = strtolower((string)parse_url($configured_url, PHP_URL_HOST));
    $retired = function_exists('ame_retired_hosts')
        ? ame_retired_hosts()
        : ['aponshop.site', 'www.aponshop.site', 'aponshop.com', 'www.aponshop.com'];
    if ($configured_url !== '' && !in_array($configured_host, $retired, true)) {
        return $configured_url . '/login.php';
    }

    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = $forwarded === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (function_exists('ame_is_retired_host') && ame_is_retired_host($host)) {
        $host = function_exists('ame_live_host') ? ame_live_host() : 'mavo12.online';
        $https = true;
    }
    if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        $host = function_exists('ame_live_host') ? ame_live_host() : 'mavo12.online';
        $https = true;
    }
    return ($https ? 'https' : 'http') . '://' . $host . '/login.php';
}

function ame_store_assign_user($db, $user_id, $store_id)
{
    $user_id = (int)$user_id;
    $store_id = (int)$store_id;
    if ($user_id <= 0 || $store_id <= 0) {
        return false;
    }
    $stmt = $db->prepare(
        'INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $user_id, $store_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ame_store_users($db, $store_id)
{
    $store_id = (int)$store_id;
    $rows = [];
    if ($store_id <= 0) {
        return $rows;
    }
    $stmt = $db->prepare(
        'SELECT u.id, u.username, u.role, u.phone
         FROM user_stores us
         INNER JOIN users u ON u.id = us.user_id
         WHERE us.store_id = ?
         ORDER BY u.username ASC'
    );
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function ame_user_store_names($db, $user_id)
{
    $user_id = (int)$user_id;
    $names = [];
    $stmt = $db->prepare(
        'SELECT s.name
         FROM user_stores us
         INNER JOIN stores s ON s.id = us.store_id
         WHERE us.user_id = ?
         ORDER BY s.name ASC'
    );
    if (!$stmt) {
        return $names;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $names[] = (string)$row['name'];
    }
    $stmt->close();
    return $names;
}

function ame_store_create_login($db, $store_id, $username, $password, $phone = '')
{
    $store_id = (int)$store_id;
    $username = trim((string)$username);
    $password = (string)$password;
    $phone = trim((string)$phone);
    if ($store_id <= 0 || $username === '' || strlen($password) < 6) {
        return [false, 'ইউজারনেম দিন এবং পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে।'];
    }

    $check = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$check) {
        return [false, 'ইউজার তৈরি করা যায়নি।'];
    }
    $check->bind_param('s', $username);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        return [false, 'এই ইউজারনেম আগে থেকেই আছে। অন্য নাম দিন।'];
    }

    $role = 'cashier';
    $role_check = @$db->prepare('SELECT id FROM user_roles WHERE role_key = ?');
    if ($role_check) {
        $role_check->bind_param('s', $role);
        $role_check->execute();
        if (!$role_check->get_result()->fetch_assoc()) {
            $role = 'employee';
        }
        $role_check->close();
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        'INSERT INTO users (username, password, role, phone, status) VALUES (?, ?, ?, ?, 1)'
    );
    if (!$stmt) {
        $stmt = $db->prepare(
            'INSERT INTO users (username, password, role) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            return [false, 'ইউজার তৈরি করা যায়নি।'];
        }
        $stmt->bind_param('sss', $username, $hash, $role);
    } else {
        $stmt->bind_param('ssss', $username, $hash, $role, $phone);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [false, 'ইউজার তৈরি করা যায়নি।'];
    }
    $user_id = (int)$stmt->insert_id;
    $stmt->close();
    ame_store_assign_user($db, $user_id, $store_id);
    return [true, $username];
}
