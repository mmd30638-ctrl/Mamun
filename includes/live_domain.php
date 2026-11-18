<?php
/**
 * চলতি লাইভ ডোমেন — .env এর APP_URL / LIVE_DOMAIN, নাহলে LIVE_DOMAIN.txt।
 * ক্রেতা শুধু ডোমেন বদলালেই এই হোস্ট বদলায়। হার্ডকোড নেই।
 */

function ame_host_from_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $host = parse_url($text, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $text = preg_replace('#^https?://#i', '', $text) ?? $text;
        $text = preg_replace('#/.*$#', '', $text) ?? $text;
        $text = preg_replace('/:\d+$/', '', $text) ?? $text;
        $host = $text;
    }
    $host = strtolower(trim($host, " \t\n\r\0\x0B/"));
    if ($host === 'your-domain.com' || $host === 'www.your-domain.com') {
        return '';
    }
    return $host;
}

function ame_resolve_live_host(): string
{
    foreach (['APP_URL', 'LIVE_DOMAIN'] as $key) {
        $val = getenv($key);
        if (is_string($val) && $val !== '') {
            $host = ame_host_from_text($val);
            if ($host !== '') {
                return $host;
            }
        }
    }
    $file = dirname(__DIR__) . '/LIVE_DOMAIN.txt';
    if (is_readable($file)) {
        $host = ame_host_from_text((string)file_get_contents($file));
        if ($host !== '') {
            return $host;
        }
    }
    $req = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($req !== '' && $req !== 'localhost' && $req !== '127.0.0.1') {
        return $req;
    }
    return $req !== '' ? $req : 'localhost';
}

if (!defined('AME_LIVE_HOST')) {
    define('AME_LIVE_HOST', ame_resolve_live_host());
}
if (!defined('AME_LIVE_URL')) {
    define('AME_LIVE_URL', 'https://' . AME_LIVE_HOST);
}

function ame_live_host(): string
{
    return AME_LIVE_HOST;
}

function ame_live_url(): string
{
    return AME_LIVE_URL;
}

function ame_retired_hosts(): array
{
    return [
        'aponshop.site',
        'www.aponshop.site',
        'aponshop.com',
        'www.aponshop.com',
    ];
}

function ame_is_retired_host(string $host): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
    return in_array($host, ame_retired_hosts(), true);
}

function ame_is_live_host(?string $host = null): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', trim((string) ($host ?? ''))) ?? '');
    if ($host === '') {
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    }
    $live = ame_live_host();
    return $host === $live || $host === 'www.' . $live;
}

function ame_request_is_https_transport(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';
}

function ame_canonical_live_redirect(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
        return;
    }
    if (str_contains($host, 'hostingersite.com')) {
        return;
    }
    if (function_exists('ame_is_staging_app') && ame_is_staging_app()) {
        return;
    }
    $live = ame_live_host();
    if ($live === '' || $live === 'localhost' || $live === 'your-domain.com') {
        return;
    }
    $https = ame_request_is_https_transport();
    $need = false;
    if ($host === 'www.' . $live) {
        $need = true;
    } elseif ($host === $live && !$https) {
        $force = strtolower((string)(getenv('FORCE_HTTPS') ?: '1'));
        if (in_array($force, ['1', 'true', 'yes', 'on', ''], true)) {
            $need = true;
        }
    } elseif (ame_is_retired_host($host) && $host !== 'staging.aponshop.site') {
        $need = true;
    }
    if (!$need) {
        return;
    }
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '') {
        $uri = '/';
    }
    header('Location: https://' . $live . $uri, true, 301);
    exit;
}
