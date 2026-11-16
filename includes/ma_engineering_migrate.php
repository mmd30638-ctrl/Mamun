<?php
/**
 * পুরনো মা ইঞ্জিনিয়ারিং POS → Aponshop
 */

function mae_core_tables(): array
{
    return [
        'customer', 'product', 'product_type', 'company', 'sale', 'sale_item',
        'sale_registered', 'sale_retail', 'purchase', 'purchase_item', 'pro_branch',
        'shop_info', 'user_info', 'emp_info',
    ];
}

function mae_is_legacy_dump(string $sql): bool
{
    $s = strtolower($sql);
    return (strpos($s, 'create table `customer`') !== false && strpos($s, 'cusid') !== false)
        || (strpos($s, 'create table `product`') !== false && strpos($s, 'procode') !== false);
}

function mae_legacy_exists(mysqli $db): bool
{
    $chk = $db->query("SHOW TABLES LIKE 'customer'");
    if (!$chk || $chk->num_rows === 0) {
        return false;
    }
    $col = $db->query("SHOW COLUMNS FROM `customer` LIKE 'CusId'");
    return $col && $col->num_rows > 0;
}

function mae_staging_prefix(): string
{
    return 'mae_';
}

function mae_table(mysqli $db, string $name, bool $staging): string
{
    if ($staging) {
        return mae_staging_prefix() . $name;
    }
    return $name;
}

function mae_sql_table(string $name): string
{
    $name = str_replace('`', '', $name);
    $parts = explode('.', $name);
    $out = [];
    foreach ($parts as $p) {
        $p = preg_replace('/[^A-Za-z0-9_]/', '', $p);
        if ($p !== '') {
            $out[] = '`' . $p . '`';
        }
    }
    return $out !== [] ? implode('.', $out) : '`customer`';
}

function mae_normalize_dump(string $sql): string
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*![\s\S]*?\*\//', '', $sql);
    $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;/im', '', $sql);
    return $sql;
}

function mae_extract_create_table(string $sql, string $table): ?string
{
    $pattern = '/CREATE TABLE\s+`' . preg_quote($table, '/') . '`\s*\(/i';
    if (!preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start = $m[0][1];
    $pos = $start + strlen($m[0][0]);
    $depth = 1;
    $len = strlen($sql);
    while ($pos < $len && $depth > 0) {
        $ch = $sql[$pos];
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        }
        $pos++;
    }
    while ($pos < $len && $sql[$pos] !== ';') {
        $pos++;
    }
    if ($pos < $len) {
        $pos++;
    }
    $stmt = trim(substr($sql, $start, $pos - $start));
    $stmt = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)\s*(ON DELETE\s+\w+)?\s*(ON UPDATE\s+\w+)?/i', '', $stmt);
    return $stmt !== '' ? $stmt : null;
}

function mae_extract_inserts(string $sql, string $table): array
{
    $out = [];
    $pattern = '/INSERT INTO\s+`' . preg_quote($table, '/') . '`/i';
    $offset = 0;
    $len = strlen($sql);
    while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $m[0][1];
        $pos = $start;
        $inString = false;
        $escape = false;
        $quote = '';
        while ($pos < $len) {
            $ch = $sql[$pos];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $quote) {
                    $inString = false;
                }
            } elseif ($ch === "'" || $ch === '"') {
                $inString = true;
                $quote = $ch;
            } elseif ($ch === ';') {
                $pos++;
                break;
            }
            $pos++;
        }
        $out[] = trim(substr($sql, $start, $pos - $start));
        $offset = $pos;
    }
    return $out;
}

/** শুধু পুরনো POS-এর প্রয়োজনীয় টেবিল রাখে — accounts ইত্যাদি Aponshop টেবিলে conflict করে না */
function mae_prepare_sql(string $sql): string
{
    $sql = mae_normalize_dump($sql);
    $parts = [];
    $prefix = mae_staging_prefix();

    foreach (mae_core_tables() as $table) {
        $create = mae_extract_create_table($sql, $table);
        if ($create !== null) {
            $parts[] = str_replace(
                "CREATE TABLE `$table`",
                'CREATE TABLE IF NOT EXISTS `' . $prefix . $table . '`',
                $create
            );
        }
        foreach (mae_extract_inserts($sql, $table) as $insert) {
            $parts[] = str_replace(
                "INSERT INTO `$table`",
                'INSERT INTO `' . $prefix . $table . '`',
                $insert
            );
        }
    }

    if ($parts === []) {
        throw new InvalidArgumentException('SQL-এ পুরনো POS টেবিল (customer, product, sale...) পাওয়া যায়নি।');
    }

    return "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n"
        . implode("\n", $parts)
        . "\nSET FOREIGN_KEY_CHECKS=1;\n";
}

function mae_core_table_list_bn(): string
{
    return implode(', ', mae_core_tables());
}

function mae_drop_staging(mysqli $db): void
{
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (mae_core_tables() as $table) {
        $db->query('DROP TABLE IF EXISTS `' . mae_staging_prefix() . $table . '`');
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}

function mae_stmt_end_offset(string $sql): ?int
{
    $len = strlen($sql);
    $inString = false;
    $escape = false;
    $quote = '';
    for ($pos = 0; $pos < $len; $pos++) {
        $ch = $sql[$pos];
        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === $quote) {
                $inString = false;
            }
        } elseif ($ch === "'" || $ch === '"') {
            $inString = true;
            $quote = $ch;
        } elseif ($ch === ';') {
            return $pos + 1;
        }
    }
    return null;
}

/** mysqldump CREATE/INSERT-এর আগে `;` ছাড়া `--` কমেন্ট থাকে — সেগুলো কেটে ম্যাচ করতে হয়। */
function mae_stmt_ltrim_sql(string $stmt): string
{
    $s = $stmt;
    $len = strlen($s);
    $i = 0;
    while ($i < $len) {
        while ($i < $len && ctype_space($s[$i])) {
            $i++;
        }
        if ($i >= $len) {
            break;
        }
        if ($s[$i] === '#' || (substr($s, $i, 2) === '--' && ($i + 2 >= $len || $s[$i + 2] === ' ' || $s[$i + 2] === "\t" || $s[$i + 2] === "\n" || $s[$i + 2] === "\r"))) {
            $nl = strpos($s, "\n", $i);
            $i = $nl === false ? $len : $nl + 1;
            continue;
        }
        if (substr($s, $i, 2) === '/*') {
            $end = strpos($s, '*/', $i + 2);
            $i = $end === false ? $len : $end + 2;
            continue;
        }
        break;
    }
    return $i === 0 ? $s : substr($s, $i);
}

function mae_core_table_lookup(): array
{
    static $set = null;
    if ($set === null) {
        $set = [];
        foreach (mae_core_tables() as $name) {
            $set[strtolower($name)] = $name;
        }
    }
    return $set;
}

function mae_stmt_core_table(string $stmt): ?string
{
    $stmt = mae_stmt_ltrim_sql($stmt);
    if (!preg_match(
        '/^(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT(?:\s+(?:IGNORE|DELAYED|HIGH_PRIORITY))*\s+INTO)\s+(?:`[^`]+`\.)?`([A-Za-z0-9_]+)`/i',
        $stmt,
        $m
    )) {
        return null;
    }
    $t = strtolower($m[1]);
    $set = mae_core_table_lookup();
    return $set[$t] ?? null;
}

function mae_split_reset_dir(string $outDir): void
{
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0755, true);
        return;
    }
    foreach (glob($outDir . '/*.sql') ?: [] as $f) {
        @unlink($f);
    }
}

function mae_split_written_tables(string $outDir): array
{
    $out = [];
    foreach (mae_core_tables() as $t) {
        $f = $outDir . '/' . $t . '.sql';
        if (is_file($f) && filesize($f) > 8) {
            $out[] = $t;
        }
    }
    return $out;
}

function mae_step_deadline(float $seconds = 8.0): float
{
    return microtime(true) + $seconds;
}

/**
 * Hostinger ~৩০সেকেন্ডে PHP কেটে দেয় — তাই অফসেট সেভ করে খণ্ডে খণ্ডে কাটে।
 *
 * @return array{offset:int,done:bool,written:array,bytes:int}
 */
function mae_split_dump_file_chunk(string $path, string $outDir, int $offset, float $deadline, bool $reset): array
{
    if (!is_file($path)) {
        throw new RuntimeException('SQL ফাইল খোলা যায়নি।');
    }
    if ($reset || $offset <= 0) {
        mae_split_reset_dir($outDir);
        $offset = 0;
    } elseif (!is_dir($outDir)) {
        @mkdir($outDir, 0755, true);
    }
    $bytes = (int) filesize($path);
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('SQL ফাইল খোলা যায়নি।');
    }
    if ($offset > 0 && fseek($fh, $offset) !== 0) {
        fclose($fh);
        throw new RuntimeException('SQL ফাইলের অফসেট পড়া যায়নি।');
    }
    $buf = '';
    $pos = $offset;
    $maxBuf = 8 * 1024 * 1024;
    while (!feof($fh) || $buf !== '') {
        if (!feof($fh) && strlen($buf) < 2 * 1024 * 1024) {
            $chunk = fread($fh, 512 * 1024);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
            }
        }
        $end = mae_stmt_end_offset($buf);
        if ($end === null) {
            if (feof($fh)) {
                $stmt = $buf;
                $buf = '';
            } elseif (strlen($buf) >= $maxBuf) {
                fclose($fh);
                throw new RuntimeException('SQL স্টেটমেন্ট ৮MB-এর বেশি — ফাইল ভাঙা যায়নি।');
            } else {
                continue;
            }
        } else {
            $stmt = substr($buf, 0, $end);
            $buf = substr($buf, $end);
        }
        $table = mae_stmt_core_table($stmt);
        if ($table !== null) {
            file_put_contents($outDir . '/' . $table . '.sql', $stmt . "\n", FILE_APPEND);
        }
        $pos += strlen($stmt);
        if (feof($fh) && $buf === '') {
            break;
        }
        if (microtime(true) >= $deadline) {
            break;
        }
    }
    $done = feof($fh) && $buf === '';
    fclose($fh);
    return [
        'offset' => $pos,
        'done' => $done,
        'written' => mae_split_written_tables($outDir),
        'bytes' => $bytes,
    ];
}

function mae_split_dump_file(string $path, string $outDir): array
{
    $offset = 0;
    $reset = true;
    $written = [];
    do {
        $chunk = mae_split_dump_file_chunk($path, $outDir, $offset, mae_step_deadline(45), $reset);
        $reset = false;
        $offset = (int) $chunk['offset'];
        $written = $chunk['written'];
    } while (empty($chunk['done']));
    return $written;
}

function mae_run_sql_chunks(mysqli $db, array $parts): void
{
    if ($parts === []) {
        return;
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    $batch = [];
    $flush = function () use ($db, &$batch): void {
        if ($batch === []) {
            return;
        }
        if (!$db->multi_query(implode("\n", $batch))) {
            throw new RuntimeException('স্টেজিং SQL: ' . $db->error);
        }
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
        if ($db->errno) {
            throw new RuntimeException('স্টেজিং SQL: ' . $db->error);
        }
        $batch = [];
    };
    foreach ($parts as $part) {
        $batch[] = $part;
        if (count($batch) >= 12) {
            $flush();
        }
    }
    $flush();
}

function mae_rewrite_staging_stmt(string $stmt, string $table): string
{
    $prefix = mae_staging_prefix();
    $quoted = preg_quote($table, '/');
    $stmt = preg_replace(
        '/\bCREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`' . $quoted . '`/i',
        'CREATE TABLE IF NOT EXISTS `' . $prefix . $table . '`',
        $stmt,
        1
    ) ?? $stmt;
    $stmt = preg_replace(
        '/\bINSERT(?:\s+IGNORE)?\s+INTO\s+`' . $quoted . '`/i',
        'INSERT IGNORE INTO `' . $prefix . $table . '`',
        $stmt,
        1
    ) ?? $stmt;
    return $stmt;
}

/**
 * একটি স্টেজিং টেবিল খণ্ডে খণ্ডে লোড — পুরো sale.sql মেমরিতে তোলে না।
 *
 * @return array{offset:int,done:bool,ran:int}
 */
function mae_load_staging_table_file_chunk(mysqli $db, string $file, string $table, int $offset, float $deadline): array
{
    if (!is_file($file) || filesize($file) < 8) {
        return ['offset' => 0, 'done' => true, 'ran' => 0];
    }
    $size = (int) filesize($file);
    if ($offset <= 0) {
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $db->query('DROP TABLE IF EXISTS `' . mae_staging_prefix() . $table . '`');
        $offset = 0;
    }
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException('স্টেজিং ফাইল খোলা যায়নি: ' . $table);
    }
    if ($offset > 0 && fseek($fh, $offset) !== 0) {
        fclose($fh);
        throw new RuntimeException('স্টেজিং অফসেট পড়া যায়নি: ' . $table);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    $buf = '';
    $pos = $offset;
    $ran = 0;
    $batch = [];
    $flush = function () use ($db, &$batch, &$ran): void {
        if ($batch === []) {
            return;
        }
        mae_run_sql_chunks($db, $batch);
        $ran += count($batch);
        $batch = [];
    };
    $maxBuf = 8 * 1024 * 1024;
    while (!feof($fh) || $buf !== '') {
        if (!feof($fh) && strlen($buf) < 2 * 1024 * 1024) {
            $chunk = fread($fh, 256 * 1024);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
            }
        }
        $end = mae_stmt_end_offset($buf);
        if ($end === null) {
            if (feof($fh)) {
                $stmt = trim($buf);
                $buf = '';
                $pos = $size;
            } elseif (strlen($buf) >= $maxBuf) {
                fclose($fh);
                throw new RuntimeException('স্টেজিং স্টেটমেন্ট খুব বড়: ' . $table);
            } else {
                continue;
            }
        } else {
            $stmt = trim(substr($buf, 0, $end));
            $buf = substr($buf, $end);
            $pos += $end;
        }
        if ($stmt !== '') {
            $batch[] = mae_rewrite_staging_stmt($stmt, $table);
            if (count($batch) >= 4) {
                $flush();
            }
        }
        if (feof($fh) && $buf === '') {
            break;
        }
        if (microtime(true) >= $deadline) {
            break;
        }
    }
    $flush();
    $done = feof($fh) && $buf === '';
    if ($done) {
        $pos = $size;
    }
    fclose($fh);
    return ['offset' => $pos, 'done' => $done, 'ran' => $ran];
}

