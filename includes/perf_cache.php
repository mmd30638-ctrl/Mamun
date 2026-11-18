<?php
/**
 * APONSHOP Performance Cache — পরিবর্তন কম হয় এমন ডাটার জন্য ফাইল ক্যাশ।
 * =====================================================================
 * নিয়ম (Golden Rule):
 * - শুধু অনুমোদিত STATIC ডাটা ক্যাশ হবে: Category, Settings, Permission, Banner।
 * - Dashboard summary-সহ সব dynamic/financial data প্রতিবার live query করতে হবে।
 * - Sales, Purchase, Stock, Ledger, Due, payment ও account balance কখনো cache নয়।
 * - TTL শেষ হলে 자동 মেয়াদোত্তীর্ণ; stampede ঠেকাতে lock + পুরনো কপি সার্ভ।
 */

if (!defined('AME_PERF_CACHE_DIR')) {
    $ame_perf_base = (function () {
        if (defined('AME_PRIVATE_DATA_DIR') && is_string(AME_PRIVATE_DATA_DIR) && AME_PRIVATE_DATA_DIR !== '') {
            return rtrim(AME_PRIVATE_DATA_DIR, '/') . '/cache';
        }
        return sys_get_temp_dir() . '/ame_perf_cache';
    })();
    define('AME_PERF_CACHE_DIR', $ame_perf_base);
}

if (!function_exists('ame_perf_cache_file')) {
    function ame_perf_cache_file(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
        return rtrim(AME_PERF_CACHE_DIR, '/') . '/' . substr(sha1($key), 0, 12) . '_' . substr((string)$safe, 0, 60) . '.json';
    }
}

if (!function_exists('ame_perf_cache_get')) {
    /**
     * @return array{hit:bool, value:mixed}
     */
    function ame_perf_cache_get(string $key, int $ttl = 300): array
    {
        $file = ame_perf_cache_file($key);
        if (!is_file($file)) {
            return ['hit' => false, 'value' => null];
        }
        $mtime = @filemtime($file);
        if ($mtime === false || (time() - $mtime) > $ttl) {
            return ['hit' => false, 'value' => null];
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return ['hit' => false, 'value' => null];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('v', $data)) {
            return ['hit' => false, 'value' => null];
        }
        return ['hit' => true, 'value' => $data['v']];
    }
}

if (!function_exists('ame_perf_cache_set')) {
    function ame_perf_cache_set(string $key, $value): bool
    {
        $dir = rtrim(AME_PERF_CACHE_DIR, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        $file = ame_perf_cache_file($key);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = @file_put_contents($tmp, json_encode(['v' => $value], JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
        if ($ok) {
            $ok = @rename($tmp, $file);
        }
        if (!$ok) {
            @unlink($tmp);
        }
        return $ok;
    }
}

if (!function_exists('ame_perf_cache_forget')) {
    function ame_perf_cache_forget(string $key): void
    {
        @unlink(ame_perf_cache_file($key));
    }
}

if (!function_exists('ame_perf_remember')) {
    /**
     * Cache-aside helper. $fn শুধু miss হলে চলে (একবার)।
     */
    function ame_perf_remember(string $key, int $ttl, callable $fn)
    {
        $got = ame_perf_cache_get($key, $ttl);
        if ($got['hit']) {
            return $got['value'];
        }
        $lock = ame_perf_cache_file($key) . '.lock';
        $fp = @fopen($lock, 'c');
        $haveLock = $fp && @flock($fp, LOCK_EX | LOCK_NB);
        try {
            // লক না পেলে (অন্য প্রসেস বানাচ্ছে) — মেয়াদোত্তীর্ণ কপি হলেও দাও।
            if (!$haveLock) {
                $file = ame_perf_cache_file($key);
                $raw = @file_get_contents($file);
                $data = $raw !== false ? json_decode($raw, true) : null;
                if (is_array($data) && array_key_exists('v', $data)) {
                    return $data['v'];
                }
                $value = $fn();
                ame_perf_cache_set($key, $value);
                return $value;
            }
            // ডবল-চেক: লক পেতে পেতে অন্য প্রসেস বানিয়ে ফেলতে পারে।
            $got = ame_perf_cache_get($key, $ttl);
            if ($got['hit']) {
                return $got['value'];
            }
            $value = $fn();
            ame_perf_cache_set($key, $value);
            return $value;
        } finally {
            if ($haveLock) {
                @flock($fp, LOCK_UN);
            }
            if ($fp) {
                @fclose($fp);
            }
        }
    }
}

if (!function_exists('ame_perf_cache_prune')) {
    /** মেয়াদোত্তীর্ণ ক্যাশ ফাইল মুছে ফেলা (রক্ষণাবেক্ষণ থেকে চলে)। */
    function ame_perf_cache_prune(int $older_than = 86400): int
    {
        $dir = rtrim(AME_PERF_CACHE_DIR, '/');
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        $now = time();
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $mt = @filemtime($file);
            if ($mt !== false && ($now - $mt) > $older_than && @unlink($file)) {
                $n++;
            }
        }
        foreach (glob($dir . '/*.lock') ?: [] as $file) {
            $mt = @filemtime($file);
            if ($mt !== false && ($now - $mt) > 3600) {
                @unlink($file);
            }
        }
        return $n;
    }
}
