<?php
/**
 * Rental agreement + digital signature.
 * Signed rows are never updated in place. A change is a new version.
 */

function ensure_hire_agreement_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $old = mysqli_report(MYSQLI_REPORT_OFF);
    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_agreements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agreement_no VARCHAR(40) NOT NULL,
            version_no INT NOT NULL DEFAULT 1,
            contract_id INT NOT NULL,
            parent_id INT NULL DEFAULT NULL,
            customer_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            customer_code VARCHAR(40) DEFAULT NULL,
            customer_name VARCHAR(180) NOT NULL DEFAULT '',
            father_or_spouse VARCHAR(180) DEFAULT NULL,
            nid VARCHAR(40) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            phone_alt VARCHAR(30) DEFAULT NULL,
            address TEXT,
            present_address TEXT,
            occupation VARCHAR(180) DEFAULT NULL,
            customer_extra TEXT,
            item_name VARCHAR(255) NOT NULL DEFAULT '',
            product_sku VARCHAR(80) DEFAULT NULL,
            serial_no VARCHAR(80) DEFAULT NULL,
            qty DECIMAL(12,3) NOT NULL DEFAULT 1,
            item_condition VARCHAR(180) DEFAULT NULL,
            security_deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
            rent_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate_type VARCHAR(20) NOT NULL DEFAULT 'DAILY',
            rent_pay_on VARCHAR(80) DEFAULT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            agreement_date DATE DEFAULT NULL,
            other_charges TEXT,
            guarantor_code VARCHAR(40) DEFAULT NULL,
            guarantor_name VARCHAR(180) DEFAULT NULL,
            guarantor_father VARCHAR(180) DEFAULT NULL,
            guarantor_nid VARCHAR(40) DEFAULT NULL,
            guarantor_phone VARCHAR(30) DEFAULT NULL,
            guarantor_phone_alt VARCHAR(30) DEFAULT NULL,
            guarantor_address TEXT,
            guarantor_occupation VARCHAR(180) DEFAULT NULL,
            guarantor_relation VARCHAR(80) DEFAULT NULL,
            guarantor_extra TEXT,
            special_terms TEXT,
            terms_snapshot MEDIUMTEXT,
            verify_ref VARCHAR(24) DEFAULT NULL,
            customer_signed_at DATETIME DEFAULT NULL,
            guarantor_signed_at DATETIME DEFAULT NULL,
            pdf_path VARCHAR(255) DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            updated_by INT NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE KEY uq_agr_ver (agreement_no, version_no),
            INDEX idx_agr_contract (contract_id),
            INDEX idx_agr_customer (customer_id),
            INDEX idx_agr_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_agreement_signs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agreement_id INT NOT NULL,
            version_no INT NOT NULL DEFAULT 1,
            role VARCHAR(20) NOT NULL,
            signer_name VARCHAR(180) DEFAULT NULL,
            signed_at DATETIME NOT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            sha256 CHAR(64) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_by INT NULL DEFAULT NULL,
            INDEX idx_ras_agr (agreement_id),
            INDEX idx_ras_role (agreement_id, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    mysqli_report($old);
}

