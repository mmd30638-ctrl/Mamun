<?php
/**
 * ডাবল পেমেন্ট / ডাবল সেল / ডাবল মেমো আটকানো।
 */

function ensure_integrity_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }

    $db->query("CREATE TABLE IF NOT EXISTS idempotency_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(40) NOT NULL,
        token VARCHAR(80) NOT NULL,
        ref_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_scope_token (scope, token),
        INDEX idx_scope_created (scope, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = [];
    $res = @$db->query("SHOW COLUMNS FROM sales LIKE 'offline_client_ref'");
    if ($res && $res->num_rows === 0) {
        @$db->query("ALTER TABLE sales ADD COLUMN offline_client_ref VARCHAR(80) NULL");
    }
    $idx = @$db->query("SHOW INDEX FROM sales WHERE Key_name = 'uniq_offline_client_ref'");
    if ($idx && $idx->num_rows === 0) {
        @$db->query("ALTER TABLE sales ADD UNIQUE KEY uniq_offline_client_ref (offline_client_ref)");
    }

    $pcols = [];
    $pres = @$db->query("SHOW COLUMNS FROM customer_payments");
    if ($pres) {
        while ($row = $pres->fetch_assoc()) {
            $pcols[$row['Field']] = true;
        }
    }
    if ($pcols && !isset($pcols['idempotency_token'])) {
        @$db->query("ALTER TABLE customer_payments ADD COLUMN idempotency_token VARCHAR(80) NULL");
    }
    $pidx = @$db->query("SHOW INDEX FROM customer_payments WHERE Key_name = 'uniq_payment_token'");
    if ($pidx && $pidx->num_rows === 0) {
        @$db->query("ALTER TABLE customer_payments ADD UNIQUE KEY uniq_payment_token (idempotency_token)");
    }
}

function find_sale_by_offline_ref($db, $ref)
{
    $ref = trim((string)$ref);
    if ($ref === '') {
        return 0;
    }
    $stmt = $db->prepare("SELECT id FROM sales WHERE offline_client_ref = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $ref);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function attach_sale_offline_ref($db, $sale_id, $ref)
{
    $ref = trim((string)$ref);
    $sale_id = (int)$sale_id;
    if ($ref === '' || $sale_id <= 0) {
        return;
    }
    $stmt = $db->prepare("UPDATE sales SET offline_client_ref = ? WHERE id = ? AND (offline_client_ref IS NULL OR offline_client_ref = '')");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('si', $ref, $sale_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * টোকেন নতুন হলে true। আগে ব্যবহার হলে $existing_ref_id সেট করে false।
 */
function claim_idempotency_token($db, $scope, $token, &$existing_ref_id = 0)
{
    $existing_ref_id = 0;
    $scope = substr(trim((string)$scope), 0, 40);
    $token = substr(trim((string)$token), 0, 80);
    if ($scope === '' || $token === '') {
        return true;
    }

    $find = $db->prepare("SELECT ref_id FROM idempotency_keys WHERE scope = ? AND token = ? LIMIT 1");
    if ($find) {
        $find->bind_param('ss', $scope, $token);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();
        $find->close();
        if ($row) {
            $existing_ref_id = (int)($row['ref_id'] ?? 0);
            return false;
        }
    }

    $ins = $db->prepare("INSERT INTO idempotency_keys (scope, token) VALUES (?, ?)");
    if (!$ins) {
        return true;
    }
    $ins->bind_param('ss', $scope, $token);
    $ok = false;
    $errno = 0;
    try {
        $ok = $ins->execute();
    } catch (mysqli_sql_exception $e) {
        $ok = false;
        $errno = (int)$e->getCode();
    }
    if ($errno === 0) {
        $errno = (int)$db->errno;
    }
    $ins->close();
    if ($ok) {
        return true;
    }
    if ($errno === 1062) {
        $find2 = $db->prepare("SELECT ref_id FROM idempotency_keys WHERE scope = ? AND token = ? LIMIT 1");
        if ($find2) {
            $find2->bind_param('ss', $scope, $token);
            $find2->execute();
            $row = $find2->get_result()->fetch_assoc();
            $find2->close();
            $existing_ref_id = (int)($row['ref_id'] ?? 0);
        }
        return false;
    }
    return true;
}

function finish_idempotency_token($db, $scope, $token, $ref_id)
{
    $scope = substr(trim((string)$scope), 0, 40);
    $token = substr(trim((string)$token), 0, 80);
    $ref_id = (int)$ref_id;
    if ($scope === '' || $token === '') {
        return;
    }
    $stmt = $db->prepare("UPDATE idempotency_keys SET ref_id = ? WHERE scope = ? AND token = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iss', $ref_id, $scope, $token);
    $stmt->execute();
    $stmt->close();
}

function ame_lock($db, $key)
{
    $name = 'ame_' . substr(hash('sha256', (string)$key), 0, 48);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 8)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int)($row[0] ?? 0) === 1;
}

function ame_unlock($db, $key)
{
    $name = 'ame_' . substr(hash('sha256', (string)$key), 0, 48);
    $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->close();
}

function ame_fingerprint(array $parts)
{
    return substr(hash('sha256', implode('|', $parts)), 0, 64);
}

function generate_unique_purchase_memo($db, $supplier_id)
{
    for ($i = 0; $i < 12; $i++) {
        $memo = 'PUR-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        if (!purchase_memo_exists($db, $supplier_id, $memo)) {
            return $memo;
        }
    }
    return 'PUR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
}
