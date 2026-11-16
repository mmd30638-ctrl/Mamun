<?php

function ame_easy_update_protected_paths(): array
{
    return [
        '.env',
        '.env.staging',
        'includes/.env.staging',
        'env.example.txt',
        'includes/db.php',
        'SPEED.txt',
    ];
}

function ame_easy_update_protected_prefixes(): array
{
    return [
        'uploads/',
        'logs/',
    ];
}

function ame_easy_update_is_protected(string $rel, string $targetRoot = ''): bool
{
    $rel = str_replace('\\', '/', ltrim($rel, '/'));
    if (!function_exists('ame_ledger_lock_protects')) {
        $lf = __DIR__ . '/ledger_lock.php';
        if (is_file($lf)) {
            require_once $lf;
        }
    }
    if (in_array($rel, ame_easy_update_protected_paths(), true)) {
        return true;
    }
    foreach (ame_easy_update_protected_prefixes() as $prefix) {
        if ($rel === rtrim($prefix, '/') || str_starts_with($rel, $prefix)) {
            return true;
        }
    }
    if (function_exists('ame_ledger_lock_protects') && ame_ledger_lock_protects($rel, $targetRoot !== '' ? $targetRoot : null)) {
        return true;
    }
    if (!function_exists('ame_engineer_bill_lock_protects')) {
        $engLock = __DIR__ . '/engineer_bill_lock.php';
        if (is_file($engLock)) {
            require_once $engLock;
        }
    }
    if (function_exists('ame_engineer_bill_lock_protects') && ame_engineer_bill_lock_protects($rel)) {
        return true;
    }
    return false;
}

function ame_easy_update_zip_available(): bool
{
    return class_exists('ZipArchive');
}

function ame_easy_update_zip_entries_safe(ZipArchive $zip): bool
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/')
            || preg_match('#^[A-Za-z]:/#', $name)) {
            return false;
        }
        foreach (explode('/', trim($name, '/')) as $part) {
            if ($part === '..') {
                return false;
            }
        }
        $opsys = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
            && (($attributes >> 16) & 0xF000) === 0xA000) {
            return false;
        }
    }
    return true;
}

function ame_easy_update_lock_path(string $targetRoot): string
{
    return dirname(rtrim($targetRoot, '/')) . '/.ame-update.lock';
}

/** @return resource|null */
function ame_easy_update_acquire_lock(string $targetRoot)
{
    $path = ame_easy_update_lock_path($targetRoot);
    $handle = @fopen($path, 'c+');
    if (!$handle || !@flock($handle, LOCK_EX | LOCK_NB)) {
        if (is_resource($handle)) {
            @fclose($handle);
        }
        return null;
    }
    @ftruncate($handle, 0);
    @fwrite($handle, json_encode([
        'started_at' => date('c'),
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
        'pid' => getmypid(),
    ], JSON_UNESCAPED_SLASHES));
    @fflush($handle);
    return $handle;
}

function ame_easy_update_release_lock($handle): void
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function ame_easy_update_php_preflight(string $source): array
{
    $errors = [];
    $checked = 0;
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walk as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen(rtrim($source, '/') . '/')));
        if (preg_match('#(^|/)(vendor|node_modules|laravel|tests|deploy)(/|$)#i', $rel)) {
            continue;
        }
        if (str_contains($rel, 'build_live_repair.php')) {
            continue;
        }
        $checked++;
        $code = @file_get_contents($path);
        if (!is_string($code)) {
            $errors[] = str_replace($source . '/', '', $path) . ': পড়া যায়নি';
            continue;
        }
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            $errors[] = str_replace($source . '/', '', $path) . ': ' . $e->getMessage();
        }
        if (count($errors) >= 10) {
            break;
        }
    }
    return ['ok' => !$errors, 'checked' => $checked, 'errors' => $errors];
}

function ame_easy_update_release_preflight(string $source): array
{
    $php = ame_easy_update_php_preflight($source);
    if (!$php['ok']) {
        return [
            'ok' => false,
            'message' => 'PHP syntax পরীক্ষা ব্যর্থ: ' . implode(' | ', $php['errors']),
            'checked_php' => $php['checked'],
        ];
    }
    $version = ame_easy_update_read_version_from_root($source);
    if ($version === null || !preg_match('/^\d+(?:\.\d+)+$/', $version)) {
        return ['ok' => false, 'message' => 'আপডেট ZIP-এ বৈধ AME_APP_VERSION পাওয়া যায়নি।'];
    }
    if (!is_file($source . '/includes/config.php')) {
        return ['ok' => false, 'message' => 'আপডেট ZIP-এ includes/config.php নেই।'];
    }
    return ['ok' => true, 'version' => $version, 'checked_php' => $php['checked']];
}