function hire_agr_terms(): string
{
    return implode("\n\n", [
        'ভাড়া/রেন্টাল চুক্তিনামা',
        'আমি/আমরা উপরে বর্ণিত Customer, প্রতিষ্ঠানের নিকট হতে উল্লেখিত পণ্য/পণ্যসমূহ নির্ধারিত শর্তে ভাড়ায় গ্রহণ করলাম। পণ্য গ্রহণের সময় পণ্যের অবস্থা, পরিমাণ ও সংশ্লিষ্ট তথ্য যাচাই করে গ্রহণ করা হয়েছে।',
        'Customer নির্ধারিত সময় অনুযায়ী ভাড়া, Advance/Security Deposit এবং চুক্তিতে উল্লেখিত অন্যান্য প্রযোজ্য অর্থ পরিশোধ করবেন।',
        'Customer ভাড়াকৃত পণ্য যথাযথভাবে সংরক্ষণ ও ব্যবহার করবেন এবং প্রতিষ্ঠানের অনুমতি ছাড়া পণ্যের মালিকানা, ব্যবহার বা হস্তান্তরের ক্ষেত্রে কোনো পরিবর্তন করতে পারবেন না।',
        'পণ্য হারিয়ে গেলে, চুক্তির শর্ত ভঙ্গ হলে অথবা Customer-এর দায়িত্বে থাকা অবস্থায় পণ্যের অস্বাভাবিক ক্ষতি হলে, চুক্তির নির্ধারিত শর্ত অনুযায়ী Customer দায়বদ্ধ থাকবেন।',
        'Customer নির্ধারিত সময়ের মধ্যে ভাড়া পরিশোধ করবেন। বকেয়া সৃষ্টি হলে সফটওয়্যারে তা Customer-এর Ledger/Due হিসেবে সংরক্ষিত হবে এবং চুক্তির প্রযোজ্য শর্ত অনুযায়ী পরবর্তী ব্যবস্থা নেওয়া যাবে।',
        'চুক্তির মেয়াদ শেষ হলে অথবা চুক্তি বাতিল হলে Customer পণ্যটি প্রতিষ্ঠানের কাছে ফেরত দেবেন এবং ফেরতের সময় পণ্যের অবস্থা যাচাই করা হবে।',
        'Customer-এর পক্ষ থেকে প্রদত্ত Advance/Security Deposit প্রতিষ্ঠানের হিসাব অনুযায়ী সংরক্ষিত থাকবে এবং চুক্তির শর্ত অনুযায়ী সমন্বয়/ফেরতযোগ্য হলে তা হিসাবের মাধ্যমে নিষ্পত্তি করা হবে।',
        'জামিনদার এই চুক্তির শর্তসমূহ সম্পর্কে অবগত থেকে Customer-এর নির্ধারিত দায়িত্ব ও চুক্তির প্রযোজ্য দায়বদ্ধতার ক্ষেত্রে চুক্তিতে স্বীকৃত ভূমিকা পালন করবেন।',
        'Customer এবং Guarantor উভয়েই চুক্তির তথ্য পড়ে/জেনে সম্মতি প্রদান করবেন এবং প্রয়োজনীয় Digital Signature সম্পন্ন করবেন।',
        'চুক্তির কোনো পরিবর্তন প্রয়োজন হলে পূর্ববর্তী Agreement পরিবর্তন না করে নতুন Version/Updated Agreement তৈরি করতে হবে এবং প্রতিটি Version-এর আলাদা রেকর্ড থাকবে।',
        'এই চুক্তির সঙ্গে Customer ও Guarantor-এর প্রয়োজনীয় পরিচয়, সম্মতি, Digital Signature, তারিখ, সময় এবং Agreement ID-এর রেকর্ড সংরক্ষণ করা হয়।',
    ]);
}

function hire_agr_root(): string
{
    $base = defined('AME_PRIVATE_DATA_DIR')
        ? AME_PRIVATE_DATA_DIR
        : dirname(__DIR__, 2) . '/private_data';
    $dir = $base . '/rental_agreements';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = dirname(__DIR__) . '/uploads/agreements';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    return $dir;
}

function hire_agr_locked(array $row): bool
{
    return strtoupper((string)($row['status'] ?? '')) === 'SIGNED';
}

function hire_agr_rate_label(string $type): string
{
    return match (strtoupper($type)) {
        'WEEKLY' => 'সাপ্তাহিক',
        'MONTHLY' => 'মাসিক',
        default => 'দৈনিক',
    };
}

