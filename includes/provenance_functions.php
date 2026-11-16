<?php
/**
 * Digital provenance — hash-chained stamps for sales and payments.
 */

function ensure_provenance_schema(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS ledger_provenance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL,
        ref_id INT NOT NULL,
        stamp VARCHAR(24) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        prev_hash CHAR(64) NOT NULL DEFAULT '',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        label VARCHAR(80) NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_stamp (stamp),
        UNIQUE KEY uniq_kind_ref (kind, ref_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS ledger_provenance_meta (
        id INT PRIMARY KEY,
        pepper VARCHAR(64) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $one = $db->query("SELECT pepper FROM ledger_provenance_meta WHERE id=1");
    if (!$one || $one->num_rows === 0) {
        $pepper = hash('sha256', (getenv('PROVENANCE_KEY') ?: '') . random_bytes(16) . shop_name());
        $stmt = $db->prepare("INSERT INTO ledger_provenance_meta (id, pepper) VALUES (1, ?)");
        $stmt->bind_param('s', $pepper);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_provenance_pepper(mysqli $db): string
{
    ensure_provenance_schema($db);
    $env = getenv('PROVENANCE_KEY');
    if (is_string($env) && $env !== '') {
        return hash('sha256', $env);
    }
    $row = $db->query("SELECT pepper FROM ledger_provenance_meta WHERE id=1")->fetch_assoc();
    return (string)($row['pepper'] ?? '');
}

function ame_provenance_stamp(): string
{
    $raw = strtoupper(bin2hex(random_bytes(5)));
    return 'AP-' . substr($raw, 0, 10);
}

function ame_provenance_record(mysqli $db, string $kind, int $ref_id, array $payload): ?string
{
    if ($ref_id <= 0 || !in_array($kind, ['sale', 'payment'], true)) {
        return null;
    }
    ensure_provenance_schema($db);
    $exists = $db->prepare("SELECT stamp FROM ledger_provenance WHERE kind=? AND ref_id=? LIMIT 1");
    $exists->bind_param('si', $kind, $ref_id);
    $exists->execute();
    $hit = $exists->get_result()->fetch_assoc();
    $exists->close();
    if ($hit) {
        return (string)$hit['stamp'];
    }

    $prev = '';
    $last = $db->query("SELECT payload_hash FROM ledger_provenance ORDER BY id DESC LIMIT 1");
    if ($last && ($lr = $last->fetch_assoc())) {
        $prev = (string)$lr['payload_hash'];
    }

    $amount = (float)($payload['amount'] ?? 0);
    $label = substr((string)($payload['label'] ?? $kind . '-' . $ref_id), 0, 80);
    $canon = $kind . '|' . $ref_id . '|' . number_format($amount, 2, '.', '') . '|' . $label . '|' . $prev;
    $hash = hash_hmac('sha256', $canon, ame_provenance_pepper($db));
    $stamp = ame_provenance_stamp();

    $stmt = $db->prepare("INSERT INTO ledger_provenance (kind, ref_id, stamp, payload_hash, prev_hash, amount, label) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('sisssds', $kind, $ref_id, $stamp, $hash, $prev, $amount, $label);
    try {
        $stmt->execute();
        $stmt->close();
        return $stamp;
    } catch (Throwable $e) {
        $stmt->close();
        error_log('Provenance record failed: ' . $e->getMessage());
        return null;
    }
}

function ame_provenance_for(mysqli $db, string $kind, int $ref_id): ?array
{
    ensure_provenance_schema($db);
    $stmt = $db->prepare("SELECT * FROM ledger_provenance WHERE kind=? AND ref_id=? LIMIT 1");
    $stmt->bind_param('si', $kind, $ref_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_provenance_by_stamp(mysqli $db, string $stamp): ?array
{
    ensure_provenance_schema($db);
    $stamp = strtoupper(trim($stamp));
    $stmt = $db->prepare("SELECT * FROM ledger_provenance WHERE stamp=? LIMIT 1");
    $stmt->bind_param('s', $stamp);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_provenance_verify_row(mysqli $db, array $row): bool
{
    $canon = $row['kind'] . '|' . (int)$row['ref_id'] . '|' . number_format((float)$row['amount'], 2, '.', '') . '|' . $row['label'] . '|' . $row['prev_hash'];
    $expect = hash_hmac('sha256', $canon, ame_provenance_pepper($db));
    return hash_equals($expect, (string)$row['payload_hash']);
}

function ame_provenance_backfill(mysqli $db, int $limit = 40): int
{
    $n = 0;
    $sales = @$db->query("SELECT id, invoice_number, net_payable, sale_date FROM sales WHERE status='completed' ORDER BY id DESC LIMIT " . (int)$limit);
    if ($sales) {
        while ($s = $sales->fetch_assoc()) {
            if (ame_provenance_record($db, 'sale', (int)$s['id'], [
                'amount' => (float)$s['net_payable'],
                'label' => (string)($s['invoice_number'] ?: ('INV-' . $s['id'])),
            ])) {
                $n++;
            }
        }
    }
    $pays = @$db->query("SELECT id, amount FROM customer_payments ORDER BY id DESC LIMIT " . (int)$limit);
    if ($pays) {
        while ($p = $pays->fetch_assoc()) {
            if (ame_provenance_record($db, 'payment', (int)$p['id'], [
                'amount' => (float)$p['amount'],
                'label' => 'PAY-' . str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT),
            ])) {
                $n++;
            }
        }
    }
    return $n;
}

function ame_provenance_footer(?array $row): string
{
    if (!$row) {
        return '';
    }
    $stamp = htmlspecialchars((string)$row['stamp'], ENT_QUOTES, 'UTF-8');
    return '<p style="font-size:11px;letter-spacing:.06em;margin-top:8px;">প্রুভেন্যান্স ' . $stamp . ' · যাচাই /verify.php?s=' . rawurlencode((string)$row['stamp']) . '</p>';
}