function ame_easy_update_delete_manifest_paths(string $source, string $targetRoot = ''): array
{
    $manifest = rtrim($source, '/') . '/PATCH_DELETE.txt';
    if (!is_file($manifest)) {
        return [];
    }
    $paths = [];
    foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $rel = str_replace('\\', '/', trim((string)$line));
        if ($rel === '' || str_starts_with($rel, '#') || str_starts_with($rel, '/')) {
            continue;
        }
        $parts = array_values(array_filter(explode('/', $rel), static fn($part) => $part !== ''));
        if (!$parts || in_array('..', $parts, true)) {
            continue;
        }
        $rel = implode('/', $parts);
        if (!ame_easy_update_is_protected($rel, $targetRoot)) {
            $paths[$rel] = true;
        }
    }
    return array_keys($paths);
}

/**
 * @return array{ok:bool,message:string,stats?:array}
 */
function ame_easy_update_apply_zip(string $zipPath, string $targetRoot, ?mysqli $db = null): array
{
    if (!ame_easy_update_zip_available()) {
        return ['ok' => false, 'message' => 'সার্ভারে ZipArchive নেই। Hostinger সাপোর্টকে জিজ্ঞেস করুন।'];
    }
    if (!is_file($zipPath) || !is_readable($zipPath)) {
        return ['ok' => false, 'message' => 'ZIP ফাইল পড়া যায়নি।'];
    }

    $fh = @fopen($zipPath, 'rb');
    $head = $fh ? (string)fread($fh, 16) : '';
    if ($fh) {
        fclose($fh);
    }
    if ($head !== '' && !str_starts_with($head, 'PK')) {
        return ['ok' => false, 'message' => 'এটা আসল ZIP না — ফাইলবিনের ওয়েব পেজ নামানো হয়েছে। সরাসরি ফাইল লিংক নয়, আর্কাইভ লিংক (…/archive/…/zip) থেকে নামান, অথবা /one_click.php?go=update ব্যবহার করুন।'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'message' => 'ZIP ফাইল খুলতে পারিনি। ফাইলটি সঠিক ZIP কিনা যাচাই করুন।'];
    }

    if (!ame_easy_update_zip_entries_safe($zip)) {
        $zip->close();
        return ['ok' => false, 'message' => 'ZIP-এ অবৈধ পাথ বা symlink আছে।'];
    }

    $temp = rtrim(sys_get_temp_dir(), '/') . '/ame_upd_' . bin2hex(random_bytes(6));
    if (!@mkdir($temp, 0755, true) && !is_dir($temp)) {
        $zip->close();
        return ['ok' => false, 'message' => 'টেম্প ফোল্ডার তৈরি হয়নি।'];
    }

    if (!$zip->extractTo($temp)) {
        $zip->close();
        ame_easy_update_rrmdir($temp);
        return ['ok' => false, 'message' => 'ZIP এক্সট্র্যাক্ট ব্যর্থ।'];
    }
    $zip->close();

    $source = ame_easy_update_detect_source_root($temp);
    if ($source === null) {
        $zips = array_merge(
            glob($temp . '/*.zip') ?: [],
            glob($temp . '/*/*.zip') ?: []
        );
        foreach ($zips as $innerZip) {
            $inner = new ZipArchive();
            if ($inner->open($innerZip) !== true) {
                continue;
            }
            if (!ame_easy_update_zip_entries_safe($inner)) {
                $inner->close();
                continue;
            }
            $sub = $temp . '/_inner_' . substr(sha1((string)$innerZip), 0, 8);
            if (!@mkdir($sub, 0755, true) && !is_dir($sub)) {
                $inner->close();
                continue;
            }
            if (!$inner->extractTo($sub)) {
                $inner->close();
                continue;
            }
            $inner->close();
            $source = ame_easy_update_detect_source_root($sub);
            if ($source !== null) {
                break;
            }
            $nestedZips = glob($sub . '/*.zip') ?: [];
            foreach ($nestedZips as $deepZip) {
                $deep = new ZipArchive();
                if ($deep->open($deepZip) !== true) {
                    continue;
                }
                if (!ame_easy_update_zip_entries_safe($deep)) {
                    $deep->close();
                    continue;
                }
                $deepDir = $sub . '/_deep';
                if (!@mkdir($deepDir, 0755, true) && !is_dir($deepDir)) {
                    $deep->close();
                    continue;
                }
                if (!$deep->extractTo($deepDir)) {
                    $deep->close();
                    continue;
                }
                $deep->close();
                $source = ame_easy_update_detect_source_root($deepDir);
                if ($source !== null) {
                    break 2;
                }
            }
        }
    }
    if ($source === null) {
        ame_easy_update_rrmdir($temp);
        return ['ok' => false, 'message' => 'ZIP-এ public_html সিস্টেম ফাইল পাওয়া যায়নি। Aponshop_POS_STACK_UPDATE.zip দিন — ভিতরে আরেকটা zip নয়।'];
    }

    $targetRoot = rtrim($targetRoot, '/');
    $preflight = ame_easy_update_release_preflight($source);
    if (!$preflight['ok']) {
        ame_easy_update_rrmdir($temp);
        return ['ok' => false, 'message' => 'Preflight ব্যর্থ—লাইভ ফাইল বদলানো হয়নি। ' . $preflight['message']];
    }

    $updateLock = ame_easy_update_acquire_lock($targetRoot);
    if (!$updateLock) {
        ame_easy_update_rrmdir($temp);
        return ['ok' => false, 'message' => 'আরেকটি আপডেট চলছে। সেটি শেষ হলে আবার চেষ্টা করুন।'];
    }

    // প্রয়োগের ঠিক আগে স্বয়ংক্রিয় সেফটি ব্যাকআপ — যেসব ফাইল বদলাবে তাদের আগের
    // কপি + সম্পূর্ণ ডাটাবেজ ডাম্প, webroot-এর বাইরে। ব্যাকআপ ব্যর্থ হলে আপডেট
    // প্রয়োগ করা হয় না, যাতে "ফেরার পথ ছাড়া" কোনো আপডেট কখনও না হয়।
    $backupId = null;
    if (function_exists('ame_update_backup_snapshot')) {
        $fromVersion = function_exists('ame_easy_update_read_version_from_root')
            ? (string)(ame_easy_update_read_version_from_root($targetRoot) ?? '')
            : '';
        $backup = ame_update_backup_snapshot($db, $source, $targetRoot, $fromVersion);
        if (!$backup['ok']) {
            ame_easy_update_release_lock($updateLock);
            ame_easy_update_rrmdir($temp);
            return ['ok' => false, 'message' => 'সেফটি ব্যাকআপ ব্যর্থ, তাই আপডেট প্রয়োগ করা হয়নি (কোনো ফাইল বদলানো হয়নি): ' . $backup['message']];
        }
        $backupId = $backup['id'] ?? null;
    }

    $stats = [
        'updated' => 0,
        'skipped' => 0,
        'created' => 0,
        'deleted' => 0,
        'files' => [],
    ];
    $patched = false;
    $expectedVersion = ame_easy_update_read_version_from_root($source);

    try {
        ame_easy_update_copy_tree($source, $targetRoot, '', $stats);
        ame_easy_update_apply_deletions($source, $targetRoot, $stats);
        $patched = ame_easy_update_patch_asset_version($targetRoot, $expectedVersion);
        $installedVersion = ame_easy_update_read_version_from_root($targetRoot);
        if ($expectedVersion !== null && $installedVersion !== $expectedVersion) {
            throw new RuntimeException(
                'ভার্সন যাচাই ব্যর্থ: প্রত্যাশিত ' . $expectedVersion
                . ', পাওয়া গেছে ' . (string)($installedVersion ?? 'unknown')
            );
        }
        if (!is_file($targetRoot . '/includes/config.php')) {
            throw new RuntimeException('Post-update health check-এ includes/config.php পাওয়া যায়নি।');
        }
    } catch (Throwable $e) {
        $rollbackMessage = '';
        if ($backupId && function_exists('ame_update_backup_restore')) {
            $rolled = ame_update_backup_restore($db, (string)$backupId, $targetRoot, false);
            $rollbackMessage = !empty($rolled['ok'])
                ? ' আংশিক ফাইল স্বয়ংক্রিয়ভাবে আগের অবস্থায় ফেরানো হয়েছে।'
                : ' স্বয়ংক্রিয় rollback ব্যর্থ; ব্যাকআপ আইডি ' . $backupId . ' ব্যবহার করুন।';
        }
        ame_easy_update_release_lock($updateLock);
        ame_easy_update_rrmdir($temp);
        error_log('easy_update apply failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'আপডেট ব্যর্থ: ' . $e->getMessage() . $rollbackMessage];
    }

    ame_easy_update_rrmdir($temp);

    // স্পিড ও নির্ভরযোগ্যতার জন্য PHP OPcache সার্ভারে চালু থাকতে পারে —
    // সেক্ষেত্রে ফাইল ওভাররাইট হলেও পুরনো কম্পাইল-করা কোড কিছু সময় (বা পরবর্তী
    // রিকোয়েস্ট পর্যন্ত) সার্ভ হতে থাকতে পারে, যার ফলে নতুন ফাংশন/পরিবর্তন
    // "undefined function" এর মতো এরর দিতে পারে যতক্ষণ না ক্যাশ নিজে থেকে
    // রিফ্রেশ হয়। আপডেটের পরপরই ক্যাশ রিসেট করলে নতুন কোড সাথে সাথেই কার্যকর
    // হয় — কোনো ফাইল/ডেটা মোছে না, শুধু কম্পাইল-করা কোডের ক্যাশ সাফ হয়।
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    ame_easy_update_release_lock($updateLock);

    if ($stats['updated'] + $stats['created'] + $stats['deleted'] === 0) {
        return ['ok' => false, 'message' => 'কোনো ফাইল আপডেট হয়নি। ZIP সঠিক কিনা দেখুন।', 'stats' => $stats];
    }

    $message = $stats['updated'] + $stats['created'] . 'টি ফাইল আপডেট হয়েছে।';
    if ($stats['deleted'] > 0) {
        $message .= ' manifest অনুযায়ী ' . $stats['deleted'] . 'টি অকেজো ফাইল সরানো হয়েছে।';
    }
    $message .= ' ' . $stats['skipped'] . 'টি সুরক্ষিত ফাইল এড়িয়ে গেছে (.env, uploads, logs, খাতা/ইঞ্জিনিয়ার লক)। Preflight-এ ' . (int)$preflight['checked_php'] . 'টি PHP ফাইল ঠিক পাওয়া গেছে।';
    if (!empty($stats['protected_blocked'])) {
        $blocked = (array)$stats['protected_blocked'];
        if (function_exists('ame_engineer_bill_lock_notify_zip_blocked')) {
            ame_engineer_bill_lock_notify_zip_blocked($blocked, $targetRoot);
        }
        $message .= ' ইঞ্জিনিয়ার/সার্ভিস লক: ' . count($blocked)
            . 'টি কোর ফাইল অননুমোদিত বদল থেকে ব্লক — অ্যাডমিন নোটিফিকেশন দেখুন।';
    }
    if ($patched) {
        $message .= ' CSS/JS ক্যাশ ভার্সন আপডেট হয়েছে।';
    }
    $message .= ' পরের authenticated request-এ additive schema sync যাচাই হবে।';
    if ($backupId) {
        $message .= ' (সেফটি ব্যাকআপ নেওয়া হয়েছে — সমস্যা হলে «সহজ আপডেট» পেজ থেকে ফিরে যেতে পারবেন।)';
    }

    return [
        'ok' => true,
        'backup_id' => $backupId,
        'message' => $message,
        'stats' => $stats,
    ];
}

function ame_easy_update_looks_like_app_root(string $dir): bool
{
    if (is_file($dir . '/includes/config.php')) {
        return true;
    }
    if (is_file($dir . '/APONSHOP_STACK_UPDATE.txt')) {
        return true;
    }
    if (is_file($dir . '/VERSION.txt') && (is_dir($dir . '/modules') || is_dir($dir . '/assets') || is_dir($dir . '/includes'))) {
        return true;
    }
    return is_file($dir . '/assets/js/pos-draft.js')
        && is_file($dir . '/modules/sales/draft_query.php');
}

function ame_easy_update_detect_source_root(string $temp): ?string
{
    $nested = [];
    $flat = [];

    if (is_dir($temp . '/public_html') && ame_easy_update_looks_like_app_root($temp . '/public_html')) {
        $nested[] = $temp . '/public_html';
    }
    if (ame_easy_update_looks_like_app_root($temp)) {
        $flat[] = $temp;
    }

    foreach (glob($temp . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (basename($dir) === '_inner') {
            continue;
        }
        if (is_dir($dir . '/public_html') && ame_easy_update_looks_like_app_root($dir . '/public_html')) {
            $nested[] = $dir . '/public_html';
        }
        if (ame_easy_update_looks_like_app_root($dir)) {
            $flat[] = $dir;
        }
    }

    /* public_html/ থাকলে সেটাই — ফ্ল্যাট রুটে মেশালে ডবল ফোল্ডার হয়। */
    if ($nested) {
        return $nested[0];
    }
    return $flat[0] ?? null;
}

function ame_easy_update_read_stack_asset_version(string $root): ?string
{
    $found = [];
    foreach (['APONSHOP_STACK_UPDATE.txt', 'APONSHOP_STOCK_ALERT.txt'] as $name) {
        $marker = $root . '/' . $name;
        if (!is_file($marker)) {
            continue;
        }
        $text = (string)@file_get_contents($marker);
        if (preg_match('/AME_ASSET_VERSION\s*=\s*([0-9]+(?:\.[0-9]+)*)/', $text, $m)) {
            $found[] = $m[1];
        }
    }
    if (!$found) {
        return null;
    }
    usort($found, 'version_compare');
    return (string)end($found);
}

function ame_easy_update_patch_asset_version(string $root, ?string $version = null): bool
{
    $fromTxt = is_file(rtrim($root, '/') . '/VERSION.txt') ? trim((string) @file_get_contents(rtrim($root, '/') . '/VERSION.txt')) : '';
    $fromCfg = function_exists('ame_easy_update_read_version_from_root')
        ? (string) (ame_easy_update_read_version_from_root($root) ?? '')
        : '';
    $fromMarker = (string) (ame_easy_update_read_stack_asset_version($root) ?? '');
    $pick = $version ?: ($fromTxt !== '' ? $fromTxt : ($fromCfg !== '' ? $fromCfg : $fromMarker));
    if ($fromCfg !== '' && $pick !== '' && version_compare($pick, $fromCfg, '<')) {
        $pick = $fromCfg;
    }
    $version = $pick ?: '5.71';
    if (!preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $version)) {
        return false;
    }
    $configPath = rtrim($root, '/') . '/includes/config.php';
    $src = @file_get_contents($configPath);
    if ($src === false) {
        return false;
    }
    $patched = preg_replace(
        "/define\\s*\\(\\s*'AME_ASSET_VERSION'\\s*,\\s*'[^']*'\\s*\\)/",
        "define('AME_ASSET_VERSION', '" . $version . "')",
        $src,
        1,
        $count
    );
    if (!is_string($patched) || $count !== 1) {
        return false;
    }
    $appPatched = preg_replace(
        "/define\\s*\\(\\s*'AME_APP_VERSION'\\s*,\\s*'[^']*'\\s*\\)/",
        "define('AME_APP_VERSION', '" . $version . "')",
        $patched,
        1,
        $appCount
    );
    if (is_string($appPatched) && $appCount === 1) {
        $patched = $appPatched;
    }
    $ok = @file_put_contents($configPath, $patched) !== false;
    $verFile = rtrim($root, '/') . '/VERSION.txt';
    @file_put_contents($verFile, $version . "\n");
    $stackFile = $root . '/APONSHOP_STACK_UPDATE.txt';
    if (is_file($stackFile)) {
        $stackText = (string) @file_get_contents($stackFile);
        $stackText = preg_replace('/^AME_ASSET_VERSION=.*$/m', 'AME_ASSET_VERSION=' . $version, $stackText, 1) ?? $stackText;
        $stackText = preg_replace('/^AME_APP_VERSION=.*$/m', 'AME_APP_VERSION=' . $version, $stackText, 1) ?? $stackText;
        @file_put_contents($stackFile, $stackText);
    }
    return $ok;
}

function ame_easy_update_copy_tree(string $source, string $targetRoot, string $rel, array &$stats): void
{
    $sourcePath = rtrim($source, '/') . ($rel !== '' ? '/' . $rel : '');
    $items = scandir($sourcePath);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $childRel = $rel === '' ? $item : $rel . '/' . $item;
        $childRel = str_replace('\\', '/', $childRel);
        $src = $sourcePath . '/' . $item;
        $dest = $targetRoot . '/' . $childRel;

        if (ame_easy_update_is_protected($childRel, $targetRoot)) {
            if (is_file($src) && is_file($dest)) {
                $srcHash = @hash_file('sha256', $src);
                $destHash = @hash_file('sha256', $dest);
                if (is_string($srcHash) && is_string($destHash) && !hash_equals($destHash, $srcHash)) {
                    if (!isset($stats['protected_blocked'])) {
                        $stats['protected_blocked'] = [];
                    }
                    $stats['protected_blocked'][] = $childRel;
                    if (function_exists('ame_engineer_bill_lock_notify_zip_blocked')) {
                        ame_engineer_bill_lock_notify_zip_blocked([$childRel], $targetRoot);
                    }
                }
            }
            $stats['skipped']++;
            continue;
        }

        if (is_dir($src)) {
            if (!is_dir($dest)) {
                @mkdir($dest, 0755, true);
            }
            ame_easy_update_copy_tree($source, $targetRoot, $childRel, $stats);
            continue;
        }

        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $existed = is_file($dest);
        $tmpDest = $dest . '.ame-new-' . bin2hex(random_bytes(4));
        if (!@copy($src, $tmpDest)) {
            @unlink($tmpDest);
            throw new RuntimeException('ফাইল staging ব্যর্থ: ' . $childRel);
        }
        $sourceSize = @filesize($src);
        $stagedSize = @filesize($tmpDest);
        if ($sourceSize === false || $stagedSize === false || $sourceSize !== $stagedSize) {
            @unlink($tmpDest);
            throw new RuntimeException('ফাইল অসম্পূর্ণ: ' . $childRel);
        }
        @chmod($tmpDest, 0644);
        if (!@rename($tmpDest, $dest)) {
            @unlink($tmpDest);
            throw new RuntimeException('ফাইল atomically বসানো যায়নি: ' . $childRel);
        }
        if (function_exists('opcache_invalidate') && strtolower(pathinfo($dest, PATHINFO_EXTENSION)) === 'php') {
            @opcache_invalidate($dest, true);
        }
        if ($existed) {
            $stats['updated']++;
        } else {
            $stats['created']++;
        }
        if (count($stats['files']) < 30) {
            $stats['files'][] = $childRel;
        }
    }
}

