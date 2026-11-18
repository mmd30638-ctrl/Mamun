<?php
/**
 * প্রতিদিন সার্ভারে দোকানের SQL ব্যাকআপ।
 * হিসাব শুধু পড়ে। টাকা কাটে না। ছবি uploads-এই থাকে।
 */

if (!function_exists('ame_db_backup_run')) {
    require_once __DIR__ . '/db_backup.php';
}

if (!function_exists('ame_daily_backup_webroot')) {
    function ame_daily_backup_webroot(): string
    {
        return function_exists('ame_backup_webroot') ? ame_backup_webroot() : dirname(__DIR__);
    }
}

if (!function_exists('ame_daily_backup_dir')) {
    function ame_daily_backup_dir(): string
    {
        $web = rtrim(str_replace('\\', '/', ame_daily_backup_webroot()), '/');
        $candidates = [];
        if (defined('AME_PRIVATE_DATA_DIR') && is_string(AME_PRIVATE_DATA_DIR) && AME_PRIVATE_DATA_DIR !== '') {
            $candidates[] = rtrim(str_replace('\\', '/', AME_PRIVATE_DATA_DIR), '/') . '/db_backups';
        }
        $candidates[] = dirname($web) . '/private_data/db_backups';
        $candidates[] = $web . '/storage/db_backups';
        foreach ($candidates as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                ame_daily_backup_protect_dir($dir);
                return $dir;
            }
        }
        foreach ($candidates as $dir) {
            if (!is_dir($dir) && @mkdir($dir, 0750, true) && is_writable($dir)) {
                ame_daily_backup_protect_dir($dir);
                return $dir;
            }
        }
        $fallback = function_exists('ame_backup_storage_dir') ? ame_backup_storage_dir() : ($web . '/storage/db_backups');
        if (!is_dir($fallback)) {
            @mkdir($fallback, 0750, true);
        }
        ame_daily_backup_protect_dir($fallback);
        return $fallback;
    }
}

if (!function_exists('ame_daily_backup_protect_dir')) {
    function ame_daily_backup_protect_dir(string $dir): void
    {
        $dir = rtrim($dir, '/');
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $deny = $dir . '/.htaccess';
        if (!is_file($deny) || (int) @filesize($deny) < 20) {
            @file_put_contents($deny, "Require all denied\nDeny from all\nOptions -Indexes\n");
        }
        $idx = $dir . '/index.php';
        if (!is_file($idx)) {
            @file_put_contents($idx, "<?php http_response_code(403); exit;\n");
        }
    }
}

