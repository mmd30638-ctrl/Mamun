<?php
/**
 * APONSHOP DB Backup Engine — chunked, low-memory, locked, retention-safe.
 * =======================================================================
 * - বড় টেবিল chunk করে পড়ে (5000 সারি), multi-row INSERT (500/ব্যাচ)।
 * - gzip স্ট্রিমে লেখে — পুরো ফাইল কখনো RAM-এ তোলে না।
 * - lock ফাইল: একসাথে দুই ব্যাকআপ চলবে না (লাইভ স্লো হয় না)।
 * - retention: N দিনের বেশি পুরনো ব্যাকআপ মুছে ফেলে।
 * - ব্যবসায়িক ডাটা বদলায় না — শুধু পড়ে (SELECT)।
 */

if (!function_exists('ame_db_backup_run')) {
    /**
     * @return array{ok:bool, file:string, bytes:int, tables:int, message:string}
     */
    function ame_db_backup_run(mysqli $db, string $backup_dir, int $retention_days = 7, int $chunk_rows = 5000): array
    {
        @set_time_limit(600);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        if (!is_dir($backup_dir) && !@mkdir($backup_dir, 0750, true) && !is_dir($backup_dir)) {
            return ['ok' => false, 'file' => '', 'bytes' => 0, 'tables' => 0, 'message' => 'ব্যাকআপ ফোল্ডার তৈরি হয়নি।'];
        }
        $lock = rtrim($backup_dir, '/') . '/.backup.lock';
        $lockFp = @fopen($lock, 'c');
        if (!$lockFp || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
            if ($lockFp) {
                @fclose($lockFp);
            }
            return ['ok' => false, 'file' => '', 'bytes' => 0, 'tables' => 0, 'message' => 'আরেকটি ব্যাকআপ ইতিমধ্যে চলছে।'];
        }
        $stamp = date('Y-m-d_H-i-s');
        $gz_file = rtrim($backup_dir, '/') . '/backup_' . $stamp . '.sql.gz';
        $gz = function_exists('gzopen') ? @gzopen($gz_file, 'w9') : false;
        $plain = '';
        if (!$gz) {
            $plain = rtrim($backup_dir, '/') . '/backup_' . $stamp . '.sql';
            $fp = @fopen($plain, 'w');
            if (!$fp) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
                return ['ok' => false, 'file' => '', 'bytes' => 0, 'tables' => 0, 'message' => 'ব্যাকআপ ফাইল খোলা যায়নি।'];
            }
            $gz = $fp;
            $gz_file = $plain;
            $use_gz = false;
        } else {
            $use_gz = true;
        }
        $write = static function ($s) use ($gz, $use_gz): void {
            if ($use_gz) {
                @gzwrite($gz, $s);
            } else {
                @fwrite($gz, $s);
            }
        };
        // ৪-বাইট অক্ষর (ইমোজি) ঠিক রাখতে কানেকশন + ডাম্প দুটোই utf8mb4।
        @$db->set_charset('utf8mb4');
        try {
            $write("-- Aponshop backup\n-- " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET @AME_OLD_SQL_MODE=@@SESSION.SQL_MODE, SESSION SQL_MODE='';\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables = [];
            $tres = @$db->query('SHOW TABLES');
            if ($tres) {
                while ($row = $tres->fetch_row()) {
                    $tables[] = (string)$row[0];
                }
                $tres->close();
            }
            foreach ($tables as $table) {
                $t = $db->real_escape_string($table);
                $create = @$db->query('SHOW CREATE TABLE `' . $t . '`');
                if (!$create) {
                    continue;
                }
                $crow = $create->fetch_row();
                $create->close();
                if (!isset($crow[1])) {
                    continue;
                }
                $write("\n\nDROP TABLE IF EXISTS `$table`;\n" . $crow[1] . ";\n");
                // Chunked read — পুরো টেবিল RAM-এ নয়।
                $offset = 0;
                $cols = null;
                while (true) {
                    $rows = @$db->query('SELECT * FROM `' . $t . '` LIMIT ' . $offset . ', ' . $chunk_rows);
                    if (!$rows) {
                        break;
                    }
                    if ($cols === null) {
                        $cols = [];
                        foreach ($rows->fetch_fields() as $f) {
                            $cols[] = '`' . str_replace('`', '``', $f->name) . '`';
                        }
                    }
                    $batch = [];
                    $got = 0;
                    while ($row = $rows->fetch_row()) {
                        $got++;
                        $vals = [];
                        foreach ($row as $v) {
                            $vals[] = $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
                        }
                        $batch[] = '(' . implode(',', $vals) . ')';
                        if (count($batch) >= 500) {
                            $write('INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES ' . implode(',', $batch) . ";\n");
                            $batch = [];
                        }
                    }
                    $rows->close();
                    if ($batch) {
                        $write('INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES ' . implode(',', $batch) . ";\n");
                    }
                    if ($got < $chunk_rows) {
                        break;
                    }
                    $offset += $chunk_rows;
                }
            }
            $write("\nSET FOREIGN_KEY_CHECKS=1;\nSET SESSION SQL_MODE=@AME_OLD_SQL_MODE;\n");
        } catch (Throwable $e) {
            if ($use_gz) {
                @gzclose($gz);
            } else {
                @fclose($gz);
            }
            @unlink($gz_file);
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
            return ['ok' => false, 'file' => '', 'bytes' => 0, 'tables' => 0, 'message' => 'ব্যাকআপ ব্যর্থ: ' . $e->getMessage()];
        }
        if ($use_gz) {
            @gzclose($gz);
        } else {
            @fclose($gz);
        }
        // Retention — পুরনো ব্যাকআপ সাফ।
        $cutoff = time() - max(1, $retention_days) * 86400;
        foreach (glob(rtrim($backup_dir, '/') . '/backup_*.sql*') ?: [] as $old) {
            if ($old === $gz_file || $old === $lock) {
                continue;
            }
            $mt = @filemtime($old);
            if ($mt !== false && $mt < $cutoff) {
                @unlink($old);
            }
        }
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
        $bytes = is_file($gz_file) ? (int)@filesize($gz_file) : 0;
        return ['ok' => true, 'file' => $gz_file, 'bytes' => $bytes, 'tables' => count($tables), 'message' => 'ব্যাকআপ সম্পন্ন (' . count($tables) . ' টেবিল)।'];
    }
}

