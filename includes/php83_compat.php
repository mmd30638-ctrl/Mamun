<?php
/**
 * PHP 8.3: htmlspecialchars(null) is deprecated.
 * Live employee_list.php:132 hits this when phone/department/photo is NULL.
 * 1) Swallow that deprecation so the error page does not fire.
 * 2) Rewrite htmlspecialchars first-args on disk once (employee_list.php).
 */

if (!defined('AME_PHP83_COMPAT')) {
    define('AME_PHP83_COMPAT', '10.98.347');
}

if (!function_exists('ame_php83_htmlspecialchars_guard')) {
    function ame_php83_htmlspecialchars_guard(
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (
            ($severity === E_DEPRECATED || $severity === E_WARNING)
            && str_contains($message, 'htmlspecialchars(): Passing null')
        ) {
            return true;
        }
        return false;
    }

    $ame_prev = set_error_handler(static function (
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ) use (&$ame_prev): bool {
        if (ame_php83_htmlspecialchars_guard($severity, $message, $file, $line)) {
            return true;
        }
        if (is_callable($ame_prev)) {
            return (bool) $ame_prev($severity, $message, $file, $line);
        }
        return false;
    });
}

if (!function_exists('ame_php83_rewrite_htmlspecialchars')) {
    function ame_php83_rewrite_htmlspecialchars(string $src): string
    {
        $tokens = token_get_all($src);
        $n = count($tokens);
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            $isHs = is_array($t)
                && $t[0] === T_STRING
                && strcasecmp((string) $t[1], 'htmlspecialchars') === 0;
            if (!$isHs) {
                $out .= is_array($t) ? $t[1] : $t;
                continue;
            }
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if ($j >= $n || $tokens[$j] !== '(') {
                $out .= $t[1];
                continue;
            }
            $k = $j + 1;
            while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                $k++;
            }
            $already = false;
            if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_STRING_CAST) {
                $already = true;
            }
            if ($k < $n && $tokens[$k] === '(') {
                $k2 = $k + 1;
                while ($k2 < $n && is_array($tokens[$k2]) && $tokens[$k2][0] === T_WHITESPACE) {
                    $k2++;
                }
                if (
                    $k2 < $n
                    && is_array($tokens[$k2])
                    && (
                        ($tokens[$k2][0] === T_STRING && strcasecmp((string) $tokens[$k2][1], 'string') === 0)
                        || $tokens[$k2][0] === T_STRING_CAST
                    )
                ) {
                    $already = true;
                }
            }
            $out .= $t[1];
            for ($x = $i + 1; $x <= $j; $x++) {
                $out .= is_array($tokens[$x]) ? $tokens[$x][1] : $tokens[$x];
            }
            $i = $j;
            if ($already) {
                continue;
            }
            $out .= '(string)(';
            $argDepth = 0;
            for ($p = $j + 1; $p < $n; $p++) {
                $tok = $tokens[$p];
                $ch = is_array($tok) ? '' : $tok;
                if ($ch === '(' || $ch === '[' || $ch === '{') {
                    $argDepth++;
                } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                    if ($argDepth === 0 && $ch === ')') {
                        $out .= " ?? '')";
                        $out .= ')';
                        $i = $p;
                        break;
                    }
                    $argDepth--;
                } elseif ($ch === ',' && $argDepth === 0) {
                    $out .= " ?? '')";
                    $out .= ',';
                    $i = $p;
                    break;
                }
                $out .= is_array($tok) ? $tok[1] : $tok;
            }
        }
        return $out;
    }
}

if (!function_exists('ame_php83_patch_file')) {
    function ame_php83_patch_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $src = (string) file_get_contents($path);
        if ($src === '' || str_contains($src, 'AME_PHP83_H')) {
            return;
        }
        if (!str_contains($src, 'htmlspecialchars')) {
            return;
        }
        $next = ame_php83_rewrite_htmlspecialchars($src);
        if ($next === $src) {
            return;
        }
        if (preg_match('/^<\?php\b/', $next)) {
            $next = preg_replace('/^<\?php\b/', '<?php /* AME_PHP83_H */', $next, 1) ?? $next;
        } else {
            $next = "<?php /* AME_PHP83_H */ ?>\n" . $next;
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

if (!function_exists('ame_php83_patch_hrm_list')) {
    function ame_php83_patch_hrm_list(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $root = dirname(__DIR__);
        $hrm = $root . '/modules/hrm';
        ame_php83_patch_file($hrm . '/employee_list.php');
        foreach (['employee_edit.php', 'employee_profile.php', 'employee_view.php', 'add_employee.php'] as $name) {
            ame_php83_patch_file($hrm . '/' . $name);
        }
    }
}

ame_php83_patch_hrm_list();
if (function_exists('ame_mysqli_patch_db_file')) {
    ame_mysqli_patch_db_file(__DIR__ . '/db.php');
} elseif (is_file(__DIR__ . '/ame_mysqli_guard.php')) {
    require_once __DIR__ . '/ame_mysqli_guard.php';
    if (function_exists('ame_mysqli_patch_db_file')) {
        ame_mysqli_patch_db_file(__DIR__ . '/db.php');
    }
}
