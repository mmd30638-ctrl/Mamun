<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repair = $root . '/deploy/repair.php';
$first = (string) file_get_contents($root . '/includes/first_install.php');
$hex = bin2hex((string) gzcompress($first, 9));
$src = (string) file_get_contents($repair);
$src = preg_replace(
    "/define\\('AME_REPAIR_FIRST_HEX', '[0-9a-f]*'\\);/",
    "define('AME_REPAIR_FIRST_HEX', '" . $hex . "');",
    $src,
    1,
    $count
);
if ($count !== 1) {
    fwrite(STDERR, "could not embed payload ($count)\n");
    exit(1);
}
file_put_contents($repair, $src);
echo 'embedded ' . strlen($hex) . " hex chars\n";
