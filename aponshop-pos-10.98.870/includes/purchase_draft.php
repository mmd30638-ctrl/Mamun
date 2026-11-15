<?php
/**
 * ক্রয় ড্রাফট — বিক্রয় ড্রাফটের মতো সার্ভারে খসড়া রাখে।
 * স্টক, সাপ্লায়ার বকেয়া বা অ্যাকাউন্ট ড্রাফটে বদলায় না।
 */

function ame_purchase_draft_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS purchase_drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draft_number VARCHAR(40) NOT NULL DEFAULT '',
            supplier_id INT NULL,
            memo_no VARCHAR(50) NULL,
            purchase_date DATE NULL,
            notes VARCHAR(500) NULL,
            items_json MEDIUMTEXT NOT NULL,
            expenses_json MEDIUMTEXT NULL,
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            account_id INT NULL,
            payment_type VARCHAR(10) NOT NULL DEFAULT 'cash',
            grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_by INT NULL,
            store_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pd_status (status),
            INDEX idx_pd_user (created_by),
            INDEX idx_pd_store (store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    ame_purchase_draft_ensure_columns($db);
}

function ame_purchase_can_access(?mysqli $db = null): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return true;
    }
    if ($db instanceof mysqli && function_exists('has_permission')) {
        return has_permission($db, 'm-purchase');
    }
    return false;
}

function ame_purchase_require_access(mysqli $db): void
{
    if (ame_purchase_can_access($db)) {
        return;
    }
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('লগইন করুন'); window.location='/index.php';</script>";
        exit();
    }
    echo "<script>alert('অনুমতি নেই!'); window.location='/index.php';</script>";
    exit();
}

function ame_purchase_draft_ensure_columns(mysqli $db): void
{
    $cols = [
        'draft_number' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'supplier_id' => 'INT NULL',
        'memo_no' => 'VARCHAR(50) NULL',
        'purchase_date' => 'DATE NULL',
        'notes' => 'VARCHAR(500) NULL',
        'items_json' => 'MEDIUMTEXT NULL',
        'expenses_json' => 'MEDIUMTEXT NULL',
        'paid_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'account_id' => 'INT NULL',
        'payment_type' => "VARCHAR(10) NOT NULL DEFAULT 'cash'",
        'grand_total' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'created_by' => 'INT NULL',
        'store_id' => 'INT NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'draft'",
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM purchase_drafts');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[] = $row['Field'];
        }
    }
    foreach ($cols as $name => $ddl) {
        if (in_array($name, $have, true)) {
            continue;
        }
        $safe = preg_replace('/[^a-z0-9_]/i', '', $name);
        @$db->query('ALTER TABLE purchase_drafts ADD COLUMN `' . $safe . '` ' . $ddl);
    }
}

function ame_purchase_draft_normalize_items($items_raw)
{
    $clean = [];
    $total = 0.0;
    if (!is_array($items_raw)) {
        return [$clean, $total];
    }

    foreach ($items_raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $product_id = (int)($item['product_id'] ?? 0);
        $qty = round((float)($item['qty'] ?? 0), 2);
        $pp = round((float)($item['purchase_price'] ?? 0), 2);
        $free_qty = max(0.0, round((float)($item['free_qty'] ?? 0), 2));
        if ($product_id <= 0 || $qty < 0 || ($qty + $free_qty) <= 0 || $pp < 0) {
            continue;
        }
        $line = [
            'product_id' => $product_id,
            'sku' => substr(trim((string)($item['sku'] ?? '')), 0, 80),
            'name' => substr(trim((string)($item['name'] ?? '')), 0, 200),
            'stock' => $item['stock'] ?? null,
            'qty' => $qty,
            'free_qty' => $free_qty,
            'purchase_price' => $pp,
            'dealer_price' => round((float)($item['dealer_price'] ?? 0), 2),
            'wholesale_price' => round((float)($item['wholesale_price'] ?? 0), 2),
            'sale_price' => round((float)($item['sale_price'] ?? 0), 2),
        ];
        $total += round($qty * $pp, 2);
        $clean[] = $line;
    }

    return [$clean, round($total, 2)];
}

function ame_purchase_draft_normalize_expenses($expenses_raw)
{
    $clean = [];
    $total = 0.0;
    if (!is_array($expenses_raw)) {
        return [$clean, $total];
    }

    foreach ($expenses_raw as $exp) {
        if (!is_array($exp)) {
            continue;
        }
        $amount = round((float)($exp['amount'] ?? 0), 2);
        if ($amount <= 0) {
            continue;
        }
        $type = substr(trim((string)($exp['type'] ?? 'অন্যান্য')), 0, 50);
        if ($type === '') {
            $type = 'অন্যান্য';
        }
        $clean[] = ['type' => $type, 'amount' => $amount];
        $total += $amount;
    }

    return [$clean, round($total, 2)];
}

function ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, $lock_row = false)
{
    $draft_id = (int)$draft_id;
    if ($draft_id <= 0 || !($db instanceof mysqli)) {
        return null;
    }

    $sql = "SELECT * FROM purchase_drafts WHERE id = ? AND status = 'draft'";
    if (!$is_admin) {
        $sql .= ' AND created_by = ?';
    }
    if ($lock_row) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($is_admin) {
        $stmt->bind_param('i', $draft_id);
    } else {
        $user_id = (int)$user_id;
        $stmt->bind_param('ii', $draft_id, $user_id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function ame_purchase_draft_mark_completed($db, $draft_id, $user_id, $is_admin)
{
    $draft = ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, true);
    if (!$draft) {
        return false;
    }

    $id = (int)$draft['id'];
    $stmt = $db->prepare("UPDATE purchase_drafts SET status = 'completed', updated_at = NOW() WHERE id = ? AND status = 'draft'");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();

    return $ok;
}
