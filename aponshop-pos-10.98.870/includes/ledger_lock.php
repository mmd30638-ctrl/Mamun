<?php
/**
 * কাস্টমার ও সাপ্লায়ার খাতা — মালিকের লক।
 * বিক্রয়/ক্রয়/পেমেন্ট খাতায় বসবে। পেজ খোলা বা আপডেট ZIP খাতা আবার গুনবে না।
 * খুলতে হলে সার্ভারে LEDGER_UNLOCK.txt লাগে — মালিকের অনুমতি ছাড়া নয়।
 */

function ame_ledger_lock_root(): string
{
    return dirname(__DIR__);
}

function ame_ledger_books_sealed(): bool
{
    if (defined('AME_LEDGER_UNLOCK') && AME_LEDGER_UNLOCK) {
        return false;
    }
    $env = getenv('AME_LEDGER_UNLOCK');
    if ($env === '1' || strtolower((string) $env) === 'true' || strtolower((string) $env) === 'yes') {
        return false;
    }
    if (is_file(ame_ledger_lock_root() . '/LEDGER_UNLOCK.txt')) {
        return false;
    }
    return true;
}

/** @return list<string> */
function ame_ledger_lock_builtin_files(): array
{
    return [
        'LEDGER_LOCK.txt',
        'includes/ledger_lock.php',
        'includes/party_amount_shield.php',
        'includes/running_ledger.php',
        'includes/purchase_functions.php',
        'LEDGER_LOCK.txt',
        'includes/supplier_settlement.php',
        'modules/customers/ledger.php',
        'modules/customers/customer_ledger_functions.php',
        'modules/customers/customer_schema.php',
        'modules/suppliers/statement.php',
        'modules/suppliers/ledger.php',
        'modules/suppliers/list.php',
        'modules/suppliers/dashboard.php',
    ];
}

/** @return list<string> */
function ame_ledger_lock_parse_files(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        if (!preg_match('#^- ([A-Za-z0-9_./-]+)$#', trim($line), $m)) {
            continue;
        }
        $path = str_replace('\\', '/', $m[1]);
        if ($path !== '' && !str_ends_with($path, '/')) {
            $out[$path] = true;
        }
    }
    return array_keys($out);
}

/** @return list<string> */
function ame_ledger_lock_files(?string $liveRoot = null): array
{
    $files = [];
    foreach (ame_ledger_lock_builtin_files() as $rel) {
        $files[$rel] = true;
    }
    $root = $liveRoot !== null && $liveRoot !== '' ? rtrim($liveRoot, '/') : ame_ledger_lock_root();
    $lock = $root . '/LEDGER_LOCK.txt';
    if (is_file($lock)) {
        foreach (ame_ledger_lock_parse_files((string) file_get_contents($lock)) as $rel) {
            $files[$rel] = true;
        }
    }
    return array_keys($files);
}

function ame_ledger_lock_protects(string $rel, ?string $liveRoot = null): bool
{
    $rel = str_replace('\\', '/', ltrim($rel, '/'));
    if ($rel === '' || str_contains($rel, '..')) {
        return false;
    }
    if ($liveRoot !== null && $liveRoot !== '' && is_file(rtrim($liveRoot, '/') . '/LEDGER_UNLOCK.txt')) {
        return false;
    }
    if (is_file(ame_ledger_lock_root() . '/LEDGER_UNLOCK.txt')) {
        return false;
    }
    return in_array($rel, ame_ledger_lock_files($liveRoot), true);
}

function ame_ledger_lock_note_skip(string $fn, string $note = ''): void
{
    static $once = [];
    $key = $fn . '|' . $note;
    if (isset($once[$key])) {
        return;
    }
    $once[$key] = true;
    error_log('LEDGER LOCK skip ' . $fn . ($note !== '' ? ': ' . $note : ''));
}

