<?php
/**
 * শুধু ইঞ্জিনিয়ারের কাজের বিল — স্টেটমেন্ট।
 * পণ্য ছাড়া মেমোতে নির্দিষ্ট কাজের টাকাও সেই ইঞ্জিনিয়ারের স্টেটমেন্টে বসে।
 * কাস্টমার বা সাপ্লায়ারের খাতা, বিক্রয়ের মোট, বা স্টক এখানে বদলায় না।
 */

function ame_engineer_statement_bill_label(): string
{
    return 'কাজের বিল';
}

/**
 * ইঞ্জিনিয়ার ও টাকা থাকলে বিল সম্পূর্ণ। বিবরণ খালি থাকলে একটি নির্দিষ্ট নাম বসে।
 *
 * @return array{engineer_id:int,service_type:string,amount:float}|null
 */
function ame_engineer_statement_normalize(int $engineerId, string $type, float $amount): ?array
{
    $amount = round(max(0, $amount), 2);
    if ($engineerId <= 0 || $amount <= 0.009) {
        return null;
    }
    $type = trim($type);
    if ($type === '') {
        $type = ame_engineer_statement_bill_label();
    }
    return [
        'engineer_id' => $engineerId,
        'service_type' => substr($type, 0, 255),
        'amount' => $amount,
    ];
}

/**
 * ভাগের টাকা থাকলে স্টেটমেন্টে সেই ভাগ। ভাগ শূন্য হলে কাজের বিল নিজেই স্টেটমেন্টে দেখা যায়।
 *
 * @return array{show:bool,credit:float,label:string}
 */
function ame_engineer_statement_credit(float $serviceAmount, float $commissionAmount): array
{
    $serviceAmount = round(max(0, $serviceAmount), 2);
    $commissionAmount = round(max(0, $commissionAmount), 2);
    if ($serviceAmount <= 0.009 && $commissionAmount <= 0.009) {
        return ['show' => false, 'credit' => 0.0, 'label' => ''];
    }
    if ($commissionAmount > 0.009) {
        return [
            'show' => true,
            'credit' => $commissionAmount,
            'label' => 'কাজের ভাগ',
        ];
    }
    return [
        'show' => true,
        'credit' => $serviceAmount,
        'label' => ame_engineer_statement_bill_label(),
    ];
}

function ame_engineer_statement_table(mysqli $db, string $table): bool
{
    $name = $db->real_escape_string($table);
    $res = @$db->query("SHOW TABLES LIKE '{$name}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function ame_engineer_statement_open_engineers(mysqli $db): array
{
    if (!ame_engineer_statement_table($db, 'engineers')) {
        return [];
    }
    $sql = "SELECT id, name FROM engineers WHERE (purged_at IS NULL OR purged_at = '') ORDER BY id ASC";
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query('SELECT id, name FROM engineers ORDER BY id ASC');
    }
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $rows[] = ['id' => $id, 'name' => trim((string)($row['name'] ?? ''))];
            }
        }
        $res->free();
    }
    return $rows;
}

function ame_engineer_statement_resolve_id(mysqli $db, int $engineerId, string $query): int
{
    if ($engineerId > 0) {
        return $engineerId;
    }
    $open = ame_engineer_statement_open_engineers($db);
    $query = trim($query);
    if ($query !== '') {
        $hit = 0;
        $n = 0;
        foreach ($open as $row) {
            if ($query === $row['name'] || $query === (string)$row['id']) {
                $hit = (int)$row['id'];
                $n++;
            }
        }
        if ($n === 1) {
            return $hit;
        }
    }
    if (count($open) === 1) {
        return (int)$open[0]['id'];
    }
    return 0;
}

/**
 * পণ্য না থাকলেও প্রতিটি কাজের বিল একজন ইঞ্জিনিয়ারের সারিতে বাঁধে।
 *
 * @param list<array<string,mixed>> $lines
 * @return list<array<string,mixed>>
 */
