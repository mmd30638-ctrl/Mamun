<?php
declare(strict_types=1);

/**
 * APONSHOP 10.98.165 — idempotent legacy sale-item recovery.
 *
 * CLI only. The source directory must contain product.sql, sale.sql and
 * sale_item.sql from the private split_mj_* backup. Dry-run is the default.
 *
 * Example:
 * php migrations/002_import_legacy_sale_items.php \
 *   --source-dir=/home/USER/private_aponshop/migration_source/split_mj_ID \
 *   --balances-json=/home/USER/private_aponshop/migration_source/data/mae_balances_20260919.json \
 *   --report=/home/USER/private_aponshop/reports/legacy-items.json
 *
 * Add --apply only after the dry-run has zero unresolved item rows.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit($code);
}

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function normalized_key(?string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return mb_strtolower($value, 'UTF-8');
}

/** @return array<string, mixed> */
function cli_options(): array
{
    $options = getopt('', [
        'source-dir:',
        'balances-json::',
        'report::',
        'apply',
        'allow-unresolved',
        'keep-staging',
    ]);
    if (!is_array($options) || empty($options['source-dir'])) {
        fail('Required option missing: --source-dir=/absolute/private/path', 2);
    }
    return $options;
}

function create_staging_tables(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS ame_stage_legacy_product (
        ProId INT NOT NULL PRIMARY KEY,
        ProCode VARCHAR(255) NULL,
        ProName VARCHAR(255) NULL,
        ProTypId INT NULL,
        PstId INT NULL,
        BraId INT NULL,
        ProDescription TEXT NULL,
        PuId INT NULL,
        ProCost DECIMAL(18,4) NULL,
        ProRetMRP DECIMAL(18,4) NULL,
        ProRetPer DECIMAL(18,4) NULL,
        ProWhoMRP DECIMAL(18,4) NULL,
        ProWhoPer DECIMAL(18,4) NULL,
        ProWarranty INT NULL,
        ProWarrType INT NULL,
        ProStatus INT NULL,
        ProMoment DATETIME NULL,
        UserId INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS ame_stage_legacy_sale (
        SalId INT NOT NULL PRIMARY KEY,
        SinId INT NULL,
        SalInvoice VARCHAR(100) NULL,
        SalDate DATETIME NULL,
        CusType INT NULL,
        SalType INT NULL,
        SalSubTotal DECIMAL(18,4) NULL,
        SalVatPerc DECIMAL(18,4) NULL,
        SalVatAmo DECIMAL(18,4) NULL,
        SalRegDisPerc DECIMAL(18,4) NULL,
        SalRegDisAmo DECIMAL(18,4) NULL,
        SalRponding DECIMAL(18,4) NULL,
        SalSpeDisc DECIMAL(18,4) NULL,
        SalNetPay DECIMAL(18,4) NULL,
        SalCash DECIMAL(18,4) NULL,
        SalBalance DECIMAL(18,4) NULL,
        SalDue DECIMAL(18,4) NULL,
        SalPayTyp INT NULL,
        SalNote TEXT NULL,
        SalCheck VARCHAR(255) NULL,
        SalStatus INT NULL,
        SalMoment DATETIME NULL,
        UserId INT NULL,
        KEY idx_legacy_sale_invoice (SalInvoice),
        KEY idx_legacy_sale_status (SalStatus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS ame_stage_legacy_sale_item (
        SiId BIGINT NOT NULL PRIMARY KEY,
        SalId INT NOT NULL,
        ProId INT NOT NULL,
        SiCost DECIMAL(18,4) NOT NULL DEFAULT 0,
        SiMRP DECIMAL(18,4) NOT NULL DEFAULT 0,
        SiQty DECIMAL(18,4) NOT NULL DEFAULT 0,
        SiTotal DECIMAL(18,4) NOT NULL DEFAULT 0,
        KEY idx_legacy_item_sale (SalId),
        KEY idx_legacy_item_product (ProId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach (['ame_stage_legacy_product', 'ame_stage_legacy_sale', 'ame_stage_legacy_sale_item'] as $table) {
        $db->query("TRUNCATE TABLE `{$table}`");
    }
}

function run_insert_statement(mysqli $db, string $statement, string $sourceTable, string $targetTable): int
{
    $statement = trim($statement);
    if ($statement === '' || !preg_match('/^INSERT\s+INTO\s+`?' . preg_quote($sourceTable, '/') . '`?\s/i', $statement)) {
        return 0;
    }
    $rewritten = preg_replace(
        '/^INSERT\s+INTO\s+`?' . preg_quote($sourceTable, '/') . '`?/i',
        'INSERT INTO `' . $targetTable . '`',
        $statement,
        1
    );
    if (!is_string($rewritten)) {
        throw new RuntimeException("Could not rewrite INSERT for {$sourceTable}");
    }
    $db->query($rewritten);
    return max(0, $db->affected_rows);
}

function load_insert_dump(
    mysqli $db,
    string $path,
    string $sourceTable,
    string $targetTable
): int {
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Cannot read source file: {$path}");
    }
    $statement = '';
    $inQuote = false;
    $escaped = false;
    $loaded = 0;

    try {
        while (($line = fgets($handle)) !== false) {
            $statement .= $line;
            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                if ($inQuote) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === "'") {
                        $inQuote = false;
                    }
                    continue;
                }
                if ($char === "'") {
                    $inQuote = true;
                } elseif ($char === ';') {
                    $loaded += run_insert_statement($db, $statement, $sourceTable, $targetTable);
                    $statement = '';
                }
            }
        }
        if (trim($statement) !== '') {
            throw new RuntimeException("Incomplete SQL statement at end of {$path}");
        }
    } finally {
        fclose($handle);
    }
    return $loaded;
}

/** @return array<int, array{sku:string,name:string}> */
function legacy_product_references(mysqli $db, ?string $balancesPath): array
{
    $refs = [];
    $result = $db->query('SELECT ProId, ProCode, ProName FROM ame_stage_legacy_product');
    while ($row = $result->fetch_assoc()) {
        $refs[(int)$row['ProId']] = [
            'sku' => trim((string)$row['ProCode']),
            'name' => trim((string)$row['ProName']),
        ];
    }

    if ($balancesPath !== null && $balancesPath !== '') {
        if (!is_file($balancesPath) || !is_readable($balancesPath)) {
            throw new RuntimeException("Balances JSON is not readable: {$balancesPath}");
        }
        $payload = json_decode((string)file_get_contents($balancesPath), true, 512, JSON_THROW_ON_ERROR);
        foreach ((array)($payload['products'] ?? []) as $row) {
            $id = (int)($row['legacy_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $refs[$id] ??= ['sku' => '', 'name' => ''];
            $sku = trim((string)($row['sku'] ?? ''));
            if ($sku !== '') {
                $refs[$id]['sku'] = $sku;
            }
        }
    }
    return $refs;
}

/**
 * @param array<int, array{sku:string,name:string}> $legacyRefs
 * @return array{map:array<int,int>, ambiguous:array<int,list<int>>, missing:array<int,string>}
 */
function build_product_map(mysqli $db, array $legacyRefs): array
{
    $bySku = [];
    $byName = [];
    $currentById = [];
    $result = $db->query('SELECT id, sku, name FROM products');
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $sku = normalized_key((string)$row['sku']);
        $name = normalized_key((string)$row['name']);
        $currentById[$id] = ['sku' => $sku, 'name' => $name];
        if ($sku !== '') {
            $bySku[$sku][] = $id;
        }
        if ($name !== '') {
            $byName[$name][] = $id;
        }
    }

    $map = [];
    $ambiguous = [];
    $missing = [];
    foreach ($legacyRefs as $legacyId => $ref) {
        $sku = normalized_key($ref['sku']);
        $name = normalized_key($ref['name']);
        $candidates = $sku !== '' ? array_values(array_unique($bySku[$sku] ?? [])) : [];

        if (count($candidates) === 1) {
            $map[$legacyId] = $candidates[0];
            continue;
        }
        if (count($candidates) > 1) {
            if (
                isset($currentById[$legacyId])
                && $sku !== ''
                && $currentById[$legacyId]['sku'] === $sku
                && in_array($legacyId, $candidates, true)
            ) {
                $map[$legacyId] = $legacyId;
            } else {
                $ambiguous[$legacyId] = $candidates;
            }
            continue;
        }

        $nameCandidates = $name !== '' ? array_values(array_unique($byName[$name] ?? [])) : [];
        if (count($nameCandidates) === 1) {
            $map[$legacyId] = $nameCandidates[0];
        } elseif (count($nameCandidates) > 1) {
            $ambiguous[$legacyId] = $nameCandidates;
        } else {
            $missing[$legacyId] = $ref['sku'] !== '' ? $ref['sku'] : $ref['name'];
        }
    }
    return ['map' => $map, 'ambiguous' => $ambiguous, 'missing' => $missing];
}

/** @return array{map:array<int,int>,missing:list<int>,ambiguous:array<string,list<int>>} */
function build_sale_map(mysqli $db): array
{
    $current = [];
    $result = $db->query("SELECT id, invoice_number FROM sales WHERE invoice_number IS NOT NULL AND invoice_number<>''");
    while ($row = $result->fetch_assoc()) {
        $current[trim((string)$row['invoice_number'])][] = (int)$row['id'];
    }

    $map = [];
    $missing = [];
    $ambiguous = [];
    $legacy = $db->query('SELECT SalId, SalInvoice FROM ame_stage_legacy_sale WHERE SalStatus=1');
    while ($row = $legacy->fetch_assoc()) {
        $legacyId = (int)$row['SalId'];
        $invoice = trim((string)$row['SalInvoice']);
        $matches = array_values(array_unique($current[$invoice] ?? []));
        if (count($matches) === 1) {
            $map[$legacyId] = $matches[0];
        } elseif ($matches === []) {
            $missing[] = $legacyId;
        } else {
            $ambiguous[$invoice] = $matches;
        }
    }
    return ['map' => $map, 'missing' => $missing, 'ambiguous' => $ambiguous];
}

/** @param array<int,int> $saleMap @param array<int,int> $productMap */
function preflight_items(mysqli $db, array $saleMap, array $productMap): array
{
    $stats = [
        'source_items' => 0,
        'mapped_items' => 0,
        'missing_sale_items' => 0,
        'missing_product_items' => 0,
        'inactive_sale_items' => 0,
        'missing_sale_ids' => [],
        'missing_product_ids' => [],
    ];
    $activeSales = [];
    $active = $db->query('SELECT SalId FROM ame_stage_legacy_sale WHERE SalStatus=1');
    while ($row = $active->fetch_assoc()) {
        $activeSales[(int)$row['SalId']] = true;
    }

    $items = $db->query('SELECT SalId, ProId FROM ame_stage_legacy_sale_item');
    while ($row = $items->fetch_assoc()) {
        $stats['source_items']++;
        $saleId = (int)$row['SalId'];
        $productId = (int)$row['ProId'];
        if (!isset($activeSales[$saleId])) {
            $stats['inactive_sale_items']++;
            continue;
        }
        $ok = true;
        if (!isset($saleMap[$saleId])) {
            $stats['missing_sale_items']++;
            $stats['missing_sale_ids'][$saleId] = true;
            $ok = false;
        }
        if (!isset($productMap[$productId])) {
            $stats['missing_product_items']++;
            $stats['missing_product_ids'][$productId] = true;
            $ok = false;
        }
        if ($ok) {
            $stats['mapped_items']++;
        }
    }
    $stats['missing_sale_ids'] = array_slice(array_map('intval', array_keys($stats['missing_sale_ids'])), 0, 100);
    $stats['missing_product_ids'] = array_slice(array_map('intval', array_keys($stats['missing_product_ids'])), 0, 100);
    return $stats;
}

function ensure_target_idempotency(mysqli $db): void
{
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM sale_items');
    while ($row = $result->fetch_assoc()) {
        $columns[] = (string)$row['Field'];
    }
    if (!in_array('legacy_key', $columns, true)) {
        $db->query('ALTER TABLE sale_items ADD COLUMN legacy_key VARCHAR(96) NULL DEFAULT NULL');
    }
    $index = $db->query("SHOW INDEX FROM sale_items WHERE Key_name='uniq_sale_item_legacy_key'");
    if ($index->num_rows === 0) {
        $db->query('ALTER TABLE sale_items ADD UNIQUE KEY uniq_sale_item_legacy_key (legacy_key)');
    }
}

/** @param array<int,int> $saleMap @param array<int,int> $productMap */
function apply_items(mysqli $db, array $saleMap, array $productMap): array
{
    ensure_target_idempotency($db);
    $stats = ['inserted' => 0, 'adopted' => 0, 'already_imported' => 0, 'conflicts' => 0];

    $existingKey = $db->prepare('SELECT id FROM sale_items WHERE legacy_key=? LIMIT 1');
    $exact = $db->prepare(
        'SELECT id FROM sale_items
         WHERE sale_id=? AND product_id=? AND qty=? AND purchase_price=? AND sale_price=?
           AND legacy_key IS NULL
         ORDER BY id ASC LIMIT 2'
    );
    $adopt = $db->prepare('UPDATE sale_items SET legacy_key=? WHERE id=? AND legacy_key IS NULL');
    $insert = $db->prepare(
        'INSERT INTO sale_items
         (sale_id, product_id, qty, purchase_price, sale_price, legacy_key)
         VALUES (?,?,?,?,?,?)'
    );

    $result = $db->query(
        'SELECT SiId, SalId, ProId, SiCost, SiMRP, SiQty
         FROM ame_stage_legacy_sale_item ORDER BY SiId ASC'
    );
    $batch = 0;
    $db->begin_transaction();
    try {
        while ($row = $result->fetch_assoc()) {
            $legacySale = (int)$row['SalId'];
            $legacyProduct = (int)$row['ProId'];
            if (!isset($saleMap[$legacySale], $productMap[$legacyProduct])) {
                continue;
            }
            $key = 'mae-sql:' . (int)$row['SiId'];
            $existingKey->bind_param('s', $key);
            $existingKey->execute();
            if ($existingKey->get_result()->fetch_assoc()) {
                $stats['already_imported']++;
                continue;
            }

            $saleId = $saleMap[$legacySale];
            $productId = $productMap[$legacyProduct];
            $qty = (float)$row['SiQty'];
            $cost = (float)$row['SiCost'];
            $price = (float)$row['SiMRP'];
            $exact->bind_param('iiddd', $saleId, $productId, $qty, $cost, $price);
            $exact->execute();
            $matches = $exact->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($matches) === 1) {
                $matchId = (int)$matches[0]['id'];
                $adopt->bind_param('si', $key, $matchId);
                $adopt->execute();
                $stats['adopted']++;
            } elseif (count($matches) > 1) {
                $stats['conflicts']++;
                continue;
            } else {
                $insert->bind_param('iiddds', $saleId, $productId, $qty, $cost, $price, $key);
                $insert->execute();
                $stats['inserted']++;
            }

            $batch++;
            if ($batch >= 1000) {
                $db->commit();
                $db->begin_transaction();
                $batch = 0;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $existingKey->close();
        $exact->close();
        $adopt->close();
        $insert->close();
    }
    return $stats;
}

function write_report(string $path, array $report): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Could not create report directory: {$dir}");
    }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException("Could not write report: {$path}");
    }
    @chmod($path, 0600);
}

