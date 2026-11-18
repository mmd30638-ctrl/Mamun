<?php
/**
 * ইঞ্জিনিয়ার সার্ভিস ও মেরামতের বিল — লক।
 * প্রতিটি বিল সেই ইঞ্জিনিয়ারের নিজের তালিকায় বসে।
 * কাস্টমার বা সাপ্লায়ারের খাতা, বিক্রয়ের মোট, বা স্টক এখানে বদলায় না।
 * পরের আপডেট এই ফাইল ছোঁবে না। অন্য জায়গা বদলালেও এই নিয়ম থাকে।
 */

function ame_engineer_bill_lock_files(): array
{
    return [
        'includes/engineer_bill_lock.php',
    ];
}

function ame_engineer_bill_lock_protects(string $rel): bool
{
    $rel = str_replace('\\', '/', ltrim($rel, '/'));
    return in_array($rel, ame_engineer_bill_lock_files(), true);
}

/**
 * একজন কর্মীর একাধিক ইঞ্জিনিয়ার থাকলে নাম মিললে সেই সারি, না হলে একমাত্র সারি।
 *
 * @param list<array<string,mixed>> $engineers
 */
function ame_engineer_bill_pick_id(array $engineers, string $name): int
{
    $name = trim($name);
    $open = [];
    foreach ($engineers as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $open[] = $row;
        if ($name !== '' && trim((string)($row['name'] ?? '')) === $name) {
            return $id;
        }
    }
    if (count($open) === 1) {
        return (int)$open[0]['id'];
    }
    return 0;
}