function ame_engineer_statement_resolve_lines(mysqli $db, array $lines, string $query = ''): array
{
    $out = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $amount = round(max(0, (float)($line['amount'] ?? 0)), 2);
        if ($amount <= 0.009) {
            continue;
        }
        $engineerId = ame_engineer_statement_resolve_id(
            $db,
            (int)($line['engineer_id'] ?? 0),
            trim((string)($line['engineer_query'] ?? $query))
        );
        $ready = ame_engineer_statement_normalize(
            $engineerId,
            (string)($line['service_type'] ?? ''),
            $amount
        );
        if ($ready === null) {
            throw new InvalidArgumentException('ইঞ্জিনিয়ারের কাজের বিলে ইঞ্জিনিয়ার বাছুন এবং টাকা দিন।');
        }
        if (!empty($line['sale_service_id'])) {
            $ready['sale_service_id'] = (int)$line['sale_service_id'];
        }
        $out[] = $ready;
    }
    return $out;
}

function ame_engineer_statement_employee_id(mysqli $db, int $engineerId): int
{
    if ($engineerId <= 0) {
        return 0;
    }
    if (function_exists('ame_engineer_bill_bind_employee')) {
        $bound = (int)ame_engineer_bill_bind_employee($db, $engineerId);
        if ($bound > 0) {
            return $bound;
        }
    }
    if (function_exists('ensure_employee_for_engineer')) {
        $made = (int)ensure_employee_for_engineer($db, $engineerId);
        if ($made > 0) {
            return $made;
        }
    }
    if (!ame_engineer_statement_table($db, 'engineers')) {
        return 0;
    }
    $stmt = @$db->prepare('SELECT employee_id FROM engineers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $engineerId);
    $stmt->execute();
    $id = (int)($stmt->get_result()->fetch_assoc()['employee_id'] ?? 0);
    $stmt->close();
    return $id;
}

function ame_engineer_statement_has_commission(
    mysqli $db,
    int $saleId,
    int $engineerId,
    float $amount,
    int $sourceId
): bool {
    if (!ame_engineer_statement_table($db, 'service_commissions')) {
        return false;
    }
    if ($sourceId > 0) {
        $stmt = @$db->prepare(
            "SELECT id FROM service_commissions WHERE source_type = 'sale_service' AND source_id = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('i', $sourceId);
            $stmt->execute();
            $hit = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($hit) {
                return true;
            }
        }
    }
    if ($saleId <= 0) {
        return false;
    }
    $stmt = @$db->prepare(
        'SELECT id FROM service_commissions
         WHERE sale_id = ? AND engineer_id = ? AND ABS(service_amount - ?) < 0.02
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iid', $saleId, $engineerId, $amount);
    $stmt->execute();
    $hit = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $hit;
}

function ame_engineer_statement_insert(
    mysqli $db,
    int $saleId,
    int $engineerId,
    float $amount,
    string $type,
    int $sourceId
): void {
    if (ame_engineer_statement_has_commission($db, $saleId, $engineerId, $amount, $sourceId)) {
        return;
    }
    $employeeId = ame_engineer_statement_employee_id($db, $engineerId);
    if ($employeeId <= 0 || !ame_engineer_statement_table($db, 'service_commissions')) {
        return;
    }
    $workDate = date('Y-m-d');
    if ($saleId > 0 && ame_engineer_statement_table($db, 'sales')) {
        $dateStmt = @$db->prepare('SELECT DATE(COALESCE(completed_at, created_at)) AS work_date FROM sales WHERE id = ? LIMIT 1');
        if ($dateStmt) {
            $dateStmt->bind_param('i', $saleId);
            $dateStmt->execute();
            $found = (string)($dateStmt->get_result()->fetch_assoc()['work_date'] ?? '');
            $dateStmt->close();
            if ($found !== '') {
                $workDate = $found;
            }
        }
    }
    $rate = 50.0;
    $commission = round($amount * 0.5, 2);
    $shop = round($amount - $commission, 2);
    if (function_exists('ame_earnings_quote')) {
        $quote = ame_earnings_quote($db, $employeeId, $amount, 1.0, 1.0, $workDate);
        $payMode = strtolower((string)($quote['payment_type'] ?? 'percentage'));
        if (in_array($payMode, ['daily', 'monthly'], true)) {
            $rate = 0.0;
            $commission = 0.0;
            $shop = $amount;
        } else {
            $rate = (float)($quote['percent_rate'] ?? $rate);
            $commission = round((float)($quote['earning'] ?? $commission), 2);
            $shop = round((float)($quote['company'] ?? ($amount - $commission)), 2);
        }
    }
    $insert = @$db->prepare(
        "INSERT INTO service_commissions
            (employee_id, sale_id, service_amount, commission_rate, commission_amount, shop_amount, engineer_id, service_type, source_type, source_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sale_service', NULLIF(?, 0), 'pending')"
    );
    if (!$insert) {
        return;
    }
    $insert->bind_param(
        'iiddddisi',
        $employeeId,
        $saleId,
        $amount,
        $rate,
        $commission,
        $shop,
        $engineerId,
        $type,
        $sourceId
    );
    $insert->execute();
    $commissionId = (int)$db->insert_id;
    $insert->close();
    if ($commissionId > 0 && function_exists('ame_earnings_lock_from_commission')) {
        ame_earnings_lock_from_commission($db, $commissionId);
    }
}

/**
 * বিক্রয় সংরক্ষণের পর একই বিল স্টেটমেন্টে যায়, পণ্য থাকুক বা না থাকুক।
 *
 * @param list<array<string,mixed>> $stored
 */
function ame_engineer_statement_sale_completed(mysqli $db, int $saleId): bool
{
    if ($saleId <= 0 || !ame_engineer_statement_table($db, 'sales')) {
        return false;
    }
    $stmt = @$db->prepare("SELECT status FROM sales WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $saleId);
    $stmt->execute();
    $status = strtolower(trim((string)($stmt->get_result()->fetch_assoc()['status'] ?? '')));
    $stmt->close();
    return $status === 'completed';
}

function ame_engineer_statement_post_stored(mysqli $db, int $saleId, array $stored): void
{
    if ($saleId <= 0 || !($db instanceof mysqli) || !ame_engineer_statement_sale_completed($db, $saleId)) {
        return;
    }
    if (!function_exists('record_service_commission')) {
        $hrm = __DIR__ . '/hrm_functions.php';
        if (is_file($hrm)) {
            require_once $hrm;
        }
    }
    foreach ($stored as $line) {
        if (!is_array($line)) {
            continue;
        }
        $engineerId = (int)($line['engineer_id'] ?? 0);
        $amount = round((float)($line['amount'] ?? 0), 2);
        $type = trim((string)($line['service_type'] ?? ''));
        if ($type === '') {
            $type = ame_engineer_statement_bill_label();
        }
        $sourceId = (int)($line['sale_service_id'] ?? 0);
        if ($engineerId <= 0 || $amount <= 0.009) {
            continue;
        }
        if (function_exists('record_service_commission')) {
            record_service_commission($db, $saleId, $engineerId, $amount, $type, 'sale_service', $sourceId);
        }
        ame_engineer_statement_insert($db, $saleId, $engineerId, $amount, $type, $sourceId);
    }
}

function ame_engineer_statement_sync(mysqli $db, int $employeeId = 0): int
{
    if (!ame_engineer_statement_table($db, 'sale_services') || !ame_engineer_statement_table($db, 'service_commissions')) {
        return 0;
    }
    $sql = "SELECT ss.id, ss.sale_id, ss.engineer_id, ss.amount, ss.service_type
            FROM sale_services ss
            LEFT JOIN service_commissions sc
              ON sc.source_type = 'sale_service' AND sc.source_id = ss.id
            WHERE ss.engineer_id > 0 AND ss.amount > 0 AND sc.id IS NULL
              AND EXISTS (
                  SELECT 1 FROM sales s
                  WHERE s.id = ss.sale_id AND s.status = 'completed'
              )";
    if ($employeeId > 0 && ame_engineer_statement_table($db, 'engineers')) {
        $sql .= ' AND ss.engineer_id IN (SELECT id FROM engineers WHERE employee_id = ' . (int)$employeeId . ')';
    }
    $sql .= ' ORDER BY ss.id ASC LIMIT 80';
    $missing = @$db->query($sql);
    if (!$missing) {
        return 0;
    }
    $n = 0;
    while ($row = $missing->fetch_assoc()) {
        ame_engineer_statement_post_stored($db, (int)$row['sale_id'], [[
            'engineer_id' => (int)$row['engineer_id'],
            'amount' => (float)$row['amount'],
            'service_type' => (string)($row['service_type'] ?? ''),
            'sale_service_id' => (int)$row['id'],
        ]]);
        $n++;
    }
    $missing->free();
    return $n;
}

function ame_engineer_statement_boot(mysqli $db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    try {
        ame_engineer_statement_sync($db, 0);
    } catch (Throwable $e) {
        error_log('Engineer statement sync skipped: ' . $e->getMessage());
    }
}
