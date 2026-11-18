<?php
/**
 * Secrets live in .env or private_data (outside public_html), not MySQL dumps.
 */

function ame_secret_file(string $name): string
{
    $dir = defined('AME_PRIVATE_DATA_DIR')
        ? AME_PRIVATE_DATA_DIR
        : dirname(__DIR__, 2) . '/private_data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return rtrim($dir, '/') . '/' . $name . '.secret.json';
}

function ame_secret_read(string $name): array
{
    $path = ame_secret_file($name);
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function ame_secret_write(string $name, array $data): bool
{
    $path = ame_secret_file($name);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $ok = @file_put_contents($path, $json, LOCK_EX);
    if ($ok === false) {
        return false;
    }
    @chmod($path, 0600);
    return true;
}

function ame_env_nonempty(string $key): string
{
    $v = getenv($key);
    if ($v === false) {
        return '';
    }
    return trim((string)$v);
}
