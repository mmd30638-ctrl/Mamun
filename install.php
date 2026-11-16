<?php
/**
 * APONSHOP — নতুন দোকান ইনস্টল। লগিন লাগে না।
 * .env লেখে, ডাটাবেজ টেবিল তৈরি করে, admin ইউজার বসায়।
 */
header('Cache-Control: no-store');
@ini_set('max_execution_time', '180');
@set_time_limit(180);

require_once __DIR__ . '/includes/env_bootstrap.php';
require_once __DIR__ . '/includes/ame_db_connect.php';
require_once __DIR__ . '/includes/first_install.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root = ame_app_root();
$lock = $root . '/storage/install.lock';
$envFile = $root . '/.env';

function ame_install_env_line(string $key, string $value): string
{
    if ($value !== '' && preg_match('/[\s#"\']/', $value)) {
        return $key . '="' . str_replace(['\\', '"'], ['\\\\', '\"'], $value) . '"';
    }
    return $key . '=' . $value;
}

function ame_install_write_env(string $file, array $data): bool
{
    $lines = [
        '# APONSHOP — এই ফাইলে শুধু ডোমেন ও ডাটাবেজ ইউজার/পাসওয়ার্ড বদলান।',
        ame_install_env_line('APP_ENV', 'production'),
        ame_install_env_line('APP_URL', $data['url']),
        ame_install_env_line('LIVE_DOMAIN', $data['url']),
        ame_install_env_line('DB_HOST', $data['host']),
        ame_install_env_line('DB_PORT', $data['port']),
        ame_install_env_line('DB_NAME', $data['name']),
        ame_install_env_line('DB_USER', $data['user']),
        ame_install_env_line('DB_PASS', $data['pass']),
        ame_install_env_line('FORCE_HTTPS', $data['https']),
        '',
    ];
    $ok = @file_put_contents($file, implode("\n", $lines)) !== false;
    if ($ok) {
        @chmod($file, 0600);
    }
    return $ok;
}

$error = '';
$done = false;
$report = null;

$existingDb = null;
if (is_file($envFile)) {
    ame_apply_env(ame_read_env_file($envFile), true);
    $existingDb = ame_open_mysqli();
}

if ($existingDb instanceof mysqli && !ame_install_needed($existingDb)) {
    header('Location: /login.php');
    exit;
}