function mae_load_staging_table_file(mysqli $db, string $file, string $table): void
{
    $offset = 0;
    do {
        $chunk = mae_load_staging_table_file_chunk($db, $file, $table, $offset, mae_step_deadline(45));
        $offset = (int) $chunk['offset'];
    } while (empty($chunk['done']));
}

function mae_fix_dumps_file_mistake(): void
{
    $paths = [];
    if (defined('AME_PRIVATE_DATA_DIR')) {
        $paths[] = rtrim((string) AME_PRIVATE_DATA_DIR, '/') . '/dumps';
    }
    $paths[] = dirname(__DIR__, 2) . '/private_data/dumps';
    foreach (array_unique($paths) as $p) {
        if (is_file($p) && !is_dir($p)) {
            @rename($p, $p . '_was_a_file.txt');
        }
        if (!is_dir($p)) {
            @mkdir($p, 0755, true);
        }
    }
}

function mae_sql_candidate_dirs(): array
{
    mae_fix_dumps_file_mistake();
    $home = dirname(__DIR__, 2);
    $web = dirname(__DIR__);
    $dirs = [];
    $add = static function (string $d) use (&$dirs): void {
        $d = rtrim($d, '/');
        if ($d !== '' && is_dir($d)) {
            $dirs[$d] = true;
        }
    };
    if (defined('AME_PRIVATE_DATA_DIR')) {
        $add((string) AME_PRIVATE_DATA_DIR);
        $add((string) AME_PRIVATE_DATA_DIR . '/dumps');
    }
    foreach ([
        $home,
        $home . '/private_data',
        $home . '/private_data/dumps',
        $home . '/tmp',
        $web,
        $web . '/uploads',
        $web . '/uploads/migrations',
        $web . '/tmp',
        sys_get_temp_dir(),
    ] as $d) {
        $add($d);
    }
    return array_keys($dirs);
}

function mae_list_server_sql_dumps(): array
{
    $out = [];
    $seen = [];
    foreach (mae_sql_candidate_dirs() as $dir) {
        foreach (glob($dir . '/*.sql') ?: [] as $path) {
            $real = realpath($path);
            if (!$real || isset($seen[$real])) {
                continue;
            }
            $seen[$real] = true;
            $out[] = [
                'path' => $real,
                'name' => basename($real),
                'mb' => round(filesize($real) / 1048576, 1),
            ];
        }
    }
    usort($out, static function (array $a, array $b): int {
        $score = static function (array $row): int {
            $n = strtolower((string) $row['name']);
            $s = 0;
            if (str_contains($n, 'maengineering')) {
                $s += 100;
            }
            if (str_contains($n, 'mamun')) {
                $s += 40;
            }
            $s += min(50, (int) ((float) $row['mb'] * 2));
            return $s;
        };
        return $score($b) <=> $score($a);
    });
    return $out;
}

function mae_pick_best_sql(): ?array
{
    $all = mae_list_server_sql_dumps();
    return $all[0] ?? null;
}

/** APONSHOP লাইভ টেবিলে এখন কত সারি আছে — ইনপুটের আগে/পরে দেখানোর জন্য। */
function mae_shop_live_counts(mysqli $db): array
{
    $map = [
        'customers' => 'customers',
        'products' => 'products',
        'sales' => 'sales',
        'purchases' => 'purchases',
        'suppliers' => 'suppliers',
    ];
    $out = [];
    foreach ($map as $key => $table) {
        $n = 0;
        try {
            $res = $db->query("SELECT COUNT(*) AS c FROM `$table`");
            if ($res && ($row = $res->fetch_assoc())) {
                $n = (int) ($row['c'] ?? 0);
            }
        } catch (Throwable $e) {
            $n = 0;
        }
        $out[$key] = $n;
    }
    return $out;
}

function mae_resolve_server_sql(string $name): ?string
{
    $base = basename(str_replace('\\', '/', $name));
    if ($base === '' || !preg_match('/\.sql$/i', $base)) {
        return null;
    }
    foreach (mae_list_server_sql_dumps() as $row) {
        if (strcasecmp($row['name'], $base) === 0) {
            return $row['path'];
        }
    }
    return null;
}