if (!function_exists('ame_daily_backup_files')) {
    /** @return list<array{file:string,path:string,time:int,bytes:int}> */
    function ame_daily_backup_files(string $dir): array
    {
        $out = [];
        foreach (glob(rtrim($dir, '/') . '/backup_*.sql*') ?: [] as $path) {
            if (str_ends_with($path, '.lock')) {
                continue;
            }
            $mt = @filemtime($path);
            if ($mt === false) {
                continue;
            }
            $out[] = [
                'file' => basename($path),
                'path' => $path,
                'time' => (int) $mt,
                'bytes' => (int) @filesize($path),
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);
        return $out;
    }
}

if (!function_exists('ame_daily_backup_today_done')) {
    function ame_daily_backup_today_done(string $dir): bool
    {
        $today = date('Y-m-d');
        $statusFile = ame_daily_backup_status_path($dir);
        if (is_file($statusFile)) {
            $raw = json_decode((string) @file_get_contents($statusFile), true);
            $at = is_array($raw) ? (string) ($raw['at'] ?? '') : '';
            if ($at !== '' && strncmp($at, $today, 10) === 0 && !empty($raw['ok'])) {
                return true;
            }
        }
        foreach (ame_daily_backup_files($dir) as $row) {
            if (date('Y-m-d', (int) $row['time']) === $today) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ame_daily_backup_catchup')) {
    function ame_daily_backup_catchup(mysqli $db): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        try {
            ame_daily_backup_tick($db, false);
        } catch (Throwable $e) {
            error_log('daily backup catchup: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ame_daily_backup_status_path')) {
    function ame_daily_backup_status_path(string $dir): string
    {
        return rtrim($dir, '/') . '/last.json';
    }
}

if (!function_exists('ame_daily_backup_write_status')) {
    function ame_daily_backup_write_status(string $dir, array $result): void
    {
        $payload = [
            'ok' => !empty($result['ok']),
            'skipped' => !empty($result['skipped']),
            'file' => basename((string) ($result['file'] ?? '')),
            'bytes' => (int) ($result['bytes'] ?? 0),
            'tables' => (int) ($result['tables'] ?? 0),
            'message' => (string) ($result['message'] ?? ''),
            'at' => date('Y-m-d H:i:s'),
            'dir' => $dir,
        ];
        @file_put_contents(ame_daily_backup_status_path($dir), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}

if (!function_exists('ame_daily_backup_tick')) {
    /**
     * @return array{ok:bool,skipped:bool,file:string,bytes:int,tables:int,message:string,dir:string}
     */
    function ame_daily_backup_tick(mysqli $db, bool $force = false): array
    {
        $dir = ame_daily_backup_dir();
        if (!$force && ame_daily_backup_today_done($dir)) {
            $latest = ame_daily_backup_files($dir)[0] ?? null;
            $result = [
                'ok' => true,
                'skipped' => true,
                'file' => (string) ($latest['path'] ?? ''),
                'bytes' => (int) ($latest['bytes'] ?? 0),
                'tables' => 0,
                'message' => 'আজকের ব্যাকআপ আগেই আছে।',
                'dir' => $dir,
            ];
            ame_daily_backup_write_status($dir, $result);
            return $result;
        }
        $run = ame_db_backup_run($db, $dir, 30);
        if (!empty($run['ok']) && !empty($run['file'])) {
            ame_daily_backup_upload_note($dir, (string) $run['file']);
        }
        $result = [
            'ok' => !empty($run['ok']),
            'skipped' => false,
            'file' => (string) ($run['file'] ?? ''),
            'bytes' => (int) ($run['bytes'] ?? 0),
            'tables' => (int) ($run['tables'] ?? 0),
            'message' => (string) ($run['message'] ?? ''),
            'dir' => $dir,
        ];
        ame_daily_backup_write_status($dir, $result);
        return $result;
    }
}

if (!function_exists('ame_daily_backup_upload_note')) {
    function ame_daily_backup_upload_note(string $dir, string $sqlFile): void
    {
        $root = ame_daily_backup_webroot() . '/uploads';
        $count = 0;
        $bytes = 0;
        $capped = false;
        if (is_dir($root)) {
            $scan = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($scan as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $count++;
                $bytes += (int) $file->getSize();
                if ($count >= 20000) {
                    $capped = true;
                    break;
                }
            }
        }
        $base = (string) pathinfo($sqlFile, PATHINFO_FILENAME);
        $base = preg_replace('/\.sql$/', '', $base) ?? $base;
        $note = rtrim($dir, '/') . '/' . $base . '.uploads.txt';
        $text = "SQL ব্যাকআপের ভিতরে পণ্যের ছবি নেই।\n"
            . "uploads ফাইল গণনা: {$count}\n"
            . "uploads বাইট গণনা: {$bytes}\n"
            . ($capped ? "গণনা ২০০০০ ফাইলে থামানো হয়েছে।\n" : '')
            . "ছবি uploads ফোল্ডারেই থাকে। এই নোট কোনো হিসাব বদলায় না।\n";
        @file_put_contents($note, $text, LOCK_EX);
        $cutoff = time() - 30 * 86400;
        foreach (glob(rtrim($dir, '/') . '/*.uploads.txt') ?: [] as $old) {
            $mt = @filemtime($old);
            if ($mt !== false && $mt < $cutoff) {
                @unlink($old);
            }
        }
    }
}

if (!function_exists('ame_daily_backup_cron_lines')) {
    /** @return array{midnight:string,optional:string} */
    function ame_daily_backup_cron_lines(): array
    {
        $web = ame_daily_backup_webroot();
        $php = '/usr/bin/php';
        if (defined('PHP_BINARY')) {
            $bin = (string) PHP_BINARY;
            if ($bin !== '' && is_file($bin) && !str_contains($bin, 'php-fpm')) {
                $php = $bin;
            }
        } elseif (!is_file($php) && is_file('/usr/local/bin/php')) {
            $php = '/usr/local/bin/php';
        }
        return [
            'midnight' => '0 0 * * * ' . $php . ' ' . $web . '/cron/duty_midnight.php',
            'optional' => '0 2 * * * ' . $php . ' ' . $web . '/cron/daily_backup.php',
        ];
    }
}

if (!function_exists('ame_daily_backup_safe_name')) {
    function ame_daily_backup_safe_name(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql(?:\.gz)?$/', $name)) {
            return '';
        }
        return $name;
    }
}

if (!function_exists('ame_backup_resolve_file')) {
    function ame_backup_resolve_file(string $dir, string $name): string
    {
        $safe = ame_daily_backup_safe_name($name);
        if ($safe === '') {
            return '';
        }
        $base = realpath($dir);
        if (!is_string($base) || $base === '' || !is_dir($base)) {
            return '';
        }
        $path = realpath($base . DIRECTORY_SEPARATOR . $safe);
        if (!is_string($path) || $path === '' || !is_file($path)) {
            return '';
        }
        $parent = realpath(dirname($path));
        if ($parent !== $base) {
            return '';
        }
        return $path;
    }
}

if (!function_exists('ame_manual_backup_cooldown_path')) {
    function ame_manual_backup_cooldown_path(string $dir): string
    {
        return rtrim($dir, '/') . '/last_manual.json';
    }
}

if (!function_exists('ame_manual_backup_wait_seconds')) {
    function ame_manual_backup_wait_seconds(string $dir, int $cool = 180): int
    {
        $p = ame_manual_backup_cooldown_path($dir);
        if (!is_file($p)) {
            return 0;
        }
        $raw = json_decode((string) @file_get_contents($p), true);
        $at = is_array($raw) ? (int) ($raw['ts'] ?? 0) : 0;
        if ($at < 1) {
            return 0;
        }
        return max(0, $cool - (time() - $at));
    }
}

if (!function_exists('ame_manual_backup_mark')) {
    function ame_manual_backup_mark(string $dir, string $file): void
    {
        $payload = [
            'ts' => time(),
            'at' => date('Y-m-d H:i:s'),
            'file' => basename($file),
            'user' => (int) ($_SESSION['user_id'] ?? 0),
        ];
        @file_put_contents(ame_manual_backup_cooldown_path($dir), json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

if (!function_exists('ame_backup_file_sha256')) {
    function ame_backup_file_sha256(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $hash = @hash_file('sha256', $path);
        return is_string($hash) ? $hash : '';
    }
}

if (!function_exists('ame_backup_send_download')) {
    function ame_backup_send_download(string $path): void
    {
        $dir = ame_daily_backup_dir();
        $resolved = ame_backup_resolve_file($dir, basename($path));
        if ($resolved === '' || !is_file($resolved)) {
            http_response_code(404);
            die('❌ ব্যাকআপ ফাইল পাওয়া যায়নি।');
        }
        $path = $resolved;
        $safe = ame_daily_backup_safe_name(basename($path));
        $size = @filesize($path);
        if ($safe === '' || $size === false || (int) $size < 1) {
            http_response_code(404);
            die('❌ ব্যাকআপ ফাইল খালি বা নাম বৈধ নয়।');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @set_time_limit(300);
        $download = 'Aponshop_' . $safe;
        $mime = str_ends_with($safe, '.gz') ? 'application/gzip' : 'application/sql';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $download . '"; filename*=UTF-8\'\'' . rawurlencode($download));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: none');
        header('Content-Length: ' . (int) $size);
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            http_response_code(500);
            die('❌ ফাইল পড়া যায়নি।');
        }
        ignore_user_abort(true);
        while (!feof($fp)) {
            $chunk = fread($fp, 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            if (function_exists('flush')) {
                flush();
            }
        }
        fclose($fp);
        exit;
    }
}
