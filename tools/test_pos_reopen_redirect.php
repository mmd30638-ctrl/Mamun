<?php
declare(strict_types=1);

/**
 * রি-ওপেন রিডাইরেক্ট URL ও POS JS হুক — স্ট্যাটিক চেক (DB ছাড়া)।
 */
$root = dirname(__DIR__);
$proc = file_get_contents($root . '/modules/reports/process_invoice_edit.php');
if ($proc === false || strpos($proc, 'open_drafts=1&reopened=1') === false) {
    fwrite(STDERR, "FAIL: process_invoice_edit missing open_drafts+reopened redirect\n");
    exit(1);
}

$js = file_get_contents($root . '/assets/js/pos-draft.js');
if ($js === false) {
    fwrite(STDERR, "FAIL: pos-draft.js missing\n");
    exit(1);
}

$needles = [
    'amePosEnterDraftModule',
    '__ameKeepDraftDrawerOpen',
    'reopenedFromInvoice',
    'is-reopened-highlight',
];
foreach ($needles as $needle) {
    if (strpos($js, $needle) === false) {
        fwrite(STDERR, "FAIL: pos-draft.js missing {$needle}\n");
        exit(1);
    }
}

require_once $root . '/tools/test_invoice_edit_reopen_action.php';