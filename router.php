<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
if (session_status() === PHP_SESSION_NONE) session_start();

$uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? '/'));
$path = parse_url($uri, PHP_URL_PATH);
$path = strtolower(rawurldecode(is_string($path) && $path !== '' ? $path : '/'));
$path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? '/', '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$ame_base = realpath(__DIR__);
$ame_home = __DIR__ . '/index.php';
$ame_front = in_array($path, ['/', '', '/index.php', '/index.html', '/home', '/home.php', '/router.php'], true);
if ($ame_front && is_file($ame_home)) {
    http_response_code(200);
    require $ame_home;
    exit;
}
$ame_rel = ltrim($path, '/');
if ($ame_base && $ame_rel !== '' && !str_contains($ame_rel, '..') && $ame_rel !== 'router.php') {
    $ame_candidates = [__DIR__ . '/' . $ame_rel];
    if (!str_ends_with($ame_rel, '.php')) {
        $ame_candidates[] = __DIR__ . '/' . $ame_rel . '.php';
        $ame_candidates[] = __DIR__ . '/' . $ame_rel . '/index.php';
    }
    foreach ($ame_candidates as $ame_candidate) {
        $ame_file = realpath($ame_candidate);
        if ($ame_file && is_file($ame_file) && str_starts_with($ame_file, $ame_base . DIRECTORY_SEPARATOR) && str_ends_with(strtolower($ame_file), '.php') && basename($ame_file) !== 'router.php') {
            http_response_code(200);
            require $ame_file;
            exit;
        }
    }
}

if (is_file(__DIR__ . '/includes/public_host_files.php')) {
    require_once __DIR__ . '/includes/public_host_files.php';
    if (function_exists('ame_ensure_public_host_files')) {
        ame_ensure_public_host_files();
    }
}
$hostPublic = [
    '/assets/css/sale-cream.css' => ['file' => __DIR__ . '/assets/css/sale-cream.css', 'type' => 'text/css; charset=UTF-8'],
    '/favicon.png' => ['file' => __DIR__ . '/favicon.png', 'type' => 'image/png'],
    '/robots.txt' => ['file' => __DIR__ . '/robots.txt', 'type' => 'text/plain; charset=UTF-8'],
];
if (isset($hostPublic[$path]) && is_file($hostPublic[$path]['file'])) {
    header('Content-Type: ' . $hostPublic[$path]['type']);
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($hostPublic[$path]['file']);
    exit;
}

$aliases = [
    '/modules/dashboard/index.php' => '/index.php',
    '/modules/dashboard/' => '/index.php',
    '/modules/dashboard' => '/index.php',
];
if (isset($aliases[$path])) {
    header('Location: ' . $aliases[$path], true, 301);
    exit();
}

function ame_router_safe_target(string $raw): ?string
{
    $raw = str_replace('\\', '/', $raw);
    $raw = ltrim($raw, '/');
    if ($raw === '' || str_contains($raw, "\0") || str_contains($raw, '..')) {
        return null;
    }
    if (preg_match('#^(?:[a-z]+:)?//#i', $raw) || str_starts_with($raw, '/')) {
        return null;
    }
    if (!preg_match('#^(modules|accounts|actions|inventory)/[A-Za-z0-9_./-]+\.php$#', $raw)) {
        return null;
    }
    $base = realpath(__DIR__);
    $candidate = realpath(__DIR__ . '/' . $raw);
    if (!$base || !$candidate || !is_file($candidate)) {
        return null;
    }
    $prefix = $base . DIRECTORY_SEPARATOR;
    if (!str_starts_with($candidate, $prefix)) {
        return null;
    }
    return $candidate;
}

// ১. ডাটাবেজ-ভিত্তিক ডায়নামিক রুট ম্যাপিং (system_routes থাকলে)
$connect_path = __DIR__ . '/includes/ame_db_connect.php';
if (is_file($connect_path)) {
    require_once $connect_path;
    if (!isset($db) || !($db instanceof mysqli)) {
        $opened = ame_open_mysqli();
        if ($opened instanceof mysqli) {
            $db = $opened;
        }
    }
}
$db_path = __DIR__ . '/includes/db.php';
if ((!isset($db) || !($db instanceof mysqli)) && file_exists($db_path)) {
    require_once $db_path;
}

if (isset($db) && $db instanceof mysqli) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'system_routes'");
        if ($check && $check->num_rows > 0) {
            $stmt = $db->prepare("SELECT target_file FROM system_routes WHERE request_slug = ? LIMIT 1");
            $stmt->bind_param("s", $uri);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && !empty($result['target_file'])) {
                $target = ame_router_safe_target((string)$result['target_file']);
                if ($target) {
                    require_once $target;
                    exit();
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        // রাউট লুকআপ ফেইল করলেও সাইট যেন ভেঙে না পড়ে
    }
}

// ২. Missing URL logging through the server error log. Runtime logs and
// throttle files must not be written into the public document root.
$missing_url = ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '/');
$missing_url = str_replace(["\r", "\n"], '', $missing_url);
error_log('404 Not Found: ' . $missing_url);

// ৩. Return correct HTTP semantics instead of masking broken links with a
// redirect to the authenticated dashboard.
ame_router_missing_page($path);

function ame_router_missing_page(string $path): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    $safe = htmlspecialchars($path !== '' ? $path : '/', ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>পাতা নেই</title><style>'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#0f172a;font-family:"Noto Sans Bengali","Segoe UI",sans-serif}'
        . 'main{width:min(420px,calc(100% - 32px));background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:28px 22px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.06)}'
        . 'p{margin:0;font-size:13px;font-weight:800;letter-spacing:.08em;color:#0b3d91}'
        . 'h1{margin:8px 0 0;font-size:32px;line-height:1.25}'
        . '.lead{margin-top:10px;font-size:16px;font-weight:600;letter-spacing:0;color:#334155;line-height:1.5}'
        . '.path{margin-top:8px;font-size:13px;font-weight:600;letter-spacing:0;color:#64748b;word-break:break-all}'
        . 'a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:18px;padding:0 18px;border-radius:999px;background:#0b3d91;color:#fff;text-decoration:none;font-weight:800}'
        . 'a:focus{outline:3px solid #93c5fd;outline-offset:3px}'
        . '</style></head><body><main>'
        . '<p>404</p><h1>পাতা নেই</h1>'
        . '<p class="lead">এই ঠিকানায় কিছু নেই।</p>'
        . '<p class="path">' . $safe . '</p>'
        . '<a href="/index.php">দোকানে যান</a>'
        . '</main></body></html>';
    exit();
}
