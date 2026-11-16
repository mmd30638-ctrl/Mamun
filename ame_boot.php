<?php
/**
 * 10.98.356 — runs before config.php / db.php (auto_prepend).
 * Neutralizes PHP 8.3 "mysqli object is already closed" on the live db.php.
 */
if (defined('AME_BOOT_347')) {
    return;
}
define('AME_BOOT_347', true);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$ame_root = __DIR__;
$ame_guard = $ame_root . '/includes/ame_mysqli_guard.php';
if (is_file($ame_guard)) {
    require_once $ame_guard;
}
if (function_exists('ame_mysqli_patch_db_file')) {
    ame_mysqli_patch_db_file($ame_root . '/includes/db.php');
}
