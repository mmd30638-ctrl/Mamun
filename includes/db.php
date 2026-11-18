<?php /* AME_MYSQLI_GUARD */
/**
 * APONSHOP - DATABASE CONNECTION (mysqli only)
 *
 * ক্রেডেনশিয়াল এখন কোডে হার্ডকোড নেই — webroot-এর বাইরে রাখা .env ফাইল থেকে লোড হয়।
 * .env ফাইল কখনো গিট/জিপে শেয়ার করবেন না (.gitignore-এ যোগ করুন)।
 */

// .env ফাইলের প্রত্যাশিত পাথ: webroot-এর ঠিক এক ধাপ উপরে (public_html-এর বাইরে)
$env_path = dirname(__DIR__, 2) . '/.env';

$env = [];
if (is_file($env_path) && is_readable($env_path)) {
    foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $env[$key] = trim($value, "\"' ");
    }
}

if (!function_exists('env_get')) {
    function env_get(array $env, string $key, string $default = ''): string {
        $fromProcess = getenv($key);
        if ($fromProcess !== false && $fromProcess !== '') {
            return $fromProcess;
        }
        return $env[$key] ?? $default;
    }
}

if (!function_exists('ame_mysqli_try')) {
    function ame_mysqli_try(string $host, string $user, string $pass, string $name): ?mysqli
    {
    try {
        $db = @new mysqli($host, $user, $pass, $name);
    } catch (Throwable $e) {
        error_log('MySQLi Connection Failed (' . $host . '): ' . $e->getMessage());
        return null;
    }
    if (!$db instanceof mysqli || $db->connect_error) {
        $why = $db instanceof mysqli ? (string) $db->connect_error : 'connect threw';
        error_log('MySQLi Connection Failed (' . $host . '): ' . $why);
        if ($db instanceof mysqli) {
            /* AME_MYSQLI_GUARD skip close */
        }
        return null;
    }
    return $db;
    }
}

$host     = env_get($env, 'DB_HOST', 'localhost');
$db_name  = env_get($env, 'DB_NAME');
$username = env_get($env, 'DB_USER');
$password = env_get($env, 'DB_PASS');

if ($db_name === '' || $username === '') {
    error_log('DB config missing: .env not found or incomplete at ' . $env_path);
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:2px solid red; margin:20px;'>"
        . "<b>সার্ভার কনফিগারেশন সমস্যা। এডমিনের সাথে যোগাযোগ করুন।</b></div>");
}

mysqli_report(MYSQLI_REPORT_OFF);
$db = ame_mysqli_try($host, $username, $password, $db_name);
if (!$db instanceof mysqli && strtolower($host) === 'localhost') {
    // Hostinger: localhost often hits a unix socket that returns EPERM.
    $db = ame_mysqli_try('127.0.0.1', $username, $password, $db_name);
}

if (!$db instanceof mysqli) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:2px solid red; margin:20px;'>
            <b>ডাটাবেজ কানেকশন সমস্যা হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।</b>
         </div>");
}

$db->set_charset("utf8mb4");