function ame_engineer_bill_table(mysqli $db, string $table): bool
{
    $name = $db->real_escape_string($table);
    $res = @$db->query("SHOW TABLES LIKE '{$name}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function ame_engineer_bill_employee_exists(mysqli $db, int $employeeId): bool
{
    if ($employeeId <= 0 || !ame_engineer_bill_table($db, 'employees')) {
        return false;
    }
    $stmt = @$db->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function ame_engineer_bill_is_open(mysqli $db, int $engineerId): bool
{
    if ($engineerId <= 0 || !ame_engineer_bill_table($db, 'engineers')) {
        return false;
    }
    $col = @$db->query("SHOW COLUMNS FROM engineers LIKE 'purged_at'");
    if (!$col || !$col->fetch_assoc()) {
        return true;
    }
    $stmt = @$db->prepare('SELECT purged_at FROM engineers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param('i', $engineerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && trim((string)($row['purged_at'] ?? '')) === '';
}

function ame_engineer_bill_link(mysqli $db, int $engineerId, int $employeeId): void
{
    if ($engineerId <= 0 || $employeeId <= 0) {
        return;
    }
    $stmt = @$db->prepare('UPDATE engineers SET employee_id = ? WHERE id = ? AND (employee_id IS NULL OR employee_id = 0)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $employeeId, $engineerId);
    $stmt->execute();
    $stmt->close();
}

function ame_engineer_bill_free_employee(mysqli $db, string $name, int $engineerId): int
{
    $name = trim($name);
    if ($name === '' || !ame_engineer_bill_table($db, 'employees')) {
        return 0;
    }
    $stmt = @$db->prepare('SELECT id FROM employees WHERE name = ? ORDER BY id ASC LIMIT 8');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
    }
    $stmt->close();
    $taken = @$db->prepare('SELECT id FROM engineers WHERE employee_id = ? AND id <> ? LIMIT 1');
    foreach ($ids as $empId) {
        if ($empId <= 0) {
            continue;
        }
        $busy = false;
        if ($taken) {
            $taken->bind_param('ii', $empId, $engineerId);
            $taken->execute();
            $takenRes = $taken->get_result();
            $busy = $takenRes && (bool)$takenRes->fetch_assoc();
            if ($takenRes) {
                $takenRes->free();
            }
        }
        if (!$busy) {
            if ($taken) {
                $taken->close();
            }
            return $empId;
        }
    }
    if ($taken) {
        $taken->close();
    }
    return 0;
}

function ame_engineer_bill_make_employee(mysqli $db, string $name, string $phone, string $status): int
{
    if (function_exists('ame_create_linked_employee')) {
        return (int)ame_create_linked_employee(
            $db,
            $name,
            $phone,
            'সার্ভিস ইঞ্জিনিয়ার',
            $status,
            1,
            'engineer'
        );
    }
    if (!ame_engineer_bill_table($db, 'employees')) {
        return 0;
    }
    $salary = 0.0;
    $share = 50.0;
    $salaryType = 'monthly';
    $code = 'ENG' . date('ymdHis');
    $joining = date('Y-m-d');
    $designation = 'সার্ভিস ইঞ্জিনিয়ার';
    $stmt = @$db->prepare(
        'INSERT INTO employees
            (name, phone, designation, salary, salary_type, employee_code, joining_date, status, service_share_percent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('sssdssssd', $name, $phone, $designation, $salary, $salaryType, $code, $joining, $status, $share);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $id = (int)$db->insert_id;
    $stmt->close();
    if ($id > 0) {
        @$db->query('UPDATE employees SET is_engineer = 1, user_role = \'engineer\', name_snapshot = name WHERE id = ' . $id);
    }
    return $id;
}

function ame_engineer_bill_bind_employee(mysqli $db, int $engineerId): int
{
    if (!ame_engineer_bill_is_open($db, $engineerId)) {
        return 0;
    }
    $stmt = @$db->prepare('SELECT id, name, phone, status, employee_id FROM engineers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $engineerId);
    $stmt->execute();
    $eng = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$eng) {
        return 0;
    }
    $employeeId = (int)($eng['employee_id'] ?? 0);
    if (ame_engineer_bill_employee_exists($db, $employeeId)) {
        return $employeeId;
    }
    $name = trim((string)($eng['name'] ?? ''));
    $found = ame_engineer_bill_free_employee($db, $name, $engineerId);
    if ($found > 0) {
        ame_engineer_bill_link($db, $engineerId, $found);
        return $found;
    }
    if ($name === '') {
        return 0;
    }
    $status = ((string)($eng['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
    $created = ame_engineer_bill_make_employee($db, $name, (string)($eng['phone'] ?? ''), $status);
    if ($created <= 0) {
        return 0;
    }
    ame_engineer_bill_link($db, $engineerId, $created);
    return $created;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_engineer_bill_expand_roster(mysqli $db, array $rows): array
{
    if (!ame_engineer_bill_table($db, 'engineers')) {
        return $rows;
    }
    $seen = [];
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $seen[$id] = true;
        }
    }
    foreach ($rows as &$row) {
        if ((int)($row['id'] ?? 0) > 0) {
            continue;
        }
        $empId = (int)($row['employee_id'] ?? 0);
        if ($empId <= 0) {
            continue;
        }
        $engId = ame_engineer_bill_engineer_for_employee($db, $empId, (string)($row['name'] ?? ''));
        if ($engId > 0 && !isset($seen[$engId])) {
            $row['id'] = $engId;
            $seen[$engId] = true;
        }
    }
    unset($row);

    $where = '1=1';
    $purged = @$db->query("SHOW COLUMNS FROM engineers LIKE 'purged_at'");
    if ($purged && $purged->fetch_assoc()) {
        $where .= " AND (purged_at IS NULL OR purged_at = '')";
    }
    $list = @$db->query('SELECT id, name, employee_id FROM engineers WHERE ' . $where . ' ORDER BY name ASC, id ASC');
    if (!$list) {
        return $rows;
    }
    while ($eng = $list->fetch_assoc()) {
        $engId = (int)($eng['id'] ?? 0);
        if ($engId <= 0 || isset($seen[$engId])) {
            continue;
        }
        $empId = (int)($eng['employee_id'] ?? 0);
        $present = false;
        foreach ($rows as $row) {
            if ($empId > 0 && (int)($row['employee_id'] ?? 0) === $empId && !empty($row['present'])) {
                $present = true;
                break;
            }
        }
        $rows[] = [
            'id' => $engId,
            'name' => (string)($eng['name'] ?? ''),
            'emp_code' => '',
            'employee_id' => $empId,
            'present' => $present,
            'activated_at' => '',
        ];
        $seen[$engId] = true;
    }
    $list->free();
    return $rows;
}

function ame_engineer_bill_engineer_for_employee(mysqli $db, int $employeeId, string $name): int
{
    if ($employeeId <= 0 || !ame_engineer_bill_table($db, 'engineers')) {
        return 0;
    }
    $where = 'employee_id = ?';
    $purged = @$db->query("SHOW COLUMNS FROM engineers LIKE 'purged_at'");
    if ($purged && $purged->fetch_assoc()) {
        $where .= " AND (purged_at IS NULL OR purged_at = '')";
    }
    $stmt = @$db->prepare('SELECT id, name FROM engineers WHERE ' . $where . ' ORDER BY id ASC');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $found[] = $row;
        }
    }
    $stmt->close();
    $pick = ame_engineer_bill_pick_id($found, $name);
    if ($pick > 0 || $found !== []) {
        return $pick;
    }
    $label = trim($name);
    if ($label === '') {
        $label = 'ইঞ্জিনিয়ার #' . $employeeId;
    }
    $ins = @$db->prepare("INSERT INTO engineers (name, status, employee_id) VALUES (?, 'active', ?)");
    if (!$ins) {
        return 0;
    }
    $ins->bind_param('si', $label, $employeeId);
    if (!$ins->execute()) {
        $ins->close();
        return 0;
    }
    $id = (int)$db->insert_id;
    $ins->close();
    return $id;
}

function ame_engineer_bill_stamp(mysqli $db, int $engineerId, int $serviceId): void
{
    if ($engineerId <= 0 || $serviceId <= 0 || !ame_engineer_bill_table($db, 'sale_services')) {
        return;
    }
    $col = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
    if (!$col || !$col->fetch_assoc()) {
        return;
    }
    $name = '';
    $ns = @$db->prepare('SELECT name FROM engineers WHERE id = ? LIMIT 1');
    if ($ns) {
        $ns->bind_param('i', $engineerId);
        $ns->execute();
        $name = trim((string)($ns->get_result()->fetch_assoc()['name'] ?? ''));
        $ns->close();
    }
    if ($name === '') {
        return;
    }
    $up = @$db->prepare("UPDATE sale_services SET engineer_name = ? WHERE id = ? AND (engineer_name IS NULL OR engineer_name = '')");
    if (!$up) {
        return;
    }
    $up->bind_param('si', $name, $serviceId);
    $up->execute();
    $up->close();
}

function ame_engineer_bill_align(mysqli $db, int $commissionId, int $engineerId, int $employeeId): void
{
    if ($commissionId <= 0 || $engineerId <= 0 || $employeeId <= 0) {
        return;
    }
    $up = @$db->prepare(
        "UPDATE service_commissions
         SET employee_id = ?, engineer_id = ?
         WHERE id = ? AND (status IS NULL OR status = '' OR status = 'pending')"
    );
    if ($up) {
        $up->bind_param('iii', $employeeId, $engineerId, $commissionId);
        $up->execute();
        $up->close();
    }
    if (!ame_engineer_bill_table($db, 'worker_earnings')) {
        return;
    }
    $earn = @$db->prepare(
        "UPDATE worker_earnings
         SET employee_id = ?, engineer_id = ?
         WHERE source_type = 'sale_service' AND source_id = ?
           AND pay_status = 'unpaid' AND paid_amount < 0.01"
    );
    if ($earn) {
        $earn->bind_param('iii', $employeeId, $engineerId, $commissionId);
        $earn->execute();
        $earn->close();
    }
}

function ame_engineer_bill_record(
    $db,
    $sale_id,
    $engineer_id,
    $service_amount,
    $service_type = '',
    $source_type = 'sale_service',
    $source_id = 0
): void {
    if (!($db instanceof mysqli)) {
        return;
    }
    $engineer_id = (int)$engineer_id;
    $service_amount = round((float)$service_amount, 2);
    $sale_id = (int)$sale_id;
    $source_id = (int)$source_id;
    if ($engineer_id <= 0 || $service_amount <= 0) {
        return;
    }
    try {
        if (function_exists('ensure_hrm_schema')) {
            ensure_hrm_schema($db);
        }
        $employee_id = ame_engineer_bill_bind_employee($db, $engineer_id);
        if ($employee_id <= 0 || !ame_engineer_bill_table($db, 'service_commissions')) {
            return;
        }
        if ($source_type === 'sale_service' && $source_id > 0) {
            ame_engineer_bill_stamp($db, $engineer_id, $source_id);
        }
        $source_type = substr(trim((string)$source_type), 0, 30);
        if ($source_type === '') {
            $source_type = 'sale_service';
        }
        if ($source_id > 0) {
            $exists = @$db->prepare('SELECT id, employee_id, engineer_id FROM service_commissions WHERE source_type = ? AND source_id = ? LIMIT 1');
            if ($exists) {
                $exists->bind_param('si', $source_type, $source_id);
                $exists->execute();
                $dup = $exists->get_result()->fetch_assoc();
                $exists->close();
                if ($dup) {
                    ame_engineer_bill_align($db, (int)$dup['id'], $engineer_id, $employee_id);
                    return;
                }
            }
        }

        $workDate = date('Y-m-d');
        if ($source_type === 'sale_service' && $sale_id > 0 && ame_engineer_bill_table($db, 'sales')) {
            $dateStmt = @$db->prepare('SELECT DATE(created_at) AS work_date, invoice_number FROM sales WHERE id = ? LIMIT 1');
            if ($dateStmt) {
                $dateStmt->bind_param('i', $sale_id);
                $dateStmt->execute();
                $saleRow = $dateStmt->get_result()->fetch_assoc() ?: [];
                $dateStmt->close();
                if (!empty($saleRow['work_date'])) {
                    $workDate = (string)$saleRow['work_date'];
                }
            }
        } elseif ($source_type === 'workshop_job' && $source_id > 0 && ame_engineer_bill_table($db, 'workshop_jobs')) {
            $dateStmt = @$db->prepare('SELECT DATE(COALESCE(delivered_at, created_at)) AS work_date FROM workshop_jobs WHERE id = ? LIMIT 1');
            if ($dateStmt) {
                $dateStmt->bind_param('i', $source_id);
                $dateStmt->execute();
                $workDate = (string)($dateStmt->get_result()->fetch_assoc()['work_date'] ?? $workDate);
                $dateStmt->close();
            }
        }

        $payMode = 'percentage';
        $quote = null;
        if (function_exists('ame_earnings_quote')) {
            $quote = ame_earnings_quote($db, $employee_id, $service_amount, 1.0, 1.0, $workDate);
            $payMode = strtolower((string)($quote['payment_type'] ?? 'percentage'));
        }
        if (in_array($payMode, ['daily', 'monthly'], true)) {
            $rate = 0.0;
            $commission_amount = 0.0;
            $shop_amount = $service_amount;
        } elseif (is_array($quote)) {
            $rate = ($quote['payment_type'] ?? '') === 'percentage' ? (float)$quote['percent_rate'] : 0.0;
            $commission_amount = (float)$quote['earning'];
            $shop_amount = (float)$quote['company'];
        } else {
            $rate = function_exists('ame_employee_share_percent') ? ame_employee_share_percent($db, $employee_id) : 50.0;
            $commission_amount = round($service_amount * ($rate / 100), 2);
            $shop_amount = round($service_amount - $commission_amount, 2);
        }
        $stype = substr(trim((string)$service_type), 0, 255);
        $insert = @$db->prepare(
            "INSERT INTO service_commissions
                (employee_id, sale_id, service_amount, commission_rate, commission_amount, shop_amount, engineer_id, service_type, source_type, source_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), 'pending')"
        );
        if (!$insert) {
            return;
        }
        $insert->bind_param(
            'iiddddissi',
            $employee_id,
            $sale_id,
            $service_amount,
            $rate,
            $commission_amount,
            $shop_amount,
            $engineer_id,
            $stype,
            $source_type,
            $source_id
        );
        $insert->execute();
        $commissionId = (int)$db->insert_id;
        $insert->close();
        if ($commissionId > 0 && function_exists('ame_earnings_lock_from_commission')) {
            ame_earnings_lock_from_commission($db, $commissionId);
        }
    } catch (Throwable $e) {
        error_log('Engineer bill record failed: ' . $e->getMessage());
    }
}

function ame_engineer_bill_sync(mysqli $db): int
{
    if (!ame_engineer_bill_table($db, 'sale_services') || !ame_engineer_bill_table($db, 'service_commissions')) {
        return 0;
    }
    $n = 0;
    $missing = @$db->query(
        "SELECT ss.id, ss.sale_id, ss.engineer_id, ss.amount, ss.service_type
         FROM sale_services ss
         LEFT JOIN service_commissions sc
           ON sc.source_type = 'sale_service' AND sc.source_id = ss.id
         WHERE ss.engineer_id > 0 AND ss.amount > 0 AND sc.id IS NULL
         ORDER BY ss.id ASC
         LIMIT 100"
    );
    if ($missing) {
        while ($row = $missing->fetch_assoc()) {
            ame_engineer_bill_record(
                $db,
                (int)$row['sale_id'],
                (int)$row['engineer_id'],
                (float)$row['amount'],
                (string)($row['service_type'] ?? ''),
                'sale_service',
                (int)$row['id']
            );
            $n++;
        }
        $missing->free();
    }
    if (ame_engineer_bill_table($db, 'workshop_jobs')) {
        $jobs = @$db->query(
            "SELECT id, engineer_id, labor_charge, item_name
             FROM workshop_jobs
             WHERE status = 'delivered' AND engineer_id > 0 AND labor_charge > 0
               AND NOT EXISTS (
                   SELECT 1 FROM service_commissions sc
                   WHERE sc.source_type = 'workshop_job' AND sc.source_id = workshop_jobs.id
               )
             ORDER BY id ASC
             LIMIT 100"
        );
        if ($jobs) {
            while ($row = $jobs->fetch_assoc()) {
                ame_engineer_bill_record(
                    $db,
                    0,
                    (int)$row['engineer_id'],
                    (float)$row['labor_charge'],
                    'মেরামত · ' . (string)($row['item_name'] ?? ''),
                    'workshop_job',
                    (int)$row['id']
                );
                $n++;
            }
            $jobs->free();
        }
    }
    return $n;
}

function ame_engineer_bill_boot(mysqli $db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    try {
        ame_engineer_bill_sync($db);
    } catch (Throwable $e) {
        error_log('Engineer bill sync skipped: ' . $e->getMessage());
    }
}