$options = cli_options();
if (!extension_loaded('mbstring')) {
    fail('The mbstring PHP extension is required.');
}

$sourceDir = rtrim((string)$options['source-dir'], '/');
$files = [
    'product' => $sourceDir . '/product.sql',
    'sale' => $sourceDir . '/sale.sql',
    'sale_item' => $sourceDir . '/sale_item.sql',
];
foreach ($files as $file) {
    if (!is_file($file) || !is_readable($file)) {
        fail("Required private source file is missing: {$file}");
    }
}

$balancesPath = isset($options['balances-json']) ? (string)$options['balances-json'] : null;
$reportPath = isset($options['report']) && (string)$options['report'] !== ''
    ? (string)$options['report']
    : dirname(__DIR__, 2) . '/private_data/migration_reports/legacy-sale-items.json';
$apply = isset($options['apply']);
$allowUnresolved = isset($options['allow-unresolved']);
$keepStaging = isset($options['keep-staging']);

require dirname(__DIR__) . '/includes/db.php';
if (!isset($db) || !$db instanceof mysqli) {
    fail('Database connection was not created.');
}

$report = [
    'migration' => '002_import_legacy_sale_items',
    'mode' => $apply ? 'apply' : 'dry-run',
    'started_at' => date(DATE_ATOM),
    'source_hashes' => [],
];