function mae_load_staging_single_table(mysqli $db, string $sql, string $table): void
{
    $prefix = mae_staging_prefix();
    $parts = [];
    $create = mae_extract_create_table($sql, $table);
    if ($create !== null) {
        $parts[] = str_replace(
            "CREATE TABLE `$table`",
            'CREATE TABLE IF NOT EXISTS `' . $prefix . $table . '`',
            $create
        );
    }
    foreach (mae_extract_inserts($sql, $table) as $insert) {
        $parts[] = str_replace(
            "INSERT INTO `$table`",
            'INSERT INTO `' . $prefix . $table . '`',
            $insert
        );
    }
    if ($parts === []) {
        return;
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    if (!$db->multi_query(implode("\n", $parts))) {
        throw new RuntimeException('স্টেজিং লোড (' . $table . '): ' . $db->error);
    }
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
    if ($db->errno) {
        throw new RuntimeException('স্টেজিং SQL (' . $table . '): ' . $db->error);
    }
}

function mae_load_staging(mysqli $db, string $sql): void
{
    if (!mae_is_legacy_dump($sql)) {
        throw new InvalidArgumentException('এটি পুরনো মা ইঞ্জিনিয়ারিং POS-এর SQL ডাম্প বলে মনে হচ্ছে না।');
    }
    mae_drop_staging($db);
    $prepared = mae_prepare_sql($sql);
    @set_time_limit(0);
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    if (!$db->multi_query($prepared)) {
        $err = $db->error;
        mae_drop_staging($db);
        throw new RuntimeException('স্টেজিং লোডে সমস্যা: ' . $err);
    }
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
    if ($db->errno) {
        mae_drop_staging($db);
        throw new RuntimeException('SQL চালানোর সময় সমস্যা: ' . $db->error);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}

function mae_count(mysqli $db, string $table, bool $staging): int
{
    $t = mae_table($db, $table, $staging);
    $res = $db->query("SELECT COUNT(*) AS c FROM `$t`");
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function mae_ensure_schemas(mysqli $db): void
{
    if (function_exists('ensure_customer_tables')) {
        require_once __DIR__ . '/../modules/customers/customer_schema.php';
        ensure_customer_tables($db);
    }
    if (function_exists('ensure_sales_schema')) {
        require_once __DIR__ . '/sales/sale_common.php';
        ensure_sales_schema($db);
    }
    if (function_exists('ensure_purchase_schema')) {
        require_once __DIR__ . '/purchase_functions.php';
        ensure_purchase_schema($db);
    }
    if (function_exists('ensure_user_schema')) {
        require_once __DIR__ . '/user_functions.php';
        ensure_user_schema($db);
    }
    if (function_exists('ensure_inventory_schema')) {
        require_once __DIR__ . '/inventory_functions.php';
        ensure_inventory_schema($db);
    }
}

function mae_table_columns(mysqli $db, string $table, bool $refresh = false): array
{
    static $cache = [];
    if ($refresh) {
        unset($cache[$table]);
    }
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $cols = [];
    $res = @$db->query("SHOW COLUMNS FROM `$table`");
    while ($res && ($row = $res->fetch_assoc())) {
        $cols[$row['Field']] = true;
    }
    $cache[$table] = $cols;
    return $cols;
}

function mae_ensure_column(mysqli $db, string $table, string $column, string $definition): void
{
    $cols = mae_table_columns($db, $table);
    if (!isset($cols[$column])) {
        @$db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        mae_table_columns($db, $table, true);
    }
}

function mae_patch_migration_schema(mysqli $db): void
{
    mae_ensure_schemas($db);

    $patches = [
        'sales' => [
            'invoice_number' => 'VARCHAR(50) NULL',
            'customer_id' => 'INT NULL',
            'customer_type' => "VARCHAR(20) NULL",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'completed'",
            'total_bill' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'discount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'net_payable' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'paid_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'due_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'sale_date' => 'DATETIME NULL',
            'created_at' => 'DATETIME NULL',
            'completed_at' => 'DATETIME NULL',
            'guest_customer_name' => 'VARCHAR(150) NULL',
        ],
        'sale_items' => [
            'sale_id' => 'INT NOT NULL',
            'product_id' => 'INT NULL',
            'qty' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'purchase_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'sale_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        ],
        'purchases' => [
            'supplier_id' => 'INT NULL',
            'memo_no' => 'VARCHAR(50) NULL',
            'product_id' => 'INT NOT NULL',
            'qty' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'purchase_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'wholesale_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'sale_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'total_cost' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'account_id' => 'INT NULL',
            'created_at' => 'DATETIME NULL',
            'paid_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'due_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'payment_type' => "VARCHAR(10) NOT NULL DEFAULT 'cash'",
            'notes' => 'VARCHAR(500) NULL',
        ],
    ];

    foreach ($patches as $table => $columns) {
        foreach ($columns as $name => $definition) {
            mae_ensure_column($db, $table, $name, $definition);
        }
    }
}

function mae_bind_type(mixed $value): string
{
    if (is_bool($value)) {
        return 'i';
    }
    if (is_int($value)) {
        return 'i';
    }
    if (is_float($value)) {
        return 'd';
    }
    if (is_string($value) && is_numeric($value)) {
        return str_contains($value, '.') ? 'd' : 'i';
    }
    return 's';
}

function mae_dynamic_insert(mysqli $db, string $table, array $data, bool $ignore = false): int
{
    $cols = mae_table_columns($db, $table);
    $fields = [];
    $values = [];
    $types = '';
    foreach ($data as $field => $value) {
        if (!isset($cols[$field])) {
            continue;
        }
        $fields[] = '`' . $field . '`';
        $values[] = $value;
        $types .= mae_bind_type($value);
    }
    if ($fields === []) {
        throw new RuntimeException($table . ' টেবিলে ইমপোর্টের জন্য কলাম পাওয়া যায়নি।');
    }
    $verb = $ignore ? 'INSERT IGNORE' : 'INSERT';
    $sql = $verb . ' INTO `' . $table . '` (' . implode(', ', $fields) . ') VALUES ('
        . implode(', ', array_fill(0, count($fields), '?')) . ')';
    $st = $db->prepare($sql);
    if (!$st) {
        throw new RuntimeException($table . ' INSERT তৈরি হয়নি: ' . $db->error);
    }
    $st->bind_param($types, ...$values);
    try {
        $st->execute();
        $id = (int)$st->insert_id;
        $st->close();
        return $id;
    } catch (Throwable $e) {
        $st->close();
        throw $e;
    }
}

function mae_default_account_id(mysqli $db): int
{
    $res = $db->query("SELECT id FROM accounts WHERE status='active' ORDER BY id ASC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return (int)$row['id'];
    }
    return 0;
}

function mae_ensure_default_account(mysqli $db): int
{
    $id = mae_default_account_id($db);
    if ($id > 0) {
        return $id;
    }
    $chk = $db->query("SHOW TABLES LIKE 'accounts'");
    if (!$chk || $chk->num_rows === 0) {
        return 0;
    }
    $name = 'ক্যাশ';
    $type = 'cash';
    $status = 'active';
    $st = $db->prepare('INSERT INTO accounts (account_name, account_type, status) VALUES (?,?,?)');
    if ($st) {
        $st->bind_param('sss', $name, $type, $status);
        $st->execute();
        $st->close();
    }
    return mae_default_account_id($db);
}

function mae_empty_stats(): array
{
    return [
        'source' => 'ma_engineering',
        'staging' => [],
        'settings' => [],
        'categories' => ['imported' => 0, 'skipped' => 0],
        'products' => ['imported' => 0, 'skipped' => 0, 'updated' => 0],
        'customers' => ['imported' => 0, 'skipped' => 0, 'updated' => 0],
        'suppliers' => ['imported' => 0, 'skipped' => 0, 'updated' => 0],
        'users' => ['imported' => 0, 'skipped' => 0],
        'purchases' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'sales' => ['imported' => 0, 'skipped' => 0],
        'sale_items' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
    ];
}

function mae_import_shop(mysqli $db, string $shop_t, array &$stats): void
{
    $res = $db->query("SELECT SinName, SinAddress, SinMobile, SinEmail FROM `$shop_t` WHERE SinStatus=1 ORDER BY SinId ASC LIMIT 1");
    if (!$res || !($row = $res->fetch_assoc())) {
        return;
    }
    $name = trim((string)$row['SinName']);
    $addr = trim((string)$row['SinAddress']);
    $phone = trim((string)$row['SinMobile']);
    $email = trim((string)$row['SinEmail']);
    if ($name !== '') {
        $st = $db->prepare('UPDATE settings SET shop_name=?, shop_address=?, shop_phone=? WHERE id=1');
        if ($st) {
            $st->bind_param('sss', $name, $addr, $phone);
            $st->execute();
            if ($st->affected_rows === 0) {
                $ins = $db->prepare('INSERT INTO settings (shop_name, shop_address, shop_phone) VALUES (?,?,?)');
                if ($ins) {
                    $ins->bind_param('sss', $name, $addr, $phone);
                    $ins->execute();
                    $ins->close();
                }
            }
            $st->close();
            $stats['settings']['shop_name'] = 1;
        }
    }
}

function mae_import_categories(mysqli $db, string $pt_t, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query("SELECT ProTypId, ProTypName FROM `$pt_t` WHERE ProTypStatus=1 ORDER BY ProTypId ASC");
    if (!$res) {
        return $map;
    }
    $ins = $db->prepare('INSERT INTO categories (name) VALUES (?)');
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)$row['ProTypName']);
        if ($name === '') {
            continue;
        }
        $old = (int)$row['ProTypId'];
        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM categories WHERE name=? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $name);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old] = (int)$found['id'];
                    $stats['categories']['skipped']++;
                    continue;
                }
            }
        }
        if ($ins) {
            $ins->bind_param('s', $name);
            $ins->execute();
            $map[$old] = (int)$ins->insert_id;
            $stats['categories']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mae_product_apply_stock_prices(
    mysqli $db,
    int $product_id,
    float $stock,
    float $purchase,
    float $wholesale,
    float $retail
): bool {
    if ($product_id <= 0) {
        return false;
    }
    $stock = round($stock, 3);
    $purchase = round($purchase, 2);
    $wholesale = round($wholesale, 2);
    $retail = round($retail, 2);
    $st = $db->prepare(
        'UPDATE products
         SET stock=?, purchase_price=?, wholesale_price=?, dealer_price=?, sale_price=?
         WHERE id=?
           AND (
             ABS(stock-?)>0.0009
             OR ABS(purchase_price-?)>0.009
             OR ABS(wholesale_price-?)>0.009
             OR ABS(sale_price-?)>0.009
           )'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param(
        'dddddidddd',
        $stock,
        $purchase,
        $wholesale,
        $wholesale,
        $retail,
        $product_id,
        $stock,
        $purchase,
        $wholesale,
        $retail
    );
    $st->execute();
    $changed = $st->affected_rows > 0;
    $st->close();
    return $changed;
}

function mae_customer_apply_master(
    mysqli $db,
    int $customer_id,
    string $name,
    string $phone,
    string $addr,
    float $due
): bool {
    if ($customer_id <= 0) {
        return false;
    }
    $due = round($due, 2);
    $st = $db->prepare(
        'UPDATE customers
         SET name=?, phone=?, address=?, receivable_balance=?
         WHERE id=?'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param('sssdi', $name, $phone, $addr, $due, $customer_id);
    $st->execute();
    $changed = $st->affected_rows > 0;
    $st->close();
    mae_ledger_align_due($db, $customer_id, $due);
    return $changed;
}

function mae_supplier_id_by_name(mysqli $db, string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $st = $db->prepare('SELECT id FROM suppliers WHERE TRIM(name)=? ORDER BY id ASC LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('s', $name);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int) ($row['id'] ?? 0);
}

function mae_supplier_apply_due(mysqli $db, int $supplier_id, string $phone, string $addr, float $due): bool
{
    if ($supplier_id <= 0) {
        return false;
    }
    $due = round($due, 2);
    $st = $db->prepare(
        'UPDATE suppliers SET phone=?, address=?, total_due=? WHERE id=?'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param('ssdi', $phone, $addr, $due, $supplier_id);
    $st->execute();
    $changed = $st->affected_rows > 0;
    $st->close();
    return $changed;
}

function mae_ledger_align_due(mysqli $db, int $customer_id, float $target): void
{
    if ($customer_id <= 0) {
        return;
    }
    $target = round($target, 2);
    $chk = @$db->query("SHOW TABLES LIKE 'customer_ledger'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }
    $openRef = 'OPEN-BACKFILL-' . $customer_id;
    $netEx = 0.0;
    $st = $db->prepare(
        'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS n
         FROM customer_ledger WHERE customer_id=? AND ref_no<>?'
    );
    if ($st) {
        $st->bind_param('is', $customer_id, $openRef);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $netEx = round((float) ($row['n'] ?? 0), 2);
    }
    $need = round($target - $netEx, 2);
    $find = $db->prepare('SELECT id FROM customer_ledger WHERE customer_id=? AND ref_no=? LIMIT 1');
    $openId = 0;
    if ($find) {
        $find->bind_param('is', $customer_id, $openRef);
        $find->execute();
        $orow = $find->get_result()->fetch_assoc();
        $find->close();
        $openId = (int) ($orow['id'] ?? 0);
    }
    if (abs($need) < 0.009) {
        if ($openId > 0) {
            $del = $db->prepare('DELETE FROM customer_ledger WHERE id=?');
            if ($del) {
                $del->bind_param('i', $openId);
                $del->execute();
                $del->close();
            }
        }
        return;
    }
    $debit = $need > 0 ? $need : 0.0;
    $credit = $need < 0 ? abs($need) : 0.0;
    $mod = $need > 0 ? 'সাবেক বকেয়া' : 'অগ্রিম জমা';
    $desc = $need > 0
        ? 'পুরনো POS ডাম্প থেকে সাবেক বকেয়া'
        : 'পুরনো POS ডাম্প থেকে অগ্রিম জমা';
    $openDate = '2026-09-19';
    $field = 'receivable_balance';
    if ($openId > 0) {
        $upd = $db->prepare(
            'UPDATE customer_ledger
             SET txn_date=?, module_type=?, description=?, debit=?, credit=?, running_balance=?
             WHERE id=?'
        );
        if ($upd) {
            $upd->bind_param('sssdddi', $openDate, $mod, $desc, $debit, $credit, $target, $openId);
            $upd->execute();
            $upd->close();
        }
        return;
    }
    $ins = $db->prepare(
        'INSERT INTO customer_ledger
            (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($ins) {
        $ins->bind_param(
            'issssddds',
            $customer_id,
            $openDate,
            $mod,
            $openRef,
            $desc,
            $debit,
            $credit,
            $target,
            $field
        );
        $ins->execute();
        $ins->close();
    }
}

function mae_stock_map(mysqli $db, string $pb_t): array
{
    $map = [];
    $res = $db->query("SELECT ProId, PbStock FROM `$pb_t`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['ProId']] = (float)$row['PbStock'];
        }
    }
    return $map;
}

function mae_normalize_sku(string $sku): string
{
    $sku = trim($sku);
    if ($sku === '') {
        return '';
    }
    $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $sku = str_replace($bn, $en, $sku);
    if (class_exists('Normalizer')) {
        $norm = Normalizer::normalize($sku, Normalizer::FORM_C);
        if (is_string($norm) && $norm !== '') {
            $sku = $norm;
        }
    }
    return $sku;
}

function mae_sku_key(string $sku): string
{
    return mb_strtolower(mae_normalize_sku($sku), 'UTF-8');
}

function mae_product_sku_index(mysqli $db): array
{
    $existing = [];
    $r = $db->query("SELECT sku, id FROM products WHERE sku IS NOT NULL AND sku != ''");
    while ($r && ($row = $r->fetch_assoc())) {
        $existing[mae_sku_key((string)$row['sku'])] = (int)$row['id'];
    }
    return $existing;
}

function mae_product_id_by_sku(mysqli $db, string $sku): int
{
    $sku = mae_normalize_sku($sku);
    if ($sku === '') {
        return 0;
    }
    $st = $db->prepare('SELECT id FROM products WHERE BINARY sku=? LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('s', $sku);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['id'] ?? 0);
}

function mae_is_duplicate_key_error(Throwable $e): bool
{
    if ((int)$e->getCode() === 1062) {
        return true;
    }
    return stripos($e->getMessage(), 'Duplicate entry') !== false;
}

function mae_product_insert_or_get(
    mysqli $db,
    string $sku,
    string $name,
    int $cat_bind,
    float $purchase,
    float $wholesale,
    float $retail,
    float $stk,
    string $status,
    int $legacy_id
): int {
    $sku = mae_normalize_sku($sku);
    if ($sku === '') {
        $sku = 'MAE-P' . str_pad((string)$legacy_id, 5, '0', STR_PAD_LEFT);
    }

    $found = mae_product_id_by_sku($db, $sku);
    if ($found > 0) {
        return $found;
    }

    $sql = 'INSERT IGNORE INTO products (sku, name, category_id, purchase_price, wholesale_price, dealer_price, sale_price, stock, min_stock, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 5, ?)';
    $ins = $db->prepare($sql);
    if (!$ins) {
        throw new RuntimeException('পণ্য স্টেটমেন্ট তৈরি হয়নি: ' . $db->error);
    }

    $try_skus = [$sku];
    $alt = mb_substr($sku, 0, 72, 'UTF-8') . '-M' . $legacy_id;
    if ($alt !== $sku) {
        $try_skus[] = $alt;
    }

    foreach ($try_skus as $try_sku) {
        $found = mae_product_id_by_sku($db, $try_sku);
        if ($found > 0) {
            $ins->close();
            return $found;
        }

        try {
            $ins->bind_param('ssiddddds', $try_sku, $name, $cat_bind, $purchase, $wholesale, $wholesale, $retail, $stk, $status);
            $ins->execute();
            $new_id = (int)$ins->insert_id;
            if ($new_id > 0) {
                $ins->close();
                return $new_id;
            }
            $found = mae_product_id_by_sku($db, $try_sku);
            if ($found > 0) {
                $ins->close();
                return $found;
            }
        } catch (mysqli_sql_exception $e) {
            if (mae_is_duplicate_key_error($e)) {
                $found = mae_product_id_by_sku($db, $try_sku);
                if ($found > 0) {
                    $ins->close();
                    return $found;
                }
                continue;
            }
            $ins->close();
            throw new RuntimeException('পণ্য ইমপোর্টে সমস্যা (' . $try_sku . '): ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            if (mae_is_duplicate_key_error($e)) {
                $found = mae_product_id_by_sku($db, $try_sku);
                if ($found > 0) {
                    $ins->close();
                    return $found;
                }
                continue;
            }
            $ins->close();
            throw $e;
        }
    }

    $ins->close();
    throw new RuntimeException('পণ্য ইমপোর্টে সমস্যা (' . $sku . '): ডুপ্লিকেট SKU মিলিয়ে নেওয়া যায়নি।');
}

function mae_import_products(mysqli $db, string $prod_t, string $pb_t, array $cat_map, array &$stats, bool $skip_existing): array
{
    $map = [];
    $stock = mae_stock_map($db, $pb_t);
    $existing = mae_product_sku_index($db);
    $res = $db->query(
        "SELECT ProId, ProCode, ProName, ProTypId, ProCost, ProRetMRP, ProWhoMRP, ProStatus
         FROM `$prod_t` ORDER BY ProId ASC"
    );
    if (!$res) {
        return $map;
    }
    while ($row = $res->fetch_assoc()) {
        $old = (int)$row['ProId'];
        $sku = mae_normalize_sku((string)$row['ProCode']);
        if ($sku === '') {
            $sku = 'MAE-P' . str_pad((string)$old, 5, '0', STR_PAD_LEFT);
        }
        $sku_key = mae_sku_key($sku);
        $purchase = (float)$row['ProCost'];
        $retail = (float)$row['ProRetMRP'];
        $wholesale = (float)$row['ProWhoMRP'];
        if ($wholesale <= 0) {
            $wholesale = $retail;
        }
        $stk = $stock[$old] ?? 0;
        $cat = (int) ($cat_map[(int) ($row['ProTypId'] ?? 0)] ?? 0);
        if (isset($existing[$sku_key])) {
            $map[$old] = $existing[$sku_key];
            if (mae_product_apply_stock_prices($db, (int) $existing[$sku_key], $stk, $purchase, $wholesale, $retail)) {
                $stats['products']['updated'] = ($stats['products']['updated'] ?? 0) + 1;
            } else {
                $stats['products']['skipped']++;
            }
            continue;
        }
        $status = (int)($row['ProStatus'] ?? 1) === 1 ? 'active' : 'inactive';
        $name = trim((string)$row['ProName']);
        $cat_bind = $cat > 0 ? $cat : 0;

        $product_id = mae_product_insert_or_get(
            $db, $sku, $name, $cat_bind, $purchase, $wholesale, $retail, $stk, $status, $old
        );
        $map[$old] = $product_id;
        $existing[$sku_key] = $product_id;
        $stats['products']['imported']++;
    }
    return $map;
}

function mae_customer_code_key(string $code): string
{
    return mb_strtolower(mae_normalize_sku($code), 'UTF-8');
}

function mae_fit_customer_code(string $raw, int $legacy_id): string
{
    $code = mae_normalize_sku($raw);
    if ($code === '' || mb_strlen($code, 'UTF-8') > 30) {
        return 'MAE-C-' . $legacy_id;
    }
    return $code;
}

function mae_customer_code_index(mysqli $db): array
{
    $existing = [];
    $r = $db->query("SELECT customer_code, id FROM customers WHERE customer_code IS NOT NULL AND customer_code != ''");
    while ($r && ($row = $r->fetch_assoc())) {
        $existing[mae_customer_code_key((string)$row['customer_code'])] = (int)$row['id'];
    }
    return $existing;
}

function mae_customer_id_by_code(mysqli $db, string $code): int
{
    $code = mae_normalize_sku($code);
    if ($code === '') {
        return 0;
    }
    $st = $db->prepare('SELECT id FROM customers WHERE customer_code=? LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['id'] ?? 0);
}

function mae_customer_insert_or_get(
    mysqli $db,
    string $raw_code,
    int $legacy_id,
    string $name,
    string $phone,
    string $addr,
    string $photo,
    float $due,
    string $note,
    int $status
): int {
    $primary = mae_fit_customer_code($raw_code, $legacy_id);
    $fallback = 'MAE-C-' . $legacy_id;
    $found_stable = mae_customer_id_by_code($db, $fallback);
    if ($found_stable > 0) {
        return $found_stable;
    }
    $try_codes = [];
    if ($primary !== '' && $primary !== $fallback && mae_customer_id_by_code($db, $primary) <= 0) {
        $try_codes[] = $primary;
    }
    $try_codes[] = $fallback;
    $try_codes = array_values(array_unique($try_codes));

    $ins = $db->prepare(
        'INSERT IGNORE INTO customers (customer_code, name, phone, address, photo, receivable_balance, notes, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$ins) {
        throw new RuntimeException('কাস্টমার স্টেটমেন্ট তৈরি হয়নি: ' . $db->error);
    }

    foreach ($try_codes as $code) {
        $found = mae_customer_id_by_code($db, $code);
        if ($found > 0) {
            $ins->close();
            return $found;
        }

        try {
            $ins->bind_param('sssssdsi', $code, $name, $phone, $addr, $photo, $due, $note, $status);
            $ins->execute();
            $new_id = (int)$ins->insert_id;
            if ($new_id > 0) {
                $ins->close();
                return $new_id;
            }
            $found = mae_customer_id_by_code($db, $code);
            if ($found > 0) {
                $ins->close();
                return $found;
            }
        } catch (mysqli_sql_exception $e) {
            if (mae_is_duplicate_key_error($e)) {
                $found = mae_customer_id_by_code($db, $code);
                if ($found > 0) {
                    $ins->close();
                    return $found;
                }
                continue;
            }
            $ins->close();
            throw new RuntimeException('কাস্টমার ইমপোর্টে সমস্যা (' . $code . '): ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            if (mae_is_duplicate_key_error($e)) {
                $found = mae_customer_id_by_code($db, $code);
                if ($found > 0) {
                    $ins->close();
                    return $found;
                }
                continue;
            }
            $ins->close();
            throw $e;
        }
    }

    $ins->close();
    throw new RuntimeException('কাস্টমার ইমপোর্টে সমস্যা (' . $primary . '): ডুপ্লিকেট কোড মিলিয়ে নেওয়া যায়নি।');
}

function mae_import_customers(mysqli $db, string $cus_t, array &$stats, bool $skip_existing): array
{
    $map = [];
    $existing = mae_customer_code_index($db);
    $first_by_code = [];
    $src = mae_sql_table($cus_t);
    $idx = $db->query("SELECT CusId, CusIdCode FROM $src ORDER BY CusId ASC");
    if ($idx) {
        while ($r = $idx->fetch_assoc()) {
            $lid = (int)$r['CusId'];
            $k = mae_customer_code_key(mae_fit_customer_code(trim((string)$r['CusIdCode']), $lid));
            if (!isset($first_by_code[$k])) {
                $first_by_code[$k] = $lid;
            }
        }
    }
    $res = $db->query(
        "SELECT CusId, CusIdCode, CusName, CusMobile, CusAddress, CusDue, CusPic, CusNote, CusStatus
         FROM $src ORDER BY CusId ASC"
    );
    if (!$res) {
        return $map;
    }
    while ($row = $res->fetch_assoc()) {
        $old = (int)$row['CusId'];
        $raw_code = trim((string)$row['CusIdCode']);
        $code = mae_fit_customer_code($raw_code, $old);
        $code_key = mae_customer_code_key($code);
        $stable_key = mae_customer_code_key('MAE-C-' . $old);
        $is_winner = ($first_by_code[$code_key] ?? $old) === $old;
        $name = function_exists('ame_clean_display_text')
            ? ame_clean_display_text((string)$row['CusName'])
            : trim((string)$row['CusName']);
        $phone = trim((string)$row['CusMobile']);
        $addr = function_exists('ame_clean_display_text')
            ? ame_clean_display_text((string)$row['CusAddress'])
            : trim((string)$row['CusAddress']);
        $photo = trim((string)$row['CusPic']);
        if ($photo !== '' && function_exists('ame_customer_photo_url') && ame_customer_photo_url($photo) === '') {
            $photo = '';
        }
        $due = (float)($row['CusDue'] ?? 0);
        if (isset($existing[$stable_key])) {
            $map[$old] = $existing[$stable_key];
            if (mae_customer_apply_master($db, (int) $existing[$stable_key], $name, $phone, $addr, $due)) {
                $stats['customers']['updated'] = ($stats['customers']['updated'] ?? 0) + 1;
            } else {
                $stats['customers']['skipped']++;
            }
            continue;
        }
        if ($is_winner && isset($existing[$code_key])) {
            $map[$old] = $existing[$code_key];
            if (mae_customer_apply_master($db, (int) $existing[$code_key], $name, $phone, $addr, $due)) {
                $stats['customers']['updated'] = ($stats['customers']['updated'] ?? 0) + 1;
            } else {
                $stats['customers']['skipped']++;
            }
            continue;
        }
        $note = function_exists('ame_clean_display_text')
            ? ame_clean_display_text((string)($row['CusNote'] ?? ''))
            : trim((string)($row['CusNote'] ?? ''));
        $status = (int)($row['CusStatus'] ?? 1) === 1 ? 1 : 0;

        $before = mae_customer_id_by_code($db, 'MAE-C-' . $old);
        $customer_id = mae_customer_insert_or_get(
            $db, $raw_code, $old, $name, $phone, $addr, $photo, $due, $note, $status
        );
        $map[$old] = $customer_id;
        $existing[mae_customer_code_key(mae_fit_customer_code($raw_code, $old))] = $customer_id;
        $existing[$stable_key] = $customer_id;
        if ($before > 0) {
            $stats['customers']['skipped']++;
        } else {
            mae_ledger_align_due($db, $customer_id, $due);
            $stats['customers']['imported']++;
        }
    }
    return $map;
}

function mae_import_suppliers(mysqli $db, string $com_t, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query(
        "SELECT ComId, ComName, ComMobile, ComAddress, ComDue, ComStatus FROM `$com_t` ORDER BY ComId ASC"
    );
    if (!$res) {
        return $map;
    }
    $ins = $db->prepare(
        'INSERT INTO suppliers (name, phone, address, total_due, status) VALUES (?, ?, ?, ?, ?)'
    );
    while ($row = $res->fetch_assoc()) {
        $old = (int)$row['ComId'];
        $name = trim((string)$row['ComName']);
        if ($name === '') {
            continue;
        }
        $phone = trim((string)$row['ComMobile']);
        $addr = trim((string)($row['ComAddress'] ?? ''));
        $due = (float)($row['ComDue'] ?? 0);
        if ($skip_existing) {
            $foundId = mae_supplier_id_by_name($db, $name);
            if ($foundId > 0) {
                $map[$old] = $foundId;
                if (mae_supplier_apply_due($db, $foundId, $phone, $addr, $due)) {
                    $stats['suppliers']['updated'] = ($stats['suppliers']['updated'] ?? 0) + 1;
                } else {
                    $stats['suppliers']['skipped']++;
                }
                continue;
            }
        }
        $status = (int)($row['ComStatus'] ?? 1) === 1 ? 'active' : 'inactive';
        if ($ins) {
            $ins->bind_param('sssds', $name, $phone, $addr, $due, $status);
            $ins->execute();
            $map[$old] = (int)$ins->insert_id;
            $stats['suppliers']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mae_map_user_role(int $us_type): string
{
    if ($us_type === 1) {
        return 'admin';
    }
    if ($us_type === 2) {
        return 'manager';
    }
    return 'employee';
}

function mae_unique_username(mysqli $db, string $base, int $id): string
{
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $base);
    if ($base === '') {
        $base = 'maeuser' . $id;
    }
    $try = strtolower(substr($base, 0, 40));
    $n = 1;
    while (true) {
        $st = $db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        if (!$st) {
            return $try;
        }
        $st->bind_param('s', $try);
        $st->execute();
        $exists = $st->get_result()->num_rows > 0;
        $st->close();
        if (!$exists) {
            return $try;
        }
        $n++;
        $try = substr($base, 0, 35) . $n;
    }
}

function mae_import_users(mysqli $db, string $ui_t, string $emp_t, array &$stats, bool $skip_existing): array
{
    $map = [];
    $sql = "SELECT u.UsInfoId, u.EiId, u.Password, u.UsType, u.UsStatus,
                   e.EiEmpId, e.EiMobNo, e.EiEmail
            FROM `$ui_t` u
            LEFT JOIN `$emp_t` e ON e.EiId = u.EiId
            ORDER BY u.UsInfoId ASC";
    $res = $db->query($sql);
    if (!$res) {
        return $map;
    }
    $ins = $db->prepare('INSERT INTO users (username, password, role, phone, email, status) VALUES (?,?,?,?,?,?)');
    while ($row = $res->fetch_assoc()) {
        $old = (int)$row['UsInfoId'];
        $username = mae_unique_username($db, (string)($row['EiEmpId'] ?? 'user' . $old), $old);
        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $username);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old] = (int)$found['id'];
                    $stats['users']['skipped']++;
                    continue;
                }
            }
        }
        $password = (string)$row['Password'];
        $role = mae_map_user_role((int)($row['UsType'] ?? 4));
        $phone = trim((string)($row['EiMobNo'] ?? ''));
        $email = trim((string)($row['EiEmail'] ?? ''));
        $status = (int)($row['UsStatus'] ?? 1) === 1 ? 1 : 0;
        if ($ins) {
            $ins->bind_param('sssssi', $username, $password, $role, $phone, $email, $status);
            $ins->execute();
            $map[$old] = (int)$ins->insert_id;
            $stats['users']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mae_import_purchases(
    mysqli $db,
    string $pur_t,
    string $pi_t,
    array $sup_map,
    array $prod_map,
    int $account_id,
    array &$stats,
    bool $skip_existing,
    int $offset = 0,
    int $limit = 0
): void {
    $sql = "SELECT pi.PiId, pi.PurId, pi.ProId, pi.PiQty, pi.PiItemTotal,
                   p.PurSupId, p.PurNetPay, p.PurCash, p.PurDue, p.PurInvoice, p.PurMoment
            FROM `$pi_t` pi
            JOIN `$pur_t` p ON p.PurId = pi.PurId
            WHERE p.PurStatus IN (1,2)
            ORDER BY pi.PiId ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(0, $offset) . ', ' . max(1, $limit);
    }
    $res = $db->query($sql);
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        $pur_id = (int)$row['PurId'];
        $line_id = (int)$row['PiId'];
        $prod_old = (int)$row['ProId'];
        if (!isset($prod_map[$prod_old])) {
            $stats['purchases']['errors']++;
            continue;
        }
        $invoice = trim((string)($row['PurInvoice'] ?? ''));
        $memo = 'MAE-P' . $pur_id . ($invoice !== '' ? '-' . $invoice : '') . '-L' . $line_id;
        if (mb_strlen($memo, 'UTF-8') > 50) {
            $memo = 'MAE-P' . $pur_id . '-L' . $line_id;
        }
        $sup_old = (int)($row['PurSupId'] ?? 0);
        $sup_new = ($sup_old > 0 && isset($sup_map[$sup_old])) ? $sup_map[$sup_old] : 0;
        $prod_new = $prod_map[$prod_old];
        $chk = $db->prepare('SELECT id FROM purchases WHERE supplier_id <=> ? AND memo_no=? LIMIT 1');
        if ($chk) {
            $chk->bind_param('is', $sup_new, $memo);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($found) {
                $stats['purchases']['skipped']++;
                continue;
            }
        }
        $qty = max(0.01, (float)$row['PiQty']);
        $line_total = (float)$row['PiItemTotal'];
        $unit = round($line_total / $qty, 2);
        $pur_total = (float)($row['PurNetPay'] ?? 0);
        $pur_paid = (float)($row['PurCash'] ?? 0);
        $ratio = $pur_total > 0 ? ($line_total / $pur_total) : 1;
        $line_paid = round($pur_paid * $ratio, 2);
        $line_due = round($line_total - $line_paid, 2);
        $pr = $db->prepare("SELECT wholesale_price, sale_price FROM products WHERE id=? LIMIT 1");
        $wholesale = $unit;
        $sale = $unit;
        if ($pr) {
            $pr->bind_param('i', $prod_new);
            $pr->execute();
            $prow = $pr->get_result()->fetch_assoc();
            $pr->close();
            if ($prow) {
                $wholesale = (float)($prow['wholesale_price'] ?? $unit);
                $sale = (float)($prow['sale_price'] ?? $unit);
            }
        }
        $created_at = $row['PurMoment'] ?? date('Y-m-d H:i:s');
        $ptype = $line_due > 0.009 ? 'credit' : 'cash';
        $notes = 'মা ইঞ্জিনিয়ারিং ইমপোর্ট';
        $acc = $account_id > 0 ? $account_id : 0;
        try {
            $new_id = mae_dynamic_insert($db, 'purchases', [
                'supplier_id' => $sup_new,
                'memo_no' => $memo,
                'product_id' => $prod_new,
                'qty' => $qty,
                'purchase_price' => $unit,
                'wholesale_price' => $wholesale,
                'sale_price' => $sale,
                'total_cost' => $line_total,
                'account_id' => $acc,
                'created_at' => $created_at,
                'paid_amount' => $line_paid,
                'due_amount' => $line_due,
                'payment_type' => $ptype,
                'notes' => $notes,
            ], true);
            if ($new_id > 0) {
                $stats['purchases']['imported']++;
            } else {
                $stats['purchases']['skipped']++;
            }
        } catch (Throwable $e) {
            if (mae_is_duplicate_key_error($e)) {
                $stats['purchases']['skipped']++;
                continue;
            }
            throw new RuntimeException('ক্রয় ইমপোর্টে সমস্যা (' . $memo . '): ' . $e->getMessage(), 0, $e);
        }
    }
}

function mae_sale_customer_maps(mysqli $db, string $reg_t, string $ret_t): array
{
    $reg = [];
    $ret = [];
    $r1 = $db->query("SELECT SalId, CusId FROM `$reg_t`");
    if ($r1) {
        while ($row = $r1->fetch_assoc()) {
            $reg[(int)$row['SalId']] = (int)$row['CusId'];
        }
    }
    $r2 = $db->query("SELECT SalId, SrCusName FROM `$ret_t`");
    if ($r2) {
        while ($row = $r2->fetch_assoc()) {
            $ret[(int)$row['SalId']] = trim((string)$row['SrCusName']);
        }
    }
    return [$reg, $ret];
}

function mae_import_sales_batch(
    mysqli $db,
    string $sale_t,
    string $si_t,
    array $cust_map,
    array $prod_map,
    array $sale_reg,
    array $sale_ret,
    array &$stats,
    bool $skip_existing,
    int $offset,
    int $limit
): void {
    $res = $db->query(
        "SELECT SalId, SalInvoice, SalDate, CusType, SalType, SalSubTotal, SalSpeDisc, SalNetPay, SalCash, SalDue, SalMoment, SalStatus
         FROM `$sale_t`
         WHERE SalStatus=1
         ORDER BY SalId ASC
         LIMIT $offset, $limit"
    );
    if (!$res) {
        return;
    }
    $sale_map = [];
    while ($row = $res->fetch_assoc()) {
        $old = (int)$row['SalId'];
        $invoice = trim((string)$row['SalInvoice']);
        if ($invoice === '') {
            $invoice = 'MAE-S' . str_pad((string)$old, 7, '0', STR_PAD_LEFT);
        }
        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM sales WHERE invoice_number=? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $invoice);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $sale_map[$old] = (int)$found['id'];
                    $stats['sales']['skipped']++;
                }
            }
        }
        if (!isset($sale_map[$old])) {
            $cust_new = 0;
            $guest = '';
            $cus_type = (int)($row['CusType'] ?? 1);
            if ($cus_type === 2 && isset($sale_reg[$old])) {
                $c_old = $sale_reg[$old];
                if (isset($cust_map[$c_old])) {
                    $cust_new = $cust_map[$c_old];
                }
            } elseif (isset($sale_ret[$old])) {
                $guest = $sale_ret[$old];
            }
            $ctype = (int)($row['SalType'] ?? 1) === 2 ? 'wholesale' : 'retail';
            $sub = (float)($row['SalSubTotal'] ?? 0);
            $disc = (float)($row['SalSpeDisc'] ?? 0);
            $net = (float)($row['SalNetPay'] ?? 0);
            $paid = (float)($row['SalCash'] ?? 0);
            $due = (float)($row['SalDue'] ?? 0);
            $bill_date = $row['SalDate'] ?? $row['SalMoment'];
            $created = $row['SalMoment'] ?? $row['SalDate'];
            if ($bill_date === null || $bill_date === '') {
                $bill_date = $created;
            }
            if ($created === null || $created === '') {
                $created = date('Y-m-d H:i:s');
                $bill_date = $created;
            }
            $status = 'completed';
            try {
                $new_id = mae_dynamic_insert($db, 'sales', [
                    'invoice_number' => $invoice,
                    'customer_id' => $cust_new,
                    'customer_type' => $ctype,
                    'status' => $status,
                    'total_bill' => $sub,
                    'discount' => $disc,
                    'net_payable' => $net,
                    'paid_amount' => $paid,
                    'due_amount' => $due,
                    'sale_date' => $bill_date,
                    'created_at' => $created,
                    'completed_at' => $bill_date,
                    'guest_customer_name' => $guest,
                ], true);
                if ($new_id <= 0) {
                    $chk = $db->prepare('SELECT id FROM sales WHERE invoice_number=? LIMIT 1');
                    if ($chk) {
                        $chk->bind_param('s', $invoice);
                        $chk->execute();
                        $found = $chk->get_result()->fetch_assoc();
                        $chk->close();
                        $new_id = (int)($found['id'] ?? 0);
                    }
                }
                if ($new_id <= 0) {
                    $stats['sales']['errors']++;
                    continue;
                }
                $sale_map[$old] = $new_id;
                $stats['sales']['imported']++;
            } catch (Throwable $e) {
                if (mae_is_duplicate_key_error($e)) {
                    $chk = $db->prepare('SELECT id FROM sales WHERE invoice_number=? LIMIT 1');
                    if ($chk) {
                        $chk->bind_param('s', $invoice);
                        $chk->execute();
                        $found = $chk->get_result()->fetch_assoc();
                        $chk->close();
                        if ($found) {
                            $sale_map[$old] = (int)$found['id'];
                            $stats['sales']['skipped']++;
                        } else {
                            $stats['sales']['errors']++;
                        }
                    }
                    if (!isset($sale_map[$old])) {
                        continue;
                    }
                } else {
                    throw new RuntimeException('বিক্রয় ইমপোর্টে সমস্যা (' . $invoice . '): ' . $e->getMessage(), 0, $e);
                }
            }
        }
        if (!isset($sale_map[$old])) {
            continue;
        }
        $items = $db->prepare("SELECT SiId, ProId, SiCost, SiMRP, SiQty FROM `$si_t` WHERE SalId=?");
        if (!$items) {
            continue;
        }
        $items->bind_param('i', $old);
        $items->execute();
        $ir = $items->get_result();
        while ($it = $ir->fetch_assoc()) {
            $p_old = (int)$it['ProId'];
            if (!isset($prod_map[$p_old])) {
                $stats['sale_items']['errors']++;
                continue;
            }
            $qty = (float)$it['SiQty'];
            $sale_p = (float)$it['SiMRP'];
            $cost = (float)$it['SiCost'];
            $p_new = $prod_map[$p_old];
            $sid = $sale_map[$old];
            try {
                mae_dynamic_insert($db, 'sale_items', [
                    'sale_id' => $sid,
                    'product_id' => $p_new,
                    'qty' => $qty,
                    'purchase_price' => $cost,
                    'sale_price' => $sale_p,
                    'legacy_key' => 'mae-sql:' . (int)$it['SiId'],
                ], true);
                $stats['sale_items']['imported']++;
            } catch (Throwable $e) {
                if (mae_is_duplicate_key_error($e)) {
                    $stats['sale_items']['skipped'] = ($stats['sale_items']['skipped'] ?? 0) + 1;
                    continue;
                }
                $stats['sale_items']['errors']++;
            }
        }
        $items->close();
    }
}

function mae_recent_gap_path(): string
{
    return dirname(__DIR__) . '/data/mae_gap_20260919.json';
}

function mae_recent_gap_available(): bool
{
    $p = mae_recent_gap_path();
    return is_file($p) && filesize($p) > 100;
}

function mae_shop_last_sale(mysqli $db): array
{
    $row = ['id' => 0, 'invoice' => '', 'sale_date' => ''];
    if ($res = $db->query('SELECT id, invoice_number, sale_date FROM sales ORDER BY sale_date DESC, id DESC LIMIT 1')) {
        $r = $res->fetch_assoc();
        if ($r) {
            $row['id'] = (int) $r['id'];
            $row['invoice'] = (string) ($r['invoice_number'] ?? '');
            $row['sale_date'] = (string) ($r['sale_date'] ?? '');
        }
    }
    return $row;
}

function mae_shop_needs_sep19(mysqli $db): bool
{
    $last = mae_shop_last_sale($db);
    $day = substr($last['sale_date'], 0, 10);
    if ($day !== '' && strcmp($day, '2026-09-19') >= 0) {
        $chk = $db->prepare("SELECT id FROM sales WHERE invoice_number='S060623' LIMIT 1");
        if ($chk) {
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();
            return !$found;
        }
        return false;
    }
    return true;
}

/**
 * @return array{ok:bool,message:string,stats:array}
 */
function mae_apply_recent_gap(mysqli $db): array
{
    $path = mae_recent_gap_path();
    if (!is_file($path)) {
        return ['ok' => false, 'message' => '১৯ তারিখের গ্যাপ ফাইল পাওয়া যায়নি।', 'stats' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || empty($data['sales'])) {
        return ['ok' => false, 'message' => 'গ্যাপ ফাইল পড়া যায়নি।', 'stats' => []];
    }
    mae_patch_migration_schema($db);
    $stats = [
        'customers' => ['imported' => 0, 'skipped' => 0],
        'sales' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'sale_items' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
    ];

    $custMap = [];
    foreach ((array) ($data['customers'] ?? []) as $c) {
        $legacy = (int) ($c['legacy_id'] ?? 0);
        if ($legacy <= 0) {
            continue;
        }
        $id = mae_customer_insert_or_get(
            $db,
            (string) ($c['code'] ?? ''),
            $legacy,
            (string) ($c['name'] ?? ''),
            (string) ($c['phone'] ?? ''),
            (string) ($c['address'] ?? ''),
            '',
            (float) ($c['due'] ?? 0),
            'MAE 19 Sep gap',
            1
        );
        $custMap[$legacy] = $id;
        if ($id > 0) {
            $stats['customers']['imported']++;
        }
    }

    $prodMap = [];
    foreach ((array) ($data['products'] ?? []) as $p) {
        $legacy = (int) ($p['legacy_id'] ?? 0);
        $sku = (string) ($p['sku'] ?? '');
        if ($legacy <= 0) {
            continue;
        }
        $pid = $sku !== '' ? mae_product_id_by_sku($db, $sku) : 0;
        if ($pid <= 0) {
            $alt = 'MAE-P' . str_pad((string) $legacy, 5, '0', STR_PAD_LEFT);
            $pid = mae_product_id_by_sku($db, $alt);
        }
        if ($pid > 0) {
            $prodMap[$legacy] = $pid;
        }
    }

    foreach ((array) $data['sales'] as $s) {
        $invoice = trim((string) ($s['invoice'] ?? ''));
        if ($invoice === '') {
            continue;
        }
        $chk = $db->prepare('SELECT id FROM sales WHERE invoice_number=? LIMIT 1');
        $existing = 0;
        if ($chk) {
            $chk->bind_param('s', $invoice);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();
            $existing = (int) ($found['id'] ?? 0);
        }
        $newId = $existing;
        if ($existing > 0) {
            $stats['sales']['skipped']++;
        } else {
            $legacyCus = (int) ($s['customer_legacy'] ?? 0);
            $custNew = ($legacyCus > 0 && isset($custMap[$legacyCus])) ? $custMap[$legacyCus] : 0;
            if ($custNew <= 0 && $legacyCus > 0) {
                $custNew = mae_customer_id_by_code($db, 'MAE-C-' . $legacyCus);
            }
            $bill = (string) ($s['sale_date'] ?: $s['saved_at'] ?? '');
            $saved = (string) ($s['saved_at'] ?: $s['sale_date'] ?? '');
            if ($bill === '') {
                $bill = date('Y-m-d H:i:s');
            }
            $ctype = ((int) ($s['sal_type'] ?? 1) === 2) ? 'wholesale' : 'retail';
            try {
                $newId = mae_dynamic_insert($db, 'sales', [
                    'invoice_number' => $invoice,
                    'customer_id' => $custNew,
                    'customer_type' => $ctype,
                    'status' => 'completed',
                    'total_bill' => (float) ($s['subtotal'] ?? 0),
                    'discount' => (float) ($s['discount'] ?? 0),
                    'net_payable' => (float) ($s['net'] ?? 0),
                    'paid_amount' => (float) ($s['paid'] ?? 0),
                    'due_amount' => (float) ($s['due'] ?? 0),
                    'sale_date' => $bill,
                    'created_at' => $saved,
                    'completed_at' => $bill,
                    'guest_customer_name' => (string) ($s['guest'] ?? ''),
                ], true);
            } catch (Throwable $e) {
                if (mae_is_duplicate_key_error($e)) {
                    $retry = $db->prepare('SELECT id FROM sales WHERE invoice_number=? LIMIT 1');
                    if ($retry) {
                        $retry->bind_param('s', $invoice);
                        $retry->execute();
                        $newId = (int)($retry->get_result()->fetch_assoc()['id'] ?? 0);
                        $retry->close();
                    }
                    $stats['sales']['skipped']++;
                } else {
                    $stats['sales']['errors']++;
                    continue;
                }
            }
        }
        if ($newId <= 0) {
            $stats['sales']['errors']++;
            continue;
        }
        if ($existing <= 0) {
            $stats['sales']['imported']++;
        }
        foreach ((array) ($s['items'] ?? []) as $itemIndex => $it) {
            $pOld = (int) ($it['pro_id'] ?? 0);
            $pNew = $prodMap[$pOld] ?? 0;
            if ($pNew <= 0) {
                $stats['sale_items']['errors']++;
                continue;
            }
            try {
                mae_dynamic_insert($db, 'sale_items', [
                    'sale_id' => $newId,
                    'product_id' => $pNew,
                    'qty' => (float) ($it['qty'] ?? 0),
                    'purchase_price' => (float) ($it['cost'] ?? 0),
                    'sale_price' => (float) ($it['mrp'] ?? 0),
                    'legacy_key' => 'mae-gap:' . (int)($s['legacy_id'] ?? 0) . ':' . (int)$itemIndex,
                ], true);
                $stats['sale_items']['imported']++;
            } catch (Throwable $e) {
                if (mae_is_duplicate_key_error($e)) {
                    $stats['sale_items']['skipped']++;
                } else {
                    $stats['sale_items']['errors']++;
                }
            }
        }
    }

    $stats['purchases'] = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    $supMap = [];
    foreach ((array) ($data['suppliers'] ?? []) as $sup) {
        $name = trim((string) ($sup['name'] ?? ''));
        $legacySup = (int) ($sup['legacy_id'] ?? 0);
        if ($name === '') {
            continue;
        }
        $sid = 0;
        $st = $db->prepare('SELECT id FROM suppliers WHERE name=? LIMIT 1');
        if ($st) {
            $st->bind_param('s', $name);
            $st->execute();
            $found = $st->get_result()->fetch_assoc();
            $st->close();
            $sid = (int) ($found['id'] ?? 0);
        }
        if ($sid > 0 && $legacySup > 0) {
            $supMap[$legacySup] = $sid;
        }
    }
    foreach ((array) ($data['purchases'] ?? []) as $pur) {
        $purId = (int) ($pur['legacy_id'] ?? 0);
        $invoice = trim((string) ($pur['invoice'] ?? ''));
        $supNew = $supMap[(int) ($pur['sup_id'] ?? 0)] ?? 0;
        $created = (string) ($pur['moment'] ?: $pur['pur_date'] ?? '');
        $purTotal = (float) ($pur['net'] ?? 0);
        $purPaid = (float) ($pur['cash'] ?? 0);
        foreach ((array) ($pur['items'] ?? []) as $line) {
            $lineId = (int) ($line['line_id'] ?? 0);
            $memo = 'MAE-P' . $purId . ($invoice !== '' ? '-' . $invoice : '') . '-L' . $lineId;
            if (mb_strlen($memo, 'UTF-8') > 50) {
                $memo = 'MAE-P' . $purId . '-L' . $lineId;
            }
            $chk = $db->prepare('SELECT id FROM purchases WHERE supplier_id <=> ? AND memo_no=? LIMIT 1');
            if ($chk) {
                $chk->bind_param('is', $supNew, $memo);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $stats['purchases']['skipped']++;
                    continue;
                }
            }
            $pOld = (int) ($line['pro_id'] ?? 0);
            $pNew = $prodMap[$pOld] ?? 0;
            if ($pNew <= 0) {
                $stats['purchases']['errors']++;
                continue;
            }
            $qty = max(0.01, (float) ($line['qty'] ?? 0));
            $lineTotal = (float) ($line['total'] ?? 0);
            $unit = round($lineTotal / $qty, 2);
            $ratio = $purTotal > 0 ? ($lineTotal / $purTotal) : 1;
            $linePaid = round($purPaid * $ratio, 2);
            $lineDue = round($lineTotal - $linePaid, 2);
            $pr = $db->prepare('SELECT wholesale_price, sale_price FROM products WHERE id=? LIMIT 1');
            $wholesale = $unit;
            $sale = $unit;
            if ($pr) {
                $pr->bind_param('i', $pNew);
                $pr->execute();
                $prow = $pr->get_result()->fetch_assoc();
                $pr->close();
                if ($prow) {
                    $wholesale = (float) ($prow['wholesale_price'] ?? $unit);
                    $sale = (float) ($prow['sale_price'] ?? $unit);
                }
            }
            try {
                $newPur = mae_dynamic_insert($db, 'purchases', [
                    'supplier_id' => $supNew,
                    'memo_no' => $memo,
                    'product_id' => $pNew,
                    'qty' => $qty,
                    'purchase_price' => $unit,
                    'wholesale_price' => $wholesale,
                    'sale_price' => $sale,
                    'total_cost' => $lineTotal,
                    'created_at' => $created !== '' ? $created : date('Y-m-d H:i:s'),
                    'paid_amount' => $linePaid,
                    'due_amount' => $lineDue,
                    'payment_type' => $lineDue > 0.009 ? 'credit' : 'cash',
                    'notes' => 'মা ইঞ্জিনিয়ারিং ইমপোর্ট',
                ], true);
                if ($newPur > 0) {
                    $stats['purchases']['imported']++;
                } else {
                    $stats['purchases']['skipped']++;
                }
            } catch (Throwable $e) {
                if (mae_is_duplicate_key_error($e)) {
                    $stats['purchases']['skipped']++;
                } else {
                    $stats['purchases']['errors']++;
                }
            }
        }
    }

    $n = (int) $stats['sales']['imported'];
    $c = (int) $stats['customers']['imported'];
    $p = (int) $stats['purchases']['imported'];
    $msg = $n . 'টি বিক্রয়, ' . $p . 'টি ক্রয়, ' . $c . 'টি কাস্টমার বসানো হয়েছে।';
    if ($n === 0 && $p === 0 && ((int) $stats['sales']['skipped'] + (int) $stats['purchases']['skipped']) > 0) {
        $msg = 'এই বিলগুলো আগেই আছে।';
    }
    return ['ok' => true, 'message' => $msg, 'stats' => $stats];
}

function mae_align_all_mismatched_ledgers(mysqli $db): int
{
    $chk = @$db->query("SHOW TABLES LIKE 'customer_ledger'");
    if (!$chk || $chk->num_rows === 0) {
        return 0;
    }
    $res = @$db->query(
        "SELECT c.id, c.receivable_balance
         FROM customers c
         LEFT JOIN customer_ledger l ON l.customer_id = c.id
         WHERE c.is_customer = 1
         GROUP BY c.id, c.receivable_balance
         HAVING ABS(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0)) > 0.009"
    );
    if (!$res) {
        return 0;
    }
    $n = 0;
    while ($row = $res->fetch_assoc()) {
        mae_ledger_align_due($db, (int) $row['id'], (float) $row['receivable_balance']);
        $n++;
    }
    return $n;
}

function mae_balances_path(): string
{
    return dirname(__DIR__) . '/data/mae_balances_20260919.json';
}

function mae_balances_available(): bool
{
    $p = mae_balances_path();
    return is_file($p) && filesize($p) > 100;
}

function mae_customer_id_from_legacy(mysqli $db, int $legacy, string $raw_code): int
{
    if ($legacy > 0) {
        $id = mae_customer_id_by_code($db, 'MAE-C-' . $legacy);
        if ($id > 0) {
            return $id;
        }
    }
    $fitted = mae_fit_customer_code($raw_code, $legacy);
    if ($fitted !== '') {
        $id = mae_customer_id_by_code($db, $fitted);
        if ($id > 0) {
            return $id;
        }
    }
    $raw = mae_normalize_sku($raw_code);
    if ($raw !== '' && $raw !== $fitted) {
        $id = mae_customer_id_by_code($db, $raw);
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

function mae_product_id_from_legacy(mysqli $db, int $legacy, string $sku): int
{
    $sku = mae_normalize_sku($sku);
    if ($sku !== '') {
        $id = mae_product_id_by_sku($db, $sku);
        if ($id > 0) {
            return $id;
        }
    }
    if ($legacy > 0) {
        $alt = 'MAE-P' . str_pad((string) $legacy, 5, '0', STR_PAD_LEFT);
        $id = mae_product_id_by_sku($db, $alt);
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

/**
 * ডাম্পের CusDue / ComDue / PbStock সব সারিতে লিখে লেজার মিলায়।
 *
 * @return array{ok:bool,message:string,stats:array}
 */
function mae_apply_balances_stock(mysqli $db): array
{
    $path = mae_balances_path();
    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'ব্যালেন্স/স্টক ফাইল পাওয়া যায়নি।', 'stats' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'ব্যালেন্স ফাইল পড়া যায়নি।', 'stats' => []];
    }
    mae_patch_migration_schema($db);
    @set_time_limit(90);
    $stats = [
        'customers' => ['updated' => 0, 'imported' => 0, 'skipped' => 0, 'missing' => 0],
        'suppliers' => ['updated' => 0, 'imported' => 0, 'skipped' => 0, 'missing' => 0],
        'products' => ['updated' => 0, 'imported' => 0, 'skipped' => 0, 'missing' => 0],
    ];

    foreach ((array) ($data['customers'] ?? []) as $c) {
        $legacy = (int) ($c['legacy_id'] ?? 0);
        $code = (string) ($c['code'] ?? '');
        $name = trim((string) ($c['name'] ?? ''));
        $phone = trim((string) ($c['phone'] ?? ''));
        $addr = trim((string) ($c['address'] ?? ''));
        $due = (float) ($c['due'] ?? 0);
        if ($legacy <= 0 && $code === '') {
            continue;
        }
        $id = mae_customer_id_from_legacy($db, $legacy, $code);
        if ($id <= 0) {
            $id = mae_customer_insert_or_get(
                $db,
                $code,
                $legacy > 0 ? $legacy : 0,
                $name !== '' ? $name : ('কাস্টমার ' . $legacy),
                $phone,
                $addr,
                '',
                $due,
                'MAE dump balance',
                1
            );
            if ($id > 0) {
                mae_customer_apply_master($db, $id, $name !== '' ? $name : ('কাস্টমার ' . $legacy), $phone, $addr, $due);
                $stats['customers']['imported']++;
            } else {
                $stats['customers']['missing']++;
            }
            continue;
        }
        if (mae_customer_apply_master($db, $id, $name !== '' ? $name : 'কাস্টমার', $phone, $addr, $due)) {
            $stats['customers']['updated']++;
        } else {
            $stats['customers']['skipped']++;
        }
    }

    foreach ((array) ($data['suppliers'] ?? []) as $s) {
        $name = trim((string) ($s['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $phone = trim((string) ($s['phone'] ?? ''));
        $addr = trim((string) ($s['address'] ?? ''));
        $due = (float) ($s['due'] ?? 0);
        $id = mae_supplier_id_by_name($db, $name);
        if ($id <= 0) {
            $ins = $db->prepare(
                'INSERT INTO suppliers (name, phone, address, total_due, status) VALUES (?, ?, ?, ?, ?)'
            );
            if ($ins) {
                $status = 'active';
                $ins->bind_param('sssds', $name, $phone, $addr, $due, $status);
                $ins->execute();
                $id = (int) $ins->insert_id;
                $ins->close();
            }
            if ($id > 0) {
                $stats['suppliers']['imported']++;
            } else {
                $stats['suppliers']['missing']++;
            }
            continue;
        }
        if (mae_supplier_apply_due($db, $id, $phone, $addr, $due)) {
            $stats['suppliers']['updated']++;
        } else {
            $stats['suppliers']['skipped']++;
        }
    }

    $usedProducts = [];
    foreach ((array) ($data['products'] ?? []) as $p) {
        $legacy = (int) ($p['legacy_id'] ?? 0);
        $sku = (string) ($p['sku'] ?? '');
        $name = trim((string) ($p['name'] ?? ''));
        $stk = (float) ($p['stock'] ?? 0);
        $cost = (float) ($p['cost'] ?? 0);
        $wholesale = (float) ($p['wholesale'] ?? 0);
        $retail = (float) ($p['retail'] ?? 0);
        if ($wholesale <= 0) {
            $wholesale = $retail;
        }
        $id = mae_product_id_from_legacy($db, $legacy, $sku);
        if ($id > 0 && isset($usedProducts[$id])) {
            $id = 0;
        }
        if ($id <= 0) {
            $id = mae_product_insert_or_get(
                $db,
                $sku,
                $name !== '' ? $name : ('পণ্য ' . $legacy),
                0,
                $cost,
                $wholesale,
                $retail,
                $stk,
                'active',
                $legacy
            );
            if ($id > 0 && isset($usedProducts[$id])) {
                $altSku = 'MAE-P' . str_pad((string) $legacy, 5, '0', STR_PAD_LEFT);
                $id = mae_product_insert_or_get(
                    $db,
                    $altSku,
                    $name !== '' ? $name : ('পণ্য ' . $legacy),
                    0,
                    $cost,
                    $wholesale,
                    $retail,
                    $stk,
                    'active',
                    $legacy
                );
            }
            if ($id > 0) {
                mae_product_apply_stock_prices($db, $id, $stk, $cost, $wholesale, $retail);
                $usedProducts[$id] = $legacy;
                $stats['products']['imported']++;
            } else {
                $stats['products']['missing']++;
            }
            continue;
        }
        $nm = $db->prepare('UPDATE products SET name=? WHERE id=? AND BINARY name<>?');
        if ($nm && $name !== '') {
            $nm->bind_param('sis', $name, $id, $name);
            $nm->execute();
            $nm->close();
        }
        if (mae_product_apply_stock_prices($db, $id, $stk, $cost, $wholesale, $retail)) {
            $stats['products']['updated']++;
        } else {
            $stats['products']['skipped']++;
        }
        $usedProducts[$id] = $legacy;
    }
    $stats['ledgers'] = ['aligned' => mae_align_all_mismatched_ledgers($db)];

    $cu = (int) $stats['customers']['updated'] + (int) $stats['customers']['imported'];
    $su = (int) $stats['suppliers']['updated'] + (int) $stats['suppliers']['imported'];
    $pu = (int) $stats['products']['updated'] + (int) $stats['products']['imported'];
    $msg = $cu . 'টি কাস্টমার বকেয়া, ' . $su . 'টি সাপ্লায়ার বকেয়া, ' . $pu . 'টি পণ্য স্টক মিলানো হয়েছে।';
    return ['ok' => true, 'message' => $msg, 'stats' => $stats];
}

/**
 * @return array{ok:bool,message:string,stats:array}
 */
function mae_apply_dump_fixall(mysqli $db): array
{
    $parts = [];
    $stats = [];
    if (mae_recent_gap_available()) {
        $g = mae_apply_recent_gap($db);
        $stats['gap'] = $g['stats'] ?? [];
        if (!empty($g['message'])) {
            $parts[] = (string) $g['message'];
        }
        if (empty($g['ok'])) {
            return $g;
        }
    }
    if (mae_balances_available()) {
        $b = mae_apply_balances_stock($db);
        $stats['balances'] = $b['stats'] ?? [];
        if (!empty($b['message'])) {
            $parts[] = (string) $b['message'];
        }
        if (empty($b['ok'])) {
            return $b + ['stats' => $stats];
        }
    } elseif ($parts === []) {
        return ['ok' => false, 'message' => 'মিলানোর ফাইল ZIP-এ নেই। আবার সহজ আপডেট করুন।', 'stats' => []];
    }
    return [
        'ok' => true,
        'message' => implode(' ', $parts),
        'stats' => $stats,
    ];
}

function mae_run_migration(mysqli $db, array $options = []): array
{
    $opts = array_merge([
        'import_settings' => true,
        'import_master' => true,
        'import_transactions' => true,
        'skip_existing' => true,
        'use_existing_tables' => false,
        'sql' => null,
    ], $options);

    $stats = mae_empty_stats();
    mae_patch_migration_schema($db);
    $staging = false;

    if (!empty($opts['use_existing_tables'])) {
        if (!mae_legacy_exists($db)) {
            throw new InvalidArgumentException('পুরনো POS টেবিল পাওয়া যায়নি। phpMyAdmin থেকে SQL ইমপোর্ট করুন।');
        }
    } elseif (!empty($opts['sql'])) {
        mae_load_staging($db, (string)$opts['sql']);
        $staging = true;
    } else {
        throw new InvalidArgumentException('SQL ফাইল বা বিদ্যমান টেবিল দরকার।');
    }

    foreach (mae_core_tables() as $t) {
        $stats['staging'][$t] = mae_count($db, $t, $staging);
    }

    $shop_t = mae_table($db, 'shop_info', $staging);
    $pt_t = mae_table($db, 'product_type', $staging);
    $prod_t = mae_table($db, 'product', $staging);
    $pb_t = mae_table($db, 'pro_branch', $staging);
    $cus_t = mae_table($db, 'customer', $staging);
    $com_t = mae_table($db, 'company', $staging);
    $ui_t = mae_table($db, 'user_info', $staging);
    $emp_t = mae_table($db, 'emp_info', $staging);
    $pur_t = mae_table($db, 'purchase', $staging);
    $pi_t = mae_table($db, 'purchase_item', $staging);
    $sale_t = mae_table($db, 'sale', $staging);
    $si_t = mae_table($db, 'sale_item', $staging);
    $reg_t = mae_table($db, 'sale_registered', $staging);
    $ret_t = mae_table($db, 'sale_retail', $staging);

    @set_time_limit(0);

    if ($opts['import_settings']) {
        mae_import_shop($db, $shop_t, $stats);
    }

    $cat_map = [];
    $prod_map = [];
    $cust_map = [];
    $sup_map = [];

    if ($opts['import_master']) {
        $cat_map = mae_import_categories($db, $pt_t, $stats, (bool)$opts['skip_existing']);
        $prod_map = mae_import_products($db, $prod_t, $pb_t, $cat_map, $stats, (bool)$opts['skip_existing']);
        $cust_map = mae_import_customers($db, $cus_t, $stats, (bool)$opts['skip_existing']);
        $sup_map = mae_import_suppliers($db, $com_t, $stats, (bool)$opts['skip_existing']);
        mae_import_users($db, $ui_t, $emp_t, $stats, (bool)$opts['skip_existing']);
    }

    if ($opts['import_transactions']) {
        $account_id = mae_default_account_id($db);
        if ($account_id <= 0 && mae_count($db, 'purchase_item', $staging) > 0) {
            throw new RuntimeException('ক্রয় ইমপোর্টের জন্য Accounts মেনু থেকে একটি সক্রিয় অ্যাকাউন্ট তৈরি করুন।');
        }
        $db->begin_transaction();
        try {
            mae_import_purchases($db, $pur_t, $pi_t, $sup_map, $prod_map, $account_id, $stats, (bool)$opts['skip_existing']);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        [$sale_reg, $sale_ret] = mae_sale_customer_maps($db, $reg_t, $ret_t);
        $cnt_res = $db->query("SELECT COUNT(*) AS c FROM `$sale_t` WHERE SalStatus=1");
        $total_sales = $cnt_res ? (int)($cnt_res->fetch_assoc()['c'] ?? 0) : 0;
        $batch = 300;
        for ($off = 0; $off < $total_sales; $off += $batch) {
            $db->begin_transaction();
            try {
                mae_import_sales_batch(
                    $db, $sale_t, $si_t, $cust_map, $prod_map, $sale_reg, $sale_ret,
                    $stats, (bool)$opts['skip_existing'], $off, $batch
                );
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }
        }
    }

    if ($staging) {
        mae_drop_staging($db);
    }

    return $stats;
}

function mae_preview(mysqli $db, bool $staging = false): array
{
    return [
        'customers' => mae_count($db, 'customer', $staging),
        'products' => mae_count($db, 'product', $staging),
        'sales' => mae_count($db, 'sale', $staging),
        'sale_items' => mae_count($db, 'sale_item', $staging),
        'suppliers' => mae_count($db, 'company', $staging),
        'purchases' => mae_count($db, 'purchase', $staging),
    ];
}

function mae_migration_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/migrations';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function mae_migration_jobs_dir(): string
{
    $dir = mae_migration_upload_dir() . '/jobs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function mae_migration_current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function mae_migration_job_path(string $job_id): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $job_id);
    return mae_migration_jobs_dir() . '/' . $safe . '.json';
}

function mae_migration_save_job(array $job): array
{
    if (empty($job['id'])) {
        $job['id'] = 'mj_' . bin2hex(random_bytes(8));
    }
    $job['user_id'] = mae_migration_current_user_id();
    $job['updated_at'] = date('c');
    $path = mae_migration_job_path((string)$job['id']);
    file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE));
    $_SESSION['mae_migration_job_id'] = $job['id'];
    $_SESSION['mae_migration_job'] = $job;
    return $job;
}

function mae_migration_load_job(string $job_id): ?array
{
    if ($job_id === '') {
        return null;
    }
    $path = mae_migration_job_path($job_id);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    if ((int)($data['user_id'] ?? 0) !== mae_migration_current_user_id()) {
        return null;
    }
    return $data;
}

function mae_migration_delete_job(string $job_id): void
{
    if ($job_id === '') {
        return;
    }
    @unlink(mae_migration_job_path($job_id));
    if (($_SESSION['mae_migration_job_id'] ?? '') === $job_id) {
        unset($_SESSION['mae_migration_job_id'], $_SESSION['mae_migration_job']);
    }
}

function mae_migration_find_latest_open_job(): ?array
{
    $uid = mae_migration_current_user_id();
    if ($uid <= 0) {
        return null;
    }
    $best = null;
    $best_time = 0;
    foreach (glob(mae_migration_jobs_dir() . '/*.json') ?: [] as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        if ((int)($data['user_id'] ?? 0) !== $uid) {
            continue;
        }
        if (($data['phase'] ?? '') === 'done') {
            continue;
        }
        $t = strtotime((string)($data['updated_at'] ?? '')) ?: 0;
        if ($t >= $best_time) {
            $best_time = $t;
            $best = $data;
        }
    }
    return $best;
}

function mae_migration_resolve_job(?string $job_id = null): ?array
{
    if ($job_id !== null && $job_id !== '') {
        $job = mae_migration_load_job($job_id);
        if ($job) {
            return $job;
        }
    }
    $session_id = (string)($_SESSION['mae_migration_job_id'] ?? '');
    if ($session_id !== '') {
        $job = mae_migration_load_job($session_id);
        if ($job) {
            return $job;
        }
    }
    if (!empty($_SESSION['mae_migration_job']) && is_array($_SESSION['mae_migration_job'])) {
        $job = $_SESSION['mae_migration_job'];
        if (!empty($job['id'])) {
            return mae_migration_load_job((string)$job['id']) ?: $job;
        }
        return $job;
    }
    return mae_migration_find_latest_open_job();
}

function mae_migration_job_response(array $job, string $message = '', bool $done = false): array
{
    return [
        'ok' => true,
        'done' => $done,
        'job_id' => (string)($job['id'] ?? ''),
        'message' => $message,
        'progress' => mae_migration_progress($job),
        'stats' => $job['stats'] ?? mae_empty_stats(),
        'resumable' => ($job['phase'] ?? '') !== 'done',
        'last_error' => (string)($job['last_error'] ?? ''),
    ];
}

function mae_migration_maps_pack(array $maps): array
{
    $out = [];
    foreach ($maps as $key => $map) {
        $out[$key] = [];
        foreach ($map as $old => $new) {
            $out[$key][(string)$old] = (int)$new;
        }
    }
    return $out;
}

function mae_migration_maps_unpack(array $maps): array
{
    $out = [];
    foreach ($maps as $key => $map) {
        $out[$key] = [];
        foreach ($map as $old => $new) {
            $out[$key][(int)$old] = (int)$new;
        }
    }
    return $out;
}

function mae_migration_new_job(array $options): array
{
    return [
        'id' => 'mj_' . bin2hex(random_bytes(8)),
        'source' => 'ma_engineering',
        'options' => $options,
        'phase' => 'init',
        'created_at' => date('c'),
        'last_error' => '',
        'staging' => !empty($options['use_existing_tables']),
        'staging_loaded' => [],
        'sql_path' => null,
        'sql_owned' => false,
        'stats' => mae_empty_stats(),
        'maps' => ['cat' => [], 'prod' => [], 'cust' => [], 'sup' => []],
        'sale_reg' => [],
        'sale_ret' => [],
        'sales_offset' => 0,
        'sales_total' => 0,
        'account_id' => 0,
        'label' => 'Ma Engineering Import',
        'split_offset' => 0,
        'split_done' => false,
        'split_started' => false,
        'split_ver' => 2,
        'split_bytes' => 0,
        'load_offset' => 0,
        'load_ran' => 0,
    ];
}

function mae_migration_progress(array $job): array
{
    $phase = (string)($job['phase'] ?? 'init');
    $labels = [
        'init' => 'প্রস্তুতি',
        'staging_prep' => 'স্টেজিং প্রস্তুতি',
        'staging' => 'SQL টেবিল লোড',
        'count_staging' => 'ডাটা গণনা',
        'wipe' => 'নতুন সাইটের দোকান খাতা মুছছি',
        'settings' => 'সেটিংস',
        'master_categories' => 'ক্যাটাগরি',
        'master_products' => 'পণ্য',
        'master_customers' => 'কাস্টমার',
        'master_suppliers' => 'সাপ্লায়ার',
        'master_users' => 'ইউজার',
        'purchases' => 'ক্রয়',
        'sales' => 'বিক্রয়',
        'cleanup' => 'পরিষ্কার',
        'done' => 'সম্পন্ন',
    ];
    $pct = 5;
    if ($phase === 'staging_prep') {
        $size = max(1, (int) ($job['split_bytes'] ?? 0));
        $off = (int) ($job['split_offset'] ?? 0);
        $pct = 2 + (int) round(min(1, $off / $size) * 10);
    } elseif ($phase === 'staging') {
        $total = count(mae_core_tables());
        $done = count($job['staging_loaded'] ?? []);
        $partial = 0.0;
        $splitDir = (string) ($job['split_dir'] ?? '');
        $pending = array_values(array_diff(mae_core_tables(), $job['staging_loaded'] ?? []));
        if ($pending !== [] && $splitDir !== '') {
            $cur = $splitDir . '/' . $pending[0] . '.sql';
            $fs = is_file($cur) ? (int) filesize($cur) : 0;
            if ($fs > 0) {
                $partial = min(1, (int) ($job['load_offset'] ?? 0) / $fs);
            }
        }
        $pct = 12 + (int) round((($done + $partial) / max(1, $total)) * 20);
    } elseif ($phase === 'wipe') {
        $total = max(1, count(mae_replace_wipe_tables()));
        $done = (int) ($job['wipe_index'] ?? 0);
        $pct = 32 + (int) round(min(1, $done / $total) * 8);
    } elseif ($phase === 'purchases') {
        $total = max(1, (int) ($job['purchase_total'] ?? 0));
        $off = (int) ($job['purchase_offset'] ?? 0);
        $pct = 42 + (int) round(min(1, $off / $total) * 12);
    } elseif ($phase === 'sales') {
        $total = max(1, (int)($job['sales_total'] ?? 0));
        $off = (int)($job['sales_offset'] ?? 0);
        $pct = 55 + (int)round(min(1, $off / $total) * 40);
    } elseif (isset($labels[$phase])) {
        $order = array_keys($labels);
        $idx = array_search($phase, $order, true);
        $pct = 5 + (int)round(($idx / max(1, count($order) - 1)) * 90);
    }
    if ($phase === 'done') {
        $pct = 100;
    }
    return [
        'phase' => $phase,
        'label' => $labels[$phase] ?? $phase,
        'percent' => min(100, max(0, $pct)),
        'sales_offset' => (int)($job['sales_offset'] ?? 0),
        'sales_total' => (int)($job['sales_total'] ?? 0),
    ];
}

function mae_migration_table_names(mysqli $db, array $job): array
{
    $staging = !empty($job['staging']);
    return [
        'shop' => mae_table($db, 'shop_info', $staging),
        'pt' => mae_table($db, 'product_type', $staging),
        'prod' => mae_table($db, 'product', $staging),
        'pb' => mae_table($db, 'pro_branch', $staging),
        'cus' => mae_table($db, 'customer', $staging),
        'com' => mae_table($db, 'company', $staging),
        'ui' => mae_table($db, 'user_info', $staging),
        'emp' => mae_table($db, 'emp_info', $staging),
        'pur' => mae_table($db, 'purchase', $staging),
        'pi' => mae_table($db, 'purchase_item', $staging),
        'sale' => mae_table($db, 'sale', $staging),
        'si' => mae_table($db, 'sale_item', $staging),
        'reg' => mae_table($db, 'sale_registered', $staging),
        'ret' => mae_table($db, 'sale_retail', $staging),
    ];
}

function mae_replace_wipe_tables(): array
{
    return [
        'sale_return_items',
        'sale_returns',
        'sale_returns_v2',
        'sale_items',
        'sale_payments',
        'sale_services',
        'sale_warranties',
        'sales',
        'purchase_return_items',
        'purchase_returns',
        'purchase_return_logs',
        'purchase_expenses',
        'purchases',
        'unit_stock_ledger',
        'product_unit_prices',
        'product_unit_factors',
        'product_unit_map',
        'products',
        'customer_ledger',
        'customer_call_log',
        'installment_contracts',
        'customers',
        'supplier_documents',
        'suppliers',
        'account_ledger',
        'categories',
    ];
}

function mae_replace_wipe_one(mysqli $db, string $table): void
{
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if ($table === '' || str_starts_with($table, 'mae_')) {
        return;
    }
    $chk = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    if (!$db->query('TRUNCATE TABLE `' . $table . '`')) {
        $db->query('DELETE FROM `' . $table . '`');
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}

function mae_migration_step(mysqli $db, array &$job): array
{
    @set_time_limit(25);
    mae_patch_migration_schema($db);
    $opts = $job['options'] ?? [];
    $stats = &$job['stats'];
    $maps = mae_migration_maps_unpack($job['maps'] ?? []);
    $message = '';
    $tables = mae_migration_table_names($db, $job);
    $deadline = mae_step_deadline(8.0);

    if (
        in_array((string) ($job['phase'] ?? ''), ['init', 'staging_prep', 'staging'], true)
        && (int) ($job['split_ver'] ?? 0) < 2
        && !empty($job['sql_path'])
    ) {
        $job['split_ver'] = 2;
        $job['split_started'] = false;
        $job['split_offset'] = 0;
        $job['split_done'] = false;
        $job['staging_loaded'] = [];
        $job['load_offset'] = 0;
        $job['phase'] = 'staging_prep';
    }

    switch ($job['phase']) {
        case 'init':
            if (!empty($opts['use_existing_tables'])) {
                if (!mae_legacy_exists($db)) {
                    throw new InvalidArgumentException('পুরনো POS টেবিল পাওয়া যায়নি।');
                }
                $job['staging'] = true;
                $job['phase'] = 'count_staging';
            } elseif (!empty($job['sql_path']) && is_file($job['sql_path'])) {
                $job['phase'] = 'staging_prep';
            } else {
                throw new InvalidArgumentException('SQL ফাইল পাওয়া যায়নি।');
            }
            break;

        case 'staging_prep':
            $split = mae_migration_jobs_dir() . '/split_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job['id']);
            $job['split_dir'] = $split;
            $reset = empty($job['split_started']);
            if ($reset) {
                mae_drop_staging($db);
                $job['staging_loaded'] = [];
                $job['split_offset'] = 0;
                $job['load_offset'] = 0;
                $job['split_started'] = true;
                $job['split_ver'] = 2;
            }
            if (empty($job['sql_path']) || !is_file((string) $job['sql_path'])) {
                throw new InvalidArgumentException('SQL ফাইল পাওয়া যায়নি।');
            }
            $chunk = mae_split_dump_file_chunk(
                (string) $job['sql_path'],
                $split,
                (int) ($job['split_offset'] ?? 0),
                $deadline,
                $reset
            );
            $job['split_offset'] = (int) $chunk['offset'];
            $job['split_bytes'] = (int) $chunk['bytes'];
            $job['split_written'] = $chunk['written'];
            if (!empty($chunk['done'])) {
                $job['split_done'] = true;
                $job['phase'] = 'staging';
                $n = count($chunk['written']);
                $message = 'SQL কেটে ' . $n . 'টি টেবিল আলাদা';
            } else {
                $size = max(1, (int) $chunk['bytes']);
                $pct = (int) round(min(100, ((int) $chunk['offset'] / $size) * 100));
                $message = 'SQL পড়ছি ' . $pct . '%';
            }
            break;

        case 'staging':
            $pending = array_values(array_diff(mae_core_tables(), $job['staging_loaded'] ?? []));
            if ($pending === []) {
                $job['staging'] = true;
                $job['phase'] = 'count_staging';
                break;
            }
            $table = $pending[0];
            $splitDir = (string) ($job['split_dir'] ?? '');
            $slice = $splitDir !== '' ? ($splitDir . '/' . $table . '.sql') : '';
            if ($slice === '' || !is_file($slice) || filesize($slice) < 8) {
                $job['staging_loaded'][] = $table;
                $job['load_offset'] = 0;
                $message = 'নেই, বাদ: ' . $table;
            } else {
                $chunk = mae_load_staging_table_file_chunk(
                    $db,
                    $slice,
                    $table,
                    (int) ($job['load_offset'] ?? 0),
                    $deadline
                );
                $job['load_offset'] = (int) $chunk['offset'];
                $job['load_ran'] = (int) ($job['load_ran'] ?? 0) + (int) $chunk['ran'];
                if (!empty($chunk['done'])) {
                    $job['staging_loaded'][] = $table;
                    $job['load_offset'] = 0;
                    $message = 'লোড: ' . $table;
                } else {
                    $fs = max(1, (int) filesize($slice));
                    $pct = (int) round(min(99, ((int) $chunk['offset'] / $fs) * 100));
                    $message = 'লোড: ' . $table . ' ' . $pct . '%';
                }
            }
            if (count($job['staging_loaded'] ?? []) >= count(mae_core_tables())) {
                $job['staging'] = true;
                $job['phase'] = 'count_staging';
            }
            break;

        case 'count_staging':
            foreach (mae_core_tables() as $t) {
                $stats['staging'][$t] = mae_count($db, $t, !empty($job['staging']));
            }
            if (!empty($opts['replace_shop'])) {
                $job['phase'] = 'wipe';
                $job['wipe_index'] = 0;
            } elseif (!empty($opts['import_settings'])) {
                $job['phase'] = 'settings';
            } elseif (!empty($opts['import_master'])) {
                $job['phase'] = 'master_categories';
            } elseif (!empty($opts['import_transactions'])) {
                $job['phase'] = 'purchases';
            } else {
                $job['phase'] = 'cleanup';
            }
            break;

        case 'wipe':
            if ((int) ($job['wipe_index'] ?? 0) === 0) {
                mae_ensure_default_account($db);
            }
            $wipeTables = mae_replace_wipe_tables();
            $wipeAt = (int) ($job['wipe_index'] ?? 0);
            if ($wipeAt >= count($wipeTables)) {
                $job['phase'] = !empty($opts['import_settings']) ? 'settings' : (
                    !empty($opts['import_master']) ? 'master_categories' : (
                        !empty($opts['import_transactions']) ? 'purchases' : 'cleanup'
                    )
                );
                $message = 'নতুন সাইটের দোকান খাতা খালি';
                break;
            }
            mae_replace_wipe_one($db, $wipeTables[$wipeAt]);
            $job['wipe_index'] = $wipeAt + 1;
            $message = 'মুছছি: ' . $wipeTables[$wipeAt];
            break;

        case 'settings':
            mae_import_shop($db, $tables['shop'], $stats);
            $job['phase'] = !empty($opts['import_master']) ? 'master_categories' : (
                !empty($opts['import_transactions']) ? 'purchases' : 'cleanup'
            );
            $message = 'সেটিংস আপডেট';
            break;

        case 'master_categories':
            $maps['cat'] = mae_import_categories($db, $tables['pt'], $stats, !empty($opts['skip_existing']));
            $job['maps'] = mae_migration_maps_pack($maps);
            $job['phase'] = 'master_products';
            $message = 'ক্যাটাগরি সম্পন্ন';
            break;

        case 'master_products':
            $maps = mae_migration_maps_unpack($job['maps']);
            $maps['prod'] = mae_import_products($db, $tables['prod'], $tables['pb'], $maps['cat'], $stats, !empty($opts['skip_existing']));
            $job['maps'] = mae_migration_maps_pack($maps);
            $job['phase'] = 'master_customers';
            $message = 'পণ্য সম্পন্ন';
            break;

        case 'master_customers':
            $maps = mae_migration_maps_unpack($job['maps']);
            $maps['cust'] = mae_import_customers($db, $tables['cus'], $stats, !empty($opts['skip_existing']));
            $job['maps'] = mae_migration_maps_pack($maps);
            $job['phase'] = 'master_suppliers';
            $message = 'কাস্টমার সম্পন্ন';
            break;

        case 'master_suppliers':
            $maps = mae_migration_maps_unpack($job['maps']);
            $maps['sup'] = mae_import_suppliers($db, $tables['com'], $stats, !empty($opts['skip_existing']));
            $job['maps'] = mae_migration_maps_pack($maps);
            $job['phase'] = 'master_users';
            $message = 'সাপ্লায়ার সম্পন্ন';
            break;

        case 'master_users':
            mae_import_users($db, $tables['ui'], $tables['emp'], $stats, !empty($opts['skip_existing']));
            $job['phase'] = !empty($opts['import_transactions']) ? 'purchases' : 'cleanup';
            $message = 'ইউজার সম্পন্ন';
            break;

        case 'purchases':
            $maps = mae_migration_maps_unpack($job['maps']);
            if (empty($job['purchase_ready'])) {
                $job['account_id'] = mae_default_account_id($db);
                if ($job['account_id'] <= 0 && mae_count($db, 'purchase_item', !empty($job['staging'])) > 0) {
                    throw new RuntimeException('ক্রয় ইমপোর্টের জন্য Accounts মেনু থেকে একটি সক্রিয় অ্যাকাউন্ট তৈরি করুন।');
                }
                $cnt = $db->query(
                    "SELECT COUNT(*) AS c FROM `{$tables['pi']}` pi
                     JOIN `{$tables['pur']}` p ON p.PurId = pi.PurId
                     WHERE p.PurStatus IN (1,2)"
                );
                $job['purchase_total'] = $cnt ? (int) ($cnt->fetch_assoc()['c'] ?? 0) : 0;
                $job['purchase_offset'] = 0;
                $job['purchase_ready'] = true;
            }
            $poff = (int) ($job['purchase_offset'] ?? 0);
            if ($poff >= (int) ($job['purchase_total'] ?? 0)) {
                [$job['sale_reg'], $job['sale_ret']] = mae_sale_customer_maps($db, $tables['reg'], $tables['ret']);
                $cnt_res = $db->query("SELECT COUNT(*) AS c FROM `{$tables['sale']}` WHERE SalStatus=1");
                $job['sales_total'] = $cnt_res ? (int) ($cnt_res->fetch_assoc()['c'] ?? 0) : 0;
                $job['sales_offset'] = 0;
                $job['phase'] = 'sales';
                $message = 'ক্রয় সম্পন্ন — বিক্রয় শুরু';
                break;
            }
            mae_import_purchases(
                $db,
                $tables['pur'],
                $tables['pi'],
                $maps['sup'],
                $maps['prod'],
                (int) $job['account_id'],
                $stats,
                !empty($opts['skip_existing']),
                $poff,
                250
            );
            $job['purchase_offset'] = $poff + 250;
            $message = 'ক্রয়: ' . min((int) $job['purchase_offset'], (int) $job['purchase_total']) . ' / ' . (int) $job['purchase_total'];
            break;

        case 'sales':
            $maps = mae_migration_maps_unpack($job['maps']);
            $batch = 120;
            $off = (int)$job['sales_offset'];
            if ($off >= (int)$job['sales_total']) {
                $job['phase'] = 'cleanup';
                break;
            }
            mae_import_sales_batch(
                $db,
                $tables['sale'],
                $tables['si'],
                $maps['cust'],
                $maps['prod'],
                $job['sale_reg'] ?? [],
                $job['sale_ret'] ?? [],
                $stats,
                !empty($opts['skip_existing']),
                $off,
                $batch
            );
            $job['sales_offset'] = $off + $batch;
            $message = 'বিক্রয়: ' . min($job['sales_offset'], (int)$job['sales_total']) . ' / ' . (int)$job['sales_total'];
            if ($job['sales_offset'] >= (int)$job['sales_total']) {
                $job['phase'] = 'cleanup';
            }
            break;

        case 'cleanup':
            if (function_exists('mae_balances_available') && mae_balances_available()) {
                $bal = mae_apply_balances_stock($db);
                $stats['balances'] = $bal['stats'] ?? [];
                $message = (string) ($bal['message'] ?? 'ব্যালেন্স মিলানো');
            }
            if (!empty($job['staging']) && empty($opts['use_existing_tables'])) {
                mae_drop_staging($db);
            }
            if (!empty($job['sql_owned']) && !empty($job['sql_path']) && is_file($job['sql_path'])) {
                @unlink($job['sql_path']);
            }
            if (!empty($job['split_dir']) && is_dir((string)$job['split_dir'])) {
                foreach (glob(rtrim((string)$job['split_dir'], '/') . '/*.sql') ?: [] as $sf) {
                    @unlink($sf);
                }
                @rmdir((string)$job['split_dir']);
            }
            $job['phase'] = 'done';
            $job['last_error'] = '';
            $message = 'ইমপোর্ট সম্পন্ন';
            break;

        default:
            $job['phase'] = 'done';
            break;
    }

    if (($job['phase'] ?? '') === 'done') {
        if (!empty($job['id'])) {
            mae_migration_delete_job((string)$job['id']);
        }
        unset($_SESSION['mae_migration_job_id'], $_SESSION['mae_migration_job']);
        return mae_migration_job_response($job, $message, true);
    }

    $job['last_error'] = '';
    mae_migration_save_job($job);
    return mae_migration_job_response($job, $message, false);
}
