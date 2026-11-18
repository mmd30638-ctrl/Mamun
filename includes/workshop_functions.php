<?php
/**
 * ওয়ার্কশপ: জব কার্ড, কোটেশন, যন্ত্রাংশ রিজার্ভ, সিরিয়াল খাতা,
 * স্টোর ট্রান্সফার ও ফিজিক্যাল স্টক কাউন্ট।
 */

function ame_workshop_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ensure_workshop_schema($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_no VARCHAR(24) NOT NULL,
            customer_id INT NULL,
            customer_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(30) NULL,
            item_name VARCHAR(200) NOT NULL DEFAULT '',
            serial_no VARCHAR(80) NULL,
            complaint TEXT NULL,
            diagnosis TEXT NULL,
            engineer_id INT NULL,
            store_id INT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'received',
            labor_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
            parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            quote_id INT NULL,
            notes TEXT NULL,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            delivered_at DATETIME NULL,
            created_by INT NULL,
            machine_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_job_no (job_no),
            INDEX idx_job_status (status),
            INDEX idx_job_customer (customer_id),
            INDEX idx_job_serial (serial_no),
            INDEX idx_job_store (store_id),
            INDEX idx_job_machine (machine_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_job_parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            product_id INT NULL,
            product_name VARCHAR(200) NOT NULL DEFAULT '',
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            `reserved` TINYINT(1) NOT NULL DEFAULT 1,
            issued TINYINT(1) NOT NULL DEFAULT 0,
            line_kind VARCHAR(20) NOT NULL DEFAULT 'repair',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_part_job (job_id),
            INDEX idx_part_product (product_id),
            INDEX idx_part_reserve (product_id, `reserved`, issued)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_machines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            machine_type VARCHAR(40) NOT NULL DEFAULT 'other',
            brand VARCHAR(120) NOT NULL DEFAULT '',
            model VARCHAR(160) NOT NULL DEFAULT '',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wsm_type (machine_type),
            INDEX idx_wsm_model (brand, model)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_machine_parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            machine_id INT NOT NULL,
            product_id INT NOT NULL,
            oem_code VARCHAR(80) DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wsm_part (machine_id, product_id),
            INDEX idx_wsmp_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ame_workshop_ensure_column($db, 'workshop_jobs', 'machine_id', 'INT NULL DEFAULT NULL');
    ame_workshop_ensure_column($db, 'workshop_jobs', 'warranty_months', 'INT NOT NULL DEFAULT 0');
    ame_workshop_ensure_column($db, 'workshop_job_parts', 'line_kind', "VARCHAR(20) NOT NULL DEFAULT 'repair'");

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_quotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quote_no VARCHAR(24) NOT NULL,
            customer_id INT NULL,
            customer_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(30) NULL,
            item_name VARCHAR(200) NOT NULL DEFAULT '',
            machine_id INT NULL,
            notes TEXT NULL,
            labor_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
            parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            job_id INT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_quote_no (quote_no),
            INDEX idx_quote_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ame_workshop_ensure_column($db, 'workshop_quotes', 'machine_id', 'INT NULL DEFAULT NULL');

    $db->query(
        "CREATE TABLE IF NOT EXISTS workshop_quote_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quote_id INT NOT NULL,
            product_id INT NULL,
            product_name VARCHAR(200) NOT NULL DEFAULT '',
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            INDEX idx_qitem_quote (quote_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS product_serials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NULL,
            serial_no VARCHAR(80) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'in_stock',
            customer_id INT NULL,
            sale_id INT NULL,
            job_id INT NULL,
            warranty_months INT NOT NULL DEFAULT 0,
            warranty_expiry DATE NULL,
            notes VARCHAR(255) NULL,
            store_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_serial_no (serial_no),
            INDEX idx_serial_product (product_id),
            INDEX idx_serial_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS store_transfers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_no VARCHAR(24) NOT NULL,
            from_store_id INT NOT NULL,
            to_store_id INT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_transfer_no (transfer_no),
            INDEX idx_tr_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS store_transfer_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT NOT NULL,
            product_id INT NOT NULL,
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            INDEX idx_tr_item (transfer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS stock_counts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            count_no VARCHAR(24) NOT NULL,
            store_id INT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_count_no (count_no),
            INDEX idx_count_store (store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS stock_count_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            count_id INT NOT NULL,
            product_id INT NOT NULL,
            system_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            counted_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            variance DECIMAL(12,3) NOT NULL DEFAULT 0,
            INDEX idx_count_item (count_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ame_workshop_ensure_column($db, string $table, string $column, string $ddl): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safe_table === '' || $safe_column === '') {
        return;
    }
    $res = @$db->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    if ($res && $res->num_rows === 0) {
        @$db->query("ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$ddl}");
    }
}

function ame_workshop_next_no($db, string $table, string $prefix, string $column = ''): string
{
    $allowed = [
        'workshop_jobs' => 'job_no',
        'workshop_quotes' => 'quote_no',
        'store_transfers' => 'transfer_no',
        'stock_counts' => 'count_no',
    ];
    if (!isset($allowed[$table])) {
        throw new InvalidArgumentException('Invalid workshop number table');
    }
    $col = $column !== '' ? $column : $allowed[$table];
    $res = @$db->query("SELECT MAX(id) AS m FROM `{$table}`");
    $next = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $next = (int)$row['m'] + 1;
    }
    do {
        $code = $prefix . '-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT id FROM `{$table}` WHERE `{$col}` = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $next++;
    } while ($exists);

    return $code;
}

function ame_workshop_statuses(): array
{
    return [
        'received' => 'গ্রহণ',
        'diagnosing' => 'ডায়াগনোসিস',
        'in_progress' => 'কাজ চলছে',
        'waiting_parts' => 'যন্ত্রাংশ অপেক্ষা',
        'ready' => 'রেডি / ডেলিভারির অপেক্ষা',
        'delivered' => 'ডেলিভারি হয়েছে',
        'cancelled' => 'বাতিল',
    ];
}

function ame_workshop_status_label(string $status): string
{
    $map = ame_workshop_statuses();
    return $map[$status] ?? $status;
}

function ame_workshop_machine_types(): array
{
    return [
        'pump' => 'পাম্প',
        'motor' => 'মোটর',
        'engine' => 'ইঞ্জিন',
        'other' => 'অন্য মেশিন',
    ];
}

function ame_workshop_machine_label(array $row): string
{
    $types = ame_workshop_machine_types();
    $type = $types[(string)($row['machine_type'] ?? '')] ?? 'মেশিন';
    $brand = trim((string)($row['brand'] ?? ''));
    $model = trim((string)($row['model'] ?? ''));
    $bits = array_filter([$type, $brand, $model], static function ($v) {
        return $v !== '';
    });
    return $bits !== [] ? implode(' ', $bits) : $type;
}

function ame_workshop_line_kind_label(string $kind): string
{
    return $kind === 'sold' ? 'সাথে কেনা' : 'মেরামত';
}

function ame_workshop_machines($db): array
{
    $res = @$db->query(
        'SELECT * FROM workshop_machines ORDER BY machine_type ASC, brand ASC, model ASC'
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_workshop_machine($db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM workshop_machines WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_workshop_machine_parts($db, int $machine_id): array
{
    if ($machine_id <= 0) {
        return [];
    }
    $stmt = $db->prepare(
        "SELECT mp.*, p.name AS product_name, p.sale_price, p.sku,
                p.oem_code AS product_oem, p.supplier_code
         FROM workshop_machine_parts mp
         INNER JOIN products p ON p.id = mp.product_id
         WHERE mp.machine_id = ?
         ORDER BY p.name ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $machine_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function ame_workshop_catalog_map($db): array
{
    $map = [];
    foreach (ame_workshop_machines($db) as $machine) {
        $mid = (int)$machine['id'];
        $parts = [];
        foreach (ame_workshop_machine_parts($db, $mid) as $part) {
            $oem = trim((string)($part['oem_code'] ?: ($part['product_oem'] ?? '')));
            $parts[] = [
                'id' => (int)$part['product_id'],
                'name' => (string)$part['product_name'],
                'price' => (float)$part['sale_price'],
                'oem' => $oem,
                'supplier' => (string)($part['supplier_code'] ?? ''),
            ];
        }
        $map[$mid] = [
            'label' => ame_workshop_machine_label($machine),
            'parts' => $parts,
        ];
    }
    return $map;
}

function ame_workshop_change_stock($db, int $product_id, float $qty, int $store_id, bool $deduct): void
{
    if ($product_id <= 0 || $qty <= 0) {
        return;
    }
    $delta = $deduct ? -$qty : $qty;
    if ($store_id > 0 && function_exists('ame_store_stock_change')) {
        if (!ame_store_stock_change($db, $store_id, $product_id, $delta, true)) {
            throw new RuntimeException('স্টক বদলানো যায়নি।');
        }
        return;
    }
    if ($deduct) {
        $upd = $db->prepare(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
        );
        $upd->bind_param('did', $qty, $product_id, $qty);
        $upd->execute();
        if ($upd->affected_rows !== 1) {
            $upd->close();
            throw new RuntimeException('স্টক কাটা যায়নি।');
        }
        $upd->close();
        return;
    }
    $upd = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $upd->bind_param('di', $qty, $product_id);
    $upd->execute();
    $upd->close();
}

function ame_workshop_reserved_qty($db, int $product_id, int $store_id = 0): float
{
    if ($product_id <= 0 || !($db instanceof mysqli)) {
        return 0.0;
    }
    $table = @$db->query("SHOW TABLES LIKE 'workshop_job_parts'");
    if (!$table || $table->num_rows === 0) {
        return 0.0;
    }

    $sql = "SELECT COALESCE(SUM(p.qty), 0) AS q
            FROM workshop_job_parts p
            INNER JOIN workshop_jobs j ON j.id = p.job_id
            WHERE p.product_id = ?
              AND p.`reserved` = 1
              AND p.issued = 0
              AND j.status NOT IN ('delivered', 'cancelled')";
    if ($store_id > 0) {
        $sql .= ' AND (j.store_id = ? OR j.store_id IS NULL OR j.store_id = 0)';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $product_id, $store_id);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $product_id);
    }
    if (!$stmt) {
        return 0.0;
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['q'] ?? 0);
}

function ame_workshop_reserved_map($db, int $store_id = 0): array
{
    $table = @$db->query("SHOW TABLES LIKE 'workshop_job_parts'");
    if (!$table || $table->num_rows === 0) {
        return [];
    }
    $sql = "SELECT p.product_id, COALESCE(SUM(p.qty), 0) AS q
            FROM workshop_job_parts p
            INNER JOIN workshop_jobs j ON j.id = p.job_id
            WHERE p.`reserved` = 1
              AND p.issued = 0
              AND p.product_id > 0
              AND j.status NOT IN ('delivered', 'cancelled')";
    if ($store_id > 0) {
        $sql .= ' AND (j.store_id = ? OR j.store_id IS NULL OR j.store_id = 0)';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $store_id);
    } else {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
    }
    $stmt->execute();
    $map = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $map[(int)$row['product_id']] = (float)$row['q'];
    }
    $stmt->close();
    return $map;
}

function ame_workshop_parts_needed($db, int $store_id = 0): array
{
    $table = @$db->query("SHOW TABLES LIKE 'workshop_job_parts'");
    if (!$table || $table->num_rows === 0) {
        return [];
    }
    $sql = "SELECT p.product_id, p.product_name,
                   COALESCE(SUM(p.qty), 0) AS need_qty,
                   GROUP_CONCAT(DISTINCT j.job_no ORDER BY j.id SEPARATOR ', ') AS job_nos
            FROM workshop_job_parts p
            INNER JOIN workshop_jobs j ON j.id = p.job_id
            WHERE p.`reserved` = 1
              AND p.issued = 0
              AND p.product_id > 0
              AND j.status NOT IN ('delivered', 'cancelled')";
    if ($store_id > 0) {
        $sql .= ' AND (j.store_id = ? OR j.store_id IS NULL OR j.store_id = 0)';
    }
    $sql .= ' GROUP BY p.product_id, p.product_name ORDER BY p.product_name ASC';
    if ($store_id > 0) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $res = @$db->query($sql);
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $out = [];
    foreach ($rows as $row) {
        $pid = (int)$row['product_id'];
        $need = (float)$row['need_qty'];
        $on_hand = ame_workshop_on_hand($db, $pid, $store_id);
        $short = max(0, round($need - $on_hand, 3));
        $out[] = [
            'product_id' => $pid,
            'product_name' => (string)$row['product_name'],
            'need_qty' => $need,
            'on_hand' => $on_hand,
            'short_qty' => $short,
            'job_nos' => (string)($row['job_nos'] ?? ''),
        ];
    }
    usort($out, static function ($a, $b) {
        if ($a['short_qty'] === $b['short_qty']) {
            return 0;
        }
        return $a['short_qty'] > $b['short_qty'] ? -1 : 1;
    });
    return $out;
}

function ame_workshop_due_jobs($db, int $limit = 80): array
{
    $limit = max(10, min(200, $limit));
    $res = @$db->query(
        "SELECT * FROM workshop_jobs
         WHERE due_amount > 0.001 AND status <> 'cancelled'
         ORDER BY due_amount DESC, id DESC
         LIMIT {$limit}"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_workshop_customer_jobs($db, int $customer_id, string $phone = '', int $except_id = 0, int $limit = 8): array
{
    $limit = max(3, min(20, $limit));
    $phone = trim($phone);
    $where = [];
    $types = '';
    $params = [];
    if ($customer_id > 0) {
        $where[] = 'customer_id = ?';
        $types .= 'i';
        $params[] = $customer_id;
    }
    if ($phone !== '') {
        $where[] = 'phone = ?';
        $types .= 's';
        $params[] = $phone;
    }
    if ($where === []) {
        return [];
    }
    $sql = 'SELECT id, job_no, item_name, status, grand_total, due_amount, received_at
            FROM workshop_jobs
            WHERE (' . implode(' OR ', $where) . ')';
    if ($except_id > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $except_id;
    }
    $sql .= " ORDER BY id DESC LIMIT {$limit}";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function ame_workshop_on_hand($db, int $product_id, int $store_id = 0): float
{
    if ($store_id <= 0 && function_exists('current_store_id')) {
        $store_id = (int)current_store_id();
    }
    if ($store_id > 0 && function_exists('ame_store_stock_qty')) {
        return (float)ame_store_stock_qty($db, $store_id, $product_id);
    }
    $stmt = $db->prepare('SELECT COALESCE(stock, 0) AS q FROM products WHERE id = ?');
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['q'] ?? 0);
}

function ame_workshop_available_qty($db, int $product_id, int $store_id = 0): float
{
    $on_hand = ame_workshop_on_hand($db, $product_id, $store_id);
    $reserved = ame_workshop_reserved_qty($db, $product_id, $store_id);
    return max(0, round($on_hand - $reserved, 3));
}

function ame_workshop_recalc_job($db, int $job_id): void
{
    $sum = $db->prepare(
        'SELECT COALESCE(SUM(line_total), 0) AS t FROM workshop_job_parts WHERE job_id = ?'
    );
    $sum->bind_param('i', $job_id);
    $sum->execute();
    $parts_total = (float)($sum->get_result()->fetch_assoc()['t'] ?? 0);
    $sum->close();

    $job = $db->prepare(
        'SELECT labor_charge, discount, paid_amount FROM workshop_jobs WHERE id = ?'
    );
    $job->bind_param('i', $job_id);
    $job->execute();
    $row = $job->get_result()->fetch_assoc();
    $job->close();
    if (!$row) {
        return;
    }

    $labor = (float)$row['labor_charge'];
    $discount = (float)$row['discount'];
    $paid = (float)$row['paid_amount'];
    $grand = round($labor + $parts_total - $discount, 2);
    if ($grand < 0) {
        $grand = 0;
    }
    $due = max(0, round($grand - $paid, 2));

    $upd = $db->prepare(
        'UPDATE workshop_jobs SET parts_total = ?, grand_total = ?, due_amount = ? WHERE id = ?'
    );
    $upd->bind_param('dddi', $parts_total, $grand, $due, $job_id);
    $upd->execute();
    $upd->close();
}

function ame_workshop_job($db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM workshop_jobs WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_workshop_job_parts($db, int $job_id): array
{
    $stmt = $db->prepare(
        'SELECT * FROM workshop_job_parts WHERE job_id = ? ORDER BY id ASC'
    );
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function ame_workshop_issue_parts($db, array $job): void
{
    $job_id = (int)$job['id'];
    $store_id = (int)($job['store_id'] ?? 0);
    if ($store_id <= 0 && function_exists('current_store_id')) {
        $store_id = (int)current_store_id();
    }
    $parts = ame_workshop_job_parts($db, $job_id);
    foreach ($parts as $part) {
        if ((int)$part['issued'] === 1) {
            continue;
        }
        $product_id = (int)$part['product_id'];
        $qty = (float)$part['qty'];
        if ($product_id > 0 && $qty > 0 && $store_id > 0 && function_exists('ame_store_stock_change')) {
            if (!ame_store_stock_change($db, $store_id, $product_id, -$qty, true)) {
                throw new RuntimeException('স্টক কাটা যায়নি: ' . $part['product_name']);
            }
        } elseif ($product_id > 0 && $qty > 0) {
            $upd = $db->prepare(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );
            $upd->bind_param('did', $qty, $product_id, $qty);
            $upd->execute();
            if ($upd->affected_rows !== 1) {
                $upd->close();
                throw new RuntimeException('স্টক কাটা যায়নি: ' . $part['product_name']);
            }
            $upd->close();
        }
        $pid = (int)$part['id'];
        $mark = $db->prepare(
            'UPDATE workshop_job_parts SET issued = 1, `reserved` = 0 WHERE id = ?'
        );
        $mark->bind_param('i', $pid);
        $mark->execute();
        $mark->close();
    }
}

function ame_workshop_touch_serial($db, string $serial, array $fields): void
{
    $serial = trim($serial);
    if ($serial === '') {
        return;
    }
    $find = $db->prepare('SELECT id FROM product_serials WHERE serial_no = ?');
    $find->bind_param('s', $serial);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();
    $find->close();

    $prod = max(0, (int)($fields['product_id'] ?? 0));
    $status = (string)($fields['status'] ?? 'on_job');
    $cid = (int)($fields['customer_id'] ?? 0);
    $jid = (int)($fields['job_id'] ?? 0);
    $sid = (int)($fields['sale_id'] ?? 0);
    $months = (int)($fields['warranty_months'] ?? 0);
    $expiry = (string)($fields['warranty_expiry'] ?? '');
    $notes = (string)($fields['notes'] ?? '');
    $stid = (int)($fields['store_id'] ?? 0);

    if ($existing) {
        $id = (int)$existing['id'];
        $prod_i = (int)($prod ?? 0);
        $cid_i = (int)($cid ?? 0);
        $jid_i = (int)($jid ?? 0);
        $sid_i = (int)($sid ?? 0);
        $stid_i = (int)($stid ?? 0);
        $expiry_s = $expiry ? (string)$expiry : '';
        $stmt = $db->prepare(
            'UPDATE product_serials
             SET product_id = IF(? > 0, ?, product_id),
                 status = ?,
                 customer_id = IF(? > 0, ?, customer_id),
                 job_id = IF(? > 0, ?, job_id),
                 sale_id = IF(? > 0, ?, sale_id),
                 warranty_months = IF(? > 0, ?, warranty_months),
                 warranty_expiry = IF(? = "", warranty_expiry, ?),
                 notes = IF(? = "", notes, ?),
                 store_id = IF(? > 0, ?, store_id)
             WHERE id = ?'
        );
        $stmt->bind_param(
            'iisiiiiiiiissssiii',
            $prod_i,
            $prod_i,
            $status,
            $cid_i,
            $cid_i,
            $jid_i,
            $jid_i,
            $sid_i,
            $sid_i,
            $months,
            $months,
            $expiry_s,
            $expiry_s,
            $notes,
            $notes,
            $stid_i,
            $stid_i,
            $id
        );
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO product_serials
            (product_id, serial_no, status, customer_id, sale_id, job_id, warranty_months, warranty_expiry, notes, store_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issiiiissi',
        $prod,
        $serial,
        $status,
        $cid,
        $sid,
        $jid,
        $months,
        $expiry,
        $notes,
        $stid
    );
    $stmt->execute();
    $stmt->close();
}

function ame_workshop_notify($phone, string $message, string $purpose = 'workshop'): void
{
    if (trim((string)$phone) === '' || $message === '') {
        return;
    }
    $sms_file = __DIR__ . '/sms_functions.php';
    if (!is_file($sms_file)) {
        return;
    }
    require_once $sms_file;
    if (function_exists('send_sms')) {
        try {
            send_sms($phone, $message, $purpose);
        } catch (Throwable $e) {
            error_log('workshop SMS: ' . $e->getMessage());
        }
    }
}

function ame_workshop_products($db, int $limit = 400): array
{
    $limit = max(50, min(800, $limit));
    if (!function_exists('ame_product_option_sql')) {
        require_once __DIR__ . '/helpers.php';
    }
    $on = ame_product_option_sql('status');
    $res = @$db->query(
        "SELECT id, name, sale_price, COALESCE(stock, 0) AS stock,
                sku, oem_code, supplier_code
         FROM products
         WHERE {$on}
         ORDER BY name ASC
         LIMIT {$limit}"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_workshop_customers($db, int $limit = 300): array
{
    $limit = max(50, min(500, $limit));
    $res = @$db->query(
        "SELECT id, customer_code, name, phone, receivable_balance, credit_limit
         FROM customers
         WHERE (is_customer = 1 OR is_customer IS NULL) AND " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '(status = 1 OR status IS NULL)') . "
         ORDER BY name ASC
         LIMIT {$limit}"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_workshop_engineers($db): array
{
    if (is_file(__DIR__ . '/staff_duty.php')) {
        require_once __DIR__ . '/attendance_functions.php';
        require_once __DIR__ . '/staff_duty.php';
        if (function_exists('ame_staff_duty_roster')) {
            $roster = ame_staff_duty_roster($db);
            if ($roster) {
                return array_map(static function ($row) {
                    return [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'employee_id' => (int)($row['employee_id'] ?? 0),
                        'present' => !empty($row['present']),
                        'activated_at' => (string)($row['activated_at'] ?? ''),
                    ];
                }, $roster);
            }
        }
    }
    $table = @$db->query("SHOW TABLES LIKE 'engineers'");
    if (!$table || $table->num_rows === 0) {
        return [];
    }
    $res = @$db->query(
        "SELECT id, name FROM engineers WHERE purged_at IS NULL AND (status = 'active' OR status IS NULL OR status = '') ORDER BY name ASC"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_workshop_duty_widget(array $engineers): string
{
    $en = function_exists('current_lang') && current_lang() === 'en';
    $msg = $en
        ? 'To call a worker or service engineer, tap the green duty icon in the header and make them Active.'
        : 'কর্মী বা সার্ভিস ইঞ্জিনিয়ার ডাকলে উপরের হেডারের 🟢 আইকন থেকে Active করুন।';
    return '<p class="ws-hint" id="wsDutyHint">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
}

function ame_workshop_accounts($db): array
{
    $res = @$db->query(
        "SELECT id, account_name, balance FROM accounts WHERE status = 'active' ORDER BY account_name ASC"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
