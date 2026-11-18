<?php
/**
 * এক ক্লিকে আপডেট ZIP — /get/Aponshop_POS_STACK_UPDATE.zip
 * লগইন লাগে না; শুধু allowlist নাম।
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/pack_zip.php';

/** GitHub-এ থাকা প্যাচ — সার্ভারে জিপ না থাকলে /get/ এখানে পাঠায় */
const AME_PATCH_GITHUB_RAW_BASE = 'https://raw.githubusercontent.com/mmd30638-ctrl/Mamun/cursor/global-responsive-architecture-ab1e/downloads/';

function ame_patch_github_raw_url(string $file): string
{
    if (preg_match('/^Aponshop_\d+\.\d+\.\d+_(PATCH|FULL_STACK|LIVE)\.zip$/i', $file) !== 1) {
        return '';
    }
    return AME_PATCH_GITHUB_RAW_BASE . rawurlencode($file);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$file = basename((string)($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.zip$/i', $file)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '❌ ফাইল নাম সঠিক নয়।';
    exit;
}

$allowed = $file === 'Aponshop_POS_STACK_UPDATE.zip'
    || $file === 'Aponshop.zip'
    || preg_match('/^Aponshop_\d+\.\d+\.\d+_(PATCH|FULL_STACK|LIVE)\.zip$/i', $file) === 1;

if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '❌ এই ফাইল সরাসরি ডাউনলোডের জন্য খোলা নয়।';
    exit;
}

$dirs = [
    __DIR__ . '/downloads',
    __DIR__ . '/deliverables',
];
$path = null;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $candidate = $dir . '/' . $file;
    if (is_file($candidate) && filesize($candidate) > 100) {
        $real = realpath($candidate);
        $realDir = realpath($dir);
        if ($real !== false && $realDir !== false && str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            $path = $real;
            break;
        }
    }
}

if ($path === null && $file === 'Aponshop_POS_STACK_UPDATE.zip') {
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $glob = glob($dir . '/Aponshop_*_FULL_STACK.zip') ?: [];
        usort($glob, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        if ($glob !== [] && is_file($glob[0])) {
            $path = $glob[0];
            break;
        }
    }
}

if ($path === null) {
    $fallbackFile = __DIR__ . '/data/patch_get_fallback.json';
    $fallbackUrl = '';
    if (is_file($fallbackFile)) {
        $raw = file_get_contents($fallbackFile);
        $map = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($map) && isset($map[$file]) && is_string($map[$file])) {
            $try = trim($map[$file]);
            if (preg_match('#^https://raw\.githubusercontent\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/[A-Za-z0-9._/-]+/downloads/[A-Za-z0-9._-]+\.zip$#', $try)) {
                $fallbackUrl = $try;
            }
        }
    }
    if ($fallbackUrl === '') {
        $fallbackUrl = ame_patch_github_raw_url($file);
    }
    if ($fallbackUrl !== '') {
        header('Location: ' . $fallbackUrl, true, 302);
        header('Cache-Control: no-store');
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    $gh = ame_patch_github_raw_url($file);
    echo '❌ ZIP এখনও সার্ভারে নেই। Hostinger ফাইল ম্যানেজারে public_html/downloads/' . $file . ' আপলোড করুন।';
    if ($gh !== '') {
        echo "\n\nসরাসরি ডাউনলোড:\n" . $gh;
    }
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ame_stream_zip_file($path, $file);