<?php
/**
 * CLI check: global responsive master files are present and version-aligned.
 * Usage: php tools/responsive_architecture_check.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'assets/css/ame-global-responsive-master.css',
    'assets/js/ame-viewport-tier.js',
    'includes/global_responsive_standard.php',
    'GLOBAL_RESPONSIVE_ARCHITECTURE.md',
    'UI_UX_DEVICE_STANDARD.md',
];

$fail = 0;
foreach ($required as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDERR, "MISSING: $rel\n");
        $fail++;
    }
}

$config = $root . '/includes/config.php';
$text = is_file($config) ? (string) file_get_contents($config) : '';
if (!preg_match("/define\\('AME_ASSET_VERSION', '10\\.98\\.939'\\)/", $text)) {
    fwrite(STDERR, "AME_ASSET_VERSION not 10.98.939 in includes/config.php\n");
    $fail++;
}

$css = $root . '/assets/css/ame-global-responsive-master.css';
if (is_file($css)) {
    $c = (string) file_get_contents($css);
    if (stripos($c, '575.98px') === false || stripos($c, '991.98px') === false || stripos($c, '992px') === false) {
        fwrite(STDERR, "Master CSS missing canonical breakpoints\n");
        $fail++;
    }
}

exit($fail > 0 ? 1 : 0);