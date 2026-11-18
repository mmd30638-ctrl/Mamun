<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/family_functions.php';
if (is_file(__DIR__ . '/../../includes/family_bazaar.php')) {
    require_once __DIR__ . '/../../includes/family_bazaar.php';
}
require_permission($db, 'm-family');
ame_family_ensure($db);
if (function_exists('fam_bazaar_ensure_house_memo')) {
    fam_bazaar_ensure_house_memo($db);
}
$lib = __DIR__ . '/../../includes/family_library.php';
if (is_file($lib)) {
    require_once $lib;
    ame_kitab_ensure($db);
}
