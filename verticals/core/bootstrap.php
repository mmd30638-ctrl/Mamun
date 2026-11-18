<?php
declare(strict_types=1);

if (!defined('VX_ROOT') || !defined('VX_APP_FILE') || !defined('VX_CORE')) {
    http_response_code(500);
    exit('App is not configured');
}

require VX_CORE . '/engine.php';
require VX_CORE . '/ui.php';
require VX_CORE . '/pages.php';

vx_load_env(VX_ROOT . '/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (($_GET['r'] ?? '') === 'asset' && (($_GET['f'] ?? '') === 'app.css')) {
    header('Content-Type: text/css; charset=utf-8');
    readfile(VX_CORE . '/css/app.css');
    exit;
}

vx_lang_boot();

try {
    vx_dispatch();
} catch (Throwable $e) {
    $logDir = VX_ROOT . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    @file_put_contents($logDir . '/app.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
    $safe = $e->getMessage();
    if (stripos($safe, 'Access denied') !== false || stripos($safe, 'Unknown database') !== false || stripos($safe, 'Connection refused') !== false) {
        $safe = vx_lang() === 'en'
            ? 'Database connection failed. Check only this project .env file.'
            : 'ডাটাবেজ সংযোগ হয়নি। শুধু এই প্রজেক্টের .env ফাইল দেখুন।';
    } elseif (stripos($safe, 'mysqli') !== false || str_contains($safe, 'SQLSTATE')) {
        $safe = t('not_found');
    }
    http_response_code(500);
    if (vx_env('DB_NAME') !== '' && function_exists('vx_installed')) {
        try {
            if (vx_installed() && vx_user()) {
                vx_layout('not_found', '<p class="alert bad">' . vx_e($safe) . '</p>');
                exit;
            }
        } catch (Throwable) {
        }
    }
    echo '<p>' . vx_e($safe) . '</p>';
}
