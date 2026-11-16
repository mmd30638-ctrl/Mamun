<?php
/**
 * Partner Accounting v2 — current-month-forward, product-scoped accounting.
 *
 * Legacy partner tables are intentionally never deleted or rewritten. They
 * remain an archive and are excluded from this ledger by the immutable cutoff.
 */

function pav2_ensure_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_version_ready') && ame_schema_version_ready($db)) {
        $done = true;
        return;
    }
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $queries = [
        "CREATE TABLE IF NOT EXISTS partner_v2_settings (
            id TINYINT NOT NULL PRIMARY KEY,
            cutoff_date DATE NOT NULL,
            initialized_by INT NULL,
            initialized_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes VARCHAR(255) NULL
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_product_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL,
            partner_one_id INT NOT NULL,
            partner_one_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
            partner_two_id INT NOT NULL,
            partner_two_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
            terms VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_pv2_rule_start (product_id, effective_from),
            INDEX idx_pv2_rule_effective (product_id, effective_from, effective_to)
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_category_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL,
            partner_one_id INT NOT NULL,
            partner_one_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
            partner_two_id INT NOT NULL,
            partner_two_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_pv2_cat_rule_start (category_id, effective_from),
            INDEX idx_pv2_cat_rule_effective (category_id, effective_from, effective_to)
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_direct_costs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            product_id INT NOT NULL,
            cost_date DATE NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            description VARCHAR(255) NULL,
            reference VARCHAR(90) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'approved',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pv2_direct_ref (reference),
            INDEX idx_pv2_direct_sale (sale_id, product_id)
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expense_date DATE NOT NULL,
            voucher_no VARCHAR(80) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description VARCHAR(500) NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
            payer_type VARCHAR(20) NOT NULL DEFAULT 'business',
            payer_partner_id INT NULL,
            account_id INT NULL,
            receipt_path VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'approved',
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            voided_by INT NULL,
            voided_at DATETIME NULL,
            void_reason VARCHAR(255) NULL,
            UNIQUE KEY uniq_pv2_expense_voucher (voucher_no),
            INDEX idx_pv2_expense_date (expense_date),
            INDEX idx_pv2_expense_partner (payer_partner_id),
            INDEX idx_pv2_expense_account (account_id)
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            movement_date DATE NOT NULL,
            movement_type VARCHAR(40) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
            account_id INT NULL,
            reference VARCHAR(90) NOT NULL,
            note VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'posted',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reversed_by INT NULL,
            reversed_at DATETIME NULL,
            reversal_reason VARCHAR(255) NULL,
            UNIQUE KEY uniq_pv2_movement_ref (reference),
            INDEX idx_pv2_move_partner_date (partner_id, movement_date)
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_months (
            period_ym CHAR(7) NOT NULL PRIMARY KEY,
            period_from DATE NOT NULL,
            period_to DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            snapshot_json LONGTEXT NULL,
            snapshot_hash CHAR(64) NULL,
            closed_by INT NULL,
            closed_at DATETIME NULL,
            reopened_by INT NULL,
            reopened_at DATETIME NULL,
            close_note VARCHAR(255) NULL
        ) {$e}",
        "CREATE TABLE IF NOT EXISTS partner_v2_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(70) NOT NULL,
            actor_id INT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(80) NULL,
            period_ym CHAR(7) NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pv2_audit_event (event_id),
            INDEX idx_pv2_audit_entity (entity_type, entity_id),
            INDEX idx_pv2_audit_period (period_ym)
        ) {$e}",
    ];
    foreach ($queries as $sql) {
        if (!$db->query($sql)) {
            throw new RuntimeException('Partner v2 schema: ' . $db->error);
        }
    }
    $cutoff = date('Y-m-01');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT IGNORE INTO partner_v2_settings (id, cutoff_date, initialized_by, notes)
         VALUES (1, ?, ?, 'Legacy partner reports archived; v2 starts at current month boundary')"
    );
    if ($stmt) {
        $stmt->bind_param('si', $cutoff, $uid);
        $stmt->execute();
        $stmt->close();
    }
    $done = true;
}

function pav2_cutoff(mysqli $db): string
{
    pav2_ensure_schema($db);
    $result = $db->query('SELECT cutoff_date FROM partner_v2_settings WHERE id=1 LIMIT 1');
    $row = $result ? $result->fetch_assoc() : null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($row['cutoff_date'] ?? ''))
        ? (string)$row['cutoff_date']
        : date('Y-m-01');
}

