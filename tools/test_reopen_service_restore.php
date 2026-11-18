<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/sales/sale_admin_edit.php';
require_once $root . '/includes/sales/sale_draft.php';

foreach (
    [
        'ame_sale_admin_services_from_commissions',
        'ame_draft_restore_service_lines',
        'ame_sale_admin_services_payload_from_sale',
    ] as $fn
) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "FAIL: missing {$fn}\n");
        exit(1);
    }
}

echo "OK reopen service restore symbols\n";