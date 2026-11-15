<?php
/**
 * Preflight-Compliant update ZIP builder (CLI + admin web).
 *
 * Usage (CLI):
 *   php make_update.php              # Aponshop_POS_STACK_UPDATE.zip (public_html/)
 *   php make_update.php --patch      # ছোট PATCH ZIP — শুধু manifest ফাইল
 *   php make_update.php --force      # cached ZIP পুনরায় বানাবে
 *
 * PATCH manifest: tools/update_manifest.txt (এক লাইনে একটি relative path)
 */
declare(strict_types=1);

$root = __DIR__;
$isCli = PHP_SAPI === 'cli';

$argvList = $isCli ? ($_SERVER['argv'] ?? []) : [];
$patchMode = in_array('--patch', $argvList, true);
$force = in_array('--force', $argvList, true) || (!$isCli && isset($_GET['force']) && (string)$_GET['force'] === '1');

if (!$isCli) {
    require_once $root . '/includes/config.php';
    admin_only();
    $patchMode = isset($_GET['patch']) && (string)$_GET['patch'] === '1';
}

require_once $root . '/includes/pack_zip.php';
require_once $root . '/includes/easy_update_functions.php';

function make_update_read_manifest(string $root): array
{
    $file = $root . '/tools/update_manifest.txt';
    if (!is_file($file)) {
        return [];
    }
    $paths = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim(str_replace('\\', '/', (string)$line));
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $paths[] = $line;
    }
    return $paths;
}

function make_update_stage_dir(string $root, array $relPaths): string
{
    $stage = $root . '/storage/update-build-' . date('Ymd-His') . '-' . getmypid();
    if (!is_dir($stage)) {
        mkdir($stage, 0755, true);
    }
  foreach ($relPaths as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            throw new RuntimeException('ম্যানিফেস্টে অবৈধ পাথ: ' . $rel);
        }
        $src = $root . '/' . $rel;
        if (!is_file($src)) {
            throw new RuntimeException('ফাইল পাওয়া যায়নি: ' . $rel);
        }
        $dest = $stage . '/public_html/' . $rel;
        $parent = dirname($dest);
        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }
        if (!copy($src, $dest)) {
            throw new RuntimeException('কপি ব্যর্থ: ' . $rel);
        }
    }
    $must = ['VERSION.txt', 'APONSHOP_STACK_UPDATE.txt', 'includes/config.php'];
    foreach ($must as $rel) {
        $dest = $stage . '/public_html/' . $rel;
        if (!is_file($dest)) {
            $src = $root . '/' . $rel;
            if (!is_file($src)) {
                throw new RuntimeException('বাধ্যতামূলক ফাইল নেই: ' . $rel);
            }
            $parent = dirname($dest);
            if (!is_dir($parent)) {
                mkdir($parent, 0755, true);
            }
            copy($src, $dest);
        }
    }
    return $stage;
}

function make_update_build_patch_zip(string $root, bool $force): array
{
    $manifest = make_update_read_manifest($root);
    if ($manifest === []) {
        throw new RuntimeException('tools/update_manifest.txt খালি। PATCH-এর জন্য ফাইল লিস্ট দিন।');
    }
    $stage = make_update_stage_dir($root, $manifest);
    $preflight = ame_easy_update_release_preflight($stage . '/public_html');
    if (!$preflight['ok']) {
        make_update_rrmdir($stage);
        throw new RuntimeException('Preflight ব্যর্থ: ' . ($preflight['message'] ?? 'unknown'));
    }
    $ver = trim((string)file_get_contents($root . '/VERSION.txt'));
    $deliver = $root . '/deliverables';
    if (!is_dir($deliver)) {
        mkdir($deliver, 0755, true);
    }
    $dest = $deliver . '/Aponshop_' . $ver . '_PATCH.zip';
    if ($force && is_file($dest)) {
        @unlink($dest);
    }
    $zip = new ZipArchive();
    $tmp = $dest . '.tmp.' . getmypid();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        make_update_rrmdir($stage);
        throw new RuntimeException('PATCH ZIP খোলা যায়নি।');
    }
    $base = $stage . '/public_html';
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walk as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $full = $file->getPathname();
        $rel = 'public_html/' . ltrim(str_replace('\\', '/', substr($full, strlen($base))), '/');
        $zip->addFile($full, $rel);
    }
    $zip->close();
    rename($tmp, $dest);
    make_update_rrmdir($stage);
    return [
        'path' => $dest,
        'filename' => basename($dest),
        'bytes' => filesize($dest),
        'sha256' => hash_file('sha256', $dest),
        'mode' => 'patch',
        'preflight_php' => (int)($preflight['checked_php'] ?? 0),
    ];
}

function make_update_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($dir);
}

try {
    if ($patchMode) {
        $result = make_update_build_patch_zip($root, $force);
    } else {
        $pack = ame_pack_public_html_zip(null, $force);
        $stageRoot = sys_get_temp_dir() . '/aponshop-preflight-' . getmypid();
        make_update_rrmdir($stageRoot);
        mkdir($stageRoot, 0755, true);
        $zip = new ZipArchive();
        if ($zip->open($pack['path']) !== true) {
            throw new RuntimeException('STACK ZIP পড়া যায়নি preflight-এর জন্য।');
        }
        $zip->extractTo($stageRoot);
        $zip->close();
        $preflight = ame_easy_update_release_preflight($stageRoot . '/public_html');
        make_update_rrmdir($stageRoot);
        if (!$preflight['ok']) {
            throw new RuntimeException('Preflight ব্যর্থ: ' . ($preflight['message'] ?? 'unknown'));
        }
        $stack = $root . '/deliverables/Aponshop_POS_STACK_UPDATE.zip';
        if (!is_dir($root . '/deliverables')) {
            mkdir($root . '/deliverables', 0755, true);
        }
        @copy($pack['path'], $stack);
        $result = array_merge($pack, [
            'path' => $stack,
            'filename' => 'Aponshop_POS_STACK_UPDATE.zip',
            'mode' => 'stack',
            'preflight_php' => (int)($preflight['checked_php'] ?? 0),
        ]);
    }
} catch (Throwable $e) {
    if ($isCli) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ZIP তৈরি হয়নি: ' . $e->getMessage();
    exit;
}

if ($isCli) {
    $mb = number_format(((int)$result['bytes']) / 1048576, 2);
    echo ($result['filename'] ?? 'update.zip') . ' — ' . $mb . " MB\n";
    echo 'SHA256: ' . ($result['sha256'] ?? '') . "\n";
    echo 'Preflight PHP: ' . ($result['preflight_php'] ?? 0) . "\n";
    echo 'Path: ' . ($result['path'] ?? '') . "\n";
    exit(0);
}

$dl = '/deliverables/' . rawurlencode((string)($result['filename'] ?? 'Aponshop_POS_STACK_UPDATE.zip'));
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><title>Update ZIP</title></head><body style="font-family:sans-serif;padding:20px">';
echo '<p>✅ ' . htmlspecialchars((string)($result['filename'] ?? ''), ENT_QUOTES, 'UTF-8') . ' তৈরি। Preflight PHP: '
    . (int)($result['preflight_php'] ?? 0) . '</p>';
echo '<p><a href="' . htmlspecialchars($dl, ENT_QUOTES, 'UTF-8') . '">ডাউনলোড</a></p></body></html>';