try {
    foreach ($files as $name => $file) {
        $report['source_hashes'][$name] = hash_file('sha256', $file);
    }
    if ($balancesPath !== null && is_file($balancesPath)) {
        $report['source_hashes']['balances_json'] = hash_file('sha256', $balancesPath);
    }

    out('Preparing isolated staging tables...');
    create_staging_tables($db);
    $report['loaded'] = [
        'products' => load_insert_dump($db, $files['product'], 'product', 'ame_stage_legacy_product'),
        'sales' => load_insert_dump($db, $files['sale'], 'sale', 'ame_stage_legacy_sale'),
        'sale_items' => load_insert_dump($db, $files['sale_item'], 'sale_item', 'ame_stage_legacy_sale_item'),
    ];

    $legacyProducts = legacy_product_references($db, $balancesPath);
    $products = build_product_map($db, $legacyProducts);
    $sales = build_sale_map($db);
    $preflight = preflight_items($db, $sales['map'], $products['map']);

    $report['mapping'] = [
        'legacy_product_references' => count($legacyProducts),
        'mapped_products' => count($products['map']),
        'ambiguous_products' => count($products['ambiguous']),
        'missing_products' => count($products['missing']),
        'mapped_sales' => count($sales['map']),
        'missing_sales' => count($sales['missing']),
        'ambiguous_sales' => count($sales['ambiguous']),
        'ambiguous_product_ids' => array_slice(array_map('intval', array_keys($products['ambiguous'])), 0, 100),
        'missing_product_reference_ids' => array_slice(array_map('intval', array_keys($products['missing'])), 0, 100),
        'missing_sale_ids' => array_slice($sales['missing'], 0, 100),
    ];
    $report['preflight'] = $preflight;

    $unresolved = (int)$preflight['missing_sale_items'] + (int)$preflight['missing_product_items'];
    if ($apply && $unresolved > 0 && !$allowUnresolved) {
        throw new RuntimeException(
            "Refusing to apply: {$unresolved} item references are unresolved. "
            . 'Review the report or explicitly pass --allow-unresolved.'
        );
    }

    if ($apply) {
        out('Applying idempotent sale-item recovery...');
        $report['apply'] = apply_items($db, $sales['map'], $products['map']);
        if ((int)$report['apply']['conflicts'] > 0) {
            throw new RuntimeException('Ambiguous existing sale-item matches were found; review the report.');
        }
    }
    $report['finished_at'] = date(DATE_ATOM);
    $report['status'] = 'ok';
    write_report($reportPath, $report);
    out('Report: ' . $reportPath);
    out(json_encode([
        'mode' => $report['mode'],
        'loaded' => $report['loaded'],
        'mapping' => $report['mapping'],
        'preflight' => $report['preflight'],
        'apply' => $report['apply'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
    $report['finished_at'] = date(DATE_ATOM);
    $report['status'] = 'failed';
    $report['error'] = $e->getMessage();
    try {
        write_report($reportPath, $report);
    } catch (Throwable) {
        // Preserve the original failure.
    }
    fail($e->getMessage());
} finally {
    if (!$keepStaging && isset($db) && $db instanceof mysqli) {
        foreach (['ame_stage_legacy_sale_item', 'ame_stage_legacy_sale', 'ame_stage_legacy_product'] as $table) {
            try {
                $db->query("DROP TABLE IF EXISTS `{$table}`");
            } catch (Throwable) {
                // Cleanup failure must not hide the migration result.
            }
        }
    }
}