function ame_easy_update_apply_deletions(string $source, string $targetRoot, array &$stats): void
{
    foreach (ame_easy_update_delete_manifest_paths($source, $targetRoot) as $rel) {
        $path = rtrim($targetRoot, '/') . '/' . $rel;
        if (is_dir($path)) {
            throw new RuntimeException('PATCH_DELETE directory মুছতে পারবে না: ' . $rel);
        }
        if (!is_file($path)) {
            continue;
        }
        if (!@unlink($path)) {
            throw new RuntimeException('অকেজো ফাইল সরানো যায়নি: ' . $rel);
        }
        $stats['deleted']++;
        if (count($stats['files']) < 30) {
            $stats['files'][] = '-' . $rel;
        }
    }
}

function ame_easy_update_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            ame_easy_update_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function ame_easy_update_boot_limits(): void
{
    @ini_set('upload_max_filesize', '64M');
    @ini_set('post_max_size', '70M');
    @ini_set('max_execution_time', '300');
    @ini_set('max_input_time', '300');
    @set_time_limit(300);
    ignore_user_abort(true);
}

function ame_easy_update_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/system_updates';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function ame_easy_update_looks_like_zip_file(string $path): bool
{
    $fh = @fopen($path, 'rb');
    $head = $fh ? (string)fread($fh, 4) : '';
    if ($fh) {
        fclose($fh);
    }
    return str_starts_with($head, 'PK');
}

