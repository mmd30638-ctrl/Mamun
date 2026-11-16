<?php
/**
 * Public sales-memo link.
 * The token is an HMAC of the sale id. No invoice row is updated.
 * Logged-in staff can still open a memo by id.
 */

function ame_memo_share_key(): string
{
    $env = getenv('AME_MEMO_KEY');
    if (is_string($env) && strlen($env) >= 16) {
        return $env;
    }
    $dir = defined('AME_PRIVATE_DATA_DIR')
        ? (string) AME_PRIVATE_DATA_DIR
        : dirname(__DIR__, 2) . '/private_data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $path = rtrim($dir, '/') . '/memo_share.key';
    if (!is_file($path)) {
        $raw = random_bytes(32);
        if (@file_put_contents($path, base64_encode($raw), LOCK_EX) === false) {
            return '';
        }
        @chmod($path, 0600);
    }
    $stored = trim((string) @file_get_contents($path));
    return strlen($stored) >= 16 ? $stored : '';
}

function ame_memo_token(int $saleId): string
{
    $key = ame_memo_share_key();
    if ($key === '' || $saleId <= 0) {
        return '';
    }
    return hash_hmac('sha256', 'sale:' . $saleId, $key);
}

function ame_memo_token_ok(int $saleId, string $token): bool
{
    $expect = ame_memo_token($saleId);
    $token = trim($token);
    if ($expect === '' || $token === '') {
        return false;
    }
    return hash_equals($expect, $token);
}

function ame_memo_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'mavo12.online');
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', $host) ?? '';
    return $host !== '' ? $host : 'mavo12.online';
}

function ame_memo_public_url(int $saleId): string
{
    $token = ame_memo_token($saleId);
    $scheme = function_exists('ame_is_https') && ame_is_https() ? 'https' : 'http';
    $query = 'id=' . $saleId;
    if ($token !== '') {
        $query .= '&t=' . rawurlencode($token);
    }
    return $scheme . '://' . ame_memo_host() . '/modules/sales/invoice.php?' . $query;
}
