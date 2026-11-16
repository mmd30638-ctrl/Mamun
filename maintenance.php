<?php
/**
 * APONSHOP Nightly Maintenance — প্রতি রাতের অটো রক্ষণাবেক্ষণ।
 * ============================================================
 * Cron থেকে চালান (প্রতিদিন রাত ২টা):
 *   php /home/USER/domains/DOMAIN/public_html/maintenance.php
 * অথবা টোকেন দিয়ে (MAINT_TOKEN .env-এ বসিয়ে):
 *   https://DOMAIN/maintenance.php?token=XXXX
 *
 * কাজ (সব নিরাপদ — ব্যবসায়িক ডাটা কখনো ডিলিট হয় না):
 *  ১. ডাটাবেজ ব্যাকআপ (.sql.gz, ৭ দিন retention, lock সহ)
 *  ২. পারফরম্যান্স ইনডেক্স নিশ্চিত (idempotent)
 *  ৩. মেয়াদোত্তীর্ণ ক্যাশ + অস্থায়ী ফাইল সাফ
 *  ৪. বড় লগ ফাইল রোটেট (৫MB+, সর্বশেষ ৩টা)
 * ফলাফল private_data/maintenance_status.json-এ থাকে (টুলস পেজে দেখায়)।
 */

$ame_maint_cli = php_sapi_name() === 'cli';

if (!$ame_maint_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    @ini_set('display_errors', '0');
}

require_once __DIR__ . '/includes/db.php';
if (!defined('AME_PRIVATE_DATA_DIR')) {
    // config.php-এর সমান পাথ (webroot-এর এক ধাপ ওপরে private_data)।
    define('AME_PRIVATE_DATA_DIR', dirname(__DIR__) . '/private_data');
}
// NOTE: config.php-তে লগইন-গেট আছে — CLI/cron থেকে সেটা চলবে না।
// তাই শুধু db.php (কানেকশন + env); ওয়েবে টোকেনই প্রমাণ।

if (!$ame_maint_cli) {
    $token = (string)($_GET['token'] ?? '');
    $want = '';
    if (function_exists('env_get') && isset($env) && is_array($env)) {
        $want = (string)env_get($env, 'MAINT_TOKEN', '');
    } else {
        $want = (string)(getenv('MAINT_TOKEN') ?: '');
    }
    if ($want === '' || !hash_equals($want, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
    @set_time_limit(600);
}

require_once __DIR__ . '/includes/db_backup.php';
require_once __DIR__ . '/includes/perf_cache.php';
require_once __DIR__ . '/includes/perf_indexes.php';

$started = time();
$report = ['started_at' => date('Y-m-d H:i:s'), 'jobs' => []];

// ১. ব্যাকআপ (webroot-এর বাইরে — private_data/db_backups)
$backup_dir = ame_backup_storage_dir();
$bak = ame_db_backup_run($db, $backup_dir, 7);
$report['jobs']['backup'] = ['ok' => $bak['ok'], 'file' => basename($bak['file']), 'bytes' => $bak['bytes'], 'tables' => $bak['tables'], 'message' => $bak['message']];

// ২. ইনডেক্স
$idx = ame_perf_ensure_indexes($db);
$report['jobs']['indexes'] = $idx;

// ৩. ক্যাশ + অস্থায়ী ফাইল সাফ
$pruned = ame_perf_cache_prune(86400);
$tmp_cleaned = 0;
foreach (glob(sys_get_temp_dir() . '/ame_upd_*') ?: [] as $d) {
    if (is_dir($d) && (time() - (int)@filemtime($d)) > 86400) {
        // খালি/পুরনো আপডেট-টেম্প — ভেতরের ফাইলসহ সরাও
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        if (@rmdir($d)) {
            $tmp_cleaned++;
        }
    }
}
// ব্যর্থ আপলোড-টেম্প (uploads/tmp-এ ২৪ঘণ্টার বেশি পুরনো ফাইল)
$up_tmp = __DIR__ . '/uploads/tmp';
if (is_dir($up_tmp)) {
    foreach (glob($up_tmp . '/*') ?: [] as $f) {
        if (is_file($f) && (time() - (int)@filemtime($f)) > 86400 && @unlink($f)) {
            $tmp_cleaned++;
        }
    }
}
$report['jobs']['prune'] = ['cache_files' => $pruned, 'tmp_cleaned' => $tmp_cleaned];

// ৪. লগ রোটেশন (৫MB+ হলে, সর্বশেষ ৩টা রাখো)
$rotated = 0;
$log_dirs = [];
if (defined('AME_PRIVATE_DATA_DIR')) {
    $log_dirs[] = rtrim(AME_PRIVATE_DATA_DIR, '/') . '/logs';
}
foreach ($log_dirs as $ld) {
    if (!is_dir($ld)) {
        continue;
    }
    foreach (glob($ld . '/*.log') ?: [] as $log) {
        if (@filesize($log) > 5 * 1024 * 1024) {
            for ($i = 3; $i >= 1; $i--) {
                $src = $i === 1 ? $log : $log . '.' . ($i - 1) . '.gz';
                if (is_file($src)) {
                    if ($i === 1) {
                        $gz = @gzopen($log . '.1.gz', 'w9');
                        if ($gz) {
                            $fh = @fopen($log, 'r');
                            if ($fh) {
                                while (!feof($fh)) {
                                    @gzwrite($gz, fread($fh, 1048576));
                                }
                                @fclose($fh);
                            }
                            @gzclose($gz);
                        }
                        @file_put_contents($log, '');
                    } else {
                        @rename($src, $log . '.' . $i . '.gz');
                    }
                }
            }
            @unlink($log . '.4.gz');
            $rotated++;
        }
    }
}
$report['jobs']['logs'] = ['rotated' => $rotated];

$report['finished_at'] = date('Y-m-d H:i:s');
$report['seconds'] = time() - $started;

// স্ট্যাটাস সংরক্ষণ (টুলস পেজে দেখানোর জন্য)
$status_file = (defined('AME_PRIVATE_DATA_DIR') ? rtrim(AME_PRIVATE_DATA_DIR, '/') : sys_get_temp_dir()) . '/maintenance_status.json';
@file_put_contents($status_file, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

foreach ($report['jobs'] as $name => $job) {
    $ok = isset($job['ok']) ? ($job['ok'] ? 'OK' : 'FAIL') : 'done';
    echo strtoupper((string)$name) . ': ' . $ok . ' — ' . ($job['message'] ?? json_encode($job, JSON_UNESCAPED_UNICODE)) . "\n";
}
echo 'সময়: ' . $report['seconds'] . "s\n";
