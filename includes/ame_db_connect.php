<?php
/**
 * Shared MySQL open for live and staging.
 * Reads the same .env path as config.php. Never invents a database name.
 * Never writes customer/supplier amounts, ledgers, payments, sales or purchases.
 */

$guard = __DIR__ . '/ame_mysqli_guard.php';
if (is_file($guard)) {
    require_once $guard;
}
$boot = __DIR__ . '/env_bootstrap.php';
if (is_file($boot)) {
    require_once $boot;
}

if (!function_exists('env_get')) {
    function env_get(array $env, string $key, string $default = ''): string
    {
        $fromProcess = getenv($key);
        if ($fromProcess !== false && $fromProcess !== '') {
            return (string) $fromProcess;
        }
        return $env[$key] ?? $default;
    }
}

if (!function_exists('ame_db_env_map')) {
    function ame_db_env_map(): array
    {
        $env_path = function_exists('ame_env_path')
            ? ame_env_path()
            : dirname(__DIR__) . '/.env';
        $env = function_exists('ame_read_env_file')
            ? ame_read_env_file($env_path)
            : [];
        if ($env === [] && is_file($env_path) && is_readable($env_path)) {
            foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $env[$key] = trim($value, "\"' ");
            }
        }
        $env['_path'] = $env_path;
        return $env;
    }
}

if (!function_exists('ame_db_connect_params')) {
    /** @return array{host:string,name:string,user:string,pass:string,port:int,path:string} */
    function ame_db_connect_params(): array
    {
        $env = ame_db_env_map();
        $host = env_get($env, 'DB_HOST', 'localhost');
        $portRaw = env_get($env, 'DB_PORT', '');
        $port = ($portRaw !== '' && ctype_digit($portRaw)) ? (int) $portRaw : 0;
        if (str_contains($host, ':') && $port === 0) {
            [$hostOnly, $hostPort] = explode(':', $host, 2);
            if ($hostOnly !== '' && ctype_digit($hostPort)) {
                $host = $hostOnly;
                $port = (int) $hostPort;
            }
        }
        return [
            'host' => $host,
            'name' => env_get($env, 'DB_NAME'),
            'user' => env_get($env, 'DB_USER'),
            'pass' => env_get($env, 'DB_PASS'),
            'port' => $port,
            'path' => (string) ($env['_path'] ?? ''),
        ];
    }
}

if (!function_exists('ame_db_host_retries')) {
    /** @return list<string> */
    function ame_db_host_retries(string $host): array
    {
        $hosts = [$host];
        $lower = strtolower($host);
        if ($lower === 'localhost') {
            $hosts[] = '127.0.0.1';
        } elseif ($lower === '127.0.0.1') {
            $hosts[] = 'localhost';
        }
        return array_values(array_unique($hosts));
    }
}

if (!function_exists('ame_open_mysqli')) {
    function ame_open_mysqli(): ?mysqli
    {
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
            return $GLOBALS['db'];
        }
        $p = ame_db_connect_params();
        if ($p['name'] === '' || $p['user'] === '') {
            error_log('DB config missing or incomplete at ' . $p['path']);
            return null;
        }
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        $db = null;
        foreach (ame_db_host_retries($p['host']) as $tryHost) {
            if (function_exists('ame_mysqli_try')) {
                $db = ame_mysqli_try($tryHost, $p['user'], $p['pass'], $p['name'], $p['port']);
            } else {
                try {
                    $db = $p['port'] > 0
                        ? @new mysqli($tryHost, $p['user'], $p['pass'], $p['name'], $p['port'])
                        : @new mysqli($tryHost, $p['user'], $p['pass'], $p['name']);
                } catch (Throwable $e) {
                    error_log('MySQLi Connection Failed (' . $tryHost . '): ' . $e->getMessage());
                    $db = null;
                }
                if ($db instanceof mysqli && $db->connect_error) {
                    error_log('MySQLi Connection Failed (' . $tryHost . '): ' . $db->connect_error);
                    $db = null;
                }
            }
            if ($db instanceof mysqli) {
                break;
            }
        }
        if (!$db instanceof mysqli) {
            return null;
        }
        $db->set_charset('utf8mb4');
        return $db;
    }
}
