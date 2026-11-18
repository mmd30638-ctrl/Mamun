<?php
/**
 * দোকান ভাড়া — আমরা ভাড়াটিয়া। মালিককে দেওয়া ভাড়া, চুক্তি ও কাগজ।
 * কাস্টমার ভাড়া (m-rental) থেকে আলাদা।
 */

function shop_rent_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka'));
}

function shop_rent_today(): string
{
    return shop_rent_now()->format('Y-m-d');
}

function shop_rent_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function shop_rent_money($amount): string
{
    return number_format((float)$amount, 2);
}

function shop_rent_month_bn(int $month): string
{
    $names = [1 => 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    return $names[$month] ?? (string)$month;
}

function shop_rent_period_label(string $ym): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return $ym;
    }
    return 'ভাড়া — ' . shop_rent_month_bn((int)$m[2]) . ' ' . $m[1];
}

function shop_rent_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = $db;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('shop_rent')) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_landlords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        phone VARCHAR(30) NULL,
        emergency_phone VARCHAR(30) NULL,
        pay_method VARCHAR(30) NOT NULL DEFAULT 'cash',
        bank_name VARCHAR(120) NULL,
        account_name VARCHAR(150) NULL,
        account_no VARCHAR(80) NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_shops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        address VARCHAR(255) NULL,
        size_sqft DECIMAL(10,2) NOT NULL DEFAULT 0,
        condition_note VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_srs_landlord (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        monthly_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
        pay_day TINYINT NOT NULL DEFAULT 5,
        advance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        advance_account_id INT NOT NULL DEFAULT 0,
        advance_posted TINYINT NOT NULL DEFAULT 0,
        increment_months INT NOT NULL DEFAULT 0,
        increment_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
        next_increment_on DATE NULL,
        alert_months TINYINT NOT NULL DEFAULT 3,
        alert_email VARCHAR(150) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sra_shop (shop_id),
        KEY idx_sra_end (end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_charges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agreement_id INT NOT NULL,
        period_ym CHAR(7) NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'rent',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        due_date DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'due',
        paid_at DATETIME NULL,
        account_id INT NOT NULL DEFAULT 0,
        pay_method VARCHAR(30) NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_src_agr (agreement_id, period_ym),
        KEY idx_src_status (status, due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        agreement_id INT NOT NULL DEFAULT 0,
        kind VARCHAR(20) NOT NULL,
        period_ym CHAR(7) NULL,
        caption VARCHAR(255) NULL,
        stored_name VARCHAR(80) NOT NULL,
        mime VARCHAR(80) NOT NULL,
        taken_at DATETIME NOT NULL,
        deleted_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_srf_shop (shop_id, kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS shop_rent_mail_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_key VARCHAR(80) NOT NULL,
        sent_on DATE NOT NULL,
        UNIQUE KEY uniq_sr_alert (alert_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('shop_rent', true);
    }
}

function shop_rent_boot(mysqli $db): void
{
    shop_rent_ensure($db);
    shop_rent_apply_increments($db);
    shop_rent_open_month($db);
    shop_rent_mail_due_alerts($db);
}

function shop_rent_root(): string
{
    return dirname(__DIR__) . '/storage/shop_rent';
}

function shop_rent_kinds(): array
{
    return [
        'condition' => 'দোকানের অবস্থা',
        'agreement' => 'চুক্তিনামা',
        'receipt' => 'ভাড়ার রসিদ',
        'owner_id' => 'মালিকের পরিচয়',
    ];
}

function shop_rent_pay_methods(): array
{
    return [
        'cash' => 'ক্যাশ',
        'bank' => 'ব্যাংক',
        'bkash' => 'বিকাশ',
        'nagad' => 'নগদ',
        'rocket' => 'রকেট',
    ];
}

function shop_rent_charge_kinds(): array
{
    return [
        'rent' => 'মাসিক ভাড়া',
        'electric' => 'বিদ্যুৎ',
        'water' => 'পানি',
        'service' => 'সার্ভিস চার্জ',
    ];
}

function shop_rent_apply_increments(mysqli $db): int
{
    $today = shop_rent_today();
    $res = @$db->query(
        "SELECT id, monthly_rent, increment_months, increment_percent, next_increment_on
         FROM shop_rent_agreements
         WHERE status = 'active'
           AND increment_months > 0
           AND increment_percent > 0
           AND next_increment_on IS NOT NULL
           AND next_increment_on <= '" . $db->real_escape_string($today) . "'"
    );
    if (!$res) {
        return 0;
    }
    $n = 0;
    $stmt = $db->prepare('UPDATE shop_rent_agreements SET monthly_rent = ?, next_increment_on = ? WHERE id = ?');
    while ($row = $res->fetch_assoc()) {
        $rent = round((float)$row['monthly_rent'], 2);
        $next = (string)$row['next_increment_on'];
        $months = max(1, (int)$row['increment_months']);
        $pct = (float)$row['increment_percent'];
        $guard = 0;
        while ($next <= $today && $guard < 24) {
            $rent = round($rent * (1 + ($pct / 100)), 2);
            $next = (new DateTimeImmutable($next))->modify('+' . $months . ' months')->format('Y-m-d');
            $guard++;
        }
        if ($stmt) {
            $id = (int)$row['id'];
            $stmt->bind_param('dsi', $rent, $next, $id);
            $stmt->execute();
            $n++;
        }
    }
    if ($stmt) {
        $stmt->close();
    }
    $res->free();
    return $n;
}

function shop_rent_open_month(mysqli $db): void
{
    $today = shop_rent_now();
    $ym = $today->format('Y-m');
    $res = @$db->query(
        "SELECT a.id, a.monthly_rent, a.pay_day
         FROM shop_rent_agreements a
         WHERE a.status = 'active'
           AND NOT EXISTS (
                SELECT 1 FROM shop_rent_charges c
                WHERE c.agreement_id = a.id AND c.period_ym = '" . $db->real_escape_string($ym) . "' AND c.kind = 'rent'
           )"
    );
    if (!$res) {
        return;
    }
    $stmt = $db->prepare(
        "INSERT INTO shop_rent_charges (agreement_id, period_ym, kind, amount, due_date, status)
         VALUES (?, ?, 'rent', ?, ?, 'due')"
    );
    while ($row = $res->fetch_assoc()) {
        $day = max(1, min(28, (int)$row['pay_day']));
        $due = $today->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        $id = (int)$row['id'];
        $amount = round((float)$row['monthly_rent'], 2);
        if ($stmt) {
            $stmt->bind_param('isds', $id, $ym, $amount, $due);
            $stmt->execute();
        }
    }
    if ($stmt) {
        $stmt->close();
    }
    $res->free();
}

function shop_rent_summary(mysqli $db): array
{
    $ym = shop_rent_now()->format('Y-m');
    $shops = 0;
    $advance = 0.0;
    $due = 0.0;
    $r = $db->query("SELECT COUNT(*) n FROM shop_rent_shops WHERE status = 'active'");
    if ($r) {
        $shops = (int)($r->fetch_assoc()['n'] ?? 0);
        $r->free();
    }
    $r = $db->query(
        "SELECT COALESCE(SUM(a.advance_amount),0) n
         FROM shop_rent_agreements a
         JOIN shop_rent_shops s ON s.id = a.shop_id
         WHERE a.status = 'active' AND s.status = 'active'"
    );
    if ($r) {
        $advance = round((float)($r->fetch_assoc()['n'] ?? 0), 2);
        $r->free();
    }
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(c.amount),0) n
         FROM shop_rent_charges c
         JOIN shop_rent_agreements a ON a.id = c.agreement_id
         WHERE c.status = 'due' AND c.period_ym = ? AND a.status = 'active'"
    );
    if ($stmt) {
        $stmt->bind_param('s', $ym);
        $stmt->execute();
        $due = round((float)($stmt->get_result()->fetch_assoc()['n'] ?? 0), 2);
        $stmt->close();
    }
    return ['shops' => $shops, 'advance' => $advance, 'due' => $due, 'month' => $ym];
}

function shop_rent_rows(mysqli $db): array
{
    $sql = "SELECT s.id, s.title, s.address, s.size_sqft, s.status, s.condition_note,
                   l.name AS landlord_name, l.phone,
                   a.id AS agreement_id, a.monthly_rent, a.start_date, a.end_date,
                   a.advance_amount, a.pay_day, a.alert_months, a.alert_email, a.status AS agr_status
            FROM shop_rent_shops s
            JOIN shop_rent_landlords l ON l.id = s.landlord_id
            LEFT JOIN shop_rent_agreements a ON a.id = (
                SELECT aa.id FROM shop_rent_agreements aa WHERE aa.shop_id = s.id ORDER BY aa.id DESC LIMIT 1
            )
            ORDER BY s.status = 'active' DESC, s.title ASC";
    $res = @$db->query($sql);
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    return $rows;
}

function shop_rent_alerts(mysqli $db): array
{
    $today = new DateTimeImmutable(shop_rent_today());
    $alerts = [];
    foreach (shop_rent_rows($db) as $row) {
        if ((string)($row['agr_status'] ?? '') !== 'active' || (string)$row['status'] !== 'active') {
            continue;
        }
        $end = (string)($row['end_date'] ?? '');
        if ($end !== '') {
            $endDay = new DateTimeImmutable($end);
            $months = max(1, min(3, (int)($row['alert_months'] ?? 3)));
            $warn = $today->modify('+' . $months . ' months');
            if ($endDay <= $warn) {
                $alerts[] = [
                    'key' => 'renew-' . (int)$row['agreement_id'] . '-' . $end,
                    'shop_id' => (int)$row['id'],
                    'email' => (string)($row['alert_email'] ?? ''),
                    'title' => (string)$row['title'],
                    'text' => $endDay < $today
                        ? 'চুক্তির মেয়াদ ' . $end . ' তারিখে শেষ হয়েছে। নবায়ন করুন।'
                        : 'চুক্তি ' . $end . ' তারিখে শেষ হবে। এখনই নবায়নের কথা বলুন।',
                    'tone' => 'renew',
                ];
            }
        }
    }
    $todayStr = shop_rent_today();
    $res = @$db->query(
        "SELECT c.id, c.amount, c.due_date, c.kind, c.period_ym, s.id AS shop_id, s.title, a.alert_email
         FROM shop_rent_charges c
         JOIN shop_rent_agreements a ON a.id = c.agreement_id AND a.status = 'active'
         JOIN shop_rent_shops s ON s.id = a.shop_id AND s.status = 'active'
         WHERE c.status = 'due' AND c.due_date IS NOT NULL AND c.due_date <= '" . $db->real_escape_string($todayStr) . "'
         ORDER BY c.due_date ASC
         LIMIT 40"
    );
    if ($res) {
        $kinds = shop_rent_charge_kinds();
        while ($row = $res->fetch_assoc()) {
            $kind = $kinds[$row['kind']] ?? 'ভাড়া';
            $alerts[] = [
                'key' => 'due-' . (int)$row['id'],
                'shop_id' => (int)$row['shop_id'],
                'email' => (string)($row['alert_email'] ?? ''),
                'title' => (string)$row['title'],
                'text' => shop_rent_period_label((string)$row['period_ym']) . ' — ' . $kind . ' ৳' . shop_rent_money($row['amount']) . ' এখনো বাকি।',
                'tone' => 'due',
            ];
        }
        $res->free();
    }
    return $alerts;
}

function shop_rent_mail_due_alerts(mysqli $db): void
{
    if (!function_exists('send_simple_email') && is_file(__DIR__ . '/mail_functions.php')) {
        require_once __DIR__ . '/mail_functions.php';
    }
    if (!function_exists('send_simple_email')) {
        return;
    }
    $today = shop_rent_today();
    foreach (shop_rent_alerts($db) as $alert) {
        $email = trim((string)$alert['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $key = (string)$alert['key'];
        $chk = $db->prepare('SELECT id FROM shop_rent_mail_log WHERE alert_key = ? LIMIT 1');
        if (!$chk) {
            continue;
        }
        $chk->bind_param('s', $key);
        $chk->execute();
        $seen = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($seen) {
            continue;
        }
        $subject = 'দোকান ভাড়া সতর্কতা — ' . $alert['title'];
        $body = '<p>' . shop_rent_e($alert['title']) . '</p><p>' . shop_rent_e($alert['text']) . '</p>';
        $ok = false;
        try {
            $ok = (bool)send_simple_email($email, $subject, $body);
        } catch (Throwable $e) {
            $ok = false;
        }
        if ($ok) {
            $ins = $db->prepare('INSERT IGNORE INTO shop_rent_mail_log (alert_key, sent_on) VALUES (?, ?)');
            if ($ins) {
                $ins->bind_param('ss', $key, $today);
                $ins->execute();
                $ins->close();
            }
        }
    }
}

function shop_rent_load(mysqli $db, int $shopId): ?array
{
    if ($shopId <= 0) {
        return null;
    }
    $stmt = $db->prepare(
        "SELECT s.*, l.name, l.phone, l.emergency_phone, l.pay_method, l.bank_name, l.account_name, l.account_no, l.note AS landlord_note,
                a.id AS agreement_id, a.start_date, a.end_date, a.monthly_rent, a.pay_day, a.advance_amount,
                a.advance_account_id, a.advance_posted, a.increment_months, a.increment_percent, a.next_increment_on,
                a.alert_months, a.alert_email, a.status AS agr_status
         FROM shop_rent_shops s
         JOIN shop_rent_landlords l ON l.id = s.landlord_id
         LEFT JOIN shop_rent_agreements a ON a.id = (
            SELECT aa.id FROM shop_rent_agreements aa WHERE aa.shop_id = s.id ORDER BY aa.id DESC LIMIT 1
         )
         WHERE s.id = ? LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $shopId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function shop_rent_save_profile(mysqli $db, array $in): int
{
    $name = trim((string)($in['name'] ?? ''));
    $title = trim((string)($in['title'] ?? ''));
    if ($name === '' || $title === '') {
        throw new InvalidArgumentException('মালিকের নাম এবং দোকানের নাম লাগবে।');
    }
    $start = (string)($in['start_date'] ?? '');
    $end = (string)($in['end_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) {
        throw new InvalidArgumentException('চুক্তির শুরু ও শেষ তারিখ ঠিক করুন।');
    }
    $phone = trim((string)($in['phone'] ?? ''));
    $emergency = trim((string)($in['emergency_phone'] ?? ''));
    $payMethod = (string)($in['pay_method'] ?? 'cash');
    if (!isset(shop_rent_pay_methods()[$payMethod])) {
        $payMethod = 'cash';
    }
    $bank = trim((string)($in['bank_name'] ?? ''));
    $acctName = trim((string)($in['account_name'] ?? ''));
    $acctNo = trim((string)($in['account_no'] ?? ''));
    $note = trim((string)($in['note'] ?? ''));
    $address = trim((string)($in['address'] ?? ''));
    $size = round((float)($in['size_sqft'] ?? 0), 2);
    $condition = trim((string)($in['condition_note'] ?? ''));
    $status = (string)($in['status'] ?? 'active') === 'left' ? 'left' : 'active';
    $rent = round((float)($in['monthly_rent'] ?? 0), 2);
    $payDay = max(1, min(28, (int)($in['pay_day'] ?? 5)));
    $advance = round((float)($in['advance_amount'] ?? 0), 2);
    $advanceAccount = (int)($in['advance_account_id'] ?? 0);
    $incMonths = max(0, (int)($in['increment_months'] ?? 0));
    $incPct = round((float)($in['increment_percent'] ?? 0), 2);
    $alertMonths = max(1, min(3, (int)($in['alert_months'] ?? 3)));
    $email = trim((string)($in['alert_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('নোটিফিকেশন ইমেইল ঠিক নেই।');
    }
    $shopId = (int)($in['shop_id'] ?? 0);
    $db->begin_transaction();
    try {
        if ($shopId > 0) {
            $current = shop_rent_load($db, $shopId);
            if (!$current) {
                throw new InvalidArgumentException('দোকানটি খুঁজে পাওয়া যায়নি।');
            }
            $landlordId = (int)$current['landlord_id'];
            $stmt = $db->prepare('UPDATE shop_rent_landlords SET name=?, phone=?, emergency_phone=?, pay_method=?, bank_name=?, account_name=?, account_no=?, note=? WHERE id=?');
            $stmt->bind_param('ssssssssi', $name, $phone, $emergency, $payMethod, $bank, $acctName, $acctNo, $note, $landlordId);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare('UPDATE shop_rent_shops SET title=?, address=?, size_sqft=?, condition_note=?, status=? WHERE id=?');
            $stmt->bind_param('ssdssi', $title, $address, $size, $condition, $status, $shopId);
            $stmt->execute();
            $stmt->close();
            $agreementId = (int)($current['agreement_id'] ?? 0);
            $posted = (int)($current['advance_posted'] ?? 0);
            $keptAdvance = $posted === 1 ? round((float)$current['advance_amount'], 2) : $advance;
            $keptAccount = $posted === 1 ? (int)$current['advance_account_id'] : $advanceAccount;
            $nextInc = null;
            if ($incMonths > 0 && $incPct > 0) {
                $existingNext = (string)($current['next_increment_on'] ?? '');
                $sameRule = (int)($current['increment_months'] ?? 0) === $incMonths
                    && abs((float)($current['increment_percent'] ?? 0) - $incPct) < 0.001
                    && (string)($current['start_date'] ?? '') === $start
                    && $existingNext !== '';
                $nextInc = $sameRule
                    ? $existingNext
                    : (new DateTimeImmutable($start))->modify('+' . $incMonths . ' months')->format('Y-m-d');
            }
            if ($agreementId > 0) {
                $stmt = $db->prepare('UPDATE shop_rent_agreements SET start_date=?, end_date=?, monthly_rent=?, pay_day=?, advance_amount=?, advance_account_id=?, increment_months=?, increment_percent=?, next_increment_on=?, alert_months=?, alert_email=?, status=? WHERE id=?');
                $agrStatus = $status === 'left' ? 'ended' : 'active';
                $stmt->bind_param('ssdidiidsissi', $start, $end, $rent, $payDay, $keptAdvance, $keptAccount, $incMonths, $incPct, $nextInc, $alertMonths, $email, $agrStatus, $agreementId);
                $stmt->execute();
                $stmt->close();
            }
            if ($posted !== 1 && $advance > 0.009) {
                shop_rent_post_advance($db, $agreementId, $advance, $advanceAccount, $title);
            }
        } else {
            $stmt = $db->prepare('INSERT INTO shop_rent_landlords (name, phone, emergency_phone, pay_method, bank_name, account_name, account_no, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssss', $name, $phone, $emergency, $payMethod, $bank, $acctName, $acctNo, $note);
            $stmt->execute();
            $landlordId = (int)$stmt->insert_id;
            $stmt->close();
            $stmt = $db->prepare('INSERT INTO shop_rent_shops (landlord_id, title, address, size_sqft, condition_note, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issdss', $landlordId, $title, $address, $size, $condition, $status);
            $stmt->execute();
            $shopId = (int)$stmt->insert_id;
            $stmt->close();
            $nextInc = null;
            if ($incMonths > 0 && $incPct > 0) {
                $nextInc = (new DateTimeImmutable($start))->modify('+' . $incMonths . ' months')->format('Y-m-d');
            }
            $agrStatus = $status === 'left' ? 'ended' : 'active';
            $stmt = $db->prepare('INSERT INTO shop_rent_agreements (shop_id, start_date, end_date, monthly_rent, pay_day, advance_amount, advance_account_id, increment_months, increment_percent, next_increment_on, alert_months, alert_email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issdidiidsiss', $shopId, $start, $end, $rent, $payDay, $advance, $advanceAccount, $incMonths, $incPct, $nextInc, $alertMonths, $email, $agrStatus);
            $stmt->execute();
            $agreementId = (int)$stmt->insert_id;
            $stmt->close();
            if ($advance > 0.009) {
                shop_rent_post_advance($db, $agreementId, $advance, $advanceAccount, $title);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $shopId;
}

function shop_rent_post_advance(mysqli $db, int $agreementId, float $amount, int $accountId, string $title): void
{
    if ($agreementId <= 0 || $amount <= 0.009) {
        return;
    }
    if ($accountId <= 0) {
        throw new InvalidArgumentException('অগ্রিম কোন অ্যাকাউন্ট থেকে যাবে সেটি বাছুন।');
    }
    if (!function_exists('account_debit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    account_debit($accountId, $amount, 'shop_rent_advance', $agreementId, 'দোকান ভাড়ার অগ্রিম: ' . $title);
    $stmt = $db->prepare('UPDATE shop_rent_agreements SET advance_posted = 1, advance_account_id = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $accountId, $agreementId);
        $stmt->execute();
        $stmt->close();
    }
}

function shop_rent_record_payment(mysqli $db, array $in): int
{
    $shopId = (int)($in['shop_id'] ?? 0);
    $shop = shop_rent_load($db, $shopId);
    if (!$shop || (int)($shop['agreement_id'] ?? 0) <= 0) {
        throw new InvalidArgumentException('আগে দোকান ও চুক্তি সেভ করুন।');
    }
    $kind = (string)($in['kind'] ?? 'rent');
    if (!isset(shop_rent_charge_kinds()[$kind])) {
        $kind = 'rent';
    }
    $ym = (string)($in['period_ym'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        throw new InvalidArgumentException('মাস বাছুন।');
    }
    $amount = round((float)($in['amount'] ?? 0), 2);
    if ($amount <= 0.009) {
        throw new InvalidArgumentException('টাকার পরিমাণ দিন।');
    }
    $accountId = (int)($in['account_id'] ?? 0);
    if ($accountId <= 0) {
        throw new InvalidArgumentException('টাকা কোন অ্যাকাউন্ট থেকে দিচ্ছেন সেটি বাছুন।');
    }
    $method = (string)($in['pay_method'] ?? 'cash');
    if (!isset(shop_rent_pay_methods()[$method])) {
        $method = 'cash';
    }
    $note = trim((string)($in['note'] ?? ''));
    $agreementId = (int)$shop['agreement_id'];
    $db->begin_transaction();
    try {
        $chargeId = 0;
        if ($kind === 'rent') {
            $find = $db->prepare("SELECT id, status FROM shop_rent_charges WHERE agreement_id = ? AND period_ym = ? AND kind = 'rent' LIMIT 1");
            $find->bind_param('is', $agreementId, $ym);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc();
            $find->close();
            if ($existing && (string)$existing['status'] === 'paid') {
                throw new InvalidArgumentException('এই মাসের ভাড়া আগেই দেওয়া আছে।');
            }
            if ($existing) {
                $chargeId = (int)$existing['id'];
                $upd = $db->prepare("UPDATE shop_rent_charges SET amount=?, status='paid', paid_at=NOW(), account_id=?, pay_method=?, note=? WHERE id=?");
                $upd->bind_param('dissi', $amount, $accountId, $method, $note, $chargeId);
                $upd->execute();
                $upd->close();
            }
        }
        if ($chargeId === 0) {
            $due = $ym . '-01';
            $ins = $db->prepare("INSERT INTO shop_rent_charges (agreement_id, period_ym, kind, amount, due_date, status, paid_at, account_id, pay_method, note) VALUES (?, ?, ?, ?, ?, 'paid', NOW(), ?, ?, ?)");
            $ins->bind_param('issdsiss', $agreementId, $ym, $kind, $amount, $due, $accountId, $method, $note);
            $ins->execute();
            $chargeId = (int)$ins->insert_id;
            $ins->close();
        }
        if (!function_exists('account_debit')) {
            require_once __DIR__ . '/account_functions.php';
        }
        $label = shop_rent_charge_kinds()[$kind] . ' ' . shop_rent_period_label($ym) . ' — ' . $shop['title'];
        account_debit($accountId, $amount, 'shop_rent', $chargeId, $label);
        $db->commit();
        return $chargeId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function shop_rent_charges(mysqli $db, int $agreementId, string $year = ''): array
{
    if ($agreementId <= 0) {
        return [];
    }
    $sql = 'SELECT * FROM shop_rent_charges WHERE agreement_id = ?';
    $types = 'i';
    $params = [$agreementId];
    if (preg_match('/^\d{4}$/', $year)) {
        $sql .= ' AND period_ym LIKE ?';
        $types .= 's';
        $params[] = $year . '-%';
    }
    $sql .= ' ORDER BY period_ym ASC, id ASC';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function shop_rent_store_file(mysqli $db, array $in, array $file): int
{
    $shopId = (int)($in['shop_id'] ?? 0);
    $shop = shop_rent_load($db, $shopId);
    if (!$shop) {
        throw new InvalidArgumentException('দোকান বাছুন।');
    }
    $kind = (string)($in['kind'] ?? '');
    if (!isset(shop_rent_kinds()[$kind])) {
        throw new InvalidArgumentException('কাগজের ধরন বাছুন।');
    }
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('ফাইল আপলোড হয়নি।');
    }
    if ((int)($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('ফাইল ৮ মেগাবাইটের মধ্যে রাখুন।');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (!isset($map[$mime])) {
        throw new InvalidArgumentException('শুধু JPG, PNG, WEBP বা PDF নেওয়া হবে।');
    }
    $period = trim((string)($in['period_ym'] ?? ''));
    if ($kind === 'receipt' && !preg_match('/^\d{4}-\d{2}$/', $period)) {
        throw new InvalidArgumentException('রসিদ কোন মাসের, সেই মাস বাছুন।');
    }
    if ($kind !== 'receipt') {
        $period = '';
    }
    $caption = trim((string)($in['caption'] ?? ''));
    $dir = shop_rent_root() . '/' . $shopId;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('কাগজ রাখার জায়গা তৈরি হয়নি।');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('ফাইল সেভ হয়নি।');
    }
    $taken = shop_rent_now()->format('Y-m-d H:i');
    if ($mime !== 'application/pdf') {
        shop_rent_stamp_image($dest, $taken);
    }
    $agreementId = (int)($shop['agreement_id'] ?? 0);
    $stmt = $db->prepare('INSERT INTO shop_rent_files (shop_id, agreement_id, kind, period_ym, caption, stored_name, mime, taken_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $periodDb = $period !== '' ? $period : null;
    $stmt->bind_param('iissssss', $shopId, $agreementId, $kind, $periodDb, $caption, $name, $mime, $taken);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function shop_rent_stamp_image(string $path, string $text): void
{
    if (!function_exists('imagecreatefromjpeg')) {
        return;
    }
    $info = @getimagesize($path);
    if (!$info) {
        return;
    }
    $im = null;
    if ($info[2] === IMAGETYPE_JPEG) {
        $im = @imagecreatefromjpeg($path);
    } elseif ($info[2] === IMAGETYPE_PNG) {
        $im = @imagecreatefrompng($path);
    } elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $im = @imagecreatefromwebp($path);
    }
    if (!$im) {
        return;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $bar = imagecolorallocatealpha($im, 0, 0, 0, 50);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, max(0, $h - 26), $w, $h, $bar);
    imagestring($im, 4, 8, max(0, $h - 20), $text, $white);
    if ($info[2] === IMAGETYPE_JPEG) {
        imagejpeg($im, $path, 86);
    } elseif ($info[2] === IMAGETYPE_PNG) {
        imagepng($im, $path);
    } elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagewebp')) {
        imagewebp($im, $path, 86);
    }
    imagedestroy($im);
}

function shop_rent_files(mysqli $db, int $shopId, string $kind = ''): array
{
    $sql = 'SELECT * FROM shop_rent_files WHERE shop_id = ? AND deleted_at IS NULL';
    $types = 'i';
    $params = [$shopId];
    if ($kind !== '' && isset(shop_rent_kinds()[$kind])) {
        $sql .= ' AND kind = ?';
        $types .= 's';
        $params[] = $kind;
    }
    $sql .= ' ORDER BY period_ym ASC, id ASC';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function shop_rent_file_path(mysqli $db, int $fileId): ?array
{
    $stmt = $db->prepare('SELECT * FROM shop_rent_files WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $base = realpath(shop_rent_root());
    $path = shop_rent_root() . '/' . (int)$row['shop_id'] . '/' . basename((string)$row['stored_name']);
    $real = realpath($path);
    if ($base === false || $real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    $row['path'] = $real;
    return $row;
}

function shop_rent_save_caption(mysqli $db, int $fileId, string $caption): void
{
    $caption = trim($caption);
    $stmt = $db->prepare('UPDATE shop_rent_files SET caption = ? WHERE id = ? AND deleted_at IS NULL');
    if ($stmt) {
        $stmt->bind_param('si', $caption, $fileId);
        $stmt->execute();
        $stmt->close();
    }
}

function shop_rent_font(): string
{
    $local = dirname(__DIR__) . '/modules/shop_rent/font/NotoSansBengali-Regular.ttf';
    if (is_file($local)) {
        return $local;
    }
    $system = '/usr/share/fonts/truetype/noto/NotoSansBengali-Regular.ttf';
    return is_file($system) ? $system : '';
}

function shop_rent_account_label(array $account): string
{
    $label = trim((string)($account['account_name'] ?? ''));
    $type = trim((string)($account['account_type'] ?? ''));
    if ($type !== '') {
        $label .= ' · ' . $type;
    }
    if (function_exists('ame_balance_label')) {
        $label .= ' · ' . ame_balance_label((float)($account['balance'] ?? 0));
    }
    return $label;
}

function shop_rent_wrap_lines(array $lines, int $width = 46): array
{
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        $buf = '';
        foreach (preg_split('/\s+/u', $line) ?: [$line] as $part) {
            $try = $buf === '' ? $part : $buf . ' ' . $part;
            if (mb_strlen($try) > $width && $buf !== '') {
                $out[] = $buf;
                $buf = $part;
            } else {
                $buf = $try;
            }
        }
        if ($buf !== '') {
            $out[] = $buf;
        }
    }
    return $out;
}

function shop_rent_document_lines(mysqli $db, int $shopId, string $year, string $doc): array
{
    $shop = shop_rent_load($db, $shopId);
    if (!$shop) {
        return ['দোকান পাওয়া যায়নি'];
    }
    $lines = [];
    if (function_exists('shop_name')) {
        $own = trim((string)shop_name());
        if ($own !== '') {
            $lines[] = $own;
        }
    }
    if ($doc === 'agreement') {
        $methods = shop_rent_pay_methods();
        $lines[] = 'চুক্তির সারাংশ';
        $lines[] = 'দোকান: ' . (string)$shop['title'];
        $lines[] = 'ঠিকানা: ' . ((string)($shop['address'] ?? '') !== '' ? $shop['address'] : '—');
        $lines[] = 'জায়গা: ' . shop_rent_money($shop['size_sqft'] ?? 0) . ' স্কয়ার ফিট';
        $lines[] = 'অবস্থা: ' . ((string)($shop['condition_note'] ?? '') !== '' ? $shop['condition_note'] : '—');
        $lines[] = 'মালিক: ' . (string)$shop['name'];
        $lines[] = 'মোবাইল: ' . ((string)($shop['phone'] ?? '') !== '' ? $shop['phone'] : '—');
        $lines[] = 'ইমার্জেন্সি: ' . ((string)($shop['emergency_phone'] ?? '') !== '' ? $shop['emergency_phone'] : '—');
        $lines[] = 'টাকা নেওয়ার মাধ্যম: ' . ($methods[$shop['pay_method'] ?? ''] ?? '—');
        $payTo = trim((string)($shop['bank_name'] ?? '') . ' ' . (string)($shop['account_name'] ?? '') . ' ' . (string)($shop['account_no'] ?? ''));
        if ($payTo !== '') {
            $lines[] = 'প্রাপক: ' . $payTo;
        }
        $lines[] = 'শুরু: ' . (string)$shop['start_date'] . '    শেষ: ' . (string)$shop['end_date'];
        $lines[] = 'মাসিক ভাড়া: ৳' . shop_rent_money($shop['monthly_rent'] ?? 0);
        $lines[] = 'প্রতি মাসের ' . (int)($shop['pay_day'] ?? 5) . ' তারিখে';
        $lines[] = 'অগ্রিম: ৳' . shop_rent_money($shop['advance_amount'] ?? 0);
        if ((int)($shop['increment_months'] ?? 0) > 0 && (float)($shop['increment_percent'] ?? 0) > 0) {
            $lines[] = (int)$shop['increment_months'] . ' মাস পর ভাড়া ' . shop_rent_money($shop['increment_percent']) . '% বাড়বে';
            if (!empty($shop['next_increment_on'])) {
                $lines[] = 'পরের বৃদ্ধি: ' . $shop['next_increment_on'];
            }
        }
        $lines[] = 'নবায়ন সতর্কতা: ' . (int)($shop['alert_months'] ?? 3) . ' মাস আগে';
        return $lines;
    }
    if (!preg_match('/^\d{4}$/', $year)) {
        $year = shop_rent_now()->format('Y');
    }
    $lines[] = 'বার্ষিক ভাড়ার স্টেটমেন্ট ' . $year;
    $lines[] = 'দোকান: ' . (string)$shop['title'] . ' — ' . (string)$shop['name'];
    $charges = shop_rent_charges($db, (int)($shop['agreement_id'] ?? 0), $year);
    $kinds = shop_rent_charge_kinds();
    $methods = shop_rent_pay_methods();
    $paid = 0.0;
    $due = 0.0;
    if (!$charges) {
        $lines[] = 'এই বছরে কোনো ভাড়া বা বিল নেই।';
    }
    foreach ($charges as $charge) {
        $kind = $kinds[$charge['kind']] ?? (string)$charge['kind'];
        $state = (string)$charge['status'] === 'paid' ? 'দেওয়া' : 'বাকি';
        $when = (string)$charge['status'] === 'paid' ? (string)$charge['paid_at'] : (string)$charge['due_date'];
        $how = $methods[$charge['pay_method'] ?? ''] ?? '';
        $lines[] = shop_rent_period_label((string)$charge['period_ym'])
            . ' · ' . $kind
            . ' · ৳' . shop_rent_money($charge['amount'])
            . ' · ' . $state
            . ($how !== '' ? ' · ' . $how : '')
            . ($when !== '' ? ' · ' . $when : '');
        if ((string)$charge['status'] === 'paid') {
            $paid += (float)$charge['amount'];
        } else {
            $due += (float)$charge['amount'];
        }
    }
    $lines[] = 'মোট দেওয়া: ৳' . shop_rent_money($paid);
    $lines[] = 'মোট বাকি: ৳' . shop_rent_money($due);
    return $lines;
}

function shop_rent_pdf(array $lines): string
{
    $font = shop_rent_font();
    if ($font === '' || !function_exists('imagettftext')) {
        throw new RuntimeException('পিডিএফের ফন্ট পাওয়া যায়নি। প্রিন্ট বোতাম ব্যবহার করুন।');
    }
    $lines = shop_rent_wrap_lines($lines);
    $pages = array_chunk($lines, 32);
    if ($pages === []) {
        $pages = [['কোনো তথ্য নেই']];
    }
    $jpegs = [];
    foreach ($pages as $chunk) {
        $w = 900;
        $h = 1200;
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, 255, 252, 245);
        $ink = imagecolorallocate($im, 17, 24, 39);
        $rule = imagecolorallocate($im, 0, 63, 143);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);
        imagefilledrectangle($im, 40, 36, $w - 40, 42, $rule);
        $y = 80;
        foreach ($chunk as $line) {
            imagettftext($im, 16, 0, 48, $y, $ink, $font, (string)$line);
            $y += 34;
        }
        ob_start();
        imagejpeg($im, null, 86);
        $jpegs[] = ob_get_clean();
        imagedestroy($im);
    }
    return shop_rent_pdf_wrap($jpegs, 900, 1200);
}

function shop_rent_pdf_wrap(array $jpegs, int $w, int $h): string
{
    $objects = [];
    $pageIds = [];
    $next = 1;
    $catalogId = $next++;
    $pagesId = $next++;
    foreach ($jpegs as $jpeg) {
        $imageId = $next++;
        $contentId = $next++;
        $pageId = $next++;
        $pageIds[] = $pageId;
        $objects[$imageId] = "<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
        $content = "q {$w} 0 0 {$h} 0 0 cm /Im Do Q";
        $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 {$w} {$h}] /Contents {$contentId} 0 R /Resources << /XObject << /Im {$imageId} 0 R >> >> >>";
    }
    $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageIds));
    $objects[$pagesId] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';
    $objects[$catalogId] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    $max = max(array_keys($objects));
    for ($i = 1; $i <= $max; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($max + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . ($max + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xref}\n%%EOF";
    return $pdf;
}
