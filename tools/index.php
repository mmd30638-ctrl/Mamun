<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
admin_only();

const STACK_ZIP = 'Aponshop_POS_STACK_UPDATE.zip';
const ZIP_LIMIT = 9437184; // 9 MB
const VERSION = '10.98.566';

$root = dirname(__DIR__);
$backupDir = $root . '/backups';
$tmpDir = $root . '/storage/update-tmp';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function list_zip_names(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat && isset($stat['name'])) {
            $names[] = str_replace('\\', '/', (string) $stat['name']);
        }
    }
    $zip->close();
    return $names;
}

function inspect_stack_zip(string $fileName, string $path): array
{
    $base = basename($fileName);
    if (preg_match('/ \(\d+\)\.zip$/i', $base)) {
        return [false, 'ফাইলের নামে (1) আছে। ফোনের ফাইল ম্যানেজারে (1) তুলে দিন — তারপর টুলসে দিন।'];
    }
    $allowed = $base === STACK_ZIP
        || (bool) preg_match('/^Aponshop_\d+\.\d+\.\d+_FULL_STACK\.zip$/i', $base)
        || (bool) preg_match('/^Aponshop_\d+\.\d+\.\d+_PATCH\.zip$/i', $base)
        || (bool) preg_match('/^aponshopone\d+\.zip$/i', $base);
    if (!$allowed) {
        return [false, 'ফাইলের নাম Aponshop_<VERSION>_FULL_STACK.zip অথবা ' . STACK_ZIP . ' হতে হবে — ভিতরে আরেকটা zip নয়।'];
    }
    if (!is_file($path)) {
        return [false, 'ZIP পাওয়া যায়নি।'];
    }
    if (filesize($path) > ZIP_LIMIT) {
        return [false, 'Hostinger ফোনে ৯ MB ZIP সীমা পেরিয়ে গেছে।'];
    }
    $names = list_zip_names($path);
    if ($names === []) {
        return [false, 'ZIP খোলা যায়নি।'];
    }
    $nested = false;
    $hasPublic = false;
    $hasSystem = false;
    foreach ($names as $name) {
        $lower = strtolower($name);
        if (str_ends_with($lower, '.zip') && !str_ends_with($name, '/')) {
            $nested = true;
        }
        if ($name === 'public_html' || $name === 'public_html/' || str_starts_with($name, 'public_html/')) {
            $hasPublic = true;
        }
        if (preg_match('#^public_html/(index\.php|v\.php|config\.php|includes/|\.htaccess|system/|APONSHOP_STACK_UPDATE\.txt|VERSION\.txt)#i', $name)) {
            $hasSystem = true;
        }
    }
    if ($nested && !$hasPublic) {
        unwrap_if_nested($path);
        $names = list_zip_names($path);
        $nested = false;
        $hasPublic = false;
        $hasSystem = false;
        foreach ($names as $name) {
            $lower = strtolower($name);
            if (str_ends_with($lower, '.zip') && !str_ends_with($name, '/')) {
                $nested = true;
            }
            if ($name === 'public_html' || $name === 'public_html/' || str_starts_with($name, 'public_html/')) {
                $hasPublic = true;
            }
            if (preg_match('#^public_html/(index\.php|v\.php|config\.php|includes/|\.htaccess|system/|APONSHOP_STACK_UPDATE\.txt|VERSION\.txt)#i', $name)) {
                $hasSystem = true;
            }
        }
    }
    if ($nested && !$hasPublic) {
        return [false, 'ZIP-এর ভিতরে আরেকটা zip আছে। ' . STACK_ZIP . ' দিন — ভিতরে আরেকটা zip নয়!'];
    }
    if (!$hasPublic || !$hasSystem) {
        return [false, 'ZIP-এ public_html সিস্টেম ফাইল পাওয়া যায়নি! ' . STACK_ZIP . ' দিন — ভিতরে আরেকটা zip নয়!'];
    }
    return [true, 'জিপ ঠিক আছে।'];
}

function should_skip(string $relative): bool
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if ($relative === '.env' || str_starts_with($relative, '.env.')) {
        return true;
    }
    if ($relative === 'uploads' || str_starts_with($relative, 'uploads/')) {
        return true;
    }
    if (str_starts_with($relative, 'backups/')) {
        return true;
    }
    return false;
}