if (!function_exists('ame_db_backup_latest')) {
    function ame_db_backup_latest(string $backup_dir): ?array
    {
        $best = null;
        foreach (glob(rtrim($backup_dir, '/') . '/backup_*.sql*') ?: [] as $file) {
            if (str_ends_with($file, '.lock')) {
                continue;
            }
            $mt = @filemtime($file);
            if ($mt === false) {
                continue;
            }
            if ($best === null || $mt > $best['time']) {
                $best = ['file' => basename($file), 'time' => $mt, 'bytes' => (int)@filesize($file)];
            }
        }
        return $best;
    }
}

if (!function_exists('ame_backup_webroot')) {
    function ame_backup_webroot(): string
    {
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $d = realpath((string)$_SERVER['DOCUMENT_ROOT']);
            if (is_string($d) && $d !== '') {
                return $d;
            }
        }
        $fromLib = realpath(dirname(__DIR__));
        return is_string($fromLib) && $fromLib !== '' ? $fromLib : dirname(__DIR__);
    }
}

if (!function_exists('ame_backup_candidate_dirs')) {
    /** @return list<string> */
    function ame_backup_candidate_dirs(): array
    {
        $out = [];
        $add = static function (string $dir) use (&$out): void {
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            if ($dir === '' || in_array($dir, $out, true)) {
                return;
            }
            $out[] = $dir;
        };
        if (defined('AME_PRIVATE_DATA_DIR') && is_string(AME_PRIVATE_DATA_DIR) && AME_PRIVATE_DATA_DIR !== '') {
            $add(rtrim(AME_PRIVATE_DATA_DIR, '/') . '/db_backups');
        }
        $web = ame_backup_webroot();
        $add(dirname($web) . '/private_data/db_backups');
        $add(dirname($web) . '/db_backups');
        $add($web . '/db_backups');
        return $out;
    }
}

if (!function_exists('ame_backup_storage_dir')) {
    function ame_backup_storage_dir(): string
    {
        foreach (ame_backup_candidate_dirs() as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }
        foreach (ame_backup_candidate_dirs() as $dir) {
            if (!is_dir($dir) && @mkdir($dir, 0750, true) && is_writable($dir)) {
                return $dir;
            }
        }
        $dirs = ame_backup_candidate_dirs();
        return $dirs[0] ?? (rtrim(sys_get_temp_dir(), '/') . '/db_backups');
    }
}

if (!function_exists('ame_backup_latest_any')) {
    function ame_backup_latest_any(): ?array
    {
        $best = null;
        foreach (ame_backup_candidate_dirs() as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $hit = ame_db_backup_latest($dir);
            if ($hit && ($best === null || (int)$hit['time'] > (int)$best['time'])) {
                $hit['dir'] = $dir;
                $best = $hit;
            }
        }
        return $best;
    }
}

if (!function_exists('ame_backup_count_any')) {
    function ame_backup_count_any(): int
    {
        $n = 0;
        foreach (ame_backup_candidate_dirs() as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob(rtrim($dir, '/') . '/backup_*.sql*') ?: [] as $file) {
                if (!str_ends_with($file, '.lock')) {
                    $n++;
                }
            }
        }
        return $n;
    }
}

if (!function_exists('ame_backup_cron_command')) {
    function ame_backup_cron_command(): string
    {
        $web = ame_backup_webroot();
        $php = 'php';
        $bin = defined('PHP_BINARY') ? (string)PHP_BINARY : '';
        if ($bin !== '' && is_file($bin) && !str_contains($bin, 'php-fpm')) {
            $php = $bin;
        } elseif (is_file('/usr/bin/php')) {
            $php = '/usr/bin/php';
        }
        return $php . ' ' . $web . '/maintenance.php';
    }
}
