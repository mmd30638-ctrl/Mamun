<?php
/**
 * 10.98.347 — PHP 8.3 mysqli already-closed guard.
 * Failed connect objects throw Error on close(); never call close().
 * Also rewrite live includes/db.php on disk so Engine Room skip cannot keep the old line.
 */
if (!defined('AME_MYSQLI_GUARD')) {
    define('AME_MYSQLI_GUARD', '10.98.365');
}

if (!function_exists('ame_mysqli_try')) {
    function ame_mysqli_try(
        string $host,
        string $user,
        string $pass,
        string $name,
        int $port = 0,
        string $socket = '',
        bool $log_fail = false
    ): ?mysqli {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        $label = $socket !== '' ? ($host . ' socket ' . $socket) : $host;
        try {
            $link = $socket !== ''
                ? @new mysqli($host, $user, $pass, $name, 0, $socket)
                : @new mysqli($host, $user, $pass, $name, $port);
        } catch (Throwable $e) {
            if ($log_fail) {
                error_log('MySQLi Connection Failed (' . $label . '): ' . $e->getMessage());
            }
            return null;
        }
        if (!$link instanceof mysqli) {
            if ($log_fail) {
                error_log('MySQLi Connection Failed (' . $label . '): connect threw');
            }
            return null;
        }
        try {
            $errno = (int) $link->connect_errno;
            $why = (string) $link->connect_error;
        } catch (Throwable $e) {
            if ($log_fail) {
                error_log('MySQLi Connection Failed (' . $label . '): ' . $e->getMessage());
            }
            return null;
        }
        if ($errno !== 0) {
            if ($log_fail) {
                error_log('MySQLi Connection Failed (' . $label . '): ' . $why);
            }
            return null;
        }
        return $link;
    }
}

if (!function_exists('ame_mysqli_safe_close')) {
    function ame_mysqli_safe_close(mixed $db = null): void
    {
        // PHP 8.3: failed mysqli is already closed. close() is a FATAL Error.
        return;
    }
}

if (!function_exists('ame_mysqli_patch_db_file')) {
    function ame_mysqli_patch_db_file(string $path): void
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return;
        }
        $src = (string) file_get_contents($path);
        if ($src === '' || (str_contains($src, 'AME_MYSQLI_GUARD') && !preg_match('/@?\$db\s*->\s*close\s*\(\s*\)\s*;/', $src))) {
            return;
        }
        $next = preg_replace(
            '/@?\$db\s*->\s*close\s*\(\s*\)\s*;/',
            '/* AME_MYSQLI_GUARD skip close */',
            $src,
            1
        );
        if (!is_string($next)) {
            $next = $src;
        }
        $next = preg_replace(
            '/mysqli_close\s*\(\s*(\$[a-zA-Z_][a-zA-Z0-9_]*)\s*\)\s*;/',
            'try { /* AME_MYSQLI_GUARD skip close */ } catch (Throwable $__ame_e) {}',
            $next
        );
        if (!is_string($next)) {
            $next = $src;
        }
        if (preg_match('/^<\?php\b/', $next)) {
            $next = preg_replace('/^<\?php\b/', '<?php /* AME_MYSQLI_GUARD */', $next, 1) ?? $next;
        } else {
            $next = "<?php /* AME_MYSQLI_GUARD */ ?>\n" . $next;
        }
        if ($next === $src) {
            return;
        }
        if (!is_writable($path)) {
            return;
        }
        $ok = @file_put_contents($path, $next, LOCK_EX);
        if ($ok === false) {
            return;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