function ame_easy_update_allowed_fetch_host(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    if ($host === 'filebin.net' || $host === 'www.filebin.net' || $host === 'storage.filebin.net') {
        return true;
    }
    if (str_ends_with($host, '.filebin.net')) {
        return true;
    }
    $self = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    return $self !== '' && $host === $self;
}

function ame_easy_update_hint_path(): string
{
    return dirname(__DIR__) . '/data/mobile_zip_url.txt';
}

function ame_easy_update_read_hint_url(): string
{
    $path = ame_easy_update_hint_path();
    if (!is_file($path)) {
        return '';
    }
    $try = trim((string)file_get_contents($path));
    return preg_match('#^https://[A-Za-z0-9._/?&=%-]+$#', $try) ? $try : '';
}

function ame_easy_update_write_hint_url(string $url): void
{
    $url = trim($url);
    if (!preg_match('#^https://[A-Za-z0-9._/?&=%-]+$#', $url)) {
        return;
    }
    $path = ame_easy_update_hint_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, $url . "\n");
}

function ame_easy_update_agent(): string
{
    $ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.98.320';
    return 'AponshopEasyUpdate/' . $ver;
}

/**
 * @return array{ok:bool,path?:string,message:string,bytes?:int}
 */
function ame_easy_update_fetch_remote_zip(string $url): array
{
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
        return ['ok' => false, 'message' => 'লিংক সঠিক HTTPS নয়।'];
    }
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!ame_easy_update_allowed_fetch_host($host)) {
        return ['ok' => false, 'message' => 'এই হোস্ট থেকে নামানো যায় না। ফাইলবিন আর্কাইভ লিংক ব্যবহার করুন।'];
    }

    $dest = ame_easy_update_upload_dir() . '/fetch_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
    $cookie = str_contains($host, 'filebin.net') ? 'verified=2024-05-24' : '';

    if (function_exists('curl_init')) {
        $fh = fopen($dest, 'wb');
        if ($fh === false) {
            return ['ok' => false, 'message' => 'ZIP সেভ করার জায়গা খোলা যায়নি।'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 25,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_PROTOCOLS => defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_REDIR_PROTOCOLS => defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => ame_easy_update_agent(),
            CURLOPT_HTTPHEADER => ['Accept: application/zip, application/octet-stream, */*'],
        ]);
        if ($cookie !== '') {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string)curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($ok === false || $code >= 400) {
            @unlink($dest);
            return ['ok' => false, 'message' => 'সার্ভার ZIP নামাতে পারেনি' . ($err !== '' ? (': ' . $err) : (' (HTTP ' . $code . ')')) . '।'];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 180,
                'follow_location' => 1,
                'header' => "Cookie: {$cookie}\r\nAccept: application/zip\r\n",
                'user_agent' => ame_easy_update_agent(),
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            return ['ok' => false, 'message' => 'সার্ভার ZIP নামাতে পারেনি।'];
        }
        if (@file_put_contents($dest, $data) === false) {
            return ['ok' => false, 'message' => 'ZIP সেভ করা যায়নি।'];
        }
    }

    if (!is_file($dest) || filesize($dest) < 100) {
        @unlink($dest);
        return ['ok' => false, 'message' => 'নামানো ফাইল খালি।'];
    }
    if (!ame_easy_update_looks_like_zip_file($dest)) {
        @unlink($dest);
        return ['ok' => false, 'message' => 'এটা আসল ZIP না — ফাইলবিনের ওয়েব পেজ নামানো হয়েছে। আর্কাইভ লিংক (…/archive/…/zip) দিন।'];
    }

    return [
        'ok' => true,
        'path' => $dest,
        'bytes' => (int)filesize($dest),
        'message' => 'ZIP নামানো হয়েছে।',
    ];
}

function ame_easy_update_read_version_from_root(string $root): ?string
{
    $root = rtrim($root, '/');
    $verFile = $root . '/VERSION.txt';
    if (is_file($verFile)) {
        $fromTxt = trim((string) @file_get_contents($verFile));
        if ($fromTxt !== '' && preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $fromTxt)) {
            return $fromTxt;
        }
    }
    $marker = ame_easy_update_read_stack_asset_version($root);
    if ($marker !== null && $marker !== '') {
        return $marker;
    }
    $config = $root . '/includes/config.php';
    if (!is_file($config)) {
        return null;
    }
    $content = @file_get_contents($config);
    if ($content === false) {
        return null;
    }
    if (preg_match("/define\s*\(\s*'AME_ASSET_VERSION'\s*,\s*'([^']+)'\s*\)/", $content, $m)) {
        return $m[1];
    }
    if (preg_match("/define\s*\(\s*'AME_APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $content, $m)) {
        return $m[1];
    }
    return null;
}
