<?php
/**
 * সরবরাহকারী (Supplier) ম্যানেজমেন্ট — শেয়ার্ড হেল্পার ফাংশন।
 * প্রোফাইলের ৭টা সেকশন (Basic/Business/Payment/Supply/Performance/Documents)
 * এবং ড্যাশবোর্ড/তালিকা — সবগুলো পেজ এই একই ফাংশন সেট ব্যবহার করে।
 */

/** ব্যবসার ধরন — ড্রপডাউনে দেখানোর জন্য */
function ame_supplier_business_types(): array
{
    return [
        'manufacturer' => 'উৎপাদনকারী (Manufacturer)',
        'wholesaler'   => 'পাইকারি বিক্রেতা (Wholesaler)',
        'distributor'  => 'ডিস্ট্রিবিউটর',
        'importer'     => 'আমদানিকারক',
        'retailer'     => 'খুচরা বিক্রেতা',
        'service'      => 'সার্ভিস প্রোভাইডার',
        'other'        => 'অন্যান্য',
    ];
}

/** পেমেন্ট শর্ত — ড্রপডাউন */
function ame_supplier_payment_terms(): array
{
    return [
        'cash'    => 'নগদ (তাৎক্ষণিক)',
        'net7'    => '৭ দিনের মধ্যে',
        'net15'   => '১৫ দিনের মধ্যে',
        'net30'   => '৩০ দিনের মধ্যে',
        'net60'   => '৬০ দিনের মধ্যে',
        'custom'  => 'কাস্টম / আলোচনা সাপেক্ষ',
    ];
}

/** পছন্দের পেমেন্ট মাধ্যম */
function ame_supplier_payment_methods(): array
{
    return [
        'cash'        => 'নগদ',
        'bank'        => 'ব্যাংক ট্রান্সফার',
        'mobile_bank' => 'মোবাইল ব্যাংকিং (বিকাশ/নগদ/রকেট)',
        'cheque'      => 'চেক',
    ];
}

function ame_supplier_business_type_label(?string $key): string
{
    $map = ame_supplier_business_types();
    return $map[$key] ?? ($key !== null && $key !== '' ? $key : '—');
}

function ame_supplier_payment_term_label(?string $key): string
{
    $map = ame_supplier_payment_terms();
    return $map[$key] ?? ($key !== null && $key !== '' ? $key : '—');
}

function ame_supplier_payment_method_label(?string $key): string
{
    $map = ame_supplier_payment_methods();
    return $map[$key] ?? ($key !== null && $key !== '' ? $key : '—');
}

function ame_supplier_schema_boot(mysqli $db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    $col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'purged_at'");
    if ($col && $col->num_rows === 0) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN purged_at DATETIME NULL DEFAULT NULL');
    }
    $col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'purged_by'");
    if ($col && $col->num_rows === 0) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN purged_by INT NULL DEFAULT NULL');
    }
}

function ame_supplier_live_sql(mysqli $db, string $alias = ''): string
{
    ame_supplier_schema_boot($db);
    $p = ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) ? ($alias . '.') : '';
    return $p . 'purged_at IS NULL';
}

function ame_supplier_is_purged(?array $row): bool
{
    if (!$row) {
        return false;
    }
    $at = trim((string)($row['purged_at'] ?? ''));
    return $at !== '' && $at !== '0000-00-00 00:00:00';
}

/** active/1/খালি = সক্রিয়। inactive/0 = নিষ্ক্রিয়। (int)'active' আর 0 হয় না। */
function ame_supplier_status_enabled($status): bool
{
    if (function_exists('ame_customer_status_enabled')) {
        return ame_customer_status_enabled($status);
    }
    $raw = strtolower(trim((string)$status));
    if ($raw === '') {
        return true;
    }
    return !in_array($raw, ['0', 'off', 'no', 'false', 'inactive', 'disabled', 'disable', 'closed', 'blocked'], true);
}

