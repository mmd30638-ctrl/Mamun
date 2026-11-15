<?php
/**
 * Hostinger-style public_html ZIP — .env / logs / dumps বাদ।
 */

function ame_releases_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/releases';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function ame_zip_basename(): string
{
    $ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.98.18';
    return 'Aponshop_' . $ver . '_FULL_STACK.zip';
}

function ame_zip_live_basename(): string
{
    $ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.98.18';
    return 'Aponshop_' . $ver . '_LIVE.zip';
}

function ame_zip_update_basename(): string
{
    return 'Aponshop_POS_STACK_UPDATE.zip';
}

function ame_zip_stub_keep(string $base): bool
{
    return in_array($base, ['.htaccess', '.gitkeep', '.user.ini', 'index.php'], true);
}

function ame_zip_should_skip(string $rel): bool
{
    $rel = str_replace('\\', '/', $rel);
    $base = basename($rel);
    if ($base === '.env' || str_starts_with($base, '.env.')) {
        return true;
    }
    if (str_ends_with(strtolower($base), '.log')) {
        return true;
    }
    if (str_ends_with(strtolower($base), '.zip')) {
        return true;
    }
    if ($base === '.DS_Store' || $base === 'Thumbs.db') {
        return true;
    }
    if (in_array($base, ['ekclick.php', 'bump_asset_version.php', 'cleanup_unused_files.php', '_shot_boot.php'], true)) {
        return true;
    }
    if (preg_match('#(^|/)\.git(/|$)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)node_modules(/|$)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)deliverables(/|$)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)assets/img/(banner_|slide_|shop_logo_|user_photo_)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)assets/img/customers(/|$)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)logs(/|$)#', $rel)) {
        return !ame_zip_stub_keep($base);
    }
    if (preg_match('#(^|/)uploads(/|$)#', $rel)) {
        return !ame_zip_stub_keep($base);
    }
    if (preg_match('#(^|/)downloads(/|$)#', $rel)) {
        return !ame_zip_stub_keep($base);
    }
    if (preg_match('#(^|/)releases(/|$)#', $rel)) {
        return !ame_zip_stub_keep($base);
    }
    if ($rel === 'data/mobile_zip_url.txt') {
        return true;
    }
    if (preg_match('#(^|/)(laravel|vendor|node_modules|tests|deploy|\.cursor|agent-tools|private_data)(/|$)#', $rel)) {
        return true;
    }
    if (preg_match('#(^|/)storage/db_backups(/|$)#', $rel)) {
        return true;
    }
    if ($base === 'MODULE_UNLOCK.txt' || $base === 'build_live_repair.php') {
        return true;
    }
    return false;
}

function ame_pack_public_html_zip(?string $dest = null, bool $force = false): array
{
    $webroot = dirname(__DIR__);
    $releases = ame_releases_dir();
    $filename = ame_zip_basename();
    $dest = $dest ?: ($releases . '/' . $filename);

    if (!$force && is_file($dest) && filesize($dest) > 1000) {
        $alias = $releases . '/APONSHOP-public_html-fix.zip';
        if (!is_file($alias) || filesize($alias) !== filesize($dest)) {
            @copy($dest, $alias);
        }
        @copy($dest, $releases . '/' . ame_zip_update_basename());
        ame_public_zip_copy($dest, $filename);
        return [
            'path' => $dest,
            'filename' => $filename,
            'bytes' => filesize($dest),
            'sha256' => hash_file('sha256', $dest),
            'reused' => true,
        ];
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive এক্সটেনশন নেই।');
    }

    $tmp = $dest . '.tmp.' . getmypid();
    $zip = new ZipArchive();
    $opened = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        throw new RuntimeException('ZIP খোলা যায়নি (কোড ' . $opened . ')।');
    }

    $webrootReal = realpath($webroot);
    if ($webrootReal === false) {
        $zip->close();
        throw new RuntimeException('public_html পাওয়া যায়নি।');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webrootReal, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileinfo) {
        /** @var SplFileInfo $fileinfo */
        if (!$fileinfo->isFile()) {
            continue;
        }
        $full = $fileinfo->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($full, strlen($webrootReal))), '/');
        if ($rel === '' || ame_zip_should_skip($rel)) {
            continue;
        }
        /* 10.98.47 সহজ আপডেট public_html/ সিস্টেম ফাইল খোঁজে — ফ্ল্যাট ZIP ফেলে দেয়। */
        $zip->addFile($full, 'public_html/' . $rel);
    }
    $zip->close();

    if (!is_file($tmp) || filesize($tmp) < 100) {
        @unlink($tmp);
        throw new RuntimeException('ZIP খালি বা তৈরি হয়নি।');
    }

    if (is_file($dest)) {
        @unlink($dest);
    }
    if (!@rename($tmp, $dest)) {
        @copy($tmp, $dest);
        @unlink($tmp);
    }

    $alias = $releases . '/APONSHOP-public_html-fix.zip';
    @copy($dest, $alias);
    @copy($dest, $releases . '/' . ame_zip_update_basename());
    ame_public_zip_copy($dest, $filename);

    return [
        'path' => $dest,
        'filename' => $filename,
        'bytes' => filesize($dest),
        'sha256' => hash_file('sha256', $dest),
        'reused' => false,
    ];
}