function pav2_date(string $value, string $fallback = ''): string
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }
    }
    return $fallback !== '' ? $fallback : date('Y-m-d');
}

function pav2_period(mysqli $db, string $ym): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    $from = $ym . '-01';
    $to = date('Y-m-t', strtotime($from));
    $cutoff = pav2_cutoff($db);
    return [
        'ym' => $ym,
        'from' => max($from, $cutoff),
        'to' => $to,
        'cutoff' => $cutoff,
        'eligible' => $to >= $cutoff,
    ];
}

function pav2_audit(mysqli $db, string $action, string $entityType, string $entityId, ?array $before, ?array $after, string $period = ''): void
{
    $event = 'PA2-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ip = mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8');
    $stmt = $db->prepare(
        'INSERT INTO partner_v2_audit
         (event_id, actor_id, action, entity_type, entity_id, period_ym, before_json, after_json, ip_address)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    if ($stmt) {
        $stmt->bind_param('sisssssss', $event, $uid, $action, $entityType, $entityId, $period, $beforeJson, $afterJson, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

function pav2_partners(mysqli $db): array
{
    $result = $db->query("SELECT id,name,status,share_percentage FROM partners ORDER BY status='active' DESC,name ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function pav2_products(mysqli $db): array
{
    if (!function_exists('ame_product_option_sql')) {
        require_once __DIR__ . '/helpers.php';
    }
    $on = ame_product_option_sql('status');
    $result = $db->query("SELECT id,name,sku FROM products WHERE {$on} ORDER BY name ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function pav2_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    return ame_active_accounts($db);
}

/**
 * Post the cash side of a business-paid expense. The expense row must already
 * exist and the caller must own the surrounding database transaction.
 */
function pav2_debit_expense_account(mysqli $db, int $expenseId, int $accountId, float $amount, string $voucher): int
{
    if ($expenseId < 1 || $accountId < 1 || $amount <= 0) {
        throw new InvalidArgumentException('খরচের অ্যাকাউন্ট তথ্য সঠিক নয়।');
    }
    if (!function_exists('ame_require_selectable_account')) {
        require_once __DIR__ . '/account_functions.php';
    }
    ame_require_selectable_account($db, $accountId);

    $existing = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_expense' AND ref_id=? AND type='debit'
         LIMIT 1 FOR UPDATE"
    );
    $existing->bind_param('i', $expenseId);
    $existing->execute();
    $row = $existing->get_result()->fetch_assoc();
    $existing->close();
    if ($row) {
        throw new RuntimeException('এই খরচের অ্যাকাউন্ট ডেবিট আগেই পোস্ট হয়েছে।');
    }

    account_debit(
        $accountId,
        $amount,
        'partner_v2_expense',
        $expenseId,
        'যৌথ খরচ · ' . $voucher
    );
    $posted = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_expense' AND ref_id=? AND type='debit'
         ORDER BY id DESC LIMIT 1"
    );
    $posted->bind_param('i', $expenseId);
    $posted->execute();
    $ledger = $posted->get_result()->fetch_assoc();
    $posted->close();
    if (!$ledger) {
        throw new RuntimeException('খরচের অ্যাকাউন্ট ডেবিট লেজারে সংরক্ষিত হয়নি।');
    }
    return (int)$ledger['id'];
}

/**
 * Restore a business-paid expense once. A partner-paid expense has no business
 * ledger entry and therefore needs no account reversal.
 */
function pav2_reverse_expense_account(mysqli $db, array $expense, string $reason): ?int
{
    if (($expense['payer_type'] ?? '') !== 'business' || (int)($expense['account_id'] ?? 0) < 1) {
        return null;
    }
    $expenseId = (int)($expense['id'] ?? 0);
    $stmt = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_expense' AND ref_id=? AND type='debit'
         ORDER BY id ASC LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $expenseId);
    $stmt->execute();
    $ledger = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ledger) {
        throw new RuntimeException('খরচের মূল অ্যাকাউন্ট ডেবিট পাওয়া যায়নি; void করা হয়নি।');
    }
    $originalLedgerId = (int)$ledger['id'];
    reverse_ledger_entry($originalLedgerId, $reason);
    $posted = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='reversal' AND ref_id=? ORDER BY id DESC LIMIT 1"
    );
    $posted->bind_param('i', $originalLedgerId);
    $posted->execute();
    $reversal = $posted->get_result()->fetch_assoc();
    $posted->close();
    if (!$reversal) {
        throw new RuntimeException('খরচের অ্যাকাউন্ট রিভার্সাল লেজারে সংরক্ষিত হয়নি।');
    }
    return (int)$reversal['id'];
}

function pav2_movement_is_money_out(string $type): bool
{
    return in_array($type, ['withdrawal', 'profit_payment', 'expense_reimbursement'], true);
}

function pav2_post_movement_account(mysqli $db, int $movementId, string $type, int $accountId, float $amount, string $reference): int
{
    if ($movementId < 1 || $amount <= 0) {
        throw new InvalidArgumentException('পার্টনার লেনদেনের অ্যাকাউন্ট তথ্য সঠিক নয়।');
    }
    ame_require_active_account($db, $accountId);
    if (!function_exists('ame_require_selectable_account')) {
        require_once __DIR__ . '/account_functions.php';
    }
    ame_require_selectable_account($db, $accountId);
    $existing = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_movement' AND ref_id=?
         LIMIT 1 FOR UPDATE"
    );
    $existing->bind_param('i', $movementId);
    $existing->execute();
    $duplicate = $existing->get_result()->fetch_assoc();
    $existing->close();
    if ($duplicate) {
        throw new RuntimeException('এই পার্টনার লেনদেনের অ্যাকাউন্ট পোস্টিং আগেই হয়েছে।');
    }
    $note = 'পার্টনার লেনদেন · ' . $reference;
    if (pav2_movement_is_money_out($type)) {
        account_debit($accountId, $amount, 'partner_v2_movement', $movementId, $note);
    } else {
        account_credit($accountId, $amount, 'partner_v2_movement', $movementId, $note);
    }
    $posted = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_movement' AND ref_id=? ORDER BY id DESC LIMIT 1"
    );
    $posted->bind_param('i', $movementId);
    $posted->execute();
    $row = $posted->get_result()->fetch_assoc();
    $posted->close();
    if (!$row) {
        throw new RuntimeException('পার্টনার লেনদেনের অ্যাকাউন্ট পোস্টিং সংরক্ষিত হয়নি।');
    }
    return (int)$row['id'];
}