function safe_target(string $root, string $relative): ?string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        return null;
    }
    $target = $root . '/' . $relative;
    $realRoot = realpath($root) ?: $root;
    $parent = dirname($target);
    if (!is_dir($parent)) {
        mkdir($parent, 0755, true);
    }
    $resolvedParent = realpath($parent);
    if ($resolvedParent === false || !str_starts_with($resolvedParent, $realRoot)) {
        return null;
    }
    return $target;
}

function make_backup(string $root, string $backupDir): string
{
    ensure_dir($backupDir);
    $stamp = date('Ymd-His');
    $file = $backupDir . '/safety-' . $stamp . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ব্যাকআপ লেখা যায়নি');
    }
    $keep = ['index.php', 'v.php', 'config.php', 'index.html', '.htaccess', 'manifest.php'];
    foreach ($keep as $name) {
        $path = $root . '/' . $name;
        if (is_file($path)) {
            $zip->addFile($path, $name);
        }
    }
    $zip->setArchiveComment('v' . VERSION . ' safety backup');
    $zip->close();
    return $file;
}

function extract_stack(string $zipPath, string $root): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('ZIP খোলা যায়নি');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat) {
            continue;
        }
        $name = str_replace('\\', '/', (string) $stat['name']);
        if (!str_starts_with($name, 'public_html/')) {
            continue;
        }
        $relative = substr($name, strlen('public_html/'));
        if ($relative === '' || str_ends_with($name, '/')) {
            continue;
        }
        if (should_skip($relative)) {
            continue;
        }
        $target = safe_target($root, $relative);
        if ($target === null) {
            continue;
        }
        $contents = $zip->getFromIndex($i);
        if ($contents === false) {
            continue;
        }
        file_put_contents($target, $contents);
    }
    $zip->close();
    $dbFile = $root . '/includes/db.php';
    $guard = $root . '/includes/ame_mysqli_guard.php';
    if (is_file($guard)) {
        require_once $guard;
        if (function_exists('ame_mysqli_patch_db_file')) {
            ame_mysqli_patch_db_file($dbFile);
        }
    }
}

function current_version(string $root): string
{
    $file = $root . '/v.php';
    if (!is_file($file)) {
        return VERSION;
    }
    $text = (string) file_get_contents($file);
    if (preg_match('/(\d+\.\d+\.\d+)/', $text, $m)) {
        return $m[1];
    }
    return VERSION;
}

function unwrap_if_nested(string $path): void
{
    $names = list_zip_names($path);
    $hasPublic = false;
    $inner = [];
    foreach ($names as $name) {
        $norm = str_replace('\\', '/', $name);
        if ($norm === 'public_html' || $norm === 'public_html/' || str_starts_with($norm, 'public_html/')) {
            $hasPublic = true;
        }
        $base = basename($norm);
        if (!str_ends_with($norm, '/') && str_ends_with(strtolower($base), '.zip')) {
            $inner[] = $norm;
        }
    }
    if ($hasPublic || count($inner) !== 1) {
        return;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return;
    }
    $bytes = $zip->getFromName($inner[0]);
    $zip->close();
    if ($bytes === false || strlen($bytes) < 100) {
        return;
    }
    file_put_contents($path, $bytes);
}

function is_html_bytes(string $data): bool
{
    $head = ltrim(substr($data, 0, 240));
    return str_starts_with($head, '<')
        || stripos($head, '<html') !== false
        || stripos($head, '<!doctype') !== false;
}

function zip_start(string $data): int
{
    $pk = strpos($data, "PK\x03\x04");
    return $pk === false ? -1 : $pk;
}