/** @return array{0:string,1:string} */
function ame_supplier_status_tokens(string $type): array
{
    $type = strtolower(trim($type));
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit|bool|boolean|decimal|numeric|float|double)/', $type)) {
        return ['1', '0'];
    }
    if (function_exists('ame_customer_status_tokens') && str_starts_with($type, 'enum(')) {
        return ame_customer_status_tokens($type);
    }
    return ['active', 'inactive'];
}

/** @return array{on:string,off:string,type:string} */
function ame_supplier_status_boot(mysqli $db): array
{
    static $meta = null;
    if (is_array($meta)) {
        return $meta;
    }
    $meta = ['on' => 'active', 'off' => 'inactive', 'type' => "enum('active','inactive')"];
    $res = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'status'");
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    if (!is_array($row)) {
        return $meta;
    }
    [$on, $off] = ame_supplier_status_tokens((string)($row['Type'] ?? ''));
    $meta = ['on' => $on, 'off' => $off, 'type' => strtolower((string)($row['Type'] ?? ''))];
    return $meta;
}

function ame_supplier_status_store(mysqli $db, bool $enabled): string
{
    $meta = ame_supplier_status_boot($db);
    return $enabled ? (string)$meta['on'] : (string)$meta['off'];
}

function ame_supplier_status_column(string $alias = ''): string
{
    $prefix = ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) ? ($alias . '.') : '';
    return $prefix . 'status';
}

function ame_supplier_status_active_sql(string $alias = ''): string
{
    $col = ame_supplier_status_column($alias);
    return '(' . $col . " IS NULL OR TRIM(CAST(" . $col . " AS CHAR)) = '' OR LOWER(TRIM(CAST(" . $col . " AS CHAR))) IN ('1','active','enabled','enable','yes','on','true','open'))";
}

function ame_supplier_status_inactive_sql(string $alias = ''): string
{
    $col = ame_supplier_status_column($alias);
    return "LOWER(TRIM(CAST(" . $col . " AS CHAR))) IN ('0','inactive','disabled','disable','no','off','false','closed','blocked')";
}

/** কাস্টমার তালিকায় থাকা মানুষ। is_customer=0 শুধু বিক্রয়ের সাপ্লায়ার-ব্রিজ, কাস্টমার নয়। */
function ame_customer_row_is_directory($row): bool
{
    if (!is_array($row) || !array_key_exists('is_customer', $row)) {
        return true;
    }
    $flag = $row['is_customer'];
    if ($flag === null || $flag === '') {
        return true;
    }
    $raw = strtolower(trim((string)$flag));
    return !in_array($raw, ['0', 'no', 'false', 'off'], true);
}

function ame_supplier_next_party_code(mysqli $db): string
{
    ame_supplier_schema_boot($db);
    $max = 0;
    $res = @$db->query("SELECT party_code FROM suppliers WHERE party_code IS NOT NULL AND party_code <> ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (preg_match('/(\d+)\s*$/', (string)$row['party_code'], $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
    }
    $id_row = @$db->query('SELECT COALESCE(MAX(id), 0) AS m FROM suppliers');
    $id_max = $id_row ? (int)($id_row->fetch_assoc()['m'] ?? 0) : 0;
    $n = max($max, $id_max) + 1;
    for ($i = 0; $i < 200; $i++) {
        $code = 'SUP-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        $chk = $db->prepare('SELECT id FROM suppliers WHERE party_code = ? LIMIT 1');
        if (!$chk) {
            return $code;
        }
        $chk->bind_param('s', $code);
        $chk->execute();
        $hit = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$hit) {
            return $code;
        }
        $n++;
    }
    return 'SUP-' . str_pad((string)(time() % 100000), 5, '0', STR_PAD_LEFT);
}

