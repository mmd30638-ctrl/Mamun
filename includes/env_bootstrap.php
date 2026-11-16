<?php
/**
 * Load the private env file from outside the webroot when PHP can read it.
 * Hostinger temporary sites often block reading above public_html, so staging
 * also accepts public_html/.env.staging.
 */

function ame_app_root(): string
{
    return dirname(__DIR__);
}

function ame_account_root(): string
{
    $dir = ame_app_root();
    for ($i = 0; $i < 6; $i++) {
        $parent = dirname($dir);
        if (is_readable($parent . '/.env.staging') || is_readable($parent . '/.env')) {
            return $parent;
        }
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    return dirname(__DIR__, 2);
}

function ame_request_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return $host;
}

function ame_is_staging_app(): bool
{
    $base = strtolower(basename(ame_app_root()));
    if ($base === 'staging' || str_starts_with($base, 'staging')) {
        return true;
    }
    $host = ame_request_host();
    // লাইভ ডোমেন mavo12.online। স্টেজিং না — নিজের .env পড়ে।
    if ($host === 'mavo12.online' || $host === 'www.mavo12.online') {
        return false;
    }
    if ($host === 'staging.aponshop.site' || str_starts_with($host, 'staging.')) {
        return true;
    }
    return str_contains($host, 'hostingersite.com');
}

function ame_env_candidates(): array
{
    $app = rtrim(str_replace('\\', '/', ame_app_root()), '/');
    $paths = [];

    if (ame_is_staging_app()) {
        $paths[] = $app . '/includes/.env.staging';
        $paths[] = $app . '/.env.staging';
    }

    // Laravel installer / sale zip writes public_html/.env (DB_DATABASE keys).
    $paths[] = $app . '/.env';
    $paths[] = $app . '/includes/.env';

    $host = ame_request_host();
    // Preview Hostinger sites must not walk up to the account .env of another shop.
    if (str_contains($host, 'hostingersite.com')) {
        return array_values(array_unique($paths));
    }

    $name = ame_is_staging_app() ? '.env.staging' : '.env';
    $dir = $app;
    for ($i = 0; $i < 6; $i++) {
        $parent = dirname($dir);
        $paths[] = $parent . '/' . $name;
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    return array_values(array_unique($paths));
}

function ame_read_env_file(string $path): array
{
    $env = [];
    if (!is_file($path) || !is_readable($path)) {
        return $env;
    }
    $raw = (string) file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        $env[$key] = trim($value, "\"' ");
    }
    return $env;
}

function ame_env_path(): string
{
    $candidates = ame_env_candidates();
    $firstReadable = '';
    foreach ($candidates as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        if ($firstReadable === '') {
            $firstReadable = $path;
        }
        $env = ame_read_env_file($path);
        $name = (string) ($env['DB_NAME'] ?? $env['DB_DATABASE'] ?? '');
        $user = (string) ($env['DB_USER'] ?? $env['DB_USERNAME'] ?? '');
        if ($name !== '' && $user !== '') {
            return $path;
        }
    }
    if ($firstReadable !== '') {
        return $firstReadable;
    }
    return $candidates[0] ?? (ame_account_root() . (ame_is_staging_app() ? '/.env.staging' : '/.env'));
}

function ame_clear_env_cache(): void
{
    $keys = [
        'APP_ENV',
        'APP_URL',
        'LIVE_DOMAIN',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'FORCE_HTTPS',
    ];
    foreach ($keys as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

function ame_apply_env(array $env, bool $overwrite = false): void
{
    if (($env['DB_NAME'] ?? '') === '' && ($env['DB_DATABASE'] ?? '') !== '') {
        $env['DB_NAME'] = $env['DB_DATABASE'];
    }
    if (($env['DB_USER'] ?? '') === '' && ($env['DB_USERNAME'] ?? '') !== '') {
        $env['DB_USER'] = $env['DB_USERNAME'];
    }
    if (($env['DB_PASS'] ?? '') === '' && array_key_exists('DB_PASSWORD', $env)) {
        $env['DB_PASS'] = (string) $env['DB_PASSWORD'];
    }
    if (($env['LIVE_DOMAIN'] ?? '') === '' && ($env['APP_URL'] ?? '') !== '') {
        $env['LIVE_DOMAIN'] = $env['APP_URL'];
    }

    foreach ($env as $key => $value) {
        if ($key === '' || str_starts_with($key, '_')) {
            continue;
        }
        if ($overwrite || getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
