<?php
/* -------------------------------------------------------------
 * UNIFIED CUSTOMER SCHEMA MANAGER — ভাগ ১/২
 * টেবিল তৈরি, কলাম প্যাচিং, মাইগ্রেশন
 * (বাকি ফাংশনগুলোর জন্য customer_ledger_functions.php দেখুন)
 * ------------------------------------------------------------- */

require_once __DIR__ . '/customer_ledger_functions.php';
if (is_file(__DIR__ . '/../../includes/party_purge.php')) {
    require_once __DIR__ . '/../../includes/party_purge.php';
}

function ame_customer_payment_reverse_schema_boot($db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    $pay_cols = [];
    $pay_res = @$db->query('SHOW COLUMNS FROM customer_payments');
    if ($pay_res) {
        while ($row = $pay_res->fetch_assoc()) {
            $pay_cols[] = $row['Field'];
        }
    }
    if ($pay_cols === []) {
        return;
    }
    if (!in_array('reversed_at', $pay_cols, true)) {
        @$db->query('ALTER TABLE customer_payments ADD COLUMN reversed_at DATETIME NULL DEFAULT NULL');
    }
    if (!in_array('reversed_by', $pay_cols, true)) {
        @$db->query('ALTER TABLE customer_payments ADD COLUMN reversed_by INT NULL DEFAULT NULL');
    }
    if (!in_array('reversal_reason', $pay_cols, true)) {
        @$db->query('ALTER TABLE customer_payments ADD COLUMN reversal_reason VARCHAR(255) NULL DEFAULT NULL');
    }
}