function ame_supplier_party_code_taken(mysqli $db, string $code, int $except_id = 0): bool
{
    $code = trim($code);
    if ($code === '') {
        return false;
    }
    $stmt = $db->prepare('SELECT id FROM suppliers WHERE party_code = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $code, $except_id);
    $stmt->execute();
    $hit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$hit;
}

function ame_supplier_assign_party_code(mysqli $db, int $id, string $wanted = ''): string
{
    if ($id <= 0) {
        return '';
    }
    $wanted = substr(trim($wanted), 0, 40);
    if ($wanted !== '' && ame_supplier_party_code_taken($db, $wanted, $id)) {
        throw new InvalidArgumentException('এই সাপ্লায়ার নাম্বার আগে ব্যবহার হয়েছে। নতুন নাম্বার দিন।');
    }
    if ($wanted === '') {
        $wanted = ame_supplier_next_party_code($db);
    }
    $stmt = $db->prepare('UPDATE suppliers SET party_code = ? WHERE id = ?');
    if (!$stmt) {
        return $wanted;
    }
    $stmt->bind_param('si', $wanted, $id);
    $stmt->execute();
    $stmt->close();
    return $wanted;
}

function ame_supplier_purge(mysqli $db, int $id): void
{
    ame_supplier_schema_boot($db);
    $row = ame_supplier_fetch($db, $id);
    if (!$row) {
        throw new InvalidArgumentException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
    }
    if (ame_supplier_is_purged($row)) {
        return;
    }
    $by = (int)($_SESSION['user_id'] ?? 0);
    $off = ame_supplier_status_store($db, false);
    $stmt = $db->prepare("UPDATE suppliers
        SET purged_at = NOW(), purged_by = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND purged_at IS NULL");
    if (!$stmt) {
        throw new RuntimeException('সাপ্লায়ার সরানো যায়নি। ক্রয়ের খাতা মুছা হয়নি।');
    }
    $stmt->bind_param('isi', $by, $off, $id);
    if (!$stmt->execute() || $stmt->affected_rows < 1) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException($err !== '' ? $err : 'সাপ্লায়ার সরানো যায়নি। ক্রয়ের খাতা মুছা হয়নি।');
    }
    $stmt->close();
    if (function_exists('log_activity')) {
        log_activity(
            'Supplier Purged',
            'Suppliers',
            'সাপ্লায়ার #' . $id . ' তালিকা থেকে চিরতরে সরানো। ক্রয়, পেমেন্ট ও লেজার আছে।'
        );
    }
}

/** একজন সাপ্লায়ারের সম্পূর্ণ রেকর্ড — না পেলে null */
function ame_supplier_fetch(mysqli $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    ame_supplier_schema_boot($db);
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * ক্রয় (purchases) টেবিল থেকে হিসাব করা পারফরম্যান্স/সরবরাহ পরিসংখ্যান —
 * কোনো নতুন কলামে সংরক্ষণ হয় না, প্রতিবার লাইভ হিসাব হয়।
 */
function ame_supplier_stats(mysqli $db, int $id): array
{
    $out = [
        'total_orders'      => 0,
        'total_purchase'    => 0.0,
        'total_paid'        => 0.0,
        'total_due_ledger'  => 0.0,
        'avg_order_value'   => 0.0,
        'last_order_date'   => null,
        'first_order_date'  => null,
        'orders_this_month' => 0,
    ];
    if ($id <= 0) {
        return $out;
    }
    $stmt = $db->prepare("SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(total_cost), 0) AS total_cost,
            COALESCE(SUM(paid_amount), 0) AS paid,
            COALESCE(SUM(due_amount), 0) AS due,
            MAX(created_at) AS last_dt,
            MIN(created_at) AS first_dt,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_month
        FROM purchases WHERE supplier_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $out['total_orders']      = (int)$row['cnt'];
        $out['total_purchase']    = (float)$row['total_cost'];
        $out['total_paid']        = (float)$row['paid'];
        $out['total_due_ledger']  = (float)$row['due'];
        $out['last_order_date']   = $row['last_dt'];
        $out['first_order_date']  = $row['first_dt'];
        $out['orders_this_month'] = (int)$row['this_month'];
        $out['avg_order_value']   = $out['total_orders'] > 0
            ? $out['total_purchase'] / $out['total_orders']
            : 0.0;
    }
    return $out;
}

/** রেটিং সংখ্যাকে ⭐ আইকনে দেখানোর হেল্পার (1-5) */
function ame_supplier_rating_stars(?int $rating): string
{
    $rating = max(0, min(5, (int)$rating));
    if ($rating <= 0) {
        return '<span style="color:var(--brand-muted);">রেট করা হয়নি</span>';
    }
    return str_repeat('⭐', $rating) . str_repeat('☆', 5 - $rating);
}

/**
 * নতুন সাপ্লায়ার তৈরি বা বিদ্যমান সাপ্লায়ার আপডেট — প্রোফাইলের সব
 * সেকশনের ইনপুট এক জায়গায় ভ্যালিডেট ও সেভ করে। সফল হলে supplier id
 * রিটার্ন করে, ব্যর্থ হলে exception ছোঁড়ে (বার্তা বাংলায়)।
 */
function ame_supplier_save(mysqli $db, array $p, int $id = 0): int
{
    $name = trim((string)($p['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('সাপ্লায়ারের নাম আবশ্যক।');
    }
    if (mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('নামটি অনেক বড় হয়ে গেছে।');
    }

    $fields = [
        'name'                      => $name,
        'phone'                     => trim((string)($p['phone'] ?? '')),
        'alt_phone'                 => trim((string)($p['alt_phone'] ?? '')),
        'email'                     => trim((string)($p['email'] ?? '')),
        'address'                   => trim((string)($p['address'] ?? '')),
        'business_name'             => trim((string)($p['business_name'] ?? '')),
        'business_type'             => trim((string)($p['business_type'] ?? '')),
        'tax_number'                => trim((string)($p['tax_number'] ?? '')),
        'trade_license_no'          => trim((string)($p['trade_license_no'] ?? '')),
        'website'                   => trim((string)($p['website'] ?? '')),
        'payment_terms'             => trim((string)($p['payment_terms'] ?? '')),
        'preferred_payment_method'  => trim((string)($p['preferred_payment_method'] ?? '')),
        'bank_name'                 => trim((string)($p['bank_name'] ?? '')),
        'bank_account_no'           => trim((string)($p['bank_account_no'] ?? '')),
        'bank_branch'               => trim((string)($p['bank_branch'] ?? '')),
        'supply_categories'         => trim((string)($p['supply_categories'] ?? '')),
        'minimum_order_note'        => trim((string)($p['minimum_order_note'] ?? '')),
        'supply_notes'              => trim((string)($p['supply_notes'] ?? '')),
        'performance_note'          => trim((string)($p['performance_note'] ?? '')),
    ];

    $opening_balance = array_key_exists('opening_balance', $p)
        ? (float)$p['opening_balance']
        : null;

    if ($id > 0 && $opening_balance === null) {
        $cur = $db->prepare('SELECT opening_balance FROM suppliers WHERE id = ? LIMIT 1');
        if ($cur) {
            $cur->bind_param('i', $id);
            $cur->execute();
            $crow = $cur->get_result()->fetch_assoc();
            $cur->close();
            $opening_balance = (float)($crow['opening_balance'] ?? 0);
        } else {
            $opening_balance = 0.0;
        }
    } elseif ($opening_balance === null) {
        $opening_balance = 0.0;
    }
    $lead_time = ($p['lead_time_days'] ?? '') !== '' ? (int)$p['lead_time_days'] : null;
    $rating = ($p['rating'] ?? '') !== '' ? max(1, min(5, (int)$p['rating'])) : null;

    ame_supplier_schema_boot($db);
    $fields['status'] = ame_supplier_status_store(
        $db,
        !array_key_exists('status', $p) || ame_supplier_status_enabled($p['status'])
    );
    if ($id > 0) {
        $existing = ame_supplier_fetch($db, $id);
        if (!$existing) {
            throw new InvalidArgumentException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
        }
        if (ame_supplier_is_purged($existing)) {
            throw new InvalidArgumentException('মুছে যাওয়া সাপ্লায়ার এডিট যায় না। নতুন নাম্বার দিয়ে আবার যোগ করুন।');
        }
    } else {
        $dup = $db->prepare('SELECT id FROM suppliers WHERE name = ? AND purged_at IS NULL LIMIT 1');
        if ($dup) {
            $dup->bind_param('s', $name);
            $dup->execute();
            $hit = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($hit) {
                throw new InvalidArgumentException('এই নামে একজন সাপ্লায়ার ইতিমধ্যে তালিকায় আছেন। মুছে গেলে নতুন নাম্বার দিয়ে যোগ করা যায়।');
            }
        }
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE suppliers SET
                name = ?, phone = ?, alt_phone = ?, email = ?, address = ?,
                business_name = ?, business_type = ?, tax_number = ?, trade_license_no = ?, website = ?,
                payment_terms = ?, preferred_payment_method = ?, bank_name = ?, bank_account_no = ?, bank_branch = ?,
                opening_balance = ?, supply_categories = ?, lead_time_days = ?, minimum_order_note = ?, supply_notes = ?,
                rating = ?, performance_note = ?, status = ?, updated_at = NOW()
            WHERE id = ?");
        $stmt->bind_param(
            'sssssssssssssssdsississi',
            $fields['name'], $fields['phone'], $fields['alt_phone'], $fields['email'], $fields['address'],
            $fields['business_name'], $fields['business_type'], $fields['tax_number'], $fields['trade_license_no'], $fields['website'],
            $fields['payment_terms'], $fields['preferred_payment_method'], $fields['bank_name'], $fields['bank_account_no'], $fields['bank_branch'],
            $opening_balance, $fields['supply_categories'], $lead_time, $fields['minimum_order_note'], $fields['supply_notes'],
            $rating, $fields['performance_note'], $fields['status'], $id
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('সাপ্লায়ার আপডেট করা যায়নি: ' . $stmt->error);
        }
        $stmt->close();
        return $id;
    }

    // নতুন সাপ্লায়ার যোগ করার সময় যদি "ওপেনিং ব্যালেন্স" (আগে থেকে পাওনা) দেওয়া
    // হয়, সেটাই তার শুরুর total_due (আসল বকেয়া ব্যালেন্স) হিসেবে বসবে —
    // যাতে সফটওয়্যার ব্যবহার শুরুর আগের পুরনো পাওনাও দিন থেকেই হিসাবে থাকে।
    $initial_due = round($opening_balance, 2);

    $stmt = $db->prepare("INSERT INTO suppliers
            (name, phone, alt_phone, email, address,
             business_name, business_type, tax_number, trade_license_no, website,
             payment_terms, preferred_payment_method, bank_name, bank_account_no, bank_branch,
             opening_balance, total_due, supply_categories, lead_time_days, minimum_order_note, supply_notes,
             rating, performance_note, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'sssssssssssssssddsississ',
        $fields['name'], $fields['phone'], $fields['alt_phone'], $fields['email'], $fields['address'],
        $fields['business_name'], $fields['business_type'], $fields['tax_number'], $fields['trade_license_no'], $fields['website'],
        $fields['payment_terms'], $fields['preferred_payment_method'], $fields['bank_name'], $fields['bank_account_no'], $fields['bank_branch'],
        $opening_balance, $initial_due, $fields['supply_categories'], $lead_time, $fields['minimum_order_note'], $fields['supply_notes'],
        $rating, $fields['performance_note'], $fields['status']
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('সাপ্লায়ার সেভ করা যায়নি: ' . $stmt->error);
    }
    $new_id = (int)$stmt->insert_id;
    $stmt->close();
    ame_supplier_assign_party_code($db, $new_id, (string)($p['party_code'] ?? ''));
    return $new_id;
}

/**
 * বিদ্যমান সাপ্লায়ারের "বর্তমান পাওনা" (total_due) সরাসরি সংশোধন —
 * প্রোফাইল এডিটে "সাবেক/বর্তমান পাওনা" বদলানোর জন্য। ক্রয়/পেমেন্টের
 * স্বাভাবিক হিসাবের বাইরে একটা ম্যানুয়াল সংশোধন, তাই FOR UPDATE লক করে
 * নিরাপদে বদলানো হয় এবং পুরনো→নতুন মান লগে (log_activity) রাখা হয় যাতে
 * ভবিষ্যতে কে/কখন/কেন বদলেছে তা যাচাই করা যায়।
 */
function ame_supplier_adjust_due(mysqli $db, int $supplier_id, float $new_due, string $reason): array
{
    if ($supplier_id <= 0) {
        throw new InvalidArgumentException('সাপ্লায়ার আইডি সঠিক নয়।');
    }
    $new_due = round($new_due, 2);

    $db->begin_transaction();
    $in_txn = true;
    try {
        $lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $supplier_id);
        $lock->execute();
        $row = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$row) {
            $db->rollback();
            $in_txn = false;
            throw new RuntimeException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
        }
        $old_due = round((float)$row['total_due'], 2);
        if (abs($new_due - $old_due) < 0.005) {
            $db->commit();
            $in_txn = false;
            return ['changed' => false, 'old' => $old_due, 'new' => $new_due];
        }
        $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        $upd->bind_param('di', $new_due, $supplier_id);
        if (!$upd->execute()) {
            $db->rollback();
            $in_txn = false;
            throw new RuntimeException('পাওনার পরিমাণ আপডেট করা যায়নি: ' . $upd->error);
        }
        $upd->close();
        $db->commit();
        $in_txn = false;

        if (function_exists('log_activity')) {
            $note = sprintf(
                'সাপ্লায়ার #%d — পাওনা %s ৳ থেকে %s ৳ করা হলো%s',
                $supplier_id,
                number_format($old_due, 2),
                number_format($new_due, 2),
                $reason !== '' ? ' (কারণ: ' . $reason . ')' : ''
            );
            log_activity('Supplier Due Adjusted', 'Suppliers', $note);
        }

        return ['changed' => true, 'old' => $old_due, 'new' => $new_due];
    } catch (Throwable $e) {
        if ($in_txn) {
            $db->rollback();
        }
        throw $e;
    }
}

/**
 * সাপ্লায়ারের পূর্বের/সাবেক ব্যালেন্স বসানো বা এডিট।
 * নতুন মানটাই পরের ক্রয় মেমোর সাবেক বকেয়া (total_due) হবে;
 * লেজারের প্রারম্ভিক ব্যালেন্সও একই পার্থক্যে সরে।
 *
 * @return array{changed: bool, old: float, new: float, opening: float}
 */
function ame_supplier_set_previous_balance(mysqli $db, int $supplier_id, float $new_previous, string $reason = ''): array
{
    if ($supplier_id <= 0) {
        throw new InvalidArgumentException('সাপ্লায়ার আইডি সঠিক নয়।');
    }
    $new_previous = round($new_previous, 2);

    $own_txn = true;
    if (method_exists($db, 'begin_transaction')) {
        $db->begin_transaction();
    } else {
        $own_txn = false;
    }
    try {
    $lock = $db->prepare('SELECT total_due, opening_balance FROM suppliers WHERE id = ? FOR UPDATE');
    if (!$lock) {
        throw new RuntimeException('সাপ্লায়ার লক করা যায়নি।');
    }
    $lock->bind_param('i', $supplier_id);
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$row) {
        throw new RuntimeException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
    }

    $old_due = round((float)($row['total_due'] ?? 0), 2);
    $old_open = round((float)($row['opening_balance'] ?? 0), 2);
    if (abs($new_previous - $old_due) < 0.005) {
        if ($own_txn) {
            $db->commit();
        }
        return ['changed' => false, 'old' => $old_due, 'new' => $new_previous, 'opening' => $old_open];
    }

    $delta = round($new_previous - $old_due, 2);
    $new_open = round($old_open + $delta, 2);

    $upd = $db->prepare('UPDATE suppliers SET total_due = ?, opening_balance = ? WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('পূর্বের ব্যালেন্স আপডেট করা যায়নি।');
    }
    $upd->bind_param('ddi', $new_previous, $new_open, $supplier_id);
    if (!$upd->execute()) {
        $err = $upd->error;
        $upd->close();
        throw new RuntimeException('পূর্বের ব্যালেন্স আপডেট করা যায়নি: ' . $err);
    }
    $upd->close();

    if ($own_txn) {
        $db->commit();
        $own_txn = false;
    }

    if (function_exists('ame_purchase_sync_linked_customer_due')) {
        ame_purchase_sync_linked_customer_due($db, $supplier_id, 0, $new_previous);
    }

    if (function_exists('log_activity')) {
        $note = sprintf(
            'সাপ্লায়ার #%d — পূর্বের ব্যালেন্স %s ৳ থেকে %s ৳%s',
            $supplier_id,
            number_format($old_due, 2),
            number_format($new_previous, 2),
            $reason !== '' ? ' (কারণ: ' . $reason . ')' : ''
        );
        log_activity('Supplier Previous Balance', 'Suppliers', $note);
    }

    return ['changed' => true, 'old' => $old_due, 'new' => $new_previous, 'opening' => $new_open];
    } catch (Throwable $e) {
        if ($own_txn) {
            $db->rollback();
        }
        throw $e;
    }
}

/** সাপ্লায়ারের আপলোড করা ডকুমেন্টের তালিকা */
function ame_supplier_documents(mysqli $db, int $supplier_id): array
{
    if ($supplier_id <= 0) {
        return [];
    }
    $stmt = $db->prepare('SELECT * FROM supplier_documents WHERE supplier_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ame_supplier_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/suppliers';
}

/** ডকুমেন্ট আপলোড রেকর্ড সংরক্ষণ */
function ame_supplier_document_add(mysqli $db, int $supplier_id, string $title, string $file_path, string $file_type, int $file_size, int $uploaded_by): int
{
    $stmt = $db->prepare('INSERT INTO supplier_documents (supplier_id, title, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssii', $supplier_id, $title, $file_path, $file_type, $file_size, $uploaded_by);
    if (!$stmt->execute()) {
        throw new RuntimeException('ডকুমেন্ট সংরক্ষণ করা যায়নি: ' . $stmt->error);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/** ডকুমেন্ট মুছে ফেলা (ডাটাবেজ + ফাইল) — শুধু নির্দিষ্ট সাপ্লায়ারের নিজের ডকুমেন্ট */
function ame_supplier_document_delete(mysqli $db, int $doc_id, int $supplier_id): bool
{
    $stmt = $db->prepare('SELECT file_path FROM supplier_documents WHERE id = ? AND supplier_id = ?');
    $stmt->bind_param('ii', $doc_id, $supplier_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    $del = $db->prepare('DELETE FROM supplier_documents WHERE id = ? AND supplier_id = ?');
    $del->bind_param('ii', $doc_id, $supplier_id);
    $ok = $del->execute();
    $del->close();
    if ($ok) {
        $full = dirname(__DIR__) . '/' . ltrim((string)$row['file_path'], '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }
    return (bool)$ok;
}
