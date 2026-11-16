<?php
/**
 * APONSHOP Performance Check (CLI) — বড় আপডেটের আগে/পরে তুলনা।
 * =================================================================
 * চালানো: php tools/perf_check.php
 * দেখায়: টেবিল সাইজ, ইনডেক্স ঘাটতি, ৫টি critical query-এর সময়,
 *         লগ/ক্যাশ/আপলোড স্টোরেজ, error rate।
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
// NOTE: config.php-তে লগইন-গেট আছে — CLI থেকে সেটা চলবে না, db.php-ই যথেষ্ট।
require $root . '/includes/db.php';
if (!defined('AME_PRIVATE_DATA_DIR')) {
    define('AME_PRIVATE_DATA_DIR', dirname($root, 1) . '/private_data');
}
require $root . '/includes/perf_indexes.php';

$line = str_repeat('=', 58) . "\n";
echo $line . 'APONSHOP PERFORMANCE CHECK — ' . date('Y-m-d H:i:s') . "\n" . $line;

$time_q = static function (string $label, string $sql) use ($db): void {
    $t0 = microtime(true);
    $res = @$db->query($sql);
    $dt = (microtime(true) - $t0) * 1000;
    $n = $res ? $res->num_rows : -1;
    if ($res) {
        $res->close();
    }
    printf("%-34s %8.1f ms  rows=%d\n", $label, $dt, $n);
};

echo "-- Critical queries --\n";
$today = date('Y-m-d');
$time_q('today sales KPI', "SELECT COUNT(*), COALESCE(SUM(net_payable),0) FROM sales WHERE status='completed' AND DATE(sale_date)='$today'");
$time_q('sales history 30d', "SELECT s.id FROM sales s WHERE s.status='completed' AND DATE(s.sale_date) BETWEEN DATE_SUB('$today', INTERVAL 30 DAY) AND '$today' ORDER BY s.id DESC LIMIT 800");
$time_q('product search', "SELECT id FROM products WHERE status='active' AND name LIKE '%m%' LIMIT 40");
$time_q('customer search', "SELECT id FROM customers WHERE name LIKE '%a%' OR phone LIKE '%01%' LIMIT 40");
$time_q('supplier due list', "SELECT id FROM suppliers WHERE status='active' ORDER BY name ASC LIMIT 500");

echo "-- Missing indexes (wanted) --\n";
$missing = 0;
foreach (ame_perf_index_list() as [$table, $index, $column]) {
    $cols = ame_perf_table_columns($db, $table);
    if ($cols === [] || !in_array($column, $cols, true)) {
        continue;
    }
    $res = @$db->query('SHOW INDEX FROM `' . $db->real_escape_string($table) . "` WHERE Key_name = '" . $db->real_escape_string($index) . "'");
    $have = $res ? $res->num_rows > 0 : true;
    if ($res) {
        $res->close();
    }
    if (!$have) {
        $missing++;
        echo "  MISSING $table.$index ($column)\n";
    }
}
echo $missing === 0 ? "  all present\n" : "  total missing: $missing (run maintenance.php)\n";

echo "-- Largest tables --\n";
$tres = @$db->query("SELECT table_name, ROUND(data_length/1048576,2) AS mb, table_rows FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY data_length DESC LIMIT 8");
if ($tres) {
    while ($r = $tres->fetch_assoc()) {
        printf("  %-28s %8s MB  rows=%s\n", $r['table_name'], $r['mb'], $r['table_rows']);
    }
    $tres->close();
}

echo "-- Storage --\n";
$du = static function (string $label, string $dir): void {
    if (!is_dir($dir)) {
        printf("  %-28s (none)\n", $label);
        return;
    }
    $kb = 0;
    $files = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $kb += $f->getSize() / 1024;
            $files++;
        }
    }
    printf("  %-28s %8.1f MB  files=%d\n", $label, $kb / 1024, $files);
};
$du('uploads/', $root . '/uploads');
$du('private logs', (defined('AME_PRIVATE_DATA_DIR') ? AME_PRIVATE_DATA_DIR : sys_get_temp_dir()) . '/logs');
$du('perf cache', (defined('AME_PRIVATE_DATA_DIR') ? rtrim(AME_PRIVATE_DATA_DIR, '/') . '/cache' : sys_get_temp_dir() . '/ame_perf_cache'));

echo "-- Error rate (24h) --\n";
$n = function_exists('ame_recent_error_count') ? ame_recent_error_count(24) : -1;
echo '  errors: ' . $n . "\n" . $line;