function ame_ledger_lock_boot(?mysqli $db = null): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    @$db->query("CREATE TABLE IF NOT EXISTS ame_ledger_seal (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        sealed TINYINT(1) NOT NULL DEFAULT 1,
        sealed_at DATETIME NOT NULL,
        note VARCHAR(190) NOT NULL DEFAULT '',
        version VARCHAR(20) NOT NULL DEFAULT '',
        supplier_due_sum DECIMAL(14,2) NULL,
        customer_recv_sum DECIMAL(14,2) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ver = defined('AME_ASSET_VERSION') ? (string) AME_ASSET_VERSION : '';
    if ($ver === '' && defined('AME_APP_VERSION')) {
        $ver = (string) AME_APP_VERSION;
    }
    $exists = @$db->query('SELECT id, version FROM ame_ledger_seal WHERE id = 1 LIMIT 1');
    if ($exists && $exists->num_rows > 0) {
        $row = $exists->fetch_assoc();
        $storedVer = (string) ($row['version'] ?? '');
        if ($ver !== '' && $storedVer !== $ver) {
            ame_ledger_seal_refresh_baseline(
                $db,
                'অটো — সংস্করণ ' . $ver . ' এ মনিটরিং বেসলাইন (পার্টি ব্যালেন্স অপরিবর্তিত)'
            );
        }
        return;
    }

    $supSum = 0.0;
    $cusSum = 0.0;
    $sr = @$db->query('SELECT COALESCE(SUM(total_due),0) AS s FROM suppliers');
    if ($sr && ($row = $sr->fetch_assoc())) {
        $supSum = round((float) $row['s'], 2);
    }
    $cr = @$db->query('SELECT COALESCE(SUM(receivable_balance),0) AS s FROM customers');
    if ($cr && ($row = $cr->fetch_assoc())) {
        $cusSum = round((float) $row['s'], 2);
    }
    $note = 'কাস্টমার ও সাপ্লায়ার খাতা মালিকের লক — পেজ খোলা বা আপডেট ZIP হিসাব আবার গুনবে না';
    $one = 1;
    $stmt = @$db->prepare(
        'INSERT INTO ame_ledger_seal (id, sealed, sealed_at, note, version, supplier_due_sum, customer_recv_sum)
         VALUES (1, ?, NOW(), ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('issdd', $one, $note, $ver, $supSum, $cusSum);
        $stmt->execute();
        $stmt->close();
    }
}

/** @return array{supplier:float,customer:float} */
function ame_ledger_seal_party_totals(mysqli $db): array
{
    $out = ['supplier' => 0.0, 'customer' => 0.0];
    $sr = @$db->query('SELECT COALESCE(SUM(total_due),0) AS s FROM suppliers');
    if ($sr && ($row = $sr->fetch_assoc())) {
        $out['supplier'] = round((float) $row['s'], 2);
    }
    $cr = @$db->query('SELECT COALESCE(SUM(receivable_balance),0) AS s FROM customers');
    if ($cr && ($row = $cr->fetch_assoc())) {
        $out['customer'] = round((float) $row['s'], 2);
    }
    return $out;
}

/**
 * শুধু ame_ledger_seal স্ন্যাপশট মিলায় — customers/suppliers সারিতে হাত দেয় না।
 *
 * @return array{ok:bool,supplier:float,customer:float,message:string}
 */
function ame_ledger_seal_refresh_baseline(mysqli $db, string $note = ''): array
{
    $totals = ame_ledger_seal_party_totals($db);
    if (!@$db->query('SELECT 1 FROM ame_ledger_seal WHERE id = 1 LIMIT 1')) {
        return ['ok' => false, 'supplier' => $totals['supplier'], 'customer' => $totals['customer'], 'message' => 'ame_ledger_seal টেবিল নেই'];
    }
    $note = trim($note);
    if ($note === '') {
        $note = 'বেসলাইন রিফ্রেশ — বর্তমান মোট দেনা/বকেয়া (কোনো পার্টি ব্যালেন্স পরিবর্তন নেই)';
    }
    if (strlen($note) > 180) {
        $note = substr($note, 0, 180);
    }
    $ver = defined('AME_ASSET_VERSION') ? (string) AME_ASSET_VERSION : '';
    $stmt = @$db->prepare(
        'UPDATE ame_ledger_seal
         SET sealed = 1,
             sealed_at = NOW(),
             note = ?,
             version = ?,
             supplier_due_sum = ?,
             customer_recv_sum = ?
         WHERE id = 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'supplier' => $totals['supplier'], 'customer' => $totals['customer'], 'message' => $db->error];
    }
    $stmt->bind_param(
        'ssdd',
        $note,
        $ver,
        $totals['supplier'],
        $totals['customer']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return [
        'ok' => (bool) $ok,
        'supplier' => $totals['supplier'],
        'customer' => $totals['customer'],
        'message' => $ok ? 'সিল বেসলাইন আপডেট হয়েছে (শুধু মনিটরিং স্ন্যাপশট)।' : 'আপডেট ব্যর্থ।',
    ];
}
