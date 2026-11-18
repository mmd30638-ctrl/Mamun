<?php
/**
 * Original Drive zip shipped a Next.js demo inside public_html.
 * Only these names under the shop root may be removed.
 */

function ame_public_html_root(): string
{
    $root = realpath(__DIR__ . '/..');
    return $root !== false ? $root : (__DIR__ . '/..');
}

function ame_leftover_demo_names(): array
{
    return [
        'src',
        'node_modules',
        '.next',
        'package.json',
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
        'next.config.ts',
        'next.config.js',
        'next.config.mjs',
        'tsconfig.json',
        'eslint.config.mjs',
        'postcss.config.mjs',
        'next-env.d.ts',
        'components.json',
        'middleware.ts',
        'middleware.js',
        'jsconfig.json',
        'eslint.config.js',
        '.eslintrc.json',
        '.eslintrc.js',
        'public',
    ];
}

function ame_leftover_demo_scan(): array
{
    $root = ame_public_html_root();
    $found = [];
    foreach (ame_leftover_demo_names() as $name) {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path)) {
            $found[] = [
                'name' => $name,
                'path' => $path,
                'dir' => is_dir($path),
            ];
        }
    }
    return $found;
}

function ame_leftover_rm(string $path, string $root): bool
{
    $real = realpath($path);
    $rootReal = realpath($root);
    if ($real === false || $rootReal === false) {
        return false;
    }
    if ($real === $rootReal || strpos($real, $rootReal . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }
    $allowed = ame_leftover_demo_names();
    $rel = ltrim(str_replace('\\', '/', substr($real, strlen($rootReal))), '/');
    $top = explode('/', $rel)[0] ?? '';
    if (!in_array($top, $allowed, true)) {
        return false;
    }
    if (is_dir($real) && !is_link($real)) {
        $ok = true;
        foreach (scandir($real) ?: [] as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }
            if (!ame_leftover_rm($real . DIRECTORY_SEPARATOR . $child, $root)) {
                $ok = false;
            }
        }
        return $ok && @rmdir($real);
    }
    return @unlink($real);
}

/**
 * একটা পুরনো ফিক্স-জিপ ভুলবশত public_html-এর বদলে uploads/-এ এক্সট্র্যাক্ট হয়ে
 * গিয়েছিল — সেই কোডের কপি (কাস্টমার ছবি/ডকুমেন্টের সাথে মিশে) এখনো uploads/-এ
 * পড়ে আছে। uploads/.htaccess script execution/access দুটোই বন্ধ রাখে, তাই এখনই
 * চালানো/খোলা যায় না — কিন্তু জায়গা নষ্ট করছে ও ভুলে এডিট হওয়ার ঝুঁকি রাখছে।
 * শুধু এই নির্দিষ্ট, যাচাই-করা রিলেটিভ পাথগুলোই সরানো হবে — বাকি সব (ছবি,
 * ডকুমেন্ট, কাস্টমার/এমপ্লয়ি ফাইল) অক্ষত থাকবে।
 */
function ame_uploads_legacy_code_rel_paths(): array
{
    return [
        'accounts',
        'actions',
        'includes',
        'inventory',
        'modules',
        'partials',
        'assets/css/brand-type.php',
        'assets/js/pay-mark.php',
    ];
}

function ame_uploads_dir(): string
{
    $root = ame_public_html_root();
    $real = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
    return $real !== false ? $real : ($root . DIRECTORY_SEPARATOR . 'uploads');
}

function ame_dir_size(string $path): int
{
    if (is_file($path)) {
        return (int) @filesize($path);
    }
    $size = 0;
    foreach (scandir($path) ?: [] as $child) {
        if ($child === '.' || $child === '..') {
            continue;
        }
        $size += ame_dir_size($path . DIRECTORY_SEPARATOR . $child);
    }
    return $size;
}

function ame_uploads_legacy_code_scan(): array
{
    $base = ame_uploads_dir();
    $found = [];
    foreach (ame_uploads_legacy_code_rel_paths() as $rel) {
        $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($path)) {
            $found[] = [
                'name' => 'uploads/' . $rel,
                'path' => $path,
                'dir' => is_dir($path),
                'bytes' => ame_dir_size($path),
            ];
        }
    }
    return $found;
}

function ame_uploads_legacy_code_rm(string $path): bool
{
    $real = realpath($path);
    $base = ame_uploads_dir();
    if ($real === false || $base === '') {
        return false;
    }
    if ($real === $base || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }
    $allowed = ame_uploads_legacy_code_rel_paths();
    $rel = ltrim(str_replace('\\', '/', substr($real, strlen($base))), '/');
    if (!in_array($rel, $allowed, true)) {
        return false;
    }
    if (is_dir($real) && !is_link($real)) {
        $ok = true;
        foreach (scandir($real) ?: [] as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }
            if (!ame_uploads_legacy_code_rm_recursive($real . DIRECTORY_SEPARATOR . $child, $base)) {
                $ok = false;
            }
        }
        return $ok && @rmdir($real);
    }
    return @unlink($real);
}

/** allowed-list প্রথম কল-এই চেক হয়ে গেছে — এর ভেতরের যেকোনো ফাইল/ফোল্ডার সরানো নিরাপদ। */
function ame_uploads_legacy_code_rm_recursive(string $path, string $base): bool
{
    $real = realpath($path);
    if ($real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }
    if (is_dir($real) && !is_link($real)) {
        $ok = true;
        foreach (scandir($real) ?: [] as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }
            if (!ame_uploads_legacy_code_rm_recursive($real . DIRECTORY_SEPARATOR . $child, $base)) {
                $ok = false;
            }
        }
        return $ok && @rmdir($real);
    }
    return @unlink($real);
}
