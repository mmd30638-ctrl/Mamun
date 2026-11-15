<?php
/**
 * এক ক্লিকে আপডেট ZIP — /get/Aponshop_POS_STACK_UPDATE.zip
 * লগইন লাগে না; শুধু allowlist নাম।
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/pack_zip.php';

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
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '❌ ZIP এখনও সার্ভারে নেই। ডেভেলপারকে deliverables/ বা downloads/ এ রাখতে বলুন।';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ame_stream_zip_file($path, $file);