function pav2_reverse_movement_account(mysqli $db, int $movementId, string $reason): int
{
    $stmt = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type='partner_v2_movement' AND ref_id=?
         ORDER BY id ASC LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $movementId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $acct = $db->prepare('SELECT account_id FROM partner_v2_movements WHERE id=? LIMIT 1');
        $acct->bind_param('i', $movementId);
        $acct->execute();
        $move = $acct->get_result()->fetch_assoc();
        $acct->close();
        if (!$move || (int)($move['account_id'] ?? 0) < 1) {
            return 0;
        }
        throw new RuntimeException('পার্টনার লেনদেনের মূল অ্যাকাউন্ট পোস্টিং পাওয়া যায়নি।');
    }
    reverse_ledger_entry((int)$row['id'], $reason);
    return (int)$row['id'];
}

function pav2_rules(mysqli $db, bool $activeOnly = false): array
{
    $sql = "SELECT r.*,p.name product_name,p.sku,p1.name partner_one_name,p2.name partner_two_name
            FROM partner_v2_product_rules r
            JOIN products p ON p.id=r.product_id
            JOIN partners p1 ON p1.id=r.partner_one_id
            JOIN partners p2 ON p2.id=r.partner_two_id";
    if ($activeOnly) {
        $sql .= " WHERE r.status='active'";
    }
    $sql .= ' ORDER BY r.product_id ASC,r.effective_from DESC,r.id DESC';
    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function pav2_rule_for(array $rulesByProduct, int $productId, string $saleDate): ?array
{
    foreach ($rulesByProduct[$productId] ?? [] as $rule) {
        if (($rule['status'] ?? '') !== 'active') {
            continue;
        }
        if ((string)$rule['effective_from'] <= $saleDate
            && (empty($rule['effective_to']) || (string)$rule['effective_to'] >= $saleDate)) {
            return $rule;
        }
    }
    return null;
}

function pav2_category_rules(mysqli $db, bool $activeOnly = false): array
{
    pav2_ensure_schema($db);
    $sql = "SELECT r.*, c.name category_name, p1.name partner_one_name, p2.name partner_two_name
            FROM partner_v2_category_rules r
            JOIN categories c ON c.id = r.category_id
            JOIN partners p1 ON p1.id = r.partner_one_id
            JOIN partners p2 ON p2.id = r.partner_two_id";
    if ($activeOnly) {
        $sql .= " WHERE r.status='active'";
    }
    $sql .= ' ORDER BY r.category_id ASC, r.effective_from DESC, r.id DESC';
    $result = @$db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function pav2_two_active_partners(mysqli $db): array
{
    $result = $db->query(
        "SELECT id, name FROM partners
         WHERE status='active' OR status='1' OR status IS NULL OR status=''
         ORDER BY id ASC"
    );
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    return count($rows) === 2 ? $rows : [];
}

/**
 * Turn on a 50/50 split for every product in a category from $from forward.
 * Older category rules stay in the table so earlier sales keep their split.
 */
function pav2_enable_category_half(mysqli $db, int $categoryId, string $from): int
{
    if ($categoryId < 1) {
        throw new InvalidArgumentException('ক্যাটাগরি সঠিক নয়।');
    }
    $pair = pav2_two_active_partners($db);
    if (!$pair) {
        throw new RuntimeException('৫০% অটো ভাগের জন্য ঠিক দুইজন সক্রিয় পার্টনার লাগবে।');
    }
    $from = pav2_date($from);
    $p1 = (int)$pair[0]['id'];
    $p2 = (int)$pair[1]['id'];
    $db->begin_transaction();
    try {
        $overlap = $db->prepare(
            "SELECT id FROM partner_v2_category_rules
             WHERE category_id=? AND status='active'
               AND effective_from<= '9999-12-31'
               AND COALESCE(effective_to,'9999-12-31')>=?
             LIMIT 1 FOR UPDATE"
        );
        $overlap->bind_param('is', $categoryId, $from);
        $overlap->execute();
        if ($overlap->get_result()->num_rows > 0) {
            $overlap->close();
            throw new RuntimeException('এই ক্যাটাগরিতে ইতিমধ্যে ৫০% ভাগ চালু আছে।');
        }
        $overlap->close();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $r1 = 50.0;
        $r2 = 50.0;
        $stmt = $db->prepare(
            'INSERT INTO partner_v2_category_rules
             (category_id,effective_from,partner_one_id,partner_one_ratio,partner_two_id,partner_two_ratio,created_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('isididi', $categoryId, $from, $p1, $r1, $p2, $r2, $uid);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        $id = (int)$db->insert_id;
        $stmt->close();
        pav2_audit($db, 'create', 'category_rule', (string)$id, null, [
            'category_id' => $categoryId, 'effective_from' => $from, 'ratio' => '50/50',
        ], substr($from, 0, 7));
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Stop future split. The rule row stays, so sales inside the old dates still count.
 */
function pav2_stop_category_half(mysqli $db, int $categoryId, string $end): int
{
    if ($categoryId < 1) {
        throw new InvalidArgumentException('ক্যাটাগরি সঠিক নয়।');
    }
    $end = pav2_date($end);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            "SELECT id, effective_from FROM partner_v2_category_rules
             WHERE category_id=? AND status='active'
               AND (effective_to IS NULL OR effective_to>=?)
             FOR UPDATE"
        );
        $stmt->bind_param('is', $categoryId, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $upd = $db->prepare(
            "UPDATE partner_v2_category_rules
             SET effective_to=?, updated_at=NOW()
             WHERE id=? AND status='active'"
        );
        $pause = $db->prepare(
            "UPDATE partner_v2_category_rules
             SET status='inactive', effective_to=effective_from, updated_at=NOW()
             WHERE id=? AND status='active'"
        );
        $changed = 0;
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if ((string)$row['effective_from'] > $end) {
                $pause->bind_param('i', $id);
                $pause->execute();
            } else {
                $upd->bind_param('si', $end, $id);
                $upd->execute();
            }
            $changed++;
        }
        $upd->close();
        $pause->close();
        if ($changed > 0) {
            pav2_audit($db, 'end', 'category_rule', (string)$categoryId, null, [
                'effective_to' => $end, 'kept' => true,
            ], substr($end, 0, 7));
        }
        $db->commit();
        return $changed;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function pav2_sale_date_expr(mysqli $db): string
{
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM sales');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = true;
        }
    }
    if (isset($columns['completed_at'])) {
        return "DATE(COALESCE(NULLIF(s.completed_at,'0000-00-00 00:00:00'),NULLIF(s.sale_date,'0000-00-00 00:00:00'),s.created_at))";
    }
    return isset($columns['sale_date']) ? 'DATE(COALESCE(s.sale_date,s.created_at))' : 'DATE(s.created_at)';
}

function pav2_return_events(mysqli $db, string $from, string $to): array
{
    $events = [];
    try {
        $stmt = $db->prepare(
            "SELECT sr.sale_id,sri.product_id,DATE(COALESCE(sr.return_date,sr.created_at)) event_date,
                    SUM(sri.returned_qty) qty,CONCAT('return-v2:',sr.id) source_ref
             FROM sale_returns_v2 sr
             JOIN sale_return_items sri ON sri.return_id=sr.id
             WHERE sr.sale_id IS NOT NULL AND sri.is_replacement=0
               AND sr.status IN ('approved','completed')
               AND DATE(COALESCE(sr.return_date,sr.created_at)) BETWEEN ? AND ?
             GROUP BY sr.id,sr.sale_id,sri.product_id,event_date"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $from, $to);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $events[] = $row;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('partner v2 modern returns: ' . $e->getMessage());
    }
    try {
        $stmt = $db->prepare(
            "SELECT sale_id,product_id,DATE(created_at) event_date,SUM(qty) qty,
                    CONCAT('return-legacy:',id) source_ref
             FROM sale_returns
             WHERE sale_id IS NOT NULL AND product_id IS NOT NULL
               AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY id,sale_id,product_id,event_date"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $from, $to);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $events[] = $row;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('partner v2 legacy returns: ' . $e->getMessage());
    }
    return $events;
}

function pav2_calculate_live(mysqli $db, string $ym): array
{
    $period = pav2_period($db, $ym);
    $empty = [
        'period' => $period, 'rows' => [], 'partners' => [], 'sales' => 0.0,
        'cost' => 0.0, 'direct_cost' => 0.0, 'gross_profit' => 0.0,
        'joint_expense' => 0.0, 'net_profit' => 0.0, 'expenses' => [],
        'movements' => [], 'returns' => 0.0,
    ];
    if (!$period['eligible']) {
        return $empty;
    }

    $rules = pav2_rules($db, true);
    $rulesByProduct = [];
    foreach ($rules as $rule) {
        $rulesByProduct[(int)$rule['product_id']][] = $rule;
    }
    $rulesByCategory = [];
    foreach (pav2_category_rules($db, true) as $rule) {
        $rulesByCategory[(int)$rule['category_id']][] = $rule;
    }
    if (!$rulesByProduct && !$rulesByCategory) {
        return $empty;
    }

    $dateExpr = pav2_sale_date_expr($db);
    $catCol = $db->query("SHOW COLUMNS FROM products LIKE 'category_id'");
    $catExpr = ($catCol && $catCol->num_rows > 0) ? 'MAX(p.category_id)' : '0';
    $cutoff = $period['cutoff'];
    $to = $period['to'];
    $stmt = $db->prepare(
        "SELECT s.id sale_id,{$dateExpr} sale_date,COALESCE(s.invoice_number,CONCAT('INV-',s.id)) invoice_no,
                COALESCE(s.discount,0) invoice_discount,si.product_id,p.name product_name,
                {$catExpr} category_id,
                SUM(si.qty) qty,SUM(si.sale_price*si.qty) gross_sales,
                SUM(si.purchase_price*si.qty) cost_amount
         FROM sales s
         JOIN sale_items si ON si.sale_id=s.id
         JOIN products p ON p.id=si.product_id
         WHERE {$dateExpr} BETWEEN ? AND ?
           AND (s.status='completed' OR s.status IS NULL OR s.status='')
           AND si.product_id IS NOT NULL
         GROUP BY s.id,{$dateExpr},s.invoice_number,s.discount,si.product_id,p.name
         ORDER BY {$dateExpr},s.id,si.product_id"
    );
    if (!$stmt) {
        throw new RuntimeException('Partner sales query: ' . $db->error);
    }
    $stmt->bind_param('ss', $cutoff, $to);
    $stmt->execute();
    $salesRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $invoiceGross = [];
    foreach ($salesRows as $row) {
        $invoiceGross[(int)$row['sale_id']] = ($invoiceGross[(int)$row['sale_id']] ?? 0) + (float)$row['gross_sales'];
    }
    $direct = [];
    $directResult = $db->query(
        "SELECT sale_id,product_id,SUM(amount) amount
         FROM partner_v2_direct_costs
         WHERE status='approved' AND cost_date<='" . $db->real_escape_string($to) . "'
         GROUP BY sale_id,product_id"
    );
    if ($directResult) {
        while ($row = $directResult->fetch_assoc()) {
            $direct[(int)$row['sale_id'] . ':' . (int)$row['product_id']] = (float)$row['amount'];
        }
    }

    $base = [];
    foreach ($salesRows as $row) {
        $splitSource = 'product';
        $rule = pav2_rule_for($rulesByProduct, (int)$row['product_id'], (string)$row['sale_date']);
        if (!$rule) {
            $splitSource = 'category';
            $rule = pav2_rule_for($rulesByCategory, (int)($row['category_id'] ?? 0), (string)$row['sale_date']);
        }
        if (!$rule) {
            continue;
        }
        $gross = (float)$row['gross_sales'];
        $invoiceTotal = max(0.0, (float)($invoiceGross[(int)$row['sale_id']] ?? 0));
        $discount = $invoiceTotal > 0
            ? min($gross, max(0.0, (float)$row['invoice_discount']) * ($gross / $invoiceTotal))
            : 0.0;
        $netSale = round($gross - $discount, 2);
        $key = (int)$row['sale_id'] . ':' . (int)$row['product_id'];
        $base[$key] = [
            'sale_id' => (int)$row['sale_id'],
            'product_id' => (int)$row['product_id'],
            'sale_date' => (string)$row['sale_date'],
            'invoice_no' => (string)$row['invoice_no'],
            'product_name' => (string)$row['product_name'],
            'qty' => (float)$row['qty'],
            'net_sale' => $netSale,
            'cost' => round((float)$row['cost_amount'], 2),
            'direct_cost' => round((float)($direct[$key] ?? 0), 2),
            'rule' => $rule,
            'split_source' => $splitSource,
        ];
    }

    $returnMap = [];
    foreach (pav2_return_events($db, $period['from'], $period['to']) as $event) {
        $key = (int)$event['sale_id'] . ':' . (int)$event['product_id'];
        $returnMap[$key] = ($returnMap[$key] ?? 0) + max(0.0, (float)$event['qty']);
    }

    $rows = [];
    $partnerTotals = [];
    $totals = ['sales' => 0.0, 'cost' => 0.0, 'direct' => 0.0, 'profit' => 0.0, 'returns' => 0.0];
    foreach ($base as $key => $line) {
        $qty = max(0.0, (float)$line['qty']);
        if ($qty <= 0) {
            continue;
        }
        $saleInPeriod = $line['sale_date'] >= $period['from'] && $line['sale_date'] <= $period['to'];
        $returnQty = min($qty, (float)($returnMap[$key] ?? 0));
        $saleFactor = $saleInPeriod ? 1.0 : 0.0;
        $returnFactor = $returnQty / $qty;
        if ($saleFactor <= 0 && $returnFactor <= 0) {
            continue;
        }
        $netSales = round($line['net_sale'] * ($saleFactor - $returnFactor), 2);
        $cost = round($line['cost'] * ($saleFactor - $returnFactor), 2);
        $directCost = $saleInPeriod ? (float)$line['direct_cost'] : 0.0;
        $profit = round($netSales - $cost - $directCost, 2);
        $rule = $line['rule'];
        $p1 = (int)$rule['partner_one_id'];
        $p2 = (int)$rule['partner_two_id'];
        $s1 = round($profit * ((float)$rule['partner_one_ratio'] / 100), 2);
        $s2 = round($profit - $s1, 2);
        $partnerTotals[$p1]['gross_share'] = ($partnerTotals[$p1]['gross_share'] ?? 0) + $s1;
        $partnerTotals[$p1]['name'] = (string)$rule['partner_one_name'];
        $partnerTotals[$p2]['gross_share'] = ($partnerTotals[$p2]['gross_share'] ?? 0) + $s2;
        $partnerTotals[$p2]['name'] = (string)$rule['partner_two_name'];
        $totals['sales'] += $netSales;
        $totals['cost'] += $cost;
        $totals['direct'] += $directCost;
        $totals['profit'] += $profit;
        $totals['returns'] += round($line['net_sale'] * $returnFactor, 2);
        $rows[] = [
            'sale_id' => $line['sale_id'], 'invoice_no' => $line['invoice_no'],
            'date' => $saleInPeriod ? $line['sale_date'] : $period['from'],
            'product_id' => $line['product_id'], 'product_name' => $line['product_name'],
            'sold_qty' => $saleInPeriod ? $qty : 0, 'returned_qty' => $returnQty,
            'sales' => $netSales, 'cost' => $cost, 'direct_cost' => $directCost,
            'profit' => $profit, 'partner_one_id' => $p1,
            'partner_one_name' => (string)$rule['partner_one_name'],
            'partner_one_ratio' => (float)$rule['partner_one_ratio'], 'partner_one_share' => $s1,
            'partner_two_id' => $p2, 'partner_two_name' => (string)$rule['partner_two_name'],
            'partner_two_ratio' => (float)$rule['partner_two_ratio'], 'partner_two_share' => $s2,
            'split_source' => (string)($line['split_source'] ?? 'product'),
        ];
    }

    $expenses = [];
    $expenseTotal = 0.0;
    $stmt = $db->prepare(
        "SELECT e.*,p.name payer_partner_name,a.account_name
         FROM partner_v2_expenses e
         LEFT JOIN partners p ON p.id=e.payer_partner_id
         LEFT JOIN accounts a ON a.id=e.account_id
         WHERE e.status='approved' AND e.expense_date BETWEEN ? AND ?
         ORDER BY e.expense_date,e.id"
    );
    $stmt->bind_param('ss', $period['from'], $period['to']);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($expenses as $expense) {
        $expenseTotal += (float)$expense['amount'];
        if (($expense['payer_type'] ?? '') === 'partner' && (int)$expense['payer_partner_id'] > 0) {
            $pid = (int)$expense['payer_partner_id'];
            $partnerTotals[$pid]['reimbursable'] = ($partnerTotals[$pid]['reimbursable'] ?? 0) + (float)$expense['amount'];
            $partnerTotals[$pid]['name'] = (string)($expense['payer_partner_name'] ?? ('#' . $pid));
        }
    }

    $grossProfit = round($totals['profit'], 2);
    $shareBase = array_sum(array_map(static fn($row) => max(0.0, (float)($row['gross_share'] ?? 0)), $partnerTotals));
    $participantCount = max(1, count($partnerTotals));
    foreach ($partnerTotals as $pid => &$partner) {
        $positiveShare = max(0.0, (float)($partner['gross_share'] ?? 0));
        $expenseShare = $shareBase > 0
            ? $expenseTotal * ($positiveShare / $shareBase)
            : $expenseTotal / $participantCount;
        $partner['expense_share'] = round($expenseShare, 2);
        $partner['net_profit_share'] = round((float)($partner['gross_share'] ?? 0) - $expenseShare, 2);
        $partner['reimbursable'] = round((float)($partner['reimbursable'] ?? 0), 2);
    }
    unset($partner);

    $movements = [];
    $stmt = $db->prepare(
        "SELECT m.*,p.name partner_name,a.account_name,a.account_type
         FROM partner_v2_movements m
         JOIN partners p ON p.id=m.partner_id
         LEFT JOIN accounts a ON a.id=m.account_id
         WHERE m.status='posted' AND m.movement_date BETWEEN ? AND ?
         ORDER BY m.movement_date,m.id"
    );
    $stmt->bind_param('ss', $period['from'], $period['to']);
    $stmt->execute();
    $movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($movements as $movement) {
        $pid = (int)$movement['partner_id'];
        $type = (string)$movement['movement_type'];
        $amount = (float)$movement['amount'];
        $partnerTotals[$pid]['name'] = (string)$movement['partner_name'];
        $partnerTotals[$pid][$type] = ($partnerTotals[$pid][$type] ?? 0) + $amount;
    }
    foreach ($partnerTotals as &$partner) {
        $partner['capital'] = round((float)($partner['capital'] ?? 0) + (float)($partner['additional_investment'] ?? 0), 2);
        $partner['withdrawal'] = round((float)($partner['withdrawal'] ?? 0), 2);
        $partner['profit_payment'] = round((float)($partner['profit_payment'] ?? 0), 2);
        $partner['expense_reimbursement'] = round((float)($partner['expense_reimbursement'] ?? 0), 2);
        $partner['receivable'] = round(
            (float)($partner['net_profit_share'] ?? 0)
            + (float)($partner['reimbursable'] ?? 0)
            - (float)$partner['profit_payment']
            - (float)$partner['expense_reimbursement'],
            2
        );
    }
    unset($partner);

    return [
        'period' => $period,
        'rows' => $rows,
        'partners' => $partnerTotals,
        'sales' => round($totals['sales'], 2),
        'cost' => round($totals['cost'], 2),
        'direct_cost' => round($totals['direct'], 2),
        'gross_profit' => $grossProfit,
        'joint_expense' => round($expenseTotal, 2),
        'net_profit' => round($grossProfit - $expenseTotal, 2),
        'expenses' => $expenses,
        'movements' => $movements,
        'returns' => round($totals['returns'], 2),
    ];
}

function pav2_month_row(mysqli $db, string $ym): ?array
{
    $stmt = $db->prepare('SELECT * FROM partner_v2_months WHERE period_ym=? LIMIT 1');
    $stmt->bind_param('s', $ym);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function pav2_report(mysqli $db, string $ym): array
{
    pav2_ensure_schema($db);
    $month = pav2_month_row($db, $ym);
    if ($month && ($month['status'] ?? '') === 'closed' && !empty($month['snapshot_json'])) {
        $decoded = json_decode((string)$month['snapshot_json'], true);
        if (is_array($decoded)) {
            $decoded['closed'] = true;
            $decoded['snapshot_hash'] = (string)($month['snapshot_hash'] ?? '');
            return $decoded;
        }
    }
    $report = pav2_calculate_live($db, $ym);
    $report['closed'] = false;
    return $report;
}

function pav2_month_locked(mysqli $db, string $ym): bool
{
    $row = pav2_month_row($db, $ym);
    return $row && ($row['status'] ?? '') === 'closed';
}

function pav2_close_month(mysqli $db, string $ym, string $note = ''): string
{
    $report = pav2_calculate_live($db, $ym);
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($json)) {
        throw new RuntimeException('মাসিক snapshot তৈরি হয়নি।');
    }
    $hash = hash('sha256', $json);
    $period = $report['period'];
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO partner_v2_months
         (period_ym,period_from,period_to,status,snapshot_json,snapshot_hash,closed_by,closed_at,close_note)
         VALUES (?,?,?,'closed',?,?,?,NOW(),?)
         ON DUPLICATE KEY UPDATE status='closed',snapshot_json=VALUES(snapshot_json),
             snapshot_hash=VALUES(snapshot_hash),closed_by=VALUES(closed_by),closed_at=NOW(),
             close_note=VALUES(close_note),reopened_by=NULL,reopened_at=NULL"
    );
    $stmt->bind_param('sssssis', $ym, $period['from'], $period['to'], $json, $hash, $uid, $note);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $stmt->close();
    pav2_audit($db, 'close', 'month', $ym, null, ['snapshot_hash' => $hash], $ym);
    return $hash;
}

function pav2_taka(float $amount): string
{
    return '৳' . number_format($amount, 2);
}

function pav2_expense_categories(): array
{
    return [
        'rent' => 'দোকানভাড়া',
        'utility' => 'বিদ্যুৎ ও ইউটিলিটি',
        'salary' => 'কর্মচারী বেতন',
        'transport' => 'পরিবহন ও ডেলিভারি',
        'repair' => 'মেরামত ও রক্ষণাবেক্ষণ',
        'internet' => 'ইন্টারনেট ও অফিস',
        'other' => 'অন্যান্য ব্যবসায়িক খরচ',
    ];
}

function pav2_movement_types(): array
{
    return [
        'capital' => 'মূলধন',
        'additional_investment' => 'অতিরিক্ত বিনিয়োগ',
        'withdrawal' => 'ব্যক্তিগত উত্তোলন',
        'profit_payment' => 'লাভ পরিশোধ',
        'expense_reimbursement' => 'ব্যক্তিগত খরচ ফেরত',
        'loan_to_business' => 'ব্যবসাকে পার্টনার ঋণ',
    ];
}