function hire_agr_get(mysqli $db, int $id): ?array
{
    $st = $db->prepare('SELECT * FROM rental_agreements WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function hire_agr_latest_for_contract(mysqli $db, int $contractId): ?array
{
    $st = $db->prepare('SELECT * FROM rental_agreements WHERE contract_id = ? ORDER BY version_no DESC, id DESC LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $contractId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function hire_agr_versions(mysqli $db, string $agreementNo): array
{
    $st = $db->prepare('SELECT id, agreement_no, version_no, status, created_at, customer_signed_at, guarantor_signed_at, verify_ref FROM rental_agreements WHERE agreement_no = ? ORDER BY version_no DESC');
    if (!$st) {
        return [];
    }
    $st->bind_param('s', $agreementNo);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows ?: [];
}

function hire_agr_search(mysqli $db, string $q, int $customerId = 0): array
{
    $sql = 'SELECT a.* FROM rental_agreements a WHERE 1=1';
    $types = '';
    $vals = [];
    if ($customerId > 0) {
        $sql .= ' AND a.customer_id = ?';
        $types .= 'i';
        $vals[] = $customerId;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (a.agreement_no LIKE ? OR a.customer_name LIKE ? OR a.phone LIKE ? OR a.nid LIKE ? OR a.guarantor_name LIKE ? OR a.guarantor_phone LIKE ? OR a.item_name LIKE ? OR a.serial_no LIKE ? OR a.verify_ref LIKE ?)';
        $types .= str_repeat('s', 9);
        for ($i = 0; $i < 9; $i++) {
            $vals[] = $like;
        }
    }
    $sql .= ' ORDER BY a.id DESC LIMIT 200';
    $st = $db->prepare($sql);
    if (!$st) {
        return [];
    }
    if ($types !== '') {
        $st->bind_param($types, ...$vals);
    }
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows ?: [];
}

function hire_agr_blank_from_contract(mysqli $db, array $contract): array
{
    $cid = (int)$contract['customer_id'];
    $cust = null;
    $st = $db->prepare('SELECT id, customer_code, name, phone, alt_phone, nid, address, father_name, guarantor_name, guarantor_phone FROM customers WHERE id = ?');
    if (!$st) {
        $st = $db->prepare('SELECT id, customer_code, name, phone, alt_phone, nid, address, father_name FROM customers WHERE id = ?');
    }
    if ($st) {
        $st->bind_param('i', $cid);
        $st->execute();
        $cust = $st->get_result()->fetch_assoc() ?: [];
        $st->close();
    }
    $cust = $cust ?: [];
    return [
        'id' => 0,
        'agreement_no' => '',
        'version_no' => 1,
        'contract_id' => (int)$contract['id'],
        'customer_id' => $cid,
        'status' => 'DRAFT',
        'customer_code' => (string)($cust['customer_code'] ?? $contract['customer_code'] ?? ''),
        'customer_name' => (string)($cust['name'] ?? $contract['customer_name'] ?? ''),
        'father_or_spouse' => (string)($cust['father_name'] ?? ''),
        'nid' => (string)($cust['nid'] ?? ''),
        'phone' => (string)($cust['phone'] ?? $contract['customer_phone'] ?? ''),
        'phone_alt' => (string)($cust['alt_phone'] ?? ''),
        'address' => (string)($cust['address'] ?? ''),
        'present_address' => '',
        'occupation' => '',
        'customer_extra' => '',
        'item_name' => (string)($contract['item_name'] ?? ''),
        'product_sku' => (string)($contract['product_sku'] ?? ''),
        'serial_no' => (string)($contract['serial_no'] ?? ''),
        'qty' => (string)($contract['qty'] ?? '1'),
        'item_condition' => 'গ্রহণের সময় যাচাই করা হয়েছে',
        'security_deposit' => (string)($contract['security_deposit'] ?? '0'),
        'rent_rate' => (string)($contract['rent_rate'] ?? '0'),
        'rate_type' => (string)($contract['rate_type'] ?? 'DAILY'),
        'rent_pay_on' => '',
        'start_date' => (string)($contract['start_date'] ?? date('Y-m-d')),
        'end_date' => (string)($contract['expected_return'] ?? ''),
        'agreement_date' => date('Y-m-d'),
        'other_charges' => '',
        'guarantor_code' => '',
        'guarantor_name' => (string)($cust['guarantor_name'] ?? ''),
        'guarantor_father' => '',
        'guarantor_nid' => '',
        'guarantor_phone' => (string)($cust['guarantor_phone'] ?? ''),
        'guarantor_phone_alt' => '',
        'guarantor_address' => '',
        'guarantor_occupation' => '',
        'guarantor_relation' => '',
        'guarantor_extra' => '',
        'special_terms' => '',
        'verify_ref' => '',
        'customer_signed_at' => null,
        'guarantor_signed_at' => null,
        'pdf_path' => null,
        'created_at' => null,
    ];
}

function hire_agr_text_keys(): array
{
    return [
        'customer_name', 'father_or_spouse', 'nid', 'phone', 'phone_alt', 'address', 'present_address',
        'occupation', 'customer_extra', 'item_name', 'product_sku', 'serial_no', 'item_condition',
        'rate_type', 'rent_pay_on', 'start_date', 'end_date', 'agreement_date', 'other_charges',
        'guarantor_name', 'guarantor_father', 'guarantor_nid', 'guarantor_phone', 'guarantor_phone_alt',
        'guarantor_address', 'guarantor_occupation', 'guarantor_relation', 'guarantor_extra', 'special_terms',
    ];
}

function hire_agr_collect(array $src): array
{
    $out = [];
    foreach (hire_agr_text_keys() as $key) {
        $out[$key] = trim((string)($src[$key] ?? ''));
    }
    $rate = strtoupper($out['rate_type']);
    $out['rate_type'] = in_array($rate, ['DAILY', 'WEEKLY', 'MONTHLY'], true) ? $rate : 'DAILY';
    $out['qty'] = max(0.001, (float)($src['qty'] ?? 1));
    $out['security_deposit'] = max(0, (float)($src['security_deposit'] ?? 0));
    $out['rent_rate'] = max(0, (float)($src['rent_rate'] ?? 0));
    foreach (['start_date', 'end_date', 'agreement_date'] as $d) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $out[$d])) {
            $out[$d] = '';
        }
    }
    return $out;
}

function hire_agr_next_no(mysqli $db): string
{
    $prefix = 'AGR-' . date('ymd') . '-';
    $like = $prefix . '%';
    $st = $db->prepare('SELECT agreement_no FROM rental_agreements WHERE agreement_no LIKE ? ORDER BY id DESC LIMIT 1');
    $n = 1;
    if ($st) {
        $st->bind_param('s', $like);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && preg_match('/-(\d+)$/', (string)$row['agreement_no'], $m)) {
            $n = (int)$m[1] + 1;
        }
    }
    return $prefix . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

function hire_agr_exec(mysqli $db, string $sql, array $pairs): mysqli_stmt
{
    $st = $db->prepare($sql);
    if (!$st) {
        throw new RuntimeException('সংরক্ষণ হয়নি।');
    }
    $types = '';
    $vals = [];
    foreach ($pairs as $pair) {
        $types .= $pair[0];
        $vals[] = $pair[1];
    }
    $st->bind_param($types, ...$vals);
    if (!$st->execute()) {
        $err = $st->error;
        $st->close();
        throw new RuntimeException('সংরক্ষণ হয়নি।' . ($err !== '' ? ' ' . $err : ''));
    }
    return $st;
}

function hire_agr_save(mysqli $db, array $contract, array $src, int $existingId = 0): int
{
    $data = hire_agr_collect($src);
    if ($data['customer_name'] === '' || $data['item_name'] === '') {
        throw new RuntimeException('কাস্টমারের নাম ও পণ্যের নাম লাগবে।');
    }
    $uid = function_exists('hire_uid') ? hire_uid() : (int)($_SESSION['user_id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $qty = $data['qty'];
    $dep = $data['security_deposit'];
    $rate = $data['rent_rate'];
    $start = $data['start_date'] !== '' ? $data['start_date'] : null;
    $end = $data['end_date'] !== '' ? $data['end_date'] : null;
    $adate = $data['agreement_date'] !== '' ? $data['agreement_date'] : date('Y-m-d');
    $common = [
        ['s', $data['customer_name']], ['s', $data['father_or_spouse']], ['s', $data['nid']], ['s', $data['phone']], ['s', $data['phone_alt']],
        ['s', $data['address']], ['s', $data['present_address']], ['s', $data['occupation']], ['s', $data['customer_extra']],
        ['s', $data['item_name']], ['s', $data['product_sku']], ['s', $data['serial_no']], ['d', $qty], ['s', $data['item_condition']],
        ['d', $dep], ['d', $rate], ['s', $data['rate_type']], ['s', $data['rent_pay_on']], ['s', $start], ['s', $end], ['s', $adate], ['s', $data['other_charges']],
        ['s', $data['guarantor_name']], ['s', $data['guarantor_father']], ['s', $data['guarantor_nid']], ['s', $data['guarantor_phone']],
        ['s', $data['guarantor_phone_alt']], ['s', $data['guarantor_address']], ['s', $data['guarantor_occupation']],
        ['s', $data['guarantor_relation']], ['s', $data['guarantor_extra']], ['s', $data['special_terms']],
    ];
    if ($existingId > 0) {
        $row = hire_agr_get($db, $existingId);
        if (!$row || (int)$row['contract_id'] !== (int)$contract['id']) {
            throw new RuntimeException('চুক্তিনামা পাওয়া যায়নি।');
        }
        if (hire_agr_locked($row)) {
            throw new RuntimeException('স্বাক্ষরিত চুক্তিনামা বদলানো যায় না। নতুন ভার্সন তৈরি করুন।');
        }
        $pairs = array_merge($common, [['i', $uid], ['s', $now], ['i', $existingId]]);
        $st = hire_agr_exec($db, 'UPDATE rental_agreements SET customer_name=?, father_or_spouse=?, nid=?, phone=?, phone_alt=?, address=?, present_address=?, occupation=?, customer_extra=?, item_name=?, product_sku=?, serial_no=?, qty=?, item_condition=?, security_deposit=?, rent_rate=?, rate_type=?, rent_pay_on=?, start_date=?, end_date=?, agreement_date=?, other_charges=?, guarantor_name=?, guarantor_father=?, guarantor_nid=?, guarantor_phone=?, guarantor_phone_alt=?, guarantor_address=?, guarantor_occupation=?, guarantor_relation=?, guarantor_extra=?, special_terms=?, updated_by=?, updated_at=? WHERE id=? AND status<>"SIGNED"', $pairs);
        $st->close();
        hire_agr_ensure_guarantor_code($db, $existingId);
        return $existingId;
    }
    $no = hire_agr_next_no($db);
    $gcode = 'G-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    $pairs = array_merge(
        [['s', $no], ['i', (int)$contract['id']], ['i', (int)$contract['customer_id']], ['s', (string)($contract['customer_code'] ?? '')]],
        array_slice($common, 0, 22),
        [['s', $gcode]],
        array_slice($common, 22),
        [['i', $uid], ['i', $uid], ['s', $now]]
    );
    $sql = 'INSERT INTO rental_agreements (agreement_no, version_no, contract_id, customer_id, status, customer_code, customer_name, father_or_spouse, nid, phone, phone_alt, address, present_address, occupation, customer_extra, item_name, product_sku, serial_no, qty, item_condition, security_deposit, rent_rate, rate_type, rent_pay_on, start_date, end_date, agreement_date, other_charges, guarantor_code, guarantor_name, guarantor_father, guarantor_nid, guarantor_phone, guarantor_phone_alt, guarantor_address, guarantor_occupation, guarantor_relation, guarantor_extra, special_terms, created_by, updated_by, updated_at) VALUES (?,1,?,?,"DRAFT",' . implode(',', array_fill(0, 37, '?')) . ')';
    $marks = substr_count($sql, '?');
    if ($marks !== count($pairs)) {
        throw new RuntimeException('ঘর ' . count($pairs) . ' / ' . $marks);
    }
    $st = hire_agr_exec($db, $sql, $pairs);
    $id = (int)$st->insert_id;
    $st->close();
    if ($id <= 0) {
        throw new RuntimeException('চুক্তিনামা তৈরি হয়নি।');
    }
    return $id;
}

function hire_agr_ensure_guarantor_code(mysqli $db, int $id): void
{
    $row = hire_agr_get($db, $id);
    if (!$row || trim((string)$row['guarantor_code']) !== '') {
        return;
    }
    $code = 'G-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    $st = $db->prepare('UPDATE rental_agreements SET guarantor_code=? WHERE id=? AND (guarantor_code IS NULL OR guarantor_code="")');
    if ($st) {
        $st->bind_param('si', $code, $id);
        $st->execute();
        $st->close();
    }
}

function hire_agr_sign_file(int $id, string $role): string
{
    $safe = $role === 'guarantor' ? 'guarantor' : 'customer';
    $dir = hire_agr_root() . '/' . $id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/' . $safe . '.png';
}

function hire_agr_store_sign(mysqli $db, array $row, string $role, string $dataUrl): void
{
    if (hire_agr_locked($row)) {
        throw new RuntimeException('স্বাক্ষরিত চুক্তিতে নতুন স্বাক্ষর বসানো যায় না।');
    }
    if (!in_array($role, ['customer', 'guarantor'], true)) {
        throw new RuntimeException('স্বাক্ষরের ধরন ঠিক নেই।');
    }
    if ($role === 'guarantor' && trim((string)($row['guarantor_name'] ?? '')) === '') {
        throw new RuntimeException('জামিনদারের নাম দিয়ে আগে সংরক্ষণ করুন।');
    }
    if (!preg_match('#^data:image/png;base64,#', $dataUrl)) {
        throw new RuntimeException('স্বাক্ষর PNG হতে হবে।');
    }
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bin === false || strlen($bin) < 80 || strlen($bin) > 1500000) {
        throw new RuntimeException('স্বাক্ষর খালি বা বড় হয়ে গেছে।');
    }
    if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new RuntimeException('স্বাক্ষর ফাইল PNG নয়।');
    }
    $hash = hash('sha256', $bin);
    $latest = hire_agr_sign_file((int)$row['id'], $role);
    $dir = dirname($latest);
    $unique = $dir . '/' . ($role === 'guarantor' ? 'guarantor' : 'customer') . '-' . date('YmdHis') . '-' . substr($hash, 0, 8) . '.png';
    if (file_put_contents($unique, $bin) === false || file_put_contents($latest, $bin) === false) {
        throw new RuntimeException('স্বাক্ষর সেভ হয়নি।');
    }
    $path = $unique;
    $when = date('Y-m-d H:i:s');
    $name = $role === 'guarantor' ? (string)$row['guarantor_name'] : (string)$row['customer_name'];
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $uid = function_exists('hire_uid') ? hire_uid() : (int)($_SESSION['user_id'] ?? 0);
    $ver = (int)$row['version_no'];
    $aid = (int)$row['id'];
    $st = $db->prepare('INSERT INTO rental_agreement_signs (agreement_id, version_no, role, signer_name, signed_at, ip, user_agent, sha256, file_path, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
    if ($st) {
        $st->bind_param('iisssssssi', $aid, $ver, $role, $name, $when, $ip, $ua, $hash, $path, $uid);
        $st->execute();
        $st->close();
    }
    $col = $role === 'guarantor' ? 'guarantor_signed_at' : 'customer_signed_at';
    $db->query('UPDATE rental_agreements SET `' . $col . '`="' . $db->real_escape_string($when) . '", updated_by=' . (int)$uid . ', updated_at="' . $db->real_escape_string($when) . '" WHERE id=' . $aid . ' AND status<>"SIGNED"');
}

function hire_agr_png_uri(array $row, string $role): string
{
    $path = hire_agr_sign_file((int)$row['id'], $role);
    if (!is_file($path)) {
        return '';
    }
    $bin = file_get_contents($path);
    if ($bin === false || $bin === '') {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($bin);
}

function hire_agr_store_pdf(mysqli $db, array $row, string $bin): string
{
    if (hire_agr_locked($row)) {
        $ref = trim((string)($row['verify_ref'] ?? ''));
        if ($ref !== '') {
            return $ref;
        }
        throw new RuntimeException('এই ভার্সন আগেই চূড়ান্ত।');
    }
    if (substr($bin, 0, 4) !== '%PDF' || strlen($bin) < 800 || strlen($bin) > 8000000) {
        throw new RuntimeException('PDF তৈরি হয়নি।');
    }
    if (empty($row['customer_signed_at']) || empty($row['guarantor_signed_at'])) {
        throw new RuntimeException('দুই পক্ষের স্বাক্ষরের পর PDF হবে।');
    }
    $dir = hire_agr_root() . '/' . (int)$row['id'];
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/agreement.pdf';
    if (file_put_contents($path, $bin) === false) {
        throw new RuntimeException('PDF সেভ হয়নি।');
    }
    $ref = strtoupper(substr(hash('sha256', $bin . '|' . $row['id'] . '|' . $row['agreement_no']), 0, 12));
    $terms = hire_agr_terms();
    $uid = function_exists('hire_uid') ? hire_uid() : (int)($_SESSION['user_id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $id = (int)$row['id'];
    $st = $db->prepare('UPDATE rental_agreements SET status="SIGNED", verify_ref=?, pdf_path=?, terms_snapshot=?, updated_by=?, updated_at=? WHERE id=? AND status<>"SIGNED"');
    if (!$st) {
        throw new RuntimeException('PDF রেকর্ড হয়নি।');
    }
    $st->bind_param('sssisi', $ref, $path, $terms, $uid, $now, $id);
    $ok = $st->execute();
    $changed = $st->affected_rows;
    $st->close();
    if (!$ok || $changed < 1) {
        $fresh = hire_agr_get($db, $id);
        if ($fresh && trim((string)($fresh['verify_ref'] ?? '')) !== '') {
            return (string)$fresh['verify_ref'];
        }
        throw new RuntimeException('PDF রেকর্ড হয়নি।');
    }
    return $ref;
}

function hire_agr_user_label(mysqli $db, int $id): string
{
    if ($id <= 0) {
        return '';
    }
    $st = @$db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if (!$st) {
        return (string)$id;
    }
    $st->bind_param('i', $id);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    $st->close();
    $name = trim((string)($user['username'] ?? ''));
    return $name !== '' ? $name : (string)$id;
}

function hire_agr_sign_log(mysqli $db, int $id): array
{
    $st = $db->prepare('SELECT id, role, signer_name, signed_at, ip, sha256, created_by FROM rental_agreement_signs WHERE agreement_id = ? ORDER BY id ASC');
    if (!$st) {
        return [];
    }
    $st->bind_param('i', $id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows ?: [];
}

function hire_agr_within_root(string $path): ?string
{
    $full = realpath($path);
    if ($full === false || !is_file($full)) {
        return null;
    }
    $dirs = [
        hire_agr_root(),
        (defined('AME_PRIVATE_DATA_DIR') ? AME_PRIVATE_DATA_DIR : dirname(__DIR__, 2) . '/private_data') . '/rental_agreements',
        dirname(__DIR__) . '/uploads/agreements',
    ];
    foreach ($dirs as $dir) {
        $root = realpath($dir);
        if ($root === false) {
            continue;
        }
        $prefix = rtrim($root, '/') . '/';
        if (str_starts_with($full, $prefix)) {
            return $full;
        }
    }
    return null;
}

function hire_agr_new_version(mysqli $db, array $row): int
{
    if (!hire_agr_locked($row)) {
        throw new RuntimeException('আগে এই ভার্সন স্বাক্ষর সম্পন্ন করুন, তারপর নতুন ভার্সন হবে।');
    }
    $uid = function_exists('hire_uid') ? hire_uid() : (int)($_SESSION['user_id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $ver = (int)$row['version_no'] + 1;
    $st = $db->prepare('SELECT id FROM rental_agreements WHERE agreement_no=? AND version_no=? LIMIT 1');
    if ($st) {
        $no = (string)$row['agreement_no'];
        $st->bind_param('si', $no, $ver);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();
        if ($exists) {
            return (int)$exists['id'];
        }
    }
    $sql = 'INSERT INTO rental_agreements (agreement_no, version_no, contract_id, parent_id, customer_id, status, customer_code, customer_name, father_or_spouse, nid, phone, phone_alt, address, present_address, occupation, customer_extra, item_name, product_sku, serial_no, qty, item_condition, security_deposit, rent_rate, rate_type, rent_pay_on, start_date, end_date, agreement_date, other_charges, guarantor_code, guarantor_name, guarantor_father, guarantor_nid, guarantor_phone, guarantor_phone_alt, guarantor_address, guarantor_occupation, guarantor_relation, guarantor_extra, special_terms, created_by, updated_by, updated_at) SELECT agreement_no, ?, contract_id, id, customer_id, "DRAFT", customer_code, customer_name, father_or_spouse, nid, phone, phone_alt, address, present_address, occupation, customer_extra, item_name, product_sku, serial_no, qty, item_condition, security_deposit, rent_rate, rate_type, rent_pay_on, start_date, end_date, CURDATE(), other_charges, guarantor_code, guarantor_name, guarantor_father, guarantor_nid, guarantor_phone, guarantor_phone_alt, guarantor_address, guarantor_occupation, guarantor_relation, guarantor_extra, special_terms, ?, ?, ? FROM rental_agreements WHERE id=?';
    $ins = $db->prepare($sql);
    if (!$ins) {
        throw new RuntimeException('নতুন ভার্সন হয়নি।');
    }
    $id = (int)$row['id'];
    $ins->bind_param('iiisi', $ver, $uid, $uid, $now, $id);
    $ok = $ins->execute();
    $newId = (int)$ins->insert_id;
    $ins->close();
    if (!$ok || $newId <= 0) {
        throw new RuntimeException('নতুন ভার্সন হয়নি।');
    }
    return $newId;
}

function hire_agr_attach_new(mysqli $db, int $contractId, array $src): int
{
    $contract = hire_contract($db, $contractId);
    if (!$contract) {
        throw new RuntimeException('ভাড়ার চুক্তি পাওয়া যায়নি।');
    }
    $rate = strtoupper(trim((string)($src['agr_rate_type'] ?? 'DAILY')));
    $mapped = [
        'customer_name' => (string)($contract['customer_name'] ?? ''),
        'father_or_spouse' => trim((string)($src['agr_father'] ?? '')),
        'nid' => trim((string)($src['agr_nid'] ?? '')),
        'phone' => trim((string)($src['agr_phone'] ?? ($contract['customer_phone'] ?? ''))),
        'phone_alt' => trim((string)($src['agr_phone_alt'] ?? '')),
        'address' => trim((string)($src['agr_address'] ?? '')),
        'present_address' => trim((string)($src['agr_present'] ?? '')),
        'occupation' => trim((string)($src['agr_occupation'] ?? '')),
        'customer_extra' => trim((string)($src['agr_customer_extra'] ?? '')),
        'item_name' => (string)($contract['item_name'] ?? ''),
        'product_sku' => (string)($contract['product_sku'] ?? ''),
        'serial_no' => (string)($contract['serial_no'] ?? ''),
        'qty' => (string)($contract['qty'] ?? '1'),
        'item_condition' => trim((string)($src['agr_condition'] ?? '')) !== '' ? trim((string)$src['agr_condition']) : 'গ্রহণের সময় যাচাই করা হয়েছে',
        'security_deposit' => (string)($contract['security_deposit'] ?? '0'),
        'rent_rate' => (string)($contract['rent_rate'] ?? '0'),
        'rate_type' => $rate,
        'rent_pay_on' => trim((string)($src['agr_pay_on'] ?? '')),
        'start_date' => (string)($contract['start_date'] ?? ''),
        'end_date' => (string)($contract['expected_return'] ?? ''),
        'agreement_date' => date('Y-m-d'),
        'other_charges' => trim((string)($src['agr_other'] ?? '')),
        'guarantor_name' => trim((string)($src['agr_g_name'] ?? '')),
        'guarantor_father' => trim((string)($src['agr_g_father'] ?? '')),
        'guarantor_nid' => trim((string)($src['agr_g_nid'] ?? '')),
        'guarantor_phone' => trim((string)($src['agr_g_phone'] ?? '')),
        'guarantor_phone_alt' => trim((string)($src['agr_g_phone_alt'] ?? '')),
        'guarantor_address' => trim((string)($src['agr_g_address'] ?? '')),
        'guarantor_occupation' => trim((string)($src['agr_g_occupation'] ?? '')),
        'guarantor_relation' => trim((string)($src['agr_g_relation'] ?? '')),
        'guarantor_extra' => trim((string)($src['agr_g_extra'] ?? '')),
        'special_terms' => trim((string)($src['agr_special'] ?? '')),
    ];
    $id = hire_agr_save($db, $contract, $mapped, 0);
    $row = hire_agr_get($db, $id);
    foreach (['customer' => 'agr_customer_sign', 'guarantor' => 'agr_guarantor_sign'] as $role => $key) {
        $png = trim((string)($src[$key] ?? ''));
        if ($png === '' || !$row) {
            continue;
        }
        try {
            hire_agr_store_sign($db, $row, $role, $png);
            $row = hire_agr_get($db, $id);
        } catch (Throwable $e) {
            error_log('hire agreement sign: ' . $e->getMessage());
        }
    }
    return $id;
}
