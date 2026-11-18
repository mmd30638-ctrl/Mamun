<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/sales/sale_draft.php';

if (!function_exists('ame_sale_draft_require_finance')) {
    fwrite(STDERR, "FAIL: ame_sale_draft_require_finance missing\n");
    exit(1);
}
ame_sale_draft_require_finance();
if (!function_exists('ame_sale_replace_services')) {
    fwrite(STDERR, "FAIL: ame_sale_replace_services not loaded\n");
    exit(1);
}

echo "OK sale_draft loads sale_finance\n";