if ($existingDb instanceof mysqli && ame_install_needed($existingDb) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $report = ame_first_install($existingDb);
    if (empty($report['fail']) || !empty($report['admin'])) {
        @mkdir($root . '/storage', 0755, true);
        @file_put_contents($lock, date('c') . "\n");
        $done = true;
    } else {
        $error = 'টেবিল বসানো যায়নি: ' . implode(' · ', array_slice($report['fail'], 0, 3));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim((string)($_POST['app_url'] ?? ''));
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $port = trim((string)($_POST['db_port'] ?? '3306'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $https = isset($_POST['force_https']) ? '1' : '0';

    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        $error = 'ডোমেন লিখুন, যেমন https://apnar-dokan.com';
    } elseif ($name === '' || $user === '') {
        $error = 'ডাটাবেজের নাম ও ইউজারনেম দিতে হবে।';
    } else {
        $url = rtrim($url, '/');
        if (!ame_install_write_env($envFile, [
            'url' => $url,
            'host' => $host !== '' ? $host : 'localhost',
            'port' => ctype_digit($port) ? $port : '3306',
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'https' => $https,
        ])) {
            $error = '.env লেখা যায়নি। public_html-এ লেখার অনুমতি দিন।';
        } else {
            @file_put_contents($root . '/LIVE_DOMAIN.txt', $url . "\n");
            if (function_exists('ame_clear_env_cache')) {
                ame_clear_env_cache();
            }
            ame_apply_env(ame_read_env_file($envFile), true);
            unset($GLOBALS['db']);
            $db = ame_open_mysqli();
            if (!$db instanceof mysqli) {
                $error = 'ডাটাবেজে যোগাযোগ হয়নি। হোস্ট, নাম, ইউজারনেম ও পাসওয়ার্ড মিলিয়ে দেখুন। খালি ডাটাবেজ Hostinger-এ আগে তৈরি থাকতে হবে।';
            } else {
                $report = ame_first_install($db);
                if (!empty($report['fail']) && empty($report['admin'])) {
                    $error = 'টেবিল বসানো যায়নি: ' . implode(' · ', array_slice($report['fail'], 0, 3));
                } else {
                    @mkdir($root . '/storage', 0755, true);
                    @file_put_contents($lock, date('c') . "\n");
                    $done = true;
                }
            }
        }
    }
}

$hostGuess = (string)($_SERVER['HTTP_HOST'] ?? 'your-domain.com');
$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
$defaultUrl = ($httpsOn ? 'https://' : 'http://') . $hostGuess;
$adminUser = ame_default_admin_username();
$adminPass = ame_default_admin_password();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>APONSHOP ইনস্টল</title>
    <style>
        :root { --ink:#0f172a; --line:#cbd5e1; --ok:#047857; --bad:#b91c1c; --bg:#f8fafc; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        .wrap { max-width: 520px; margin: 24px auto; padding: 0 16px 40px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p.lead { color:#475569; margin: 0 0 20px; line-height: 1.5; }
        form, .card { background:#fff; border:1px solid var(--line); border-radius: 16px; padding: 20px; }
        label { display:block; font-weight: 700; font-size: 14px; margin: 12px 0 4px; }
        input[type=text], input[type=password], input[type=url] {
            width:100%; padding: 12px; border:1px solid var(--line); border-radius: 10px; font-size: 16px;
        }
        .row { display:grid; grid-template-columns: 1fr 90px; gap: 8px; }
        button {
            margin-top: 18px; width:100%; padding: 14px; border:0; border-radius: 12px;
            background:#1d4ed8; color:#fff; font-weight: 800; font-size: 16px;
        }
        .err { background:#fef2f2; color: var(--bad); padding: 12px; border-radius: 10px; margin-bottom: 12px; }
        .ok { background:#ecfdf5; color: var(--ok); padding: 16px; border-radius: 12px; }
        .ok code { font-size: 16px; font-weight: 800; }
        .hint { font-size: 13px; color:#64748b; margin-top: 4px; }
        .check { display:flex; gap:8px; align-items:center; margin-top: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>APONSHOP ইনস্টল</h1>
    <?php if ($done): ?>
        <div class="ok">
            <p>টেবিল বসেছে। সফটওয়্যারে ঢুকুন:</p>
            <p>ইউজারনেম: <code><?php echo htmlspecialchars($adminUser, ENT_QUOTES, 'UTF-8'); ?></code><br>
               পাসওয়ার্ড: <code><?php echo htmlspecialchars($adminPass, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p><a href="/login.php">লগইন পাতা খুলুন</a></p>
        </div>
    <?php else: ?>
        <p class="lead">Hostinger-এ আগে খালি ডাটাবেজ তৈরি করুন। নিচে ডোমেন ও ডাটাবেজের ইউজারনেম/পাসওয়ার্ড দিন — টেবিল নিজেই বসবে। পুরোনো হিসাব মুছে যায় না।</p>
        <?php if ($error !== ''): ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
            <label>ডোমেন (APP_URL)</label>
            <input type="url" name="app_url" required value="<?php echo htmlspecialchars($defaultUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://apnar-dokan.com">
            <p class="hint">শুধু এই ডোমেন ও নিচের ডাটাবেজ তথ্য বদলালেই সফটওয়্যার চলে।</p>

            <label>ডাটাবেজ হোস্ট</label>
            <div class="row">
                <input type="text" name="db_host" value="localhost" required>
                <input type="text" name="db_port" value="3306" inputmode="numeric">
            </div>
            <label>ডাটাবেজের নাম</label>
            <input type="text" name="db_name" required placeholder="u123_aponshop" value="">
            <label>ডাটাবেজ ইউজারনেম</label>
            <input type="text" name="db_user" required autocomplete="off" value="">
            <label>ডাটাবেজ পাসওয়ার্ড</label>
            <input type="password" name="db_pass" autocomplete="new-password">
            <label class="check"><input type="checkbox" name="force_https" value="1" checked> HTTPS বাধ্যতামূলক</label>
            <button type="submit">টেবিল বসাও ও শেষ করো</button>
        </form>
        <p class="hint" style="margin-top:16px">লগইন: <strong><?php echo htmlspecialchars($adminUser . ' / ' . $adminPass, ENT_QUOTES, 'UTF-8'); ?></strong></p>
    <?php endif; ?>
</div>
</body>
</html>
