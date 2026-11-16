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
        'includes/running_ledger.php',
        'includes/purchase_functions.php',
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

    $ver = defined('AME_APP_VERSION') ? (string) AME_APP_VERSION : '';
    $exists = @$db->query('SELECT id FROM ame_ledger_seal WHERE id = 1 LIMIT 1');
    if ($exists && $exists->num_rows > 0) {
        if ($ver !== '') {
            $stmt = @$db->prepare('UPDATE ame_ledger_seal SET version = ? WHERE id = 1 AND sealed = 1');
            if ($stmt) {
                $stmt->bind_param('s', $ver);
                $stmt->execute();
                $stmt->close();
            }
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

if (!function_exists('ame_ledger_seal_compute_party_sums')) {
    /** @return array{supplier:float,customer:float} */
    function ame_ledger_seal_compute_party_sums(mysqli $db): array
    {
        $supplier = 0.0;
        $customer = 0.0;
        $sr = @$db->query('SELECT COALESCE(SUM(total_due),0) AS s FROM suppliers');
        if ($sr && ($row = $sr->fetch_assoc())) {
            $supplier = round((float)$row['s'], 2);
        }
        if ($sr instanceof mysqli_result) {
            $sr->free();
        }
        $cr = @$db->query('SELECT COALESCE(SUM(receivable_balance),0) AS s FROM customers');
        if ($cr && ($row = $cr->fetch_assoc())) {
            $customer = round((float)$row['s'], 2);
        }
        if ($cr instanceof mysqli_result) {
            $cr->free();
        }
        return ['supplier' => $supplier, 'customer' => $customer];
    }
}

if (!function_exists('ame_ledger_seal_refresh_baseline')) {
    /**
     * মনিটরিং স্ন্যাপশট — individual পার্টি ব্যালেন্স স্পর্শ করে না।
     *
     * @return array{ok:bool,message:string,supplier:float,customer:float}
     */
    function ame_ledger_seal_refresh_baseline(mysqli $db, string $note = ''): array
    {
        ame_ledger_lock_boot($db);
        $sums = ame_ledger_seal_compute_party_sums($db);
        $note = mb_substr(trim($note), 0, 190);
        if ($note === '') {
            $note = 'বেসলাইন রিফ্রেশ';
        }
        $ver = defined('AME_APP_VERSION') ? (string)AME_APP_VERSION : '';
        $stmt = @$db->prepare(
            'UPDATE ame_ledger_seal SET supplier_due_sum = ?, customer_recv_sum = ?, sealed_at = NOW(), note = ?,
             version = IF(? <> "", ?, version) WHERE id = 1 AND sealed = 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'সিল আপডেট প্রস্তুত হয়নি: ' . $db->error, 'supplier' => $sums['supplier'], 'customer' => $sums['customer']];
        }
        $stmt->bind_param('ddsss', $sums['supplier'], $sums['customer'], $note, $ver, $ver);
        $stmt->execute();
        $aff = $stmt->affected_rows;
        $stmt->close();
        if ($aff < 1) {
            $one = 1;
            $ins = @$db->prepare(
                'INSERT INTO ame_ledger_seal (id, sealed, sealed_at, note, version, supplier_due_sum, customer_recv_sum)
                 VALUES (1, ?, NOW(), ?, ?, ?, ?)'
            );
            if ($ins) {
                $ins->bind_param('issdd', $one, $note, $ver, $sums['supplier'], $sums['customer']);
                $ins->execute();
                $ins->close();
            }
        }
        return [
            'ok' => true,
            'message' => 'মনিটরিং বেসলাইন বর্তমান মোটের সাথে মিলেছে (কোনো পার্টি ব্যালেন্স বদলায়নি)।',
            'supplier' => $sums['supplier'],
            'customer' => $sums['customer'],
        ];
    }
}

if (!function_exists('ame_ledger_seal_rebaseline_totals')) {
    /** @return array{ok:bool,message:string,supplier:float,customer:float} */
    function ame_ledger_seal_rebaseline_totals(mysqli $db): array
    {
        return ame_ledger_seal_refresh_baseline($db, 'CLI rebaseline totals');
    }
}