function ensure_customer_tables($db) {
    if ($db instanceof mysqli && function_exists('ame_customer_purge_schema_boot')) {
        ame_customer_purge_schema_boot($db);
    }
    ame_customer_payment_reverse_schema_boot($db);
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('customer_tables')) {
        return;
    }
    try {
        // ১. customers টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_code VARCHAR(30) NOT NULL,
            name VARCHAR(150) NOT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            alt_phone VARCHAR(20) DEFAULT NULL,
            nid VARCHAR(30) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            father_name VARCHAR(150) DEFAULT NULL,
            blood_group VARCHAR(10) DEFAULT NULL,
            is_customer TINYINT(1) NOT NULL DEFAULT 1,
            is_supplier TINYINT(1) NOT NULL DEFAULT 0,
            is_installment TINYINT(1) NOT NULL DEFAULT 0,
            is_rental TINYINT(1) NOT NULL DEFAULT 0,
            credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
            receivable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            payable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            rental_deposit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            ai_credit_score INT NOT NULL DEFAULT 70,
            ai_risk_level VARCHAR(20) NOT NULL DEFAULT 'Normal',
            notes TEXT DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ২. customer_ledger টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS customer_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            txn_date DATE NOT NULL,
            module_type VARCHAR(30) NOT NULL,
            ref_no VARCHAR(50) DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            debit DECIMAL(12,2) NOT NULL DEFAULT 0,
            credit DECIMAL(12,2) NOT NULL DEFAULT 0,
            running_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            balance_field VARCHAR(30) NOT NULL DEFAULT 'receivable_balance',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৩. installment_contracts টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS installment_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_no VARCHAR(30) NOT NULL,
            customer_id INT NOT NULL,
            product_details VARCHAR(255) NOT NULL,
            product_condition VARCHAR(30) NOT NULL DEFAULT 'NEW',
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            down_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_installments INT NOT NULL DEFAULT 1,
            installment_type VARCHAR(20) NOT NULL DEFAULT 'MONTHLY',
            remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৪. rental_contracts টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS rental_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rental_no VARCHAR(30) NOT NULL,
            customer_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            security_deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
            rent_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate_type VARCHAR(20) NOT NULL DEFAULT 'DAILY',
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            last_charged_date DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৫. customer_groups টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS customer_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৬. customer_areas টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS customer_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৭. customer_payments টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS customer_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_date DATE NOT NULL,
            method VARCHAR(30) NOT NULL DEFAULT 'CASH',
            reference_no VARCHAR(50) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pay_cols = [];
        $pay_res = @$db->query("SHOW COLUMNS FROM customer_payments");
        if ($pay_res) {
            while ($row = $pay_res->fetch_assoc()) {
                $pay_cols[] = $row['Field'];
            }
        }
        if (!in_array('account_id', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN account_id INT NULL DEFAULT NULL AFTER method");
        }
        if (!in_array('idempotency_token', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN idempotency_token VARCHAR(80) NULL DEFAULT NULL");
        }
        if (!in_array('discount', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount");
        }
        if (!in_array('reversed_at', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN reversed_at DATETIME NULL DEFAULT NULL");
        }
        if (!in_array('reversed_by', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN reversed_by INT NULL DEFAULT NULL");
        }
        if (!in_array('reversal_reason', $pay_cols, true)) {
            @$db->query("ALTER TABLE customer_payments ADD COLUMN reversal_reason VARCHAR(255) NULL DEFAULT NULL");
        }
        $pay_idx = @$db->query("SHOW INDEX FROM customer_payments WHERE Key_name = 'idx_pay_date'");
        if ($pay_idx && $pay_idx->num_rows === 0) {
            @$db->query("ALTER TABLE customer_payments ADD INDEX idx_pay_date (payment_date, id)");
        }

        $db->query("CREATE TABLE IF NOT EXISTS customer_withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            withdraw_date DATE NOT NULL,
            method VARCHAR(30) NOT NULL DEFAULT 'CASH',
            account_id INT NULL DEFAULT NULL,
            reference_no VARCHAR(50) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            idempotency_token VARCHAR(80) NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cw_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // উত্তোলন রিপোর্ট তারিখ-রেঞ্জ দিয়ে ফিল্টার করে (withdraw.php-এর বাইরের
        // পুরনো টেবিলেও এই ইনডেক্স না থাকতে পারে) — স্পিডের জন্য ইনডেক্স চেক/যোগ করা
        $cw_index_result = $db->query("SHOW INDEX FROM customer_withdrawals WHERE Key_name = 'idx_cw_date'");
        if ($cw_index_result && $cw_index_result->num_rows === 0) {
            @$db->query("ALTER TABLE customer_withdrawals ADD INDEX idx_cw_date (withdraw_date)");
        }

        // ৮. সিস্টেম ফ্ল্যাগ টেবিল
        $db->query("CREATE TABLE IF NOT EXISTS system_flags (
            flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
            flag_value VARCHAR(50) NOT NULL DEFAULT '1',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ৯. ডায়নামিক কলাম প্যাচার (customers)
        $required = [
            'customer_code' => "VARCHAR(30) NOT NULL",
            'photo' => "VARCHAR(255) DEFAULT NULL",
            'alt_phone' => "VARCHAR(20) DEFAULT NULL",
            'nid' => "VARCHAR(30) DEFAULT NULL",
            'father_name' => "VARCHAR(150) DEFAULT NULL",
            'blood_group' => "VARCHAR(10) DEFAULT NULL",
            'is_customer' => "TINYINT(1) NOT NULL DEFAULT 1",
            'is_supplier' => "TINYINT(1) NOT NULL DEFAULT 0",
            'is_installment' => "TINYINT(1) NOT NULL DEFAULT 0",
            'is_rental' => "TINYINT(1) NOT NULL DEFAULT 0",
            'credit_limit' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'receivable_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'payable_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'rental_deposit_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'ai_credit_score' => "INT NOT NULL DEFAULT 70",
            'ai_risk_level' => "VARCHAR(20) NOT NULL DEFAULT 'Normal'",
            'notes' => "TEXT DEFAULT NULL",
            'status' => "TINYINT(1) NOT NULL DEFAULT 1",
            'guarantor_name' => "VARCHAR(150) DEFAULT NULL",
            'guarantor_phone' => "VARCHAR(20) DEFAULT NULL",
            'group_id' => "INT DEFAULT NULL",
            'area_id' => "INT DEFAULT NULL",
            'additional_notes' => "TEXT DEFAULT NULL",
            'pdf_file' => "VARCHAR(255) DEFAULT NULL",
            'custom_requirement' => "TEXT DEFAULT NULL",
            'next_due_date' => "DATE DEFAULT NULL",
            'due_note' => "VARCHAR(255) DEFAULT NULL",
            'due_watch' => "TINYINT(1) NOT NULL DEFAULT 0",
            'due_watch_on' => "DATE DEFAULT NULL",
            'due_notify_suppressed' => "TINYINT(1) NOT NULL DEFAULT 0",
            'purged_at' => "DATETIME NULL DEFAULT NULL",
            'purged_by' => "INT NULL DEFAULT NULL",
            'agreement_signature' => "VARCHAR(255) DEFAULT NULL",
            'agreement_signed_at' => "DATETIME DEFAULT NULL",
        ];
        $existing = [];
        $res = $db->query("SHOW COLUMNS FROM customers");
        while ($row = $res->fetch_assoc()) { $existing[] = $row['Field']; }
        foreach ($required as $col => $def) {
            if (!in_array($col, $existing)) {
                $db->query("ALTER TABLE customers ADD COLUMN $col $def");
            }
        }

        // ৯.৫ rental_contracts টেবিলে last_charged_date কলাম প্যাচার
        $rental_cols = [];
        $rres = $db->query("SHOW COLUMNS FROM rental_contracts");
        while ($row = $rres->fetch_assoc()) { $rental_cols[] = $row['Field']; }
        if (!in_array('last_charged_date', $rental_cols)) {
            $db->query("ALTER TABLE rental_contracts ADD COLUMN last_charged_date DATE DEFAULT NULL");
        }

        // ১০. ইউনিক ইনডেক্স বসানোর আগে খালি/ডুপ্লিকেট মান ঠিক করা
        fix_blank_unique_fields($db, 'customer_code', 'CUS');

        // ১১. ইউনিক ইনডেক্স নিরাপদভাবে
        $idx = $db->query("SHOW INDEX FROM customers WHERE Key_name = 'idx_customer_code'");
        if ($idx->num_rows === 0) {
            $db->query("ALTER TABLE customers ADD UNIQUE INDEX idx_customer_code (customer_code)");
        }

        $phoneIdx = $db->query("SHOW INDEX FROM customers WHERE Key_name = 'idx_phone'");
        if ($phoneIdx->num_rows > 0) {
            $db->query("ALTER TABLE customers DROP INDEX idx_phone");
        }

        $phoneCol = $db->query("SHOW COLUMNS FROM customers WHERE Field = 'phone'");
        if ($phoneCol && $row = $phoneCol->fetch_assoc()) {
            if (strtoupper($row['Null']) === 'NO') {
                $db->query("ALTER TABLE customers MODIFY phone VARCHAR(20) DEFAULT NULL");
            }
        }

        // ১২. customer_groups ও customer_areas টেবিলের কলাম প্যাচার
        $group_cols = [];
        $gres = $db->query("SHOW COLUMNS FROM customer_groups");
        while ($row = $gres->fetch_assoc()) { $group_cols[] = $row['Field']; }
        if (!in_array('name', $group_cols)) {
            $db->query("ALTER TABLE customer_groups ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT ''");
        }

        $area_cols = [];
        $ares = $db->query("SHOW COLUMNS FROM customer_areas");
        while ($row = $ares->fetch_assoc()) { $area_cols[] = $row['Field']; }
        if (!in_array('name', $area_cols)) {
            $db->query("ALTER TABLE customer_areas ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT ''");
        }

        // ১৩. customer_ledger এ balance_field কলাম প্যাচার
        $ledger_cols = [];
        $lres = $db->query("SHOW COLUMNS FROM customer_ledger");
        while ($row = $lres->fetch_assoc()) { $ledger_cols[] = $row['Field']; }
        if (!in_array('balance_field', $ledger_cols)) {
            $db->query("ALTER TABLE customer_ledger ADD COLUMN balance_field VARCHAR(30) NOT NULL DEFAULT 'receivable_balance'");
        }

        // ১৪. একবারের জন্য: single-balance মডেলে মাইগ্রেশন
        migrate_to_single_balance($db);

        // ১৫. অনুপস্থিত লেজার স্টেটমেন্ট (মাইগ্রেটেড ক্রেতা) — ব্যালেন্স কাটে না
        if (function_exists('ame_customer_ledger_backfill_if_needed')) {
            ame_customer_ledger_backfill_if_needed($db);
        }
        if (function_exists('ame_customer_return_sync_if_needed')) {
            ame_customer_return_sync_if_needed($db);
        }

        if (is_file(__DIR__ . '/../../includes/hire_schema.php')) {
            require_once __DIR__ . '/../../includes/hire_schema.php';
            if (function_exists('ensure_hire_schema')) {
                ensure_hire_schema($db);
            }
        }
        if (function_exists('ame_schema_stamp')) {
            ame_schema_stamp('customer_tables', true);
        }

    } catch (mysqli_sql_exception $e) {
        die("❌ টেবিল স্ট্রাকচার তৈরিতে সমস্যা: " . htmlspecialchars($e->getMessage()));
    }
}

function migrate_to_single_balance($db) {
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        return;
    }
    $chk = $db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'single_balance_migrated'");
    if ($chk && $chk->num_rows > 0) { return; }

    $db->query("UPDATE customers SET receivable_balance = receivable_balance - payable_balance");
    $db->query("INSERT INTO system_flags (flag_name, flag_value) VALUES ('single_balance_migrated', '1')");
}

function fix_blank_unique_fields($db, $column, $prefix) {
    // নিরাপত্তা: $column কখনো ইউজার-ইনপুট থেকে আসা উচিত না — শুধু কোডে হার্ডকোড করা নাম
    // গ্রহণযোগ্য, তাই এখানে একটা whitelist-regex দিয়ে extra safeguard রাখা হলো
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
        throw new InvalidArgumentException('Invalid column name passed to fix_blank_unique_fields()');
    }
    $res = $db->query("SELECT id FROM customers WHERE $column IS NULL OR $column = ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $new_val = $prefix . '-AUTO-' . $row['id'];
            $upd = $db->prepare("UPDATE customers SET $column = ? WHERE id = ?");
            $upd->bind_param("si", $new_val, $row['id']);
            $upd->execute();
        }
    }
    $dupRes = $db->query("SELECT $column, COUNT(*) as cnt FROM customers GROUP BY $column HAVING cnt > 1");
    if ($dupRes) {
        while ($dupRow = $dupRes->fetch_assoc()) {
            $val = $dupRow[$column];
            $rows = $db->query("SELECT id FROM customers WHERE $column = '" . $db->real_escape_string($val) . "' ORDER BY id ASC");
            $first = true;
            while ($r = $rows->fetch_assoc()) {
                if ($first) { $first = false; continue; }
                $new_val = $prefix . '-FIX-' . $r['id'];
                $upd = $db->prepare("UPDATE customers SET $column = ? WHERE id = ?");
                $upd->bind_param("si", $new_val, $r['id']);
                $upd->execute();
            }
        }
    }
}

function ame_customer_photo_url($photo)
{
    $photo = trim((string)$photo);
    if ($photo === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $photo)) {
        return $photo;
    }
    $photo = str_replace('\\', '/', $photo);
    $photo = ltrim($photo, '/');
    $webroot = function_exists('ame_webroot_path') ? ame_webroot_path() : dirname(__DIR__, 2);
    $base = basename($photo);
    $candidates = [];
    if (strpos($photo, 'uploads/') === 0 || strpos($photo, 'assets/') === 0 || strpos($photo, 'img/') === 0) {
        $candidates[] = [$webroot . '/' . $photo, '/' . $photo];
    }
    foreach (ame_customer_photo_dirs() as $dir) {
        $candidates[] = [$dir . '/' . $base, ame_customer_photo_public_url($dir, $base)];
    }
    foreach ($candidates as [$fs, $url]) {
        if ($url !== '' && is_file($fs)) {
            return $url;
        }
    }
    return '';
}

function ame_customer_orient_image($img, $binary)
{
    if (!function_exists('exif_read_data') || !is_string($binary) || $binary === '') {
        return $img;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'amex');
    if ($tmp === false || @file_put_contents($tmp, $binary) === false) {
        return $img;
    }
    $exif = @exif_read_data($tmp);
    @unlink($tmp);
    $ori = (int)($exif['Orientation'] ?? 1);
    $deg = 0;
    if ($ori === 3) {
        $deg = 180;
    } elseif ($ori === 6) {
        $deg = -90;
    } elseif ($ori === 8) {
        $deg = 90;
    }
    if ($deg === 0) {
        return $img;
    }
    $rotated = @imagerotate($img, $deg, 0);
    if ($rotated) {
        imagedestroy($img);
        return $rotated;
    }
    return $img;
}

function ame_customer_save_converted_image($binary)
{
    if (!is_string($binary) || $binary === '') {
        return '';
    }
    $img = @imagecreatefromstring($binary);
    if (!$img) {
        return '';
    }
    $img = ame_customer_orient_image($img, $binary);
    $w = imagesx($img);
    $h = imagesy($img);
    $max = 900;
    if ($w > $max || $h > $max) {
        $scale = $max / max($w, $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }
    $upload_dir = dirname(__DIR__, 2) . '/uploads/customers/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        imagedestroy($img);
        return '';
    }
    // একই ছবি আবার এলে নতুন ফাইল নয় — হ্যাশ নামে আগেরটাই (স্টোরেজ বাঁচে)।
    ob_start();
    $shot = imagejpeg($img, null, 82);
    $jpeg_data = ob_get_clean();
    imagedestroy($img);
    if (!$shot || !is_string($jpeg_data) || $jpeg_data === '') {
        return '';
    }
    $filename = 'cus_' . substr(sha1($jpeg_data), 0, 12) . '.jpg';
    if (!is_file($upload_dir . $filename) && @file_put_contents($upload_dir . $filename, $jpeg_data, LOCK_EX) === false) {
        return '';
    }
    return 'uploads/customers/' . $filename;
}

function ame_customer_purchase_stats_map($db)
{
    $out = [];
    $tables = $db->query("SHOW TABLES LIKE 'sales'");
    if (!$tables || $tables->num_rows === 0) {
        return $out;
    }
    $sql = "SELECT s.customer_id,
                   COALESCE(SUM(si.qty), 0) AS qty,
                   COUNT(DISTINCT s.id) AS invoices,
                   COALESCE(SUM(s.net_payable), 0) AS amount
            FROM sales s
            LEFT JOIN sale_items si ON si.sale_id = s.id
            WHERE s.customer_id IS NOT NULL AND s.customer_id > 0
              AND s.status = 'completed'
            GROUP BY s.customer_id";
    $res = $db->query($sql);
    if (!$res) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $out[(int)$row['customer_id']] = [
            'qty' => (float)$row['qty'],
            'invoices' => (int)$row['invoices'],
            'amount' => (float)$row['amount'],
        ];
    }
    return $out;
}

/** স্টার কাস্টমার — মোট লেনদেন ১ লাখ টাকা বা তার বেশি (সব সময়ের বিক্রয়)। */
function ame_star_customer_id_set($db)
{
    $stats = ame_customer_purchase_stats_map($db);
    $stars = [];
    if (!$stats) {
        return $stars;
    }
    foreach ($stats as $id => $s) {
        if ((float)$s['amount'] >= 100000) {
            $stars[(int)$id] = true;
        }
    }
    return $stars;
}

function ame_customer_lookup_name($db, $table, $id)
{
    $id = (int)$id;
    $allowed = ['customer_groups' => true, 'customer_areas' => true];
    if ($id <= 0 || empty($allowed[$table])) {
        return '';
    }
    $chk = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    if (!$chk || $chk->num_rows === 0) {
        return '';
    }
    $stmt = $db->prepare("SELECT name FROM `{$table}` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['name'] ?? ''));
}

/** শুধু নিজের সিগনেচার ফোল্ডারের PNG। অন্য পাথ প্রোফাইলে দেখায় না। */
function ame_customer_signature_path($stored): string
{
    $stored = str_replace('\\', '/', trim((string)$stored));
    $stored = ltrim($stored, '/');
    if (!preg_match('#^uploads/customers/signatures/sig_[0-9]+_[0-9]+\.png$#', $stored)) {
        return '';
    }
    return $stored;
}

function ame_customer_signature_file($stored): string
{
    $rel = ame_customer_signature_path($stored);
    if ($rel === '') {
        return '';
    }
    $full = dirname(__DIR__, 2) . '/' . $rel;
    return is_file($full) ? $full : '';
}

function ame_customer_drop_signature_file($stored): void
{
    $full = ame_customer_signature_file($stored);
    if ($full !== '') {
        @unlink($full);
    }
}

/** ক্যানভাসের PNG ডেটা। অন্য ছবি বা খালি বোর্ড সেভ হয় না। */
function ame_customer_save_signature(string $data): string
{
    $data = trim($data);
    if ($data === '' || !preg_match('#^data:image/png;base64,#i', $data)) {
        return '';
    }
    $comma = strpos($data, ',');
    if ($comma === false) {
        return '';
    }
    $raw = substr($data, $comma + 1);
    $raw = str_replace(' ', '+', $raw);
    if ($raw === '' || strlen($raw) > 1200000) {
        return '';
    }
    $binary = base64_decode($raw, true);
    if ($binary === false || strlen($binary) < 32 || strlen($binary) > 900000) {
        return '';
    }
    if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return '';
    }
    $dir = dirname(__DIR__, 2) . '/uploads/customers/signatures';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return '';
    }
    $name = 'sig_' . time() . '_' . random_int(1000, 9999) . '.png';
    if (file_put_contents($dir . '/' . $name, $binary) === false) {
        return '';
    }
    return 'uploads/customers/signatures/' . $name;
}