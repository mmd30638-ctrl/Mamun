<?php
/**
 * শেষ হওয়া মডিউলের ফাইল বদলালে এই স্ক্রিপ্ট আটকায়।
 *
 * লকের উৎস: MODULE_LOCK.txt
 * এক কাজের অনুমতি: MODULE_UNLOCK.txt (কমিট হয় না)
 *
 *   php tools/module_lock_check.php
 *   php tools/module_lock_check.php --paths modules/customers/ledger.php
 */
declare(strict_types=1);

function ame_module_lock_normalize(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $dir = str_ends_with($path, '/');
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }
    $out = implode('/', $parts);
    if ($dir && $out !== '') {
        $out .= '/';
    }
    return $out;
}

/** @return array{dirs: list<string>, files: list<string>} */
function ame_module_lock_parse(string $text): array
{
    $dirs = [];
    $files = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        if (!preg_match('#^- ([A-Za-z0-9_./-]+)$#', trim($line), $m)) {
            continue;
        }
        $path = ame_module_lock_normalize($m[1]);
        if ($path === '') {
            continue;
        }
        if (str_ends_with($path, '/')) {
            $dirs[$path] = true;
        } else {
            $files[$path] = true;
        }
    }
    return ['dirs' => array_keys($dirs), 'files' => array_keys($files)];
}

/** @return list<string> */
function ame_module_lock_parse_unlock(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $line = (string)preg_replace('/^- /', '', $line);
        $path = ame_module_lock_normalize($line);
        if ($path !== '') {
            $out[] = $path;
        }
    }
    return $out;
}

/**
 * অনুমতি একটা মডিউল ফোল্ডার বা একটা ফাইলের চেয়ে চওড়া হলে বাতিল।
 *
 * @param array{dirs: list<string>, files: list<string>} $locks
 */
function ame_module_lock_unlock_is_specific(string $unlock, array $locks): bool
{
    $unlock = ame_module_lock_normalize($unlock);
    if ($unlock === '') {
        return false;
    }
    if (in_array($unlock, $locks['files'], true) || in_array($unlock, $locks['dirs'], true)) {
        return true;
    }
    $inside = 0;
    $covers = 0;
    foreach ($locks['dirs'] as $dir) {
        if (str_starts_with($unlock, $dir)) {
            $inside++;
        } elseif (str_starts_with($dir, $unlock)) {
            $covers++;
        }
    }
    if ($covers > 0) {
        return false;
    }
    if ($inside === 1) {
        return true;
    }
    foreach ($locks['files'] as $file) {
        if ($file === $unlock) {
            return true;
        }
    }
    return false;
}

/**
 * @param array{dirs: list<string>, files: list<string>} $locks
 */
function ame_module_lock_is_protected(string $path, array $locks): bool
{
    $path = ame_module_lock_normalize($path);
    if ($path === '' || str_ends_with($path, '/')) {
        return false;
    }
    if (in_array($path, $locks['files'], true)) {
        return true;
    }
    foreach ($locks['dirs'] as $dir) {
        if (str_starts_with($path, $dir)) {
            return true;
        }
    }
    return false;
}

/**
 * @param list<string> $paths
 * @param array{dirs: list<string>, files: list<string>} $locks
 * @param list<string> $unlocks
 * @return list<string>
 */
function ame_module_lock_violations(array $paths, array $locks, array $unlocks): array
{
    $bad = [];
    foreach ($paths as $path) {
        $norm = ame_module_lock_normalize((string)$path);
        if ($norm === 'MODULE_UNLOCK.txt') {
            $bad[] = $norm;
            continue;
        }
        if (!ame_module_lock_is_protected($norm, $locks)) {
            continue;
        }
        $open = false;
        foreach ($unlocks as $unlock) {
            $unlock = ame_module_lock_normalize($unlock);
            if ($unlock === $norm) {
                $open = true;
                break;
            }
            if (str_ends_with($unlock, '/') && str_starts_with($norm, $unlock)) {
                $open = true;
                break;
            }
        }
        if (!$open) {
            $bad[] = $norm;
        }
    }
    return array_values(array_unique($bad));
}

/** @return list<string> */
function ame_module_lock_git_paths(string $root): array
{
    $lists = [];
    $commands = [
        'git diff --name-only main',
        'git diff --name-only --cached',
        'git ls-files --others --exclude-standard',
    ];
    foreach ($commands as $command) {
        $lines = [];
        $code = 0;
        exec('cd ' . escapeshellarg($root) . ' && ' . $command, $lines, $code);
        if ($code !== 0) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lists[] = $line;
            }
        }
    }
    return array_values(array_unique($lists));
}

function ame_module_lock_root(): string
{
    return dirname(__DIR__);
}

/**
 * @param list<string> $argv
 */
function ame_module_lock_cli(array $argv): int
{
    $root = ame_module_lock_root();
    $lockFile = $root . '/MODULE_LOCK.txt';
    $text = is_file($lockFile) ? (string)file_get_contents($lockFile) : '';
    $locks = ame_module_lock_parse($text);
    if ($locks['dirs'] === []) {
        fwrite(STDERR, "লক তালিকা পড়া যায়নি। MODULE_LOCK.txt এ ফোল্ডারের নাম আছে কিনা দেখুন।\n");
        return 2;
    }

    $unlockFile = $root . '/MODULE_UNLOCK.txt';
    $unlocks = is_file($unlockFile)
        ? ame_module_lock_parse_unlock((string)file_get_contents($unlockFile))
        : [];
    foreach ($unlocks as $unlock) {
        if (!ame_module_lock_unlock_is_specific($unlock, $locks)) {
            fwrite(STDERR, "এই অনুমতি অনেক চওড়া: {$unlock}\nএকটা মডিউলের ফোল্ডার লিখুন, যেমন modules/returns/\n");
            return 2;
        }
    }

    $paths = [];
    $useArgs = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--paths') {
            $useArgs = true;
            continue;
        }
        if ($useArgs) {
            $paths[] = $arg;
        }
    }
    if (!$useArgs) {
        $paths = ame_module_lock_git_paths($root);
    }

    $bad = ame_module_lock_violations($paths, $locks, $unlocks);
    if ($bad !== []) {
        fwrite(STDERR, "এই ফাইলগুলো লক করা মডিউলের। মালিক সেই মডিউলের নাম না বললে বদলানো যাবে না:\n");
        foreach ($bad as $path) {
            fwrite(STDERR, "- {$path}\n");
        }
        fwrite(STDERR, "অনুমতি থাকলে শুধু এই কাজের জন্য MODULE_UNLOCK.txt তে সেই ফোল্ডারের নাম লিখুন। কাজ শেষে ফাইলটি মুছে দিন। কমিট করবেন না।\n");
        return 1;
    }

    echo 'মডিউল লক ঠিক আছে। লক করা ফোল্ডার ' . count($locks['dirs'])
        . 'টি, লক করা ফাইল ' . count($locks['files']) . "টি। এই কাজে সেগুলো বদলায়নি।\n";
    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(ame_module_lock_cli($argv));
}
