<?php
function fam_ensure_column(mysqli $db, string $table, string $column, string $definition): void
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return;
    }
    try {
        $res = @$db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($column) . "'");
        if ($res && $res->num_rows === 0) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    } catch (Throwable $e) {
        error_log('fam_ensure_column ' . $table . '.' . $column . ': ' . $e->getMessage());
    }
}

function ensure_family_schema(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS family_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NULL,
        name VARCHAR(120) NOT NULL,
        mobile VARCHAR(30) DEFAULT NULL,
        alt_mobile VARCHAR(30) DEFAULT NULL,
        address TEXT,
        email VARCHAR(120) DEFAULT NULL,
        profession VARCHAR(120) DEFAULT NULL,
        organization VARCHAR(120) DEFAULT NULL,
        relation_note VARCHAR(80) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY group_id (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        relation_name VARCHAR(80) DEFAULT NULL,
        birth_date DATE NULL,
        mobile VARCHAR(30) DEFAULT NULL,
        address TEXT,
        notes TEXT,
        emergency_name VARCHAR(120) DEFAULT NULL,
        emergency_phone VARCHAR(30) DEFAULT NULL,
        important_info TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /*
     * হিসাব নিকাশের family_members (name/role/is_active) আগে তৈরি হলে
     * উপরের CREATE IF NOT EXISTS কিছু করে না — সদস্য সেভ তখন ফেল করে।
     * দুই মডিউল একই টেবিল ব্যবহার করে, তাই বাড়তি কলাম এখানে যোগ হয়।
     */
    fam_ensure_column($db, 'family_members', 'relation_name', 'VARCHAR(80) DEFAULT NULL');
    fam_ensure_column($db, 'family_members', 'birth_date', 'DATE NULL');
    fam_ensure_column($db, 'family_members', 'mobile', 'VARCHAR(30) DEFAULT NULL');
    fam_ensure_column($db, 'family_members', 'address', 'TEXT NULL');
    fam_ensure_column($db, 'family_members', 'notes', 'TEXT NULL');
    fam_ensure_column($db, 'family_members', 'emergency_name', 'VARCHAR(120) DEFAULT NULL');
    fam_ensure_column($db, 'family_members', 'emergency_phone', 'VARCHAR(30) DEFAULT NULL');
    fam_ensure_column($db, 'family_members', 'important_info', 'TEXT NULL');
    fam_ensure_column($db, 'family_members', 'role', "VARCHAR(30) NOT NULL DEFAULT 'other'");
    fam_ensure_column($db, 'family_members', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');

    $db->query("CREATE TABLE IF NOT EXISTS family_doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        name VARCHAR(120) NOT NULL,
        specialist VARCHAR(80) DEFAULT NULL,
        hospital VARCHAR(160) DEFAULT NULL,
        mobile VARCHAR(30) DEFAULT NULL,
        address TEXT,
        chamber_time VARCHAR(160) DEFAULT NULL,
        appointment_date DATE NULL,
        last_visit DATE NULL,
        visit_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        next_visit DATE NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY member_id (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    fam_ensure_column($db, 'family_doctors', 'visit_fee', 'DECIMAL(12,2) NOT NULL DEFAULT 0');

    $db->query("CREATE TABLE IF NOT EXISTS family_medicines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        doctor_id INT NULL,
        name VARCHAR(160) NOT NULL,
        generic_name VARCHAR(160) DEFAULT NULL,
        company VARCHAR(120) DEFAULT NULL,
        strength VARCHAR(40) DEFAULT NULL,
        bought_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock DECIMAL(10,2) NOT NULL DEFAULT 0,
        when_to_take VARCHAR(160) DEFAULT NULL,
        times_per_day INT NOT NULL DEFAULT 1,
        days_for INT NOT NULL DEFAULT 0,
        rx_date DATE NULL,
        refill_date DATE NULL,
        expiry_date DATE NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY member_id (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_med_buys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_id INT NULL,
        member_id INT NULL,
        buy_date DATE NULL,
        name VARCHAR(160) NOT NULL,
        qty DECIMAL(10,2) NOT NULL DEFAULT 0,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        pharmacy VARCHAR(160) DEFAULT NULL,
        invoice_file VARCHAR(255) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        title VARCHAR(200) NOT NULL,
        author VARCHAR(160) DEFAULT NULL,
        publisher VARCHAR(160) DEFAULT NULL,
        subject VARCHAR(80) DEFAULT NULL,
        language VARCHAR(40) DEFAULT NULL,
        copies INT NOT NULL DEFAULT 1,
        buy_date DATE NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        source VARCHAR(160) DEFAULT NULL,
        location_note VARCHAR(160) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_shopping (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        name VARCHAR(160) NOT NULL,
        category VARCHAR(80) DEFAULT NULL,
        qty VARCHAR(40) DEFAULT NULL,
        est_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        priority VARCHAR(20) DEFAULT 'মাঝারি',
        buyer VARCHAR(80) DEFAULT NULL,
        shop_name VARCHAR(160) DEFAULT NULL,
        is_bought TINYINT(1) NOT NULL DEFAULT 0,
        buy_date DATE NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_bazaar_demand (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ym CHAR(7) NOT NULL,
        memo_no VARCHAR(24) NOT NULL,
        title VARCHAR(160) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ym (ym),
        KEY memo_no (memo_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_bazaar_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        demand_id INT NOT NULL,
        category VARCHAR(80) NOT NULL DEFAULT 'অন্যান্য',
        name VARCHAR(160) NOT NULL,
        qty VARCHAR(40) DEFAULT NULL,
        unit VARCHAR(24) DEFAULT 'কেজি',
        est_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        paid_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        is_bought TINYINT(1) NOT NULL DEFAULT 0,
        buy_date DATE NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY demand_id (demand_id),
        KEY category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        category VARCHAR(80) DEFAULT NULL,
        qty VARCHAR(40) DEFAULT '1',
        buy_date DATE NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        condition_note VARCHAR(80) DEFAULT 'ভালো',
        location_note VARCHAR(160) DEFAULT NULL,
        warranty_until DATE NULL,
        receipt_file VARCHAR(255) DEFAULT NULL,
        maintain_date DATE NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        exp_date DATE NULL,
        category VARCHAR(80) DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        spent_by VARCHAR(80) DEFAULT NULL,
        purpose VARCHAR(200) DEFAULT NULL,
        pay_method VARCHAR(40) DEFAULT 'নগদ',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY exp_date (exp_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        kind VARCHAR(40) NOT NULL DEFAULT 'অন্যান্য',
        event_date DATE NOT NULL,
        member_id INT NULL,
        ref_kind VARCHAR(40) DEFAULT NULL,
        ref_id INT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY event_date (event_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        doctor_id INT NULL,
        title VARCHAR(200) NOT NULL,
        kind VARCHAR(80) DEFAULT 'অন্যান্য',
        file_name VARCHAR(255) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rxCol = @$db->query("SHOW COLUMNS FROM family_documents LIKE 'doctor_id'");
    if ($rxCol && $rxCol->num_rows === 0) {
        $db->query('ALTER TABLE family_documents ADD COLUMN doctor_id INT NULL AFTER member_id');
    }
    fam_ensure_column($db, 'family_documents', 'rx_date', 'DATE NULL');
    fam_ensure_column($db, 'family_documents', 'archived_at', 'DATETIME NULL');
    fam_ensure_column($db, 'family_medicines', 'document_id', 'INT NULL');
    $db->query("CREATE TABLE IF NOT EXISTS family_rx_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        medicine_id INT NULL,
        name VARCHAR(160) NOT NULL,
        strength VARCHAR(40) DEFAULT NULL,
        when_to_take VARCHAR(160) DEFAULT NULL,
        times_per_day INT NOT NULL DEFAULT 1,
        days_for INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY document_id (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @$db->query("UPDATE family_documents SET kind='বিল' WHERE kind='প্রেসক্রিপশন' AND title LIKE 'ওষুধ রসিদ:%'");

    $db->query("CREATE TABLE IF NOT EXISTS family_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        title VARCHAR(160) DEFAULT NULL,
        body TEXT,
        is_pinned TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role_key VARCHAR(20) NOT NULL DEFAULT 'member',
        can_health TINYINT(1) NOT NULL DEFAULT 1,
        can_money TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = $db->query("SELECT COUNT(*) c FROM family_groups");
    $n = $count ? (int)$count->fetch_assoc()['c'] : 0;
    if ($n === 0) {
        foreach (['পরিবার', 'আত্মীয়', 'বন্ধু', 'ডাক্তার', 'ব্যবসায়িক পরিচিত', 'প্রতিবেশী', 'জরুরি যোগাযোগ', 'অন্যান্য'] as $g) {
            $st = $db->prepare("INSERT INTO family_groups (name, is_system) VALUES (?, 1)");
            $st->bind_param('s', $g);
            $st->execute();
            $st->close();
        }
    }

    $dir = dirname(__DIR__) . '/uploads/family';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\nDeny from all\n</FilesMatch>\n");
    }
}

function ame_family_ensure(?mysqli $db = null): void
{
    $db = $db ?? ($GLOBALS['db'] ?? null);
    if (!$db instanceof mysqli) {
        return;
    }
    try {
        ensure_family_schema($db);
    } catch (Throwable $e) {
        error_log('Family schema: ' . $e->getMessage());
    }
}

function fam_specialists(): array
{
    return ['Medicine', 'Cardiology', 'Neurology', 'Orthopedics', 'Dermatology', 'Dental', 'Eye', 'ENT', 'Child Specialist', 'অন্যান্য'];
}

function fam_spec_label(string $key): string
{
    $bn = [
        'Medicine' => 'মেডিসিন',
        'Cardiology' => 'হৃদরোগ',
        'Neurology' => 'স্নায়ু',
        'Orthopedics' => 'অস্থি',
        'Dermatology' => 'চর্ম',
        'Dental' => 'দন্ত',
        'Eye' => 'চক্ষু',
        'ENT' => 'নাক-কান-গলা',
        'Child Specialist' => 'শিশু',
        'অন্যান্য' => 'অন্যান্য',
    ];
    $en = [
        'Medicine' => 'Medicine',
        'Cardiology' => 'Cardiology',
        'Neurology' => 'Neurology',
        'Orthopedics' => 'Orthopedics',
        'Dermatology' => 'Dermatology',
        'Dental' => 'Dental',
        'Eye' => 'Eye',
        'ENT' => 'ENT',
        'Child Specialist' => 'Child specialist',
        'অন্যান্য' => 'Other',
    ];
    $map = function_exists('is_en') && is_en() ? $en : $bn;
    return $map[$key] ?? $key;
}

function fam_book_cats(): array
{
    return ['কুরআন', 'হাদিস', 'ইসলামিক বই', 'ধর্মীয় বই', 'উপন্যাস', 'শিক্ষামূলক', 'শিশুদের বই', 'অন্যান্য'];
}

function fam_shop_cats(): array
{
    return ['চাল', 'ডাল', 'তেল', 'মসলা', 'কাপড়', 'পরিষ্কার', 'রান্নাঘর', 'ওষুধ', 'বই', 'ইলেকট্রনিক্স', 'অন্যান্য'];
}

function fam_bazaar_cats(): array
{
    return ['মাছ বাজার', 'আনাজ', 'মসলা', 'চাল-ডাল', 'তেল', 'মাংস', 'দুধ-ডিম', 'ফল', 'পরিষ্কার', 'অন্যান্য'];
}

function fam_bazaar_units(): array
{
    return ['কেজি', 'গ্রাম', 'লিটার', 'পিস', 'হালি', 'বস্তা', 'প্যাকেট', 'অন্যান্য'];
}

function fam_inv_cats(): array
{
    return ['ফার্নিচার', 'ইলেকট্রনিক্স', 'রান্নাঘর', 'বই', 'কিতাব', 'পোশাক', 'যন্ত্রপাতি', 'অন্যান্য'];
}

function fam_exp_cats(): array
{
    return ['বাজার', 'চিকিৎসা', 'ওষুধ', 'শিক্ষা', 'বই', 'যাতায়াত', 'বিদ্যুৎ', 'পানি', 'ইন্টারনেট', 'মোবাইল', 'বাসা', 'পোশাক', 'অন্যান্য'];
}

function fam_event_kinds(): array
{
    return [
        'birthday' => 'জন্মদিন',
        'anniversary' => 'বিবাহবার্ষিকী',
        'appointment' => 'ডাক্তার',
        'refill' => 'ওষুধ refill',
        'shopping' => 'বাজার',
        'bill' => 'বিল',
        'warranty' => 'ওয়ারেন্টি',
        'book' => 'বই',
        'other' => 'অন্যান্য',
    ];
}

function fam_relations(): array
{
    return ['বাবা', 'মা', 'স্বামী', 'স্ত্রী', 'ছেলে', 'মেয়ে', 'ভাই', 'বোন', 'দাদা', 'দাদি', 'নানা', 'নানি', 'অন্যান্য'];
}

function fam_priorities(): array
{
    return ['জরুরি', 'উচ্চ', 'মাঝারি', 'কম'];
}

function fam_pay_methods(): array
{
    return ['নগদ', 'বিকাশ', 'নগদ-মোবাইল', 'রকেট', 'কার্ড', 'ব্যাংক'];
}

function fam_conditions(): array
{
    return ['ভালো', 'মোটামুটি', 'মেরামত লাগবে', 'খারাপ'];
}

function fam_doc_kinds(): array
{
    return ['বিল', 'চিকিৎসা', 'প্রেসক্রিপশন', 'বইয়ের invoice', 'warranty', 'অন্যান্য'];
}