function http_get_filebin(string $url): string
{
    $ua = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36';
    $headers = [
        'User-Agent: ' . $ua,
        'Accept: application/zip,application/octet-stream,*/*',
        'Cookie: verified=2024-05-24',
    ];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (is_string($body) && $body !== '') {
            return $body;
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 90,
            'follow_location' => 1,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return is_string($data) ? $data : '';
}

function fetch_archive(string $url, string $dest): void
{
    if (!preg_match('#^https://(filebin\.net|storage\.filebin\.net)/#i', $url)) {
        throw new RuntimeException('শুধু filebin.net আর্কাইভ লিংক চলবে।');
    }
    if (preg_match('#^https://filebin\.net/([^/]+)/[^/]+\.zip$#i', $url, $m)
        && !str_starts_with(strtolower($m[1]), 'archive')) {
        throw new RuntimeException('ফাইলবিন সরাসরি ফাইল লিংক HTML নামান — আর্কাইভ লিংক ব্যবহার করুন।');
    }
    $data = http_get_filebin($url);
    if ($data === '' || strlen($data) < 100) {
        throw new RuntimeException('আর্কাইভ লিংক থেকে ZIP নামানো যায়নি।');
    }
    $pk = zip_start($data);
    if (is_html_bytes($data) || $pk < 0) {
        throw new RuntimeException('ফাইলবিন সরাসরি ফাইল লিংক HTML এসেছে — আর্কাইভ লিংক ব্যবহার করুন।');
    }
    if ($pk > 0) {
        $data = substr($data, $pk);
    }
    file_put_contents($dest, $data);
    unwrap_if_nested($dest);
}

$message = '';
$error = '';
$ok = false;

ensure_dir($backupDir);
ensure_dir($tmpDir);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $zipPath = $tmpDir . '/' . STACK_ZIP;
        if (!empty($_POST['archive_url'])) {
            fetch_archive(trim((string) $_POST['archive_url']), $zipPath);
            $fileName = STACK_ZIP;
        } elseif (!empty($_FILES['zip']['tmp_name'])) {
            $fileName = (string) ($_FILES['zip']['name'] ?? '');
            if (!move_uploaded_file($_FILES['zip']['tmp_name'], $zipPath)) {
                throw new RuntimeException('আপলোড যায়নি।');
            }
            unwrap_if_nested($zipPath);
            if (is_file($zipPath)) {
                $fileName = STACK_ZIP;
            }
        } else {
            throw new RuntimeException('ZIP বা আর্কাইভ লিংক দিন।');
        }

        [$valid, $note] = inspect_stack_zip($fileName, $zipPath);
        if (!$valid) {
            throw new RuntimeException($note);
        }
        make_backup($root, $backupDir);
        extract_stack($zipPath, $root);
        @unlink($zipPath);
        $ok = true;
        $message = 'ইমপোর্ট হয়েছে। /v.php খুলে ভার্সন দেখুন।';
    } elseif (!empty($_GET['archive'])) {
        $_POST['archive_url'] = (string) $_GET['archive'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $zipPath = $tmpDir . '/' . STACK_ZIP;
        fetch_archive(trim((string) $_GET['archive']), $zipPath);
        [$valid, $note] = inspect_stack_zip(STACK_ZIP, $zipPath);
        if (!$valid) {
            throw new RuntimeException($note);
        }
        make_backup($root, $backupDir);
        extract_stack($zipPath, $root);
        @unlink($zipPath);
        $ok = true;
        $message = 'আর্কাইভ লিংক থেকে ইমপোর্ট হয়েছে। /v.php খুলুন।';
    } elseif (!empty($_GET['restore']) && preg_match('/^safety-\d{8}-\d{6}\.zip$/', (string) $_GET['restore'])) {
        $file = $backupDir . '/' . $_GET['restore'];
        if (!is_file($file)) {
            throw new RuntimeException('ব্যাকআপ নেই।');
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('ব্যাকআপ খোলা যায়নি।');
        }
        $zip->extractTo($root);
        $zip->close();
        $ok = true;
        $message = 'ব্যাকআপ ফিরিয়ে আনা হয়েছে।';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$backups = [];
foreach (glob($backupDir . '/safety-*.zip') ?: [] as $file) {
    $backups[] = [
        'name' => basename($file),
        'at' => filemtime($file) ?: time(),
        'bytes' => filesize($file) ?: 0,
    ];
}
usort($backups, fn($a, $b) => $b['at'] <=> $a['at']);
$backups = array_slice($backups, 0, 8);
$version = current_version($root);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>সেটিংস টুলস · v<?= h($version) ?></title>
  <style>
    :root { color-scheme: dark; }
    body { margin:0; font-family: system-ui, sans-serif; background:#07111d; color:#e2e8f0; }
    .wrap { max-width: 42rem; margin: 0 auto; padding: 1rem 1rem 3rem; }
    .bar { display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; padding:12px 0; }
    .pill { border-radius:999px; padding:4px 10px; font-size:11px; background:#134e4a; color:#bbf7d0; }
    .card { border:1px solid #1e293b; background:#0f172a; border-radius:16px; padding:16px; margin:12px 0; }
    .gold { color:#fde68a; }
    .btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:8px 14px; border-radius:10px; border:0; background:#f59e0b; color:#082f49; font-weight:700; }
    .btn2 { background:#1e293b; color:#e2e8f0; border:1px solid #334155; }
    input[type=file], input[type=url] { width:100%; margin:8px 0; color:#e2e8f0; }
    .ok { color:#6ee7b7; } .err { color:#fca5a5; }
    .row { display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; border:1px solid #1e293b; border-radius:10px; padding:10px; margin-top:8px; }
    a { color:#fde68a; }
    .gauge { height:8px; background:#1e293b; border-radius:999px; overflow:hidden; }
    .gauge > span { display:block; height:100%; background:linear-gradient(90deg,#f59e0b,#fb923c); width:<?= $ok ? 92 : ($error ? 18 : 40) ?>%; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="bar">
      <strong>স্ট্যাক আপডেট ইঞ্জিন · v<?= h($version) ?></strong>
      <div>
        <span class="pill">পাওয়ার</span>
        <span class="pill">ইঞ্জিন</span>
        <span class="pill">চাপ</span>
      </div>
    </div>
    <div class="card">
      <div class="gauge"><span></span></div>
      <p>Hostinger ফোনে <b class="gold">৯ MB ZIP</b> একবারে পোস্ট করলে পেজ মরে যায় (ERR_HTTP2_PING_FAILED)। নিচে <b>লিংক থেকে সার্ভার নামান</b> চাপুন, অথবা ছোট PATCH ZIP খণ্ডে আপলোড হবে — পেজ বদলাবে না। ফাইলবিন সরাসরি ফাইল লিংক HTML নামান; আর্কাইভ লিংক ব্যবহার করুন। <code>.env</code> ও <code>uploads</code> ছবি থাকবে। স্পিড সিস্টেম (<code>SPEED.txt</code>) থাকবে — <a href="/modules/settings/speed_system.php">সেটিংস → স্পিড সিস্টেম</a>। মুছবেন না।</p>
    </div>
    <?php if ($error): ?>
      <script>alert(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>);</script>
      <div class="card err"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="card ok"><?= h($message) ?></div>
    <?php endif; ?>
    <div class="card">
      <h3 class="gold">ধাপ ১ — ব্যাকআপ</h3>
      <p>প্রতিটি ইমপোর্টের ঠিক আগে ইঞ্জিন নিজে থেকেই একটা সেফটি-ব্যাকআপ বেঁধে রাখে — নিচে «ব্যাকআপ ও ফিরে যাওয়া» অংশে দেখতে পাবেন।</p>
    </div>
    <div class="card">
      <h3 class="gold">খণ্ডে আপলোড</h3>
      <p>শুধু <code>Aponshop_&lt;VERSION&gt;_FULL_STACK.zip</code> দিন — ভিতরে <code>public_html</code> সিস্টেম ফাইল থাকতে হবে, আরেকটা zip নয়। ফোন থেকে ৯ MB একবারে পোস্ট করবেন না।</p>
      <form method="post" enctype="multipart/form-data">
        <input type="file" name="zip" accept=".zip,application/zip">
        <button class="btn" type="submit">ছোট PATCH ZIP খণ্ডে আপলোড</button>
      </form>
      <form method="post" style="margin-top:12px">
        <input type="url" name="archive_url" value="https://filebin.net/archive/mavo12one566a/zip" placeholder="https://filebin.net/archive/mavo12one566a/zip">
        <button class="btn btn2" type="submit">লিংক থেকে সার্ভার নামান</button>
      </form>
      <p>
        সহজ আপডেট ডিরেক্ট জিপ:
        <a href="https://filebin.net/archive/mavo12one566a/zip">https://filebin.net/archive/mavo12one566a/zip</a>
      </p>
      <p>ইমপোর্টের পর <a href="/v.php">/v.php</a> খুলুন — উপরে দেখাবে বর্তমান ভার্সন নম্বর।</p>
    </div>
    <div class="card">
      <h3 class="gold">ব্যাকআপ ও ফিরে যাওয়া</h3>
      <p>কোনো আপডেটের পর সমস্যা হলে নিচের যেকোনোটা সময়ে ফিরে যেতে পারবেন।</p>
      <?php if (!$backups): ?>
        <p>এখনও কোনো ব্যাকআপ নেই।</p>
      <?php endif; ?>
      <?php foreach ($backups as $item): ?>
        <div class="row">
          <div>
            <div><?= h(date('d M Y, h:i A', $item['at'])) ?></div>
            <small>ভার্সন v<?= h($version) ?> · আকার: <?= (int) ceil($item['bytes'] / 1024) ?> KB</small>
          </div>
          <a class="btn btn2" href="?restore=<?= h(urlencode($item['name'])) ?>">ফিরে যান</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