function ame_pack_live_zip(?string $dest = null, bool $force = false): array
{
    $webroot = dirname(__DIR__);
    $releases = ame_releases_dir();
    $filename = ame_zip_live_basename();
    $dest = $dest ?: ($releases . '/' . $filename);

    if (!$force && is_file($dest) && filesize($dest) > 1000) {
        ame_public_zip_copy($dest, $filename);
        return [
            'path' => $dest,
            'filename' => $filename,
            'bytes' => filesize($dest),
            'sha256' => hash_file('sha256', $dest),
            'reused' => true,
        ];
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive এক্সটেনশন নেই।');
    }

    $tmp = $dest . '.tmp.' . getmypid();
    $zip = new ZipArchive();
    $opened = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        throw new RuntimeException('ZIP খোলা যায়নি (কোড ' . $opened . ')।');
    }

    $webrootReal = realpath($webroot);
    if ($webrootReal === false) {
        $zip->close();
        throw new RuntimeException('public_html পাওয়া যায়নি।');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webrootReal, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileinfo) {
        /** @var SplFileInfo $fileinfo */
        if (!$fileinfo->isFile()) {
            continue;
        }
        $full = $fileinfo->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($full, strlen($webrootReal))), '/');
        if ($rel === '' || ame_zip_should_skip($rel)) {
            continue;
        }
        $zip->addFile($full, $rel);
    }
    $zip->close();

    if (!is_file($tmp) || filesize($tmp) < 100) {
        @unlink($tmp);
        throw new RuntimeException('LIVE ZIP খালি বা তৈরি হয়নি।');
    }

    if (is_file($dest)) {
        @unlink($dest);
    }
    if (!@rename($tmp, $dest)) {
        @copy($tmp, $dest);
        @unlink($tmp);
    }

    ame_public_zip_copy($dest, $filename);

    return [
        'path' => $dest,
        'filename' => $filename,
        'bytes' => filesize($dest),
        'sha256' => hash_file('sha256', $dest),
        'reused' => false,
    ];
}

function ame_public_zip_copy(string $src, string $filename): void
{
    $dir = dirname(__DIR__) . '/downloads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_file($src)) {
        return;
    }
    @copy($src, $dir . '/' . $filename);
    if (str_ends_with($filename, '_FULL_STACK.zip') || $filename === ame_zip_update_basename()) {
        @copy($src, $dir . '/Aponshop.zip');
        @copy($src, $dir . '/' . ame_zip_update_basename());
    }
}

function ame_stream_zip_file(string $path, string $downloadName): void
{
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'ZIP পাওয়া যায়নি।';
        exit;
    }
    $size = filesize($path);
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'Aponshop.zip';
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($safe));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($path);
    exit;